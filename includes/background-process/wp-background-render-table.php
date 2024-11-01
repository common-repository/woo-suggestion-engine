<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! class_exists( 'VIWSE_Background_Render_Table' ) ) {
	class VIWSE_Background_Render_Table extends WP_Background_Process {
		protected static $instance = null;
		/**
		 * @var string
		 */
		protected $action = 'viwse_background_render_table';

		public static function get_instance() {
			return self::$instance == null ? self::$instance = new self : self::$instance;
		}

		/**
		 * Task
		 *
		 * Override this method to perform any actions required on each
		 * queue item. Return the modified item for further processing
		 * in the next pass through. Or, return false to remove the
		 * item from the queue.
		 *
		 * @param mixed $item Queue item to iterate over
		 *
		 * @return mixed
		 */
		protected function task( $item ) {
			try {
				$ids          = $item['ids'] ?? array();
				$type         = $item['type'] ?? '';
				$current_task = $item['key'] ?? '';
				if ( empty( $ids ) || ! $current_task ) {
					return false;
				}
				$viwse_background_running = get_transient( 'viwse_background_running' );
				if ( is_array( $viwse_background_running ) ) {
					$viwse_background_running = array();
				}
				if ( isset( $viwse_background_running[ $current_task ] ) ) {
					return true;
				}
				if ( get_transient( 'viwse_background_processed_task_' . $current_task ) ) {
					delete_transient( 'viwse_background_processed_task_' . $current_task );
					return false;
				}
				$viwse_background_running[ $current_task ] = $current_task;
				set_transient( 'viwse_background_running', $viwse_background_running, 3600 );
				$type_t = $type === 'product_cat' ? 'product categories' : ( $type === 'product_tag' ? 'product tags' : 'products' );
				if ( ! is_array( $ids ) ) {
					$ids = array( $ids );
				}
				if ( $type === 'product_cat' && ! VIWSE_DATA::get_instance()->get_params( 'search_category_enable' ) ) {
					throw new Exception( 'Can not build search data for product categories because the feature is turn off.' );
				}
				if ( $type === 'product_tag' && ! VIWSE_DATA::get_instance()->get_params( 'search_tag_enable' ) ) {
					throw new Exception( 'Can not build search data for product tags because the feature is turn off.' );
				}
				$start     = microtime( true );
				$total_ids = count( $ids );
				$id_start  = current( $ids );
				$id_end    = end( $ids );
				VIWSE_DATA::wc_log( "Processing {$total_ids} {$type_t} ( {$id_start} - {$id_end} ) " );
				viwse_init_set();
				switch ( $type ) {
					case 'product_cat':
					case 'product_tag':
						VIWSE_Render_Search_Table::get_instance()::delete_tax_info( $ids, $type );
						$terms = array();
						foreach ( $ids as $id ) {
							viwse_init_set();
							if ( ! $id ) {
								continue;
							}
							$term = get_term( $id );
							if ( ! $term || ! is_a( $term, 'WP_Term' ) || $term->taxonomy !== $type
							     || apply_filters( 'viwse_' . $type . '_can_not_search', false, $term )
							) {
								continue;
							}
							$terms[] = $term;
						}
						if ( ! empty( $terms ) ) {
							do_action( 'viwse_before_save-taxonomy_info', $terms, $type );
							VIWSE_Render_Search_Table::get_instance()::insert_tax_info( $terms, $type );
							do_action( 'viwse_after_save-taxonomy_info', $terms, $type );
						}
						break;
					default:
						VIWSE_Render_Search_Table::get_instance()::delete_product_info( $ids );
						$product_delete    = $product_insert = $product_words = array();
						$product_search_in = VIWSE_DATA::get_instance()->get_params( 'search_product_search_in' );
						if ( ! is_array( $product_search_in ) ) {
							$product_search_in = array();
						}
						$product_search_in[] = 'name';
						do_action( 'viwse_before_save-product_info', $ids );
						foreach ( $ids as $id ) {
							viwse_init_set();
							if ( ! $id ) {
								continue;
							}
							$product = wc_get_product( $id );
							if ( ! $product ) {
								$product_delete[] = $id;
								continue;
							}
							if ( apply_filters( 'viwse_product_can_not_search', ! $product->is_visible() || $product->get_status() !== 'publish', $product ) ) {
								continue;
							}
							$words = array();
							if ( is_array( $product_search_in ) ) {
								VIWSE_Render_Search_Table::get_word_list( $words, $product, $product_search_in );
								if ( $product->has_child() && $product->is_type( 'variable' ) && count( $product_search_in ) > 1 ) {
									$product_children = $product->get_children();
									if ( is_array( $product_children ) ) {
										foreach ( $product_children as $product_child ) {
											VIWSE_Render_Search_Table::get_word_list( $words, wc_get_product( $product_child ), $product_search_in );
										}
									}
								}
							}
							$words = array_unique( $words );
							if ( ! empty( $words ) ) {
								$product_insert[]                    = $product;
								$product_words[ $product->get_id() ] = $words;
							}
						}
						if ( ! empty( $product_delete ) ) {
							VIWSE_Render_Search_Table::get_instance()::delete_search_history( $product_delete );
						}
						if ( ! empty( $product_insert ) ) {
							VIWSE_Render_Search_Table::get_instance()::insert_product_data( $product_insert );
						}
						if ( ! empty( $product_words ) ) {
							VIWSE_Render_Search_Table::get_instance()::insert_product_relationships( $product_words );
						}
						do_action( 'viwse_after_save-product_info', $ids );
				}
				$time = microtime( true ) - $start;
				VIWSE_DATA::wc_log( "Processed {$total_ids} {$type_t} ( {$id_start} - {$id_end} ) in {$time}s" );
				unset( $viwse_background_running[ $current_task ] );
				set_transient( 'viwse_background_running', $viwse_background_running );
				set_transient( 'viwse_background_processed_task_' . $current_task, 1, 3600 );
			} catch ( \Exception $e ) {
				if ( isset( $viwse_background_running[ $current_task ] ) ) {
					unset( $viwse_background_running[ $current_task ] );
					set_transient( 'viwse_background_running', $viwse_background_running );
				}
				VIWSE_DATA::wc_log( $e->getMessage() );

				return false;
			}

			return false;
		}

		/**
		 * Is the updater running?
		 *
		 * @return boolean
		 */
		public function is_process_running() {
			return parent::is_process_running();
		}

		/**
		 * Is the queue empty
		 *
		 * @return boolean
		 */
		public function is_queue_empty() {
			return parent::is_queue_empty();
		}

		/**
		 * Cancel Process
		 *
		 * Stop processing queue items, clear cronjob and delete batch.
		 *
		 */
		public function kill_process() {

			if ( ! empty( get_option( 'viwse_background_render_processing', '' ) ) ) {
				VIWSE_DATA::wc_log( "Canceled Processing" );
			}
			if ( ! $this->is_queue_empty() ) {
				$this->delete_all_batches();
				wp_clear_scheduled_hook( $this->cron_hook_identifier );
			}

		}

		/**
		 * Delete all batches.
		 *
		 * @return VIWSE_Background_Render_Table
		 */
		public function delete_all_batches() {
			global $wpdb;

			$table  = $wpdb->options;
			$column = 'option_name';

			if ( is_multisite() ) {
				$table  = $wpdb->sitemeta;
				$column = 'meta_key';
			}

			$key = $wpdb->esc_like( $this->identifier . '_batch_' ) . '%';

			$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE {$column} LIKE %s", $key ) ); // @codingStandardsIgnoreLine.

			return $this;
		}


		/**
		 * Complete.
		 *
		 * Override if applicable, but ensure that the below actions are
		 * performed, or, call parent::complete().
		 */
		protected function complete() {
			if ( empty( get_transient( 'viwse_background_running' ) ) && $this->is_queue_empty() && ! $this->is_process_running() ) {
				VI_WOO_SUGGESTION_ENGINE_Admin_Search_Engine::$background_render_product_ids = array();
				VI_WOO_SUGGESTION_ENGINE_Admin_Search_Engine::$bg_render_product_cats        = array();
				VI_WOO_SUGGESTION_ENGINE_Admin_Search_Engine::$bg_render_product_tags        = array();
				VIWSE_Render_Search_Table::get_instance()::rename_tables();
				$bg_processing = get_option( 'viwse_background_render_processing',
					array(
						'lang'  => '',
						'start' => '',
					)
				);
				if ( ! empty( $bg_processing['start'] ) ) {
					$bg_complete = array(
						'lang' => $bg_processing['lang'] ?? '',
						'end'  => microtime( true ),
					);
					update_option( 'viwse_background_render_complete', $bg_complete );
					VIWSE_DATA::wc_log( 'End building search data' );
				}
				delete_transient( 'viwse_background_running' );
				delete_option( 'viwse_background_render_processing' );
				do_action( 'viwse_background_render_complete' );
			}
			parent::complete();
		}
	}
}