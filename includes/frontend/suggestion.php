<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VI_WOO_SUGGESTION_ENGINE_Frontend_Suggestion {
	public static $settings, $hook_args, $cache = array();

	public function __construct() {
		self::$settings = VIWSE_DATA::get_instance();
		add_action( 'init', array( $this, 'shortcode_init' ) );
		if ( self::$settings->get_params( 'suggest_related_product' ) ) {
			add_action( 'woocommerce_after_single_product_summary', array( $this, 'hide_related_product' ), 9 );
		}
		self::$hook_args = array(
			//show suggestions on archive page
			'archive'  => array(
				'woocommerce_before_main_content' => 10,
				'woocommerce_before_shop_loop'    => 15,
				'woocommerce_after_shop_loop'     => 15,
				'woocommerce_after_main_content'  => 15
			),
			//show suggestions on single product page
			'single'   => array(
				'woocommerce_before_single_product'        => 15,
				'woocommerce_after_single_product_summary' => 21,
				'woocommerce_after_single_product'         => 5
			),
			//show suggestions on cart page
			'cart'     => array(
				'woocommerce_before_cart'      => 10,
				'woocommerce_after_cart_table' => 10,
				'woocommerce_after_cart'       => 10
			),
			//show suggestions on checkout page
			'checkout' => array(
				'woocommerce_before_checkout_form'              => 10,
				'woocommerce_before_checkout_billing_form'      => 10,
				'woocommerce_review_order_before_cart_contents' => 10,
				'woocommerce_review_order_after_payment'        => 10,
				'woocommerce_after_checkout_form'               => 10
			),
		);
		if ( ! empty( self::$settings->get_params( 'suggested_products' ) ) ) {
			foreach ( self::$hook_args as $args ) {
				foreach ( $args as $hook => $priority ) {
					add_action( $hook, array( $this, 'display_suggestion' ), $priority );
				}
			}
		}
	}

	public function shortcode_init() {
		add_shortcode( 'viwse_suggestion', array( $this, 'viwse_suggestion' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'viwse_wp_enqueue_scripts' ) );
	}

	public static function add_sug_product_class( $classes ) {
		$classes[] = 'viwse-suggestion-product';

		return $classes;
	}

	public function viwse_suggestion( $atts ) {
		extract( shortcode_atts( array(
			'title'              => '',
			'suggestion'         => 'best_selling',
			'include_products'   => '',
			'include_categories' => '',
			'out_of_stock'       => 1,
			'limit'              => 20,
			'cols'               => 4,
			'cols_mobile'        => 2,
			'is_slide'           => 1,
		), $atts ) );
		$suggestion         = is_array( $suggestion ) ? $suggestion : ( $suggestion ? explode( ',', $suggestion ) : array() );
		$include_products   = is_array( $include_products ) ? $include_products : ( $include_products ? explode( ',', trim( $include_products ) ) : array() );
		$include_categories = is_array( $include_categories ) ? $include_categories : ( $include_categories ? explode( ',', trim( $include_categories ) ) : array() );
		if ( empty( $suggestion ) && empty( $include_products ) && empty( $include_categories ) ) {
			return '';
		}
		$product_ids = array();
		if ( ! empty( $suggestion ) ) {
			foreach ( $suggestion as $type ) {
				$continue = true;
				$temp     = null;
				$arg      = array(
					'viwse'      => 'publish',
					'status'     => 'publish',
					'visibility' => 'visible',
					'return'     => 'ids',
					'limit'      => $limit,
				);
				switch ( $type ) {
					case 'best_selling':
						$arg['meta_key'] = 'total_sales';// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
						$arg['orderby']  = 'meta_value_num';
						break;
					case 'feature':
						$product_ids_temp = wc_get_featured_product_ids();
						if ( is_array( $product_ids_temp ) && ! empty( $product_ids_temp ) ) {
							$include_products_t = ! empty( $include_products ) ? array_intersect( $include_products, $product_ids_temp ) : $product_ids_temp;
						} else {
							$continue = false;
						}
						break;
					case 'latest':
						$arg['order'] = 'DESC';
						break;
					case 'on_sale':
						$product_ids_temp = wc_get_product_ids_on_sale();
						if ( is_array( $product_ids_temp ) && ! empty( $product_ids_temp ) ) {
							$include_products_t = ! empty( $include_products ) ? array_intersect( $include_products, $product_ids_temp ) : $product_ids_temp;
						} else {
							$continue = false;
						}
						break;
					case 'top_rated':
						$arg['meta_key'] = '_wc_review_count';// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
						$arg['orderby']  = 'meta_value_num';
						break;
					case 'top_searched':
						$tables = VIWSE_Tables::get_instance();
						$history_table = $tables::$wpdb->prefix . 'viwse_search_history';
						if (!$tables::check_exist($history_table)){
							$tables::create_table( $history_table);
						}
						$query            = "SELECT product_id, SUM(num_hits) as total FROM {$history_table} GROUP BY product_id ORDER BY `total` DESC LIMIT {$limit}";
						$product_ids_temp = $tables::$wpdb->get_results( $query, ARRAY_A );// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
						$product_ids_temp = ! empty( array_column( $product_ids_temp, 'product_id' ) ) ? array_map( 'intval', array_column( $product_ids_temp, 'product_id' ) )
							: '';
						if ( is_array( $product_ids_temp ) && ! empty( $product_ids_temp ) ) {
							$include_products_t = ! empty( $include_products ) ? array_intersect( $include_products, $product_ids_temp ) : $product_ids_temp;
						} else {
							$continue = false;
						}
						break;
				}
				if ( ! $continue ) {
					continue;
				}
				if ( ! isset( $temp ) ) {
					if ( empty( $out_of_stock ) ) {
						$arg['stock_status'] = array( 'instock', 'onbackorder' );
					}
					if ( ! empty( $include_categories ) ) {
						$arg['category'] = $include_categories;
					}
					if ( ! empty( $include_products_t ) ) {
						$arg['include'] = $include_products_t;
					} elseif ( ! empty( $include_products ) ) {
						$arg['include'] = $include_products;
					}
					$temp = wc_get_products( $arg );
				}
				if ( ! empty( $temp ) && is_array( $temp ) ) {
					$product_ids = array_merge( $temp, $product_ids );
				}
			}
		} else {
			$arg = array(
				'viwse'      => 'publish',
				'status'     => 'publish',
				'visibility' => 'visible',
				'return'     => 'ids',
				'limit'      => $limit,
			);
			if ( empty( $out_of_stock ) ) {
				$arg['stock_status'] = array( 'instock', 'onbackorder' );
			}
			if ( ! empty( $include_categories ) ) {
				$arg['category'] = $include_categories;
			}
			if ( ! empty( $include_products ) ) {
				$arg['include'] = $include_products;
			}
			$product_ids = wc_get_products( $arg );
		}
		if ( ! empty( $product_ids ) ) {
			$product_ids = array_unique( $product_ids );
			shuffle( $product_ids );
			$product_ids = array_slice( $product_ids, 0, $limit );
		} else {
			return '';
		}

		return wc_get_template_html( 'viwse-suggestion-html.php',
			array(
				'product_ids' => $product_ids,
				'title'       => $title,
				'cols'        => $cols,
				'cols_mobile' => $cols_mobile,
				'is_slide'    => $is_slide,
			),
			'woo-suggestion-engine' . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR,
			VI_WOO_SUGGESTION_ENGINE_TEMPLATES );
	}

	public function viwse_wp_enqueue_scripts() {
		self::$settings::enqueue_style(
			array( 'viwse-suggestion' ),
			array( 'frontend-suggestion' ),
			array(),
			'register'
		);
		self::$settings::enqueue_script(
			array( 'viwse-suggestion', 'flexslider' ),
			array( 'frontend-suggestion', 'flexslider' ),
			array( 0, 1 ),
			array( 'jquery' ),
			'register'
		);
	}

	public function hide_related_product() {
		remove_all_actions( 'woocommerce_after_single_product_summary', 20 );
	}

	public function display_suggestion() {
		$sug_ids = $this->get_suggestion( current_action() );
		if ( is_array( $sug_ids ) && count( $sug_ids ) ) {
			foreach ( $sug_ids as $id ) {
				if ( ! self::$settings->get_current_setting_by_subtitle( 'suggested_products', $id, 'enable', '' ) ) {
					continue;
				}
				$shortcode_atts = array(
					'title'              => self::$settings->get_current_setting_by_subtitle( 'suggested_products', $id, 'title', '' ),
					'suggestion'         => implode( ',', (array) self::$settings->get_current_setting_by_subtitle( 'suggested_products', $id, 'suggestion', array() ) ),
					'include_products'   => implode( ',', (array) self::$settings->get_current_setting_by_subtitle( 'suggested_products', $id, 'include_products', array() ) ),
					'include_categories' => implode( ',', (array) self::$settings->get_current_setting_by_subtitle( 'suggested_products', $id, 'include_categories', array() ) ),
					'out_of_stock'       => self::$settings->get_current_setting_by_subtitle( 'suggested_products', $id, 'out_of_stock', 1 ),
					'cols'               => self::$settings->get_current_setting_by_subtitle( 'suggested_products', $id, 'cols', 4 ),
					'cols_mobile'        => self::$settings->get_current_setting_by_subtitle( 'suggested_products', $id, 'cols_mobile', 2 ),
					'limit'              => self::$settings->get_current_setting_by_subtitle( 'suggested_products', $id, 'limit', 20 ),
					'is_slide'           => self::$settings->get_current_setting_by_subtitle( 'suggested_products', $id, 'is_slide', 1 ),
				);
				$shortcode      = '[viwse_suggestion';
				foreach ( $shortcode_atts as $k => $v ) {
					$shortcode .= " $k='$v' ";
				}
				$shortcode .= ']';
				echo do_shortcode( $shortcode );
			}
		}
	}

	public function get_suggestion( $action ) {
		$hook_args = self::$hook_args;
		if ( is_search() ) {
			$page               = 'search';
			$hook_args[ $page ] = self::$hook_args['archive'];
		} elseif ( is_shop() ) {
			$page               = 'shop';
			$hook_args[ $page ] = self::$hook_args['archive'];
		} elseif ( is_product_category() ) {
			$page               = 'cat';
			$hook_args[ $page ] = self::$hook_args['archive'];
		} elseif ( is_single() || is_product() ) {
			$page = 'single';
		} elseif ( is_cart() ) {
			$page = 'cart';
		} elseif ( is_checkout() ) {
			$page = 'checkout';
		}
		if ( ! isset( $hook_args[ $page ][ $action ] ) ) {
			return '';
		}
		if ( isset( self::$cache[ $page ][ $action ] ) ) {
			return self::$cache[ $page ][ $action ];
		}
		if ( ! isset( self::$cache[ $page ] ) ) {
			self::$cache[ $page ] = $this->get_suggestion_for_page( $page );
		}

		return self::$cache[ $page ][ $action ] ?? '';
	}

	public function get_suggestion_for_page( $page ) {
		if ( isset( self::$cache[ $page ] ) ) {
			return self::$cache[ $page ];
		}
		self::$cache[ $page ] = array();
		switch ( $page ) {
			case 'search':
			case 'shop':
			case 'cat':
				foreach ( self::$hook_args['archive'] as $k => $v ) {
					self::$cache[ $page ][ $k ] = array();
				}
				break;
			default:
				foreach ( self::$hook_args[ $page ] as $k => $v ) {
					self::$cache[ $page ][ $k ] = array();
				}
		}
		$suggested_products = self::$settings->get_params( 'suggested_products' );
		if ( is_array( $suggested_products ) && ! empty( $suggested_products ) ) {
			foreach ( array_keys( $suggested_products ) as $id ) {
				$position = self::$settings->get_current_setting_by_subtitle( 'suggested_products', $id, $page . '_page_position', '' );
				if ( ! $position ) {
					continue;
				}
				switch ( $page ) {
					case 'search':
					case 'shop':
					case 'cat':
						switch ( $position ) {
							case 'before_content':
								self::$cache[ $page ]['woocommerce_before_main_content'][] = $id;
								break;
							case 'before_loop':
								self::$cache[ $page ]['woocommerce_before_shop_loop'][] = $id;
								break;
							case 'after_loop':
								self::$cache[ $page ]['woocommerce_after_shop_loop'][] = $id;
								break;
							case 'after_content':
								self::$cache[ $page ]['woocommerce_after_main_content'][] = $id;
								break;
						}
						break;
					case 'single':
						switch ( $position ) {
							case 'before_content':
								self::$cache[ $page ]['woocommerce_before_single_product'][] = $id;
								break;
							case 'after_summary':
								self::$cache[ $page ]['woocommerce_after_single_product_summary'][] = $id;
								break;
							case 'after_content':
								self::$cache[ $page ]['woocommerce_after_single_product'][] = $id;
								break;
						}
						break;
					case 'cart':
						switch ( $position ) {
							case 'before_content':
								self::$cache[ $page ]['woocommerce_before_cart'][] = $id;
								break;
							case 'after_table':
								self::$cache[ $page ]['woocommerce_after_cart_table'][] = $id;
								break;
							case 'after_content':
								self::$cache[ $page ]['woocommerce_after_cart'][] = $id;
								break;
						}
						break;
					case 'checkout':
						switch ( $position ) {
							case 'before_content':
								self::$cache[ $page ]['woocommerce_before_checkout_form'][] = $id;
								break;
							case 'before_billing':
								self::$cache[ $page ]['woocommerce_before_checkout_billing_form'][] = $id;
								break;
							case 'before_order_review':
								self::$cache[ $page ]['woocommerce_review_order_before_cart_contents'][] = $id;
								break;
							case 'after_payment':
								self::$cache[ $page ]['woocommerce_review_order_after_payment'][] = $id;
								break;
							case 'after_content':
								self::$cache[ $page ]['woocommerce_after_checkout_form'][] = $id;
								break;
						}
						break;
				}
			}
		}

		return self::$cache[ $page ];
	}
}
