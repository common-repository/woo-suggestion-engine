<?php
/**
 * Plugin Name: Suggestion Engine for WooCommerce
 * Plugin URI:https://villatheme.com/extensions/woo-suggestion-engine/
 * Description: The easiest way helps you sell more products by search engine and suggested product form your WooCommerce store.
 * Author: VillaTheme
 * Author URI: https://villatheme.com
 * License:   GPL v2 or later
 * License URI:https://www.gnu.org/licenses/gpl-2.0.html
 * Version: 2.0.3
 * Text Domain: woo-suggestion-engine
 * Domain Path: /languages
 * Copyright 2019-2024 VillaTheme.com. All rights reserved.
 * Requires Plugins: woocommerce
 * Requires PHP: 7.0
 * Requires at least: 5.0
 * Tested up to: 6.5
 * WC requires at least: 7.0
 * WC tested up to: 8.9
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit();
}
include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

class WOO_SUGGESTION_ENGINE {
	protected $plugin_name;
	protected $php_version, $wp_version, $wc_version;
	protected $errors;

	public function __construct() {
		$this->define();
		add_action( 'plugins_loaded', array( $this, 'init' ) );
		//compatible with 'High-Performance order storage (COT)'
		add_action( 'before_woocommerce_init', array( $this, 'before_woocommerce_init' ) );
	}
	public function init() {
		$include_dir = plugin_dir_path( __FILE__ ) . 'includes/';
		if ( ! class_exists( 'VillaTheme_Require_Environment' ) ) {
			include_once $include_dir . 'support.php';
		}

		$environment = new VillaTheme_Require_Environment( [
				'plugin_name'     => 'Suggestion Engine for WooCommerce',
				'php_version'     => '7.0',
				'wp_version'      => '5.0',
				'require_plugins' => [
					[
						'slug' => 'woocommerce',
						'name' => 'WooCommerce' ,
						'required_version' => '7.0',
					]
				]
			]
		);

		if ( $environment->has_error() ) {
			return;
		}
		$this->includes();
	}

	public function before_woocommerce_init() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}

	protected function define() {
		$this->plugin_name = 'Suggestion Engine for WooCommerce';
		$this->php_version = '7.0';
		$this->wp_version  = '5.0';
		$this->wc_version  = '5.0';
		define( 'VI_WOO_SUGGESTION_ENGINE_VERSION', '2.0.3' );
		define( 'VI_WOO_SUGGESTION_ENGINE_DIR', plugin_dir_path( __FILE__ ) );
		define( 'VI_WOO_SUGGESTION_ENGINE_LANGUAGES', VI_WOO_SUGGESTION_ENGINE_DIR . "languages" . DIRECTORY_SEPARATOR );
		define( 'VI_WOO_SUGGESTION_ENGINE_INCLUDES', VI_WOO_SUGGESTION_ENGINE_DIR . "includes" . DIRECTORY_SEPARATOR );
		define( 'VI_WOO_SUGGESTION_ENGINE_ADMIN', VI_WOO_SUGGESTION_ENGINE_INCLUDES . "admin" . DIRECTORY_SEPARATOR );
		define( 'VI_WOO_SUGGESTION_ENGINE_FRONTEND', VI_WOO_SUGGESTION_ENGINE_INCLUDES . "frontend" . DIRECTORY_SEPARATOR );
		define( 'VI_WOO_SUGGESTION_ENGINE_TEMPLATES', VI_WOO_SUGGESTION_ENGINE_INCLUDES . "templates" . DIRECTORY_SEPARATOR );
		$plugin_url = plugins_url( 'assets/', __FILE__ );
		define( 'VI_WOO_SUGGESTION_ENGINE_CSS', $plugin_url . "css/" );
		define( 'VI_WOO_SUGGESTION_ENGINE_JS', $plugin_url . "js/" );
		define( 'VI_WOO_SUGGESTION_ENGINE_IMG', $plugin_url . "images/" );
	}

	protected function includes() {
		$files = array(
			VI_WOO_SUGGESTION_ENGINE_INCLUDES . 'data.php',
			VI_WOO_SUGGESTION_ENGINE_INCLUDES . 'functions.php',
			VI_WOO_SUGGESTION_ENGINE_INCLUDES . 'support.php',
		);
		foreach ( $files as $file ) {
			if ( file_exists( $file ) ) {
				require_once $file;
			}
		}

		villatheme_include_folder( VI_WOO_SUGGESTION_ENGINE_INCLUDES . "background-process" . DIRECTORY_SEPARATOR, 'just_require' );
		villatheme_include_folder( VI_WOO_SUGGESTION_ENGINE_INCLUDES . "class" . DIRECTORY_SEPARATOR, 'just_require' );
		villatheme_include_folder( VI_WOO_SUGGESTION_ENGINE_ADMIN, 'VI_WOO_SUGGESTION_ENGINE_Admin_' );
		villatheme_include_folder( VI_WOO_SUGGESTION_ENGINE_FRONTEND, 'VI_WOO_SUGGESTION_ENGINE_Frontend_' );
	}
}

new WOO_SUGGESTION_ENGINE();