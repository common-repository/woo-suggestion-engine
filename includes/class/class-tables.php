<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VIWSE_Tables {
	protected static $instance = null;
	public static $wpdb;

	public function __construct() {
		global $wpdb;
		self::$wpdb              = $wpdb;
		self::$wpdb->show_errors = false;
	}

	public static function get_instance() {
		return self::$instance === null ? self::$instance = new self() : self::$instance;
	}

	public static function check_exist( $table ) {
		$table = self::$wpdb->prefix . $table;

		return self::$wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) === $table;// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
	}

	public static function create_table( $table ) {
		$collate = self::$wpdb->has_cap( 'collation' ) ? self::$wpdb->get_charset_collate() : '';
		switch ( str_replace( self::$wpdb->prefix, '', $table ) ) {
			case 'viwse_product_list':
				$query = "CREATE TABLE IF NOT EXISTS {$table} (
                             `id` bigint(20) NOT NULL AUTO_INCREMENT,
                             `product_id` bigint(20) NOT NULL,
                             `name` longtext ,
                             `sku` longtext ,
                             `price` longtext ,
                             `image` longtext ,
                             `permalink` longtext ,
                             `is_sale` longtext ,
                             `total_sale` bigint(20) ,
                             PRIMARY KEY  (`id`) 
                             ){$collate};";
				break;
			case 'viwse_taxonomy_list':
				$query = "CREATE TABLE IF NOT EXISTS {$table} (
                             `id` bigint(20) NOT NULL AUTO_INCREMENT,
                             `tax_id` bigint(20) NOT NULL,
                             `name` longtext NOT NULL,
                             `slug` longtext ,
                             `desc` longtext ,
                             `permalink` longtext ,
                             `type` longtext NOT NULL,
                             PRIMARY KEY  (`id`),FULLTEXT taxonomy(`name`,`slug`,`desc`) 
                             ){$collate};";
				break;
			case 'viwse_word_list':
				$query = "CREATE TABLE IF NOT EXISTS {$table} (
                             `id` bigint(20) NOT NULL AUTO_INCREMENT,
                             `word` longtext NOT NULL,
                             PRIMARY KEY  (`id`),FULLTEXT (word) 
                             ){$collate};";
				break;
			case 'viwse_relationships':
				$query = "CREATE TABLE IF NOT EXISTS {$table} (
                             `id` bigint(20) NOT NULL AUTO_INCREMENT,
                             `product_id` bigint(20) NOT NULL,
                             `word_id` bigint(20) NOT NULL,
                             PRIMARY KEY  (`id`)
                             ){$collate};";
				break;
			case 'viwse_search_history':
				$query = "CREATE TABLE IF NOT EXISTS {$table} (
                             `id` bigint(20) NOT NULL AUTO_INCREMENT,
                             `product_id` bigint(20) NOT NULL,
                             `word` longtext NOT NULL,
                             `num_hits` bigint(20) NOT NULL,
                             PRIMARY KEY  (`id`)
                             ){$collate};";
				break;
		}
		if ( ! empty( $query ) ) {
			return self::$wpdb->query( $query );// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		}

		return false;
	}

	public static function create_table_tmp( $table, $reset = false ) {
		$table_t   = $table;
		$table     = self::$wpdb->prefix . $table;
		$table_tmp = $table . '_tmp';
		if ( ! self::check_exist( $table_t . '_tmp' ) && self::create_table( $table ) ) {
			self::$wpdb->query( "CREATE TABLE IF NOT EXISTS {$table_tmp} LIKE {$table} ;" );// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
			self::$wpdb->query( "TRUNCATE TABLE {$table_tmp};" );// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
			if ( ! $reset ) {
				self::$wpdb->query( "INSERT INTO {$table_tmp} SELECT * FROM {$table};" );// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
			}
		}
	}

	public static function rename( $table ) {
		if ( self::check_exist( $table . '_tmp' ) ) {
			self::query( $table, "DROP TABLE IF EXISTS `{table_name}`;" );
			self::query( $table, "ALTER TABLE `{table_name}_tmp` RENAME TO  `{table_name}`;" );
		}
	}

	public static function delete_table( $table ) {
		self::query( $table, "DROP TABLE IF EXISTS `{table_name}`;" );
		self::query( $table, "DROP TABLE IF EXISTS `{table_name}_tmp`;" );
	}

	public static function query( $table, $query ) {
		if ( ! $table || empty( $query ) ) {
			return false;
		}
		$table  = self::$wpdb->prefix . $table;
		$query  = str_replace( '{table_name}', $table, $query );
		$result = self::$wpdb->query( $query );// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		if ( ! empty( self::$wpdb->last_error ) ) {
			VIWSE_DATA::wc_log( sprintf( 'WordPress database error %1$s for query %2$s', self::$wpdb->last_error, self::$wpdb->last_query ) );
		}

		return $result;
	}

	public static function get_col( $table, $query ) {
		if ( ! $table || empty( $query ) ) {
			return false;
		}
		$table = self::$wpdb->prefix . $table;
		$query = str_replace( '{table_name}', $table, $query );

		return self::$wpdb->get_col( $query );// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
	}

	public static function insert( $table, $param, $format = null, $suffix = '' ) {
		if ( empty( $param ) ) {
			return false;
		}
		$table = self::$wpdb->prefix . $table . $suffix;
		self::$wpdb->insert( $table, $param, $format );// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return self::$wpdb->insert_id;
	}

	public static function update( $table, $param, $where, $suffix = '' ) {
		if ( empty( $param ) || empty( $where ) ) {
			return false;
		}
		$table = self::$wpdb->prefix . $table . $suffix;

		return self::$wpdb->update( $table, $param, $where );// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	public static function delete( $table, $where ) {
		if ( ! $table || empty( $where ) ) {
			return '';
		}
		$table = self::$wpdb->prefix . $table . '_tmp';

		return self::$wpdb->delete( $table, $where );// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	public static function save_search_history( $product_id, $text ) {
		if ( ! $product_id || ! $text ) {
			return;
		}
		if ( ! wc_get_product( $product_id ) ) {
			return;
		}
		$table = 'viwse_search_history';
		if ( self::check_exist( $table . '_tmp' ) ) {
			$table .= '_tmp';
		}
		$history_table = self::$wpdb->prefix . $table;
		if (!self::check_exist($history_table)){
			self::create_table( $history_table );
		}
		$tmp = self::$wpdb->get_results( self::$wpdb->prepare( "SELECT * FROM {$history_table} WHERE product_id = {$product_id} AND word = %s ;", $text ), ARRAY_A );// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		if ( ! empty( $tmp[0] ) && ! empty( $tmp[0]['id'] ) ) {
			$num_hits = floatval( $tmp[0]['num_hits'] ?? 1 ) + 1;
			self::update( $table, array( 'num_hits' => $num_hits ), array( 'id' => $tmp[0]['id'] ) );
		} else {
			$arg    = array(
				'product_id' => $product_id,
				'word'       => $text,
				'num_hits'   => 1,
			);
			$format = array( '%d', '%s', '%d' );
			self::insert( $table, $arg, $format );
		}
	}

	public static function get_product_suggestion( $suggestion, $limit = 7, $ids=null ) {
		$products = array();
		$product_table = self::$wpdb->prefix . 'viwse_product_list';
		switch ( $suggestion ) {
			case 'best_selling':
				$query = "SELECT * FROM {$product_table} GROUP BY total_sale DESC LIMIT {$limit};";
				break;
			case 'latest':
				$query = "SELECT * FROM {$product_table} GROUP BY product_id DESC LIMIT {$limit};";
				break;
			case 'is_sale':
				$query = "SELECT * FROM {$product_table} WHERE is_sale = 1 LIMIT {$limit};";
				break;
			default:
				if ( ! empty( $ids ) ) {
					$product_id = is_array( $ids ) ? implode( ',', $ids ) : $ids;
					$query = "SELECT * FROM {$product_table} WHERE product_id IN ({$product_id})";
				}
		}
		if ( ! empty( $query ) ) {
			$products = self::$wpdb->get_results( $query, ARRAY_A );// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		}

		return $products;
	}

	public static function get_product_id_by_word( $word, $text = '' ) {
		if ( empty( $word ) ) {
			return array();
		}
		$word          = is_array( $word ) ? implode( ' ', $word ) : $word;
		$words_table   = self::$wpdb->prefix . 'viwse_word_list';
		$rel_table     = self::$wpdb->prefix . 'viwse_relationships';
		if ( $text ) {
			$words = self::$wpdb->get_results( "SELECT * FROM {$words_table} WHERE MATCH(word) AGAINST('{$word}' IN BOOLEAN MODE)", ARRAY_A );// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
			if ( empty( $words ) ) {
				return array();
			}
			$word_id = $tmp = array();
			foreach ( $words as $item ) {
				if ( empty( $item['id'] ) || empty( $item['word'] ) ) {
					continue;
				}
				$distance = levenshtein( $item['word'], $text );
				if ( $distance <= ( VI_WOO_SUGGESTION_ENGINE_Frontend_Search::$cache['fuzzy_search_distance'] ?? 2 ) ) {
					$tmp[ $item['id'] ] = $distance;
				}
			}
			if ( ! empty( $tmp ) ) {
				asort( $tmp );
				$word_id = array_slice( array_keys( $tmp ), 0,
					VI_WOO_SUGGESTION_ENGINE_Frontend_Search::$cache['limit'] ?? VIWSE_DATA::get_instance()->get_params( 'search_fuzzy_enable' ) );
			}
			if ( empty( $word_id ) ) {
				return array();
			}
			$word_id    = implode( ',', $word_id );
			$product_id = self::$wpdb->get_col( "SELECT DISTINCT(product_id) FROM {$rel_table} WHERE word_id IN ({$word_id})" );// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		} else {
			$product_id
				= self::$wpdb->get_col( "SELECT DISTINCT(product_id) FROM {$rel_table} WHERE word_id IN (SELECT id FROM {$words_table} WHERE MATCH(word) AGAINST('{$word}' IN BOOLEAN MODE))" );// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		}
		return $product_id;
	}

	public static function get_tax_by_word( $word, $type ) {
		if ( empty( $word ) || ! in_array( $type, array( 'product_cat', 'product_tag' ) ) ) {
			return array();
		}
		$word  = is_array( $word ) ? implode( ' ', $word ) : $word;
		$table = self::$wpdb->prefix . 'viwse_taxonomy_list';
		$query = "SELECT * FROM {$table} WHERE type = '{$type}' AND MATCH(`name`,`slug`,`desc`) AGAINST('{$word}' IN BOOLEAN MODE)";

		return self::$wpdb->get_results( $query, ARRAY_A );// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
	}
}

class VIWSE_Render_Search_Table {
	protected static $instance = null, $tables;
	public static $background_process, $cat_ids = array(), $tag_ids = array(), $product_ids = array();

	public static function get_instance() {
		return self::$instance == null ? self::$instance = new self : self::$instance;
	}

	public function __construct() {
		self::$tables             = VIWSE_Tables::get_instance();
		self::$background_process = VIWSE_Background_Render_Table::get_instance();
	}

	public function maybe_handle( $reset = false, $type = '' ) {
		if ( $reset ) {
			self::$background_process->kill_process();
			update_option( 'viwse_background_render_processing', array(
				'lang'  => get_locale(),
				'start' => microtime( true ),
			) );
			VIWSE_DATA::wc_log( 'Start building search data' );
			$args              = apply_filters( 'viwse_bg_get_product_ids', array(
				'status'         => 'publish',
				'visibility'     => 'visible',
				'posts_per_page' => - 1,
				'return'         => 'ids',
			) );
			self::$product_ids = wc_get_products( $args );
			$tax_type          = array();
			if ( VIWSE_DATA::get_instance()->get_params( 'search_category_enable' ) ) {
				$tax_type[] = 'product_cat';
			}
			if ( VIWSE_DATA::get_instance()->get_params( 'search_tag_enable' ) ) {
				$tax_type[] = 'product_tag';
			}
			if ( ! empty( $tax_type ) ) {
				$terms = get_terms( apply_filters( 'viwse_bg_get_product_cats', array( 'taxonomy' => $tax_type ) ) );
				if ( is_array( $terms ) && count( $terms ) ) {
					foreach ( $terms as $term ) {
						if ( $term->taxonomy === 'product_cat' ) {
							self::$cat_ids[] = $term->term_id;
						} elseif ( $term->taxonomy === 'product_tag' ) {
							self::$tag_ids[] = $term->term_id;
						}
					}
				}
			}
			self::create_tables( array( 'viwse_word_list', 'viwse_product_list', 'viwse_relationships', 'viwse_taxonomy_list' ), true );
		} else {
			switch ( $type ) {
				case 'product':
					global $viwse_background_render_product;
					self::$product_ids               = $viwse_background_render_product;
					$viwse_background_render_product = null;
					break;
				case 'product_cat':
					global $viwse_background_render_product_cats;
					self::$cat_ids                        = $viwse_background_render_product_cats;
					$viwse_background_render_product_cats = null;
					break;
				case 'product_tag':
					global $viwse_background_render_tags;
					self::$tag_ids                = $viwse_background_render_tags;
					$viwse_background_render_tags = null;
					break;
			}
		}
		if ( ( empty( self::$product_ids ) || ! is_array( self::$product_ids ) )
		     && ( empty( self::$cat_ids ) || ! is_array( self::$cat_ids ) )
		     && ( empty( self::$tag_ids ) || ! is_array( self::$tag_ids ) )
		) {
			return;
		}
		if ( ! empty( self::$product_ids ) ) {
			self::$product_ids = array_unique( self::$product_ids );
		}
		if ( ! empty( self::$cat_ids ) ) {
			self::$cat_ids = array_unique( self::$cat_ids );
		}
		if ( ! empty( self::$tag_ids ) ) {
			self::$tag_ids = array_unique( self::$tag_ids );
		}
		$this->handle();
	}

	public function handle() {
		$run               = $tmp = array();
		$item_per_schedule = apply_filters( 'viwse_background_get_item_per_schedule', 200 );
		$log               = array();
		if ( ! empty( self::$product_ids ) && is_array( self::$product_ids ) ) {
			self::create_tables();
			$log[] = count( self::$product_ids ) . ' products';
			foreach ( self::$product_ids as $id ) {
				$tmp[] = $id;
				if ( count( $tmp ) === $item_per_schedule ) {
					$run[] = array(
						'key'  => 'product_' . $tmp[0] . '_' . microtime( true ),
						'type' => 'product',
						'ids'  => $tmp
					);
					$tmp   = array();
				}
			}
			if ( ! empty( $tmp ) ) {
				$run[] = array(
					'key'  => 'product_' . $tmp[0] . '_' . microtime( true ),
					'type' => 'product',
					'ids'  => $tmp
				);
				$tmp   = array();
			}
		}
		if ( ! empty( self::$cat_ids ) && is_array( self::$cat_ids ) && VIWSE_DATA::get_instance()->get_params( 'search_category_enable' ) ) {
			self::create_tables( 'viwse_taxonomy_list' );
			$log[] = count( self::$cat_ids ) . ' product categories';
			foreach ( self::$cat_ids as $id ) {
				$tmp[] = $id;
				if ( count( $tmp ) === $item_per_schedule ) {
					$run[] = array(
						'key'  => 'product_cat_' . $tmp[0] . '_' . microtime( true ),
						'type' => 'product_cat',
						'ids'  => $tmp
					);
					$tmp   = array();
				}
			}
			if ( ! empty( $tmp ) ) {
				$run[] = array(
					'key'  => 'product_cat_' . $tmp[0] . '_' . microtime( true ),
					'type' => 'product_cat',
					'ids'  => $tmp
				);
				$tmp   = array();
			}
		}
		if ( ! empty( self::$tag_ids ) && is_array( self::$tag_ids ) && VIWSE_DATA::get_instance()->get_params( 'search_tag_enable' ) ) {
			self::create_tables( 'viwse_taxonomy_list' );
			$log[] = count( self::$tag_ids ) . ' product tags';
			foreach ( self::$tag_ids as $id ) {
				$tmp[] = $id;
				if ( count( $tmp ) === $item_per_schedule ) {
					$run[] = array(
						'key'  => 'product_tag_' . $tmp[0] . '_' . microtime( true ),
						'type' => 'product_tag',
						'ids'  => $tmp
					);
					$tmp   = array();
				}
			}
			if ( ! empty( $tmp ) ) {
				$run[] = array(
					'key'  => 'product_tag_' . $tmp[0] . '_' . microtime( true ),
					'type' => 'product_tag',
					'ids'  => $tmp
				);
			}
		}
		if ( ! empty( get_option( 'viwse_background_render_processing', '' ) ) && ! empty( $log ) ) {
			VIWSE_DATA::wc_log( 'Processing total: ' . implode( ', ', $log ) );
		}
		if ( ! empty( $run ) ) {
			foreach ( $run as $item ) {
				self::$background_process->push_to_queue( $item );
			}
			self::$background_process->save()->dispatch();
		}
	}

	public static function create_tables( $tables = array( 'viwse_word_list', 'viwse_product_list', 'viwse_relationships', 'viwse_search_history' ), $reset = false ) {
		if ( is_array( $tables ) ) {
			foreach ( $tables as $table ) {
				self::$tables::create_table_tmp( $table, $reset );
			}
		} else {
			self::$tables::create_table_tmp( $tables, $reset );
		}
	}

	public static function rename_tables( $tables = array( 'viwse_word_list', 'viwse_product_list', 'viwse_relationships', 'viwse_search_history', 'viwse_taxonomy_list' ) ) {
		if ( is_array( $tables ) ) {
			foreach ( $tables as $table ) {
				self::$tables::rename( $table );
			}
		} else {
			self::$tables::rename( $tables );
		}
	}

	public static function delete_tables( $tables = array( 'viwse_word_list', 'viwse_product_list', 'viwse_relationships', 'viwse_search_history', 'viwse_taxonomy_list' ) ) {
		if ( is_array( $tables ) ) {
			foreach ( $tables as $table ) {
				self::$tables::delete_table( $table );
			}
		} else {
			self::$tables::delete_table( $tables );
		}
	}

	public static function delete_tax_info( $id, $type ) {
		if ( empty( $id ) || ! $type ) {
			return false;
		}
		if ( is_array( $id ) ) {
			$id = implode( ',', $id );
		}
		self::$tables::query( 'viwse_taxonomy_list', "DELETE FROM `{table_name}_tmp` WHERE tax_id IN ( {$id} ) AND type = '{$type}'" );

		return true;
	}

	public static function insert_tax_info( $terms, $type ) {
		if ( ! $type || ! is_array( $terms ) || empty( $terms ) ) {
			return;
		}
		$values = array();
		$fields = array( 'tax_id', 'name', 'slug', 'desc', 'permalink', 'type' );
		$fields = '`' . implode( '`,`', $fields ) . '`';
		$format = array( '%d', '%s', '%s', '%s', '%s', '%s' );
		$format = '( ' . implode( ',', $format ) . ' )';
		foreach ( $terms as $term ) {
			if ( ! is_a( $term, 'WP_Term' ) ) {
				continue;
			}
			if ( ! $term->term_id || $term->taxonomy !== $type ) {
				continue;
			}
			$tmp      = array(
				'tax_id'    => $term->term_id,
				'name'      => $term->name,
				'slug'      => $term->slug,
				'desc'      => $term->description,
				'permalink' => get_term_link( $term->term_id ),
				'type'      => $type,
			);
			$values[] = self::$tables::$wpdb->prepare( $format, array_values( $tmp ) );// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			if ( ! empty( $values ) && count($values) >= 1000) {
				$values = implode( ', ', $values );
				self::$tables::query( 'viwse_taxonomy_list', "INSERT INTO `{table_name}_tmp` ({$fields}) VALUES {$values}" );
				$values = array();
			}
		}
		if ( ! empty( $values ) ) {
			$values = implode( ', ', $values );
			self::$tables::query( 'viwse_taxonomy_list', "INSERT INTO `{table_name}_tmp` ({$fields}) VALUES {$values}" );
		}
	}

	public static function delete_product_info( $id ) {
		if ( empty( $id ) ) {
			return false;
		}
		if ( is_array( $id ) ) {
			$id = implode( ',', $id );
		}
		$word_id = self::$tables::get_col( 'viwse_relationships', "SELECT DISTINCT(word_id) FROM `{table_name}_tmp` WHERE product_id  IN ({$id});" );
		if ( ! empty( $word_id ) && is_array( $word_id ) ) {
			self::$tables::query( 'viwse_relationships', "DELETE FROM `{table_name}_tmp` WHERE product_id IN ( {$id} )" );
			$word_id_delete  = $word_id;
			$word_id         = implode( ',', $word_id );
			$word_id_exclude = self::$tables::get_col( 'viwse_relationships', "SELECT DISTINCT(word_id) FROM `{table_name}_tmp` WHERE word_id IN ( {$word_id} );" );
			$word_id_delete  = ! empty( $word_id_exclude ) && is_array( $word_id_exclude ) ? array_diff( $word_id_delete, $word_id_exclude ) : $word_id_delete;
			if ( ! empty( $word_id_delete ) ) {
				$word_id_delete = implode( ',', $word_id_delete );
				self::$tables::query( 'viwse_word_list', "DELETE FROM `{table_name}_tmp` WHERE id IN ({$word_id_delete})" );
			}
		}
		self::$tables::query( 'viwse_product_list', "DELETE FROM `{table_name}_tmp` WHERE product_id  IN ( {$id})" );

		return true;
	}

	public static function delete_search_history( $id ) {
		if ( empty( $id ) ) {
			return false;
		}
		if ( is_array( $id ) ) {
			$id = implode( ',', $id );
		}
		self::$tables::query( 'viwse_search_history', "DELETE FROM `{table_name}_tmp` WHERE product_id  IN ( {$id})" );

		return true;
	}

	public static function insert_product_data( $products ) {
		if ( ! is_array( $products ) || empty( $products ) ) {
			return;
		}
		$values = array();
		$fields = array( 'product_id', 'name', 'sku', 'price', 'image', 'permalink', 'is_sale', 'total_sale' );
		$fields = '`' . implode( '`,`', $fields ) . '`';
		$format = array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d' );
		$format = '( ' . implode( ',', $format ) . ' )';
		foreach ( $products as $product ) {
			if ( ! is_a( $product, 'WC_Product' ) ) {
				continue;
			}
			$product_id = $product->get_id();
			$check      = self::$tables::get_col( 'viwse_product_list', "SELECT DISTINCT(id) FROM `{table_name}_tmp` WHERE product_id = '{$product_id}';" );
			if ( ! empty( $check[0] ) ) {
				continue;
			}
			$tmp      = array(
				'product_id' => $product_id,
				'name'       => $product->get_name( 'edit' ),
				'sku'        => $product->get_sku( 'edit' ),
				'price'      => $product->get_price_html( 'edit' ),
				'image'      => wp_get_attachment_image_src( get_post_thumbnail_id( $product->get_id() ), 'woocommerce_gallery_thumbnail' )[0] ?? '',
				'permalink'  => $product->get_permalink(),
				'is_sale'    => $product->is_on_sale( 'edit' ) ? '1' : '',
				'total_sale' => $product->get_total_sales( 'edit' ) ?: 0,
			);
			$values[] = self::$tables::$wpdb->prepare( $format, array_values( $tmp ) );// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			if ( ! empty( $values ) && count($values) >= 1000) {
				$values = implode( ', ', $values );
				self::$tables::query( 'viwse_product_list', "INSERT INTO `{table_name}_tmp` ({$fields}) VALUES {$values}" );
				$values = array();
			}
		}
		if ( ! empty( $values ) ) {
			$values = implode( ', ', $values );
			self::$tables::query( 'viwse_product_list', "INSERT INTO `{table_name}_tmp` ({$fields}) VALUES {$values}" );
		}
	}

	public static function insert_product_relationships( $words ) {
		if ( ! is_array( $words ) || empty( $words ) ) {
			return;
		}
		$fields   = array( 'product_id', 'word_id' );
		$fields   = '`' . implode( '`,`', $fields ) . '`';
		$word_ids = $values = array();
		foreach ( $words as $product_id => $word ) {
			foreach ( $word as $v ) {
				if ( ! isset( $word_ids[ $v ] ) ) {
					$word_id = self::$tables::get_col( 'viwse_word_list', "SELECT DISTINCT(id) FROM `{table_name}_tmp` WHERE word = '{$v}';" );
					if ( ! empty( $word_id[0] ) ) {
						$word_id = $word_id[0];
					} else {
						$word_id = self::$tables::insert( 'viwse_word_list', array( 'word' => $v ), array( '%s' ), '_tmp' );
					}
					$word_ids[ $v ] = $word_id;
				}
				$word_id = $word_ids[ $v ];
				if ( $word_id ) {
					$values[] = "({$product_id},{$word_id})";
				}
			}
			if ( ! empty( $values ) && count($values) >= 1000) {
				$values = implode( ', ', $values );
				self::$tables::query( 'viwse_relationships', "INSERT INTO `{table_name}_tmp` ({$fields}) VALUES {$values}" );
				$values = array();
			}
		}
		if ( ! empty( $values ) ) {
			$values = implode( ', ', $values );
			self::$tables::query( 'viwse_relationships', "INSERT INTO `{table_name}_tmp` ({$fields}) VALUES {$values}" );
		}
	}

	public static function get_word_list( &$words, $product, $search ) {
		if ( ! is_a( $product, 'WC_Product' ) || ! is_array( $search ) || empty( $search ) ) {
			return;
		}
		foreach ( $search as $type ) {
			switch ( $type ) {
				case 'sku':
					self::get_word( $words, $product->get_sku() );
				case 'desc':
					self::get_word( $words, $product->get_description() );
					break;
				case 'name':
					self::get_word( $words, $product->get_name() );
					break;
			}
		}
	}

	public static function get_word( &$words, $text, $action = 'render' ) {
		if ( ! $text ) {
			return;
		}
		$text = self::strip_tags( $text );
		if ( ! $text ) {
			return;
		}
		$text_arg = preg_split( apply_filters( 'viwse_' . $action . '_text_split', "/[^\p{L}\p{N}]+/u" ), $text );
		$text_arg = apply_filters( 'viwse_' . $action . '_get_word', array_filter( array_unique( $text_arg ) ), $text );
		if ( is_array( $text_arg ) && count( $text_arg ) ) {
			$words = array_merge( $words, $text_arg );
		}
	}

	public static function strip_tags( $text ) {
		if ( ! $text ) {
			return '';
		}
		$text = wp_strip_all_tags( preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $text ) );
		$text = function_exists( 'mb_strtolower' ) ? mb_strtolower( $text ) : strtolower( $text );

		return trim( $text );
	}
}