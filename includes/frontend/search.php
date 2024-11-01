<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VI_WOO_SUGGESTION_ENGINE_Frontend_Search {
	public static $settings, $cache = array();

	public function __construct() {
		self::$settings = VIWSE_DATA::get_instance();
		if ( ! self::$settings->get_params( 'search_enable' ) ) {
			return;
		}
		if ( ! isset( $_REQUEST['_viwse_nonce'] ) || wp_verify_nonce( sanitize_key( $_REQUEST['_viwse_nonce'] ), '_viwse_action_nonce' ) ) {
			if ( ! empty( $_GET['viwse-search'] ) ) {
				self::viwse_autocomplete();

				return;
			}
		}
		self::add_ajax_events();
		add_action( 'rest_api_init', array( $this, 'register_api' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	public static function add_ajax_events() {
		$ajax_events = array(
			'viwse_ajax_autocomplete' => true,
		);
		foreach ( $ajax_events as $ajax_event => $nopriv ) {
			add_action( 'wp_ajax_woocommerce_' . $ajax_event, array( __CLASS__, $ajax_event ) );
			if ( $nopriv ) {
				add_action( 'wp_ajax_nopriv_woocommerce_' . $ajax_event, array( __CLASS__, $ajax_event ) );
				// WC AJAX can be used for frontend ajax requests
				add_action( 'wc_ajax_' . $ajax_event, array( __CLASS__, $ajax_event ) );
			}
		}
	}

	public function register_api() {
		register_rest_route(
			'woo-suggestion-engine', '/viwse_autocomplete_history', array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'viwse_autocomplete_history' ),
				'permission_callback' => array( $this, 'permissions_check' ),
				'args'                => array(
					'product_id' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'description'       => '',
					),
					'key'        => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'description'       => '',
					),
				),
			)
		);
	}

	/**
	 * @param $request WP_REST_Request
	 *
	 * @return bool
	 */
	public function permissions_check( $request ) {
		$key     = $request->get_param( 'key' );
		$product = wc_get_product( $request->get_param( 'product_id' ) );
		if ( ! $key || ! $product ) {
			return false;
		}

		return true;
	}

	/**
	 * @param $request WP_REST_Request
	 */
	public function viwse_autocomplete_history( $request ) {
		$key        = $request->get_param( 'key' );
		$product_id = $request->get_param( 'product_id' );
		if ( ! $key || ! $product_id ) {
			return;
		}
		VIWSE_Tables::get_instance()::save_search_history( $product_id, $key );
	}

	public static function viwse_ajax_autocomplete(){
		if ( isset( $_REQUEST['_viwse_nonce'] ) && ! wp_verify_nonce( sanitize_key( $_REQUEST['_viwse_nonce'] ), '_viwse_action_nonce' ) ) {
			return;
		}
		$result                    = array();
		$key                       = isset( $_REQUEST['s'] ) ? wc_clean( $_REQUEST['s'] ) : '';
		self::$cache['start_time'] = microtime( true );
		if ( ! $key ) {
			$result[] = array(
				'no_result' => 1,
				'type'      => array( 'title', 'notice' ),
				'value'     => '',
				'message'   => esc_html__( 'Please enter some text to search', 'woo-suggestion-engine' ),
			);
			wp_send_json(
				array(
					'suggestions' => $result,
					'time'        => ( microtime( true ) - self::$cache['start_time'] ) . 's'
				)
			);
		}
		if ( ! isset( self::$cache['limit'] ) ) {
			self::$cache['limit'] = (float) self::$settings->get_params( 'search_product_total' );
		}
		if ( ! isset( self::$cache['result_position'] ) ) {
			self::$cache['result_position'] = self::$settings->get_params( 'search_result_sort' );
		}
		$position = self::$cache['result_position'];
		if ( is_array( $position ) ) {
			foreach ( $position as $item ) {
				switch ( $item ) {
					case 'product_cat':
					case 'product_tag':
						self::ajax_search_tax( $result, $key, $item );
						break;
					case 'product':
						self::ajax_search_product( $result, $key, false,self::$cache['limit'] );
						break;
				}
			}
		}
		if ( ! isset( $result['product_cat_title'] ) && ! isset( $result['product_tag_title'] ) && isset( $result['product_title'] ) ) {
			unset( $result['product_title'] );
		}
		if ( empty( $result ) ) {
			$result['no_result'] = array(
				'no_result' => 1,
				'type'      => array( 'title', 'notice' ),
				'value'     => '',
				'message'   => self::$settings->get_params( 'search_no_result_title' ),
			);
			if ( $suggestion = self::$settings->get_params( 'search_no_result_suggestion' ) ) {
				if (!isset(self::$cache['no_result_limit'])){
					self::$cache['no_result_limit'] = self::$settings->get_params('search_no_result_suggestion_total') ?? 7;
				}
				$arg = array(
					'status'     => 'publish',
					'visibility' => 'visible',
					'return'     => 'ids',
					'limit'      => self::$cache['no_result_limit'],
				);
				switch ( $suggestion ) {
					case 'best_selling':
						$arg['meta_key'] = 'total_sales';// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
						$arg['orderby']  = 'meta_value_num';
						break;
					case 'latest':
						$arg['order'] = 'DESC';
						break;
					case 'is_sale':
						$include_products = wc_get_product_ids_on_sale();
						if ( ! is_array( $include_products ) || empty( $include_products ) ) {
							$arg = array();
							break;
						}
						$arg['include'] = $include_products;
						break;
				}
				if ( ! empty( $arg ) ) {
					$temp = wc_get_products( $arg );
				}
				if ( ! empty( $temp ) ) {
					self::ajax_suggestion_information( $result, $temp );
				}
			}
		}
		wp_send_json(
			array(
				'suggestions' => apply_filters( 'viwse_autocomplete_results', self::sort_suggestion( $result ,$key), $key ),
				'time'        => ( microtime( true ) - self::$cache['start_time'] ) . 's'
			)
		);
	}
	public static function sort_suggestion($result, $key){
		if (!$key || empty($result) || isset($result['no_result'])){
			return array_values($result);
		}
		$tmp = $product = $tags = $cat = array();
		foreach ($result as $k => $v){
			if (in_array($k, ['product_cat_title','product_tag_title','product_title'])){
				continue;
			}
			if (strpos($k,'product_cat') === 0){
				if (strpos($v['name']??'',$key) !== false){
					array_unshift( $cat, $v );
				}else{
					array_push($cat, $v);
				}
			}elseif (strpos($k,'product_tag') === 0){
				if (strpos($v['name']??'',$key) !== false){
					array_unshift( $tags, $v );
				}else{
					array_push($tags, $v);
				}
			}elseif (strpos($k,'pd_') === 0){
				if (strpos($v['name']??'',$key) !== false){
					array_unshift( $product, $v );
				}else{
					array_push($product, $v);
				}
			}
		}
		$position = self::$cache['result_position'];
		if ( is_array( $position ) ) {
			foreach ( $position as $item ) {
				if (isset($result[$item.'_title'])){
					$tmp[]=$result[$item.'_title'];
				}
				switch ( $item ) {
					case 'product_cat':
						$tmp +=$cat;
						break;
					case 'product_tag':
						$tmp +=$tags;
						break;
					case 'product':
						$tmp +=$product;
						break;
				}
			}
		}
		return $tmp;
	}
	public static function ajax_search_tax( &$result, $key, $type ) {
		if ( ! in_array( $type, array( 'product_cat', 'product_tag' ) ) ) {
			return;
		}
		if ( $type === 'product_cat' && ! self::$settings->get_params( 'search_category_enable' ) ) {
			return;
		}
		if ( $type === 'product_tag' && ! self::$settings->get_params( 'search_tag_enable' ) ) {
			return;
		}
		$words = self::get_search_words( $key );
		if ( ! is_array( $words ) || empty( $words ) ) {
			return;
		}
		$search_result = $ids = $all_result = array();
		foreach ( $words as $k => $v ) {
			if ( isset( self::$cache[ 'search_result_' . $type ] ) ) {
				self::$cache[ 'search_result_' . $type ] = array();
			}
			if ( ! isset( self::$cache[ 'search_result_' . $type ][ $k ] ) ) {
				self::$cache[ 'search_result_' . $type ][ $k ] = self::get_tax_by_word( $v, $type ) ;
			}
			$search_result[ $k ] = self::$cache[ 'search_result_' . $type ][ $k ];
			if ( empty( $ids ) ) {
				$ids = array_column( $search_result[ $k ], 'id' );
			} else {
				$ids = array_intersect( $ids, array_column( $search_result[ $k ], 'id' ) );
			}
			foreach ( $search_result[ $k ] as $v_t ) {
				$all_result[ $v_t['id'] ] = $v_t;
			}
		}
		if ( empty( $all_result ) ) {
			return;
		}
		if ( empty( $ids ) ) {
			$temp = array_slice( array_values( $all_result ), 0, self::$cache['limit'] );
		} else {
			$temp  = array();
			$ids   = array_unique( $ids );
			$count = 0;
			foreach ( $ids as $id ) {
				if ( isset( $all_result[ $id ] ) ) {
					$count ++;
					$temp[] = $all_result[ $id ];
					unset( $all_result[ $id ] );
				}
			}
			if ( $count < self::$cache['limit'] ) {
				$temp = $temp + array_slice( array_values( $all_result ), 0, self::$cache['limit'] - $count );
			}
		}
		if ( ! empty( $temp ) ) {
			if ( ! isset( $result[ $type . '_title' ] ) ) {
				$result[ $type . '_title' ] = array(
					$type . '_title' => 1,
					'type'           => array( 'title', $type ),
					'value'          => '',
					'message'        => $type === 'product_cat' ? self::$settings->get_params( 'search_category_title' ) : self::$settings->get_params( 'search_tag_title' ),
				);
			}
			foreach ( $temp as $tax ) {
				$result[ $type . $tax['id'] ] = apply_filters( 'viwse_autocomplete_' . $type, array(
					'is_taxonomy' => 1,
					'is_' . $type => 1,
					'type'        => array( $type ),
					'id'          => $tax['id'],
					'name'        => $tax['name'],
					'url'         => $tax['permalink'],
					'value'       => '',
				), $tax, $result );
			}
		}
	}
	public static function get_tax_by_word( $words, $type ){
		if (is_array($words)){
			$ids = array();
			foreach ($words as $word){
				$tmp = self::get_tax_by_word($word,$type);
				$ids = $ids + $tmp;
			}
			$result = $ids;
		}else {
			$words = trim($words,'*');
			if (isset(self::$cache['ajax_'.$type.'_by_word'][$words])){
				return self::$cache['ajax_'.$type.'_by_word'][$words];
			}
			$arg = array(
				'taxonomy'   => $type,
				'orderby'    => 'name',
				'order'      => 'ASC',
				'search'     => $words,
				'hide_empty' => true
			);
			if (!isset(self::$cache['ajax_'.$type.'_by_word'])){
				self::$cache['ajax_'.$type.'_by_word'] = array();
			}
			if (!isset(self::$cache['ajax_'.$type.'_by_word'][$words])){
				$terms = get_terms($arg);
				$tmp = array();
				if ( count( $terms ) ) {
					foreach ( $terms as $term ) {
						$tmp[] = array(
							'id' => $term->term_id,
							'name' => $term->name,
							'permalink' => get_term_link( $term->term_id ),
						);
					}
				}
				self::$cache['ajax_'.$type.'_by_word'][$words] = $tmp;
			}
			$result = self::$cache['ajax_'.$type.'_by_word'][$words];
		}
		return $result;
	}
	public static function ajax_search_product( &$result, $key, $fuzzy_search = false, $limit = 7 ) {
		if ( ! $limit ) {
			return;
		}
		if ($limit < self::$cache['limit'] || $fuzzy_search) {
			$words = self::get_search_words( $key, false );
		}else {
			$words = array( $key => $key );
		}
		if ( ! is_array( $words ) || empty( $words ) ) {
			return;
		}
		$search_result = $ids = $all_result = array();
		foreach ( $words as $k => $v ) {
				if ( isset( self::$cache['search_result'] ) ) {
					self::$cache['search_result'] = array();
				}
				if ( ! isset( self::$cache['search_result'][ $k ] ) ) {
					self::$cache['search_result'][ $k ] = self::get_product_id_by_word( $v );
				}
				$search_result[ $k ] = self::$cache['search_result'][ $k ];
			if ( empty( $ids ) ) {
				$ids = $search_result[ $k ];
			} else {
				$ids = array_intersect( $ids, $search_result[ $k ] );
			}
			$all_result = array_merge( $all_result, $search_result[ $k ] );
		}
		if ( empty( $all_result ) ) {
			return;
		}
		$all_result = array_unique( $all_result );
		if ( empty( $ids ) ) {
			$ids = array_slice( $all_result, 0, $limit );
		} elseif ( count( $ids ) < $limit) {
			$ids     = array_unique( $ids );
			$all_result = array_diff( $all_result, $ids );
			$ids     = ! empty( $all_result ) ? array_merge( $ids, array_slice( $all_result, 0, $limit - count( $ids ) ) ) : $ids;
		} else {
			$ids     = array_unique( $ids );
			$ids = array_slice( $ids, 0, $limit );
		}
		if ( ! empty( $ids ) ) {
			if ( ! isset( $result['product_title'] ) ) {
				$result['product_title'] = array(
					'product_title' => 1,
					'type'          => array( 'title', 'product' ),
					'value'         => '',
					'message'       => self::$settings->get_params( 'search_product_title' ),
				);
			}
			$limit -= count($ids);
			self::ajax_suggestion_information( $result, $ids );
		}
		if ($limit > 0 && !$fuzzy_search){
			self::ajax_search_product( $result, $key, true,$limit );
		}
	}
	public static function get_product_id_by_word($words){
		if (is_array($words)){
			$ids = array();
			foreach ($words as $word){
				$tmp = self::get_product_id_by_word($word);
				if (empty($ids)){
					$ids = $tmp;
				}elseif (is_array($tmp) && count($tmp)){
					$ids = array_intersect($ids, $tmp);
				}
			}
			$result = $ids;
		}else {
			$words = trim($words,'*');
			if (isset(self::$cache['ajax_product_id_by_word'][$words])){
				return self::$cache['ajax_product_id_by_word'][$words];
			}
			$arg = array(
				'status'     => 'publish',
				'visibility' => 'visible',
				'return'     => 'ids',
				's'     => $words,
				'limit'      => self::$cache['limit'],
			);
			if (!isset(self::$cache['ajax_product_id_by_word'])){
				self::$cache['ajax_product_id_by_word'] = array();
			}
			if (!isset(self::$cache['ajax_product_id_by_word'][$words])){
				self::$cache['ajax_product_id_by_word'][$words] = wc_get_products($arg);
			}
			$result = self::$cache['ajax_product_id_by_word'][$words];
		}
		return $result;
	}
	public static function ajax_suggestion_information( &$result, $product ) {
		if ( ! is_a( $product, 'WC_Product' ) && is_array( $product ) ) {
			foreach ( $product as $item ) {
				self::ajax_suggestion_information( $result, $item );
			}
			return;
		}
		$product = wc_get_product( $product );
		if ( ! $product || isset( $result[ 'pd_' . $product->get_id() ] ) ) {
			return;
		}
		$temp                                 = array(
			'is_product' => 1,
			'type'       => array( 'product' ),
			'id'         => $product->get_id(),
			'url'        => $product->get_permalink(),
			'name'       => $product->get_name( 'edit' ),
			'price'      => $product->get_price_html(),
			'sku'        => $product->get_sku(),
			'img_src'    => wp_get_attachment_image_src( get_post_thumbnail_id( $product->get_id() ), 'woocommerce_gallery_thumbnail' )[0] ?? '',
			'value'      => '',
		);
		$result[ 'pd_' . $product->get_id() ] = apply_filters( 'viwse_autocomplete_product', $temp, $product, $result );
	}
	public static function viwse_autocomplete() {
		if ( isset( $_REQUEST['_viwse_nonce'] ) && ! wp_verify_nonce( sanitize_key( $_REQUEST['_viwse_nonce'] ), '_viwse_action_nonce' ) ) {
			return;
		}
		if ( empty( $_GET['viwse-search'] ) ) {
			return;
		}
		self::$cache['start_time'] = microtime( true );
		if ( ! wp_doing_ajax() ) {
			define( 'DOING_AJAX', true );
			define( 'VIWSE_DOING_AJAX', true );
			@header( 'Content-Type: application/json' . get_option( 'blog_charset' ) );
			@header( 'X-Robots-Tag: noindex' );
			send_origin_headers();
			send_nosniff_header();
			nocache_headers();
			status_header( 200 );
		}
		$result = array();
		$key    = isset( $_REQUEST['s'] ) ? sanitize_text_field( $_REQUEST['s'] ) : '';
		if ( ! $key ) {
			$result[] = array(
				'no_result' => 1,
				'type'      => array( 'title', 'notice' ),
				'value'     => '',
				'message'   => esc_html__( 'Please enter some text to search', 'woo-suggestion-engine' ),
			);
			wp_send_json(
				array(
					'suggestions' => $result,
					'time'        => ( microtime( true ) - self::$cache['start_time'] ) . 's'
				)
			);
		}
		if ( ! isset( self::$cache['limit'] ) ) {
			self::$cache['limit'] = (float) self::$settings->get_params( 'search_product_total' );
		}
		if ( ! isset( self::$cache['result_position'] ) ) {
			self::$cache['result_position'] = self::$settings->get_params( 'search_result_sort' );
		}
		$position = self::$cache['result_position'];
		if ( is_array( $position ) ) {
			foreach ( $position as $item ) {
				switch ( $item ) {
					case 'product_cat':
					case 'product_tag':
						self::search_tax( $result, $key, $item );
						break;
					case 'product':
						self::search_product( $result, $key,false, self::$cache['limit'] );
						break;
				}
			}
		}
		if ( ! isset( $result['product_cat_title'] ) && ! isset( $result['product_tag_title'] ) && isset( $result['product_title'] ) ) {
			unset( $result['product_title'] );
		}
		if ( empty( $result ) ) {
			$result['no_result'] = array(
				'no_result' => 1,
				'type'      => array( 'title', 'notice' ),
				'value'     => '',
				'message'   => self::$settings->get_params( 'search_no_result_title' ),
			);
			if ( $suggestion = self::$settings->get_params( 'search_no_result_suggestion' ) ) {
				if (!isset(self::$cache['no_result_limit'])){
					self::$cache['no_result_limit'] = self::$settings->get_params('search_no_result_suggestion_total') ?? 7;
				}
				$temp = VIWSE_Tables::get_instance()::get_product_suggestion( $suggestion, self::$cache['no_result_limit'] );
				if ( ! empty( $temp ) ) {
					self::suggestion_information( $result,  $temp );
				}
			}
		}
		wp_send_json(
			array(
				'suggestions' => apply_filters( 'viwse_autocomplete_results',  self::sort_suggestion( $result,$key ), $key ),
				'time'        => ( microtime( true ) - self::$cache['start_time'] ) . 's'
			)
		);
	}

	public static function search_tax( &$result, $key, $type ) {
		if ( ! in_array( $type, array( 'product_cat', 'product_tag' ) ) ) {
			return;
		}
		if ( $type === 'product_cat' && ! self::$settings->get_params( 'search_category_enable' ) ) {
			return;
		}
		if ( $type === 'product_tag' && ! self::$settings->get_params( 'search_tag_enable' ) ) {
			return;
		}
		$words = self::get_search_words( $key );
		if ( ! is_array( $words ) || empty( $words ) ) {
			return;
		}
		$search_result = $ids = $all_result = array();
		foreach ( $words as $k => $v ) {
			if ( isset( self::$cache[ 'search_result_' . $type ] ) ) {
				self::$cache[ 'search_result_' . $type ] = array();
			}
			if ( ! isset( self::$cache[ 'search_result_' . $type ][ $k ] ) ) {
				self::$cache[ 'search_result_' . $type ][ $k ] = array_values( VIWSE_Tables::get_instance()::get_tax_by_word( $v, $type ) );
			}
			$search_result[ $k ] = self::$cache[ 'search_result_' . $type ][ $k ];
			if ( empty( $ids ) ) {
				$ids = array_column( $search_result[ $k ], 'id' );
			} else {
				$ids = array_intersect( $ids, array_column( $search_result[ $k ], 'id' ) );
			}
			foreach ( $search_result[ $k ] as $v_t ) {
				$all_result[ $v_t['id'] ] = $v_t;
			}
		}
		if ( empty( $all_result ) ) {
			return;
		}
		if ( empty( $ids ) ) {
			$temp = array_slice( array_values( $all_result ), 0, self::$cache['limit'] );
		} else {
			$temp  = array();
			$ids   = array_unique( $ids );
			$count = 0;
			foreach ( $ids as $id ) {
				if ( isset( $all_result[ $id ] ) ) {
					$count ++;
					$temp[] = $all_result[ $id ];
					unset( $all_result[ $id ] );
				}
			}
			if ( $count < self::$cache['limit'] ) {
				$temp = $temp + array_slice( array_values( $all_result ), 0, self::$cache['limit'] - $count );
			}
		}
		if ( ! empty( $temp ) ) {
			if ( ! isset( $result[ $type . '_title' ] ) ) {
				$result[ $type . '_title' ] = array(
					$type . '_title' => 1,
					'type'           => array( 'title', $type ),
					'value'          => '',
					'message'        => $type === 'product_cat' ? self::$settings->get_params( 'search_category_title' ) : self::$settings->get_params( 'search_tag_title' ),
				);
			}
			foreach ( $temp as $tax ) {
				$result[ $type . $tax['id'] ] = apply_filters( 'viwse_autocomplete_' . $type, array(
					'is_taxonomy' => 1,
					'is_' . $type => 1,
					'type'        => array( $type ),
					'id'          => $tax['id'],
					'name'        => $tax['name'],
					'url'         => $tax['permalink'],
					'value'       => '',
				), $tax, $result );
			}
		}
	}

	public static function search_product( &$result, $key, $fuzzy_search = false, $limit = 7 ) {
		if ( ! $limit ) {
			return;
		}
		$words = self::get_search_words( $key, $fuzzy_search );
		if ( ! is_array( $words ) || empty( $words ) ) {
			return;
		}
		$search_result = $ids = $all_result = array();
		foreach ( $words as $k => $v ) {
			if ( $fuzzy_search ) {
				if ( isset( self::$cache['fuzzy_search_result'] ) ) {
					self::$cache['fuzzy_search_result'] = array();
				}
				if ( ! isset( self::$cache['fuzzy_search_result'][ $k ] ) ) {
					self::$cache['fuzzy_search_result'][ $k ] = VIWSE_Tables::get_instance()::get_product_id_by_word( $v, $k );
				}
				$search_result[ $k ] = self::$cache['fuzzy_search_result'][ $k ];
			} else {
				if ( isset( self::$cache['search_result'] ) ) {
					self::$cache['search_result'] = array();
				}
				if ( ! isset( self::$cache['search_result'][ $k ] ) ) {
					self::$cache['search_result'][ $k ] = VIWSE_Tables::get_instance()::get_product_id_by_word( $v );
				}
				$search_result[ $k ] = self::$cache['search_result'][ $k ];
			}
			if ( empty( $ids ) ) {
				$ids = $search_result[$k];
			} else {
				$ids = array_intersect( $ids, $search_result[ $k ]);
			}
			$all_result += $search_result[$k];
		}
		if ( empty($ids) && empty( $all_result ) ) {
			if ( ! $fuzzy_search && self::$cache['fuzzy_search'] ) {
				self::search_product( $result, $key, true, $limit );
			}
			return;
		}
		$all_result = array_unique($all_result);
		if ( empty( $ids ) ) {
			$temp = array_slice( array_values( $all_result ), 0, $limit );
		} else {
			$temp = $ids   = array_unique( $ids );
			$count = count($ids);
			if ( $count < $limit ) {
				$temp = $temp + array_slice( array_diff($all_result,$ids), 0, $limit - $count );
			}
		}
		if ( ! empty( $temp ) ) {
			if ( ! isset( $result['product_title'] ) ) {
				$result['product_title'] = array(
					'product_title' => 1,
					'type'          => array( 'title', 'product' ),
					'value'         => '',
					'message'       => self::$settings->get_params( 'search_product_title' ),
				);
			}
			$result[current_time('timestamp')] = $temp;
			$products = VIWSE_Tables::get_instance()::get_product_suggestion('','', $temp);
			if ( ! empty( $products ) ) {
				self::suggestion_information( $result,  $products );
			}
		}
		$limit_t = ! $fuzzy_search && self::$cache['fuzzy_search'] ? $limit - count( $temp ) : 0;
		if ( $limit_t ) {
			self::search_product( $result, $key, true, $limit_t );
		}
	}

	public static function get_search_words( $text, $fuzzy_search = false ) {
		$result = $words = array();
		if ( ! isset( self::$cache['split'] ) ) {
			self::$cache['split'] = array();
		}
		if ( ! isset( self::$cache['split'][ $text ] ) ) {
			VIWSE_Render_Search_Table::get_word( $words, $text, 'search' );
			self::$cache['split'][ $text ] = $words;
		} else {
			$words = self::$cache['split'][ $text ];
		}
		if ( empty( $words ) ) {
			return $result;
		}
		if ( ! isset( self::$cache['fuzzy_search'] ) ) {
			self::$cache['fuzzy_search'] = self::$settings->get_params( 'search_fuzzy_enable' );
		}
		if ( $fuzzy_search ) {
			if ( ! isset( self::$cache['fuzzy_search_distance'] ) ) {
				self::$cache['fuzzy_search_distance'] = apply_filters( 'viwse_fuzzy_search_distance', 2 );
			}
			if ( ! isset( self::$cache['fuzzy_search_words'] ) ) {
				self::$cache['fuzzy_search_words'] = array();
			}
			foreach ( $words as $word ) {
				if ( isset( self::$cache['fuzzy_search_words'][ $word ] ) ) {
					$result[ $word ] = self::$cache['fuzzy_search_words'][ $word ];
					continue;
				}
				$tmp[]           = ( function_exists( 'mb_substr' ) ? mb_substr( $word, 0, 1 ) : substr( $word, 0, 1 ) ) . '*';
				$result[ $word ] = self::$cache['fuzzy_search_words'][ $word ] = $tmp;
			}
		} else {
			if ( ! isset( self::$cache['words'] ) ) {
				self::$cache['words'] = array();
			}
			if ( ! isset( self::$cache['synonyms'] ) ) {
				$synonyms = self::$settings->get_params( 'search_synonyms' );
				$tmp      = array();
				if ( $synonyms ) {
					$synonyms = explode( "\n", $synonyms );
					foreach ( $synonyms as $v ) {
						if ( $v ) {
							$tmp[] = array_map( 'trim', explode( ',', $v ) );
						}
					}
				}
				self::$cache['synonyms'] = $tmp;
			}
			foreach ( $words as $word ) {
				if ( isset( self::$cache['words'][ $word ] ) ) {
					$result[ $word ] = self::$cache['words'][ $word ];
					continue;
				}
				$tmp = array();
				if ( ! empty( self::$cache['synonyms'] ) ) {
					foreach ( self::$cache['synonyms'] as $synonyms ) {
						if ( is_array( $synonyms ) && in_array( $word, $synonyms ) ) {
							$tmp = $synonyms;
						}
					}
				}
				$tmp[]           = $word . '*';
				$result[ $word ] = self::$cache['words'][ $word ] = $tmp;
			}
		}

		return $result;
	}

	public static function suggestion_information( &$result, $product ) {
		if ( ! isset( $product['product_id'] ) ) {
			if ( is_array( $product ) ) {
				foreach ( $product as $item ) {
					self::suggestion_information( $result, $item );
				}
			}
			return;
		}
		$temp                                     = array(
			'is_product' => 1,
			'type'       => array( 'product' ),
			'id'         => $product['product_id'],
			'url'        => $product['permalink'] ?? '',
			'name'       => $product['name'] ?? '',
			'price'      => $product['price'] ?? '',
			'sku'        => $product['sku'] ?? '',
			'img_src'    => $product['image'] ?? '',
			'value'      => '',
		);
		$result[ 'pd_' . $product['product_id'] ] = apply_filters( 'viwse_autocomplete_product', $temp, $product, $result );
	}

	public function enqueue_scripts() {
		self::$settings::enqueue_style(
			array( 'viwse-search' ),
			array( 'frontend-search' )
		);
		self::$settings::enqueue_script(
			array( 'viwse-autocomplete', 'viwse-search' ),
			array( 'autocomplete', 'frontend-search' )
		);
		$search_url = add_query_arg( 'viwse-search', '1',
			remove_query_arg( array( 'remove_item', 'add-to-cart', 'added-to-cart', 'order_again', '_wpnonce' ), home_url( '/', 'relative' ) ) );
		$bg_render = get_option('viwse_background_render_complete','');
		$language = '';
		if (!$bg_render || self::$settings->get_params('search_ajax_enable') ){
			$search_url=WC_AJAX::get_endpoint('viwse_ajax_autocomplete');
		}elseif ( is_plugin_active( 'sitepress-multilingual-cms/sitepress.php' ) ) {
			$default_lang     = apply_filters( 'wpml_default_language', null );
			$current_language = apply_filters( 'wpml_current_language', null );
			if ( $current_language && $current_language !== $default_lang ) {
				$search_url=WC_AJAX::get_endpoint('viwse_ajax_autocomplete');
				$language = $current_language;
			}
		} else if ( class_exists( 'Polylang' ) ) {
			$default_lang     = pll_default_language( 'slug' );
			$current_language = pll_current_language( 'slug' );
			if ( $current_language && $current_language !== $default_lang ) {
				$search_url=WC_AJAX::get_endpoint('viwse_ajax_autocomplete');
				$language = $current_language;
			}
		}elseif (!isset($bg_render['lang']) || $bg_render['lang'] !== get_locale()){
			$search_url=WC_AJAX::get_endpoint('viwse_ajax_autocomplete');
			$language = get_locale();
		}
		$viwse_param = array(
			'search_history'      => site_url('/wp-json/woo-suggestion-engine/viwse_autocomplete_history') ,
			'search_url'          => $search_url,
			'language'          => $language,
			'min_chars'           => apply_filters( 'viwse_autocomplete_min_chars', 3 ),
			'suggestion_info'     => self::$settings->get_params( 'search_product_show' ),
			'placeholder_img_src' => wc_placeholder_img_src( 'woocommerce_gallery_thumbnail' ),
			'loading_icon'        => apply_filters( 'viwse_autocomplete_show_loading_icon', 1 ) ?: '',
		);
		wp_localize_script( 'viwse-search', 'viwse_param', $viwse_param );
	}
}