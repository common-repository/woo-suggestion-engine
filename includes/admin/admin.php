<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VI_WOO_SUGGESTION_ENGINE_Admin_Admin {

	public function __construct() {
		add_filter( 'plugin_action_links_woo-suggestion-engine/woo-suggestion-engine.php', array( $this, 'settings_link' ) );
		add_action( 'init', array( $this, 'init' ) );
	}

	public function init() {
		load_plugin_textdomain( 'woo-suggestion-engine' );
		$this->load_plugin_textdomain();
		if ( class_exists( 'VillaTheme_Support' ) ) {
			new VillaTheme_Support(
				array(
					'support'    => 'https://wordpress.org/support/plugin/woo-suggestion-engine/',
					'docs'       => 'http://docs.villatheme.com/?item=woo-suggestion-engine',
					'review'     => 'https://wordpress.org/support/plugin/woo-suggestion-engine/reviews/?rate=5#rate-response',
					'css'        => VI_WOO_SUGGESTION_ENGINE_CSS,
					'image'      => VI_WOO_SUGGESTION_ENGINE_IMG,
					'slug'       => 'woo-suggestion-engine',
					'menu_slug'  => 'woo-suggestion-engine',
					'survey_url' => 'https://script.google.com/macros/s/AKfycbyk-iBVNbm1unRU26sX2eLMOt1v9edGKRHXJP3nGo9FDDQ5fdVuSId5jxzGYAAdiONS/exec',
					'version'    => VI_WOO_SUGGESTION_ENGINE_VERSION
				)
			);
		}

	}

	public function load_plugin_textdomain() {
		$locale = apply_filters( 'plugin_locale', get_locale(), 'woo-suggestion-engine' );
		// Global + Frontend Locale
		load_textdomain( 'woo-suggestion-engine', VI_WOO_SUGGESTION_ENGINE_LANGUAGES . "woo-suggestion-engine-$locale.mo" );
		load_plugin_textdomain( 'woo-suggestion-engine', false, VI_WOO_SUGGESTION_ENGINE_LANGUAGES );
	}

	public function settings_link( $links ) {
		$settings_link = sprintf( '<a href="%s" title="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=woo-suggestion-engine' ) ),
			esc_html__( 'Settings', 'woo-suggestion-engine' ), esc_html__( 'Settings', 'woo-suggestion-engine' )
		);
		array_unshift( $links, $settings_link );

		return $links;
	}
}