<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VIWSE_DATA {
	protected static $instance = null, $date_format, $time_format;
	private $params, $default;

	public function __construct() {
		global $viwse_settings;
		if ( ! $viwse_settings ) {
			$viwse_settings = get_option( 'viwse_params', array() );
		}
		$search_engine      = array(
			'search_enable'                     => 1,
			'search_ajax_enable'                     => 1,
			'search_fuzzy_enable'               => 1,
			'search_synonyms'                   => '',
			'search_history_enable'             => 0,
			'search_product_title'              => 'PRODUCTS',
			'search_product_total'              => 8,
			'search_product_search_in'          => array(),
			'search_product_show'               => array( 'image', 'price' ),
			'search_category_enable'            => 0,
			'search_category_title'             => 'CATEGORIES',
			'search_category_total'             => 3,
			'search_tag_enable'                 => 0,
			'search_tag_title'                  => 'TAGS',
			'search_tag_total'                  => 3,
			'search_result_sort'                => array( 'product_cat', 'product_tag', 'product' ),
			'search_no_result_title'            => 'NO RESULT',
			'search_no_result_suggestion'       => 0,
			'search_no_result_suggestion_total' => 5,
		);
		$suggested_products = array(
			'suggest_related_product' => 0,
			'suggested_products'      => array(),
		);
		$this->default      = array_merge( $search_engine, $suggested_products );
		$this->params       = apply_filters( 'viwse_settings_args', wp_parse_args( $viwse_settings, $this->default ) );
	}

	public static function get_instance( $new = false ) {
		if ( $new || null === self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	public function get_current_setting_by_subtitle( $name = "", $subtitle = "", $i = 0, $default = false ) {
		if ( empty( $name ) ) {
			return false;
		}
		if ( $default !== false ) {
			$result = $this->get_current_setting( $name, $subtitle )[ $i ] ?? $default;
		} else {
			$result = $this->get_current_setting( $name, $subtitle )[ $i ] ?? null;
		}

		return $result;
	}

	public function get_current_setting( $name = "", $i = 0, $default = false ) {
		if ( empty( $name ) ) {
			return false;
		}
		if ( $default !== false ) {
			$result = $this->get_params( $name )[ $i ] ?? $default;
		} else {
			$result = $this->get_params( $name )[ $i ] ?? null;
		}

		return $result;
	}

	public function get_params( $name = "" ) {
		$result = null;
		if ( ! $name ) {
			return $this->params;
		} elseif ( isset( $this->params[ $name ] ) ) {
			$result = $this->params[ $name ];
		}

		return apply_filters( 'viwse_params_' . $name, $result );
	}

	public function get_default( $name = "" ) {
		$result = null;
		if ( ! $name ) {
			$result = $this->default;
		} elseif ( isset( $this->default[ $name ] ) ) {
			$result = $this->default[ $name ];
		}

		return apply_filters( 'viwse_params_default-' . $name, $result );
	}

	public function get_user_capability() {
		return apply_filters( 'viwse_user_capability', 'manage_woocommerce' );
	}
	public static function get_date_format() {
		if ( self::$date_format === null ) {
			self::$date_format = get_option( 'date_format', 'F d, Y' );
			if ( ! self::$date_format ) {
				self::$date_format = 'F d, Y';
			}
		}

		return self::$date_format;
	}

	public static function get_time_format() {
		if ( self::$time_format === null ) {
			self::$time_format = get_option( 'time_format', 'H:i:s' );
			if ( ! self::$time_format ) {
				self::$time_format = 'H:i:s';
			}
		}

		return self::$time_format;
	}

	public static function get_datetime_format() {
		return self::get_date_format() . ' ' . self::get_time_format();
	}
	public static function wc_log( $content, $source = 'background_debug', $level = 'info' ) {
		$content = wp_strip_all_tags($content );
		$log     = wc_get_logger();
		$log->log( $level,
			$content,
			array(
				'source' => 'viwse-' . $source,
			)
		);
	}
	public static function extend_post_allowed_html() {
		return array_merge( wp_kses_allowed_html( 'post' ), array(
				'input' => array(
					'type'         => 1,
					'id'           => 1,
					'name'         => 1,
					'class'        => 1,
					'placeholder'  => 1,
					'autocomplete' => 1,
					'style'        => 1,
					'value'        => 1,
					'data-*'       => 1,
					'size'         => 1,
				),
				'form'  => array(
					'type'   => 1,
					'id'     => 1,
					'name'   => 1,
					'class'  => 1,
					'style'  => 1,
					'method' => 1,
					'action' => 1,
					'data-*' => 1,
				),
				'style' => array(
					'id'    => 1,
					'class' => 1,
					'type'  => 1,
				),
			)
		);
	}

	public static function remove_other_script() {
		global $wp_scripts;
		if ( isset( $wp_scripts->registered['jquery-ui-accordion'] ) ) {
			unset( $wp_scripts->registered['jquery-ui-accordion'] );
			wp_dequeue_script( 'jquery-ui-accordion' );
		}
		if ( isset( $wp_scripts->registered['accordion'] ) ) {
			unset( $wp_scripts->registered['accordion'] );
			wp_dequeue_script( 'accordion' );
		}
		$scripts = $wp_scripts->registered;
		foreach ( $scripts as $script ) {
			if ( in_array( $script->handle, array( 'query-monitor', 'uip-app', 'uip-vue', 'uip-toolbar-app' ) ) ) {
				continue;
			}
			preg_match( '/\/wp-/i', $script->src, $result );
			if ( count( array_filter( $result ) ) ) {
				preg_match( '/(\/wp-content\/plugins|\/wp-content\/themes)/i', $script->src, $result1 );
				if ( count( array_filter( $result1 ) ) ) {
					wp_dequeue_script( $script->handle );
				}
			} else {
				wp_dequeue_script( $script->handle );
			}
		}
	}

	public static function enqueue_style( $handles = array(), $srcs = array(), $is_suffix = array(), $des = array(), $type = 'enqueue' ) {
		if ( empty( $handles ) || empty( $srcs ) ) {
			return;
		}
		$action = $type === 'enqueue' ? 'wp_enqueue_style' : 'wp_register_style';
		$suffix = WP_DEBUG ? '' : '.min';
		foreach ( $handles as $i => $handle ) {
			if ( ! $handle || empty( $srcs[ $i ] ) ) {
				continue;
			}
			$suffix_t = ! empty( $is_suffix[ $i ] ) ? '.min' : $suffix;
			$action( $handle, VI_WOO_SUGGESTION_ENGINE_CSS . $srcs[ $i ] . $suffix_t . '.css', ! empty( $des[ $i ] ) ? $des[ $i ] : array(), VI_WOO_SUGGESTION_ENGINE_VERSION );
		}
	}

	public static function enqueue_script( $handles = array(), $srcs = array(), $is_suffix = array(), $des = array(), $type = 'enqueue', $in_footer = false ) {
		if ( empty( $handles ) || empty( $srcs ) ) {
			return;
		}
		$action = $type === 'register' ? 'wp_register_script' : 'wp_enqueue_script';
		$suffix = WP_DEBUG ? '' : '.min';
		foreach ( $handles as $i => $handle ) {
			if ( ! $handle || empty( $srcs[ $i ] ) ) {
				continue;
			}
			$suffix_t = ! empty( $is_suffix[ $i ] ) ? '.min' : $suffix;
			$action( $handle, VI_WOO_SUGGESTION_ENGINE_JS . $srcs[ $i ] . $suffix_t . '.js', ! empty( $des[ $i ] ) ? $des[ $i ] : array( 'jquery' ),
				VI_WOO_SUGGESTION_ENGINE_VERSION, $in_footer );
		}
	}
}