<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VI_WOO_SUGGESTION_ENGINE_Admin_Search_Engine {
	protected $settings;
	public static $background_render_product_ids = array(), $bg_render_product_cats = array(), $bg_render_product_tags = array();

	public function __construct() {
		$this->settings = VIWSE_DATA::get_instance();
		add_action( 'init', array( $this, 'background_process' ) );
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'save_settings' ), 99 );
		add_action( 'wp_ajax_viwse_search_ajax_enable', array( $this, 'viwse_search_ajax_enable' ) );
		add_action( 'wp_ajax_viwse_background_settings', array( $this, 'viwse_background_settings' ) );
		add_action( 'wp_ajax_viwse_background_processing_status', array( $this, 'viwse_background_processing_status' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ), PHP_INT_MAX );
		if ( ! wp_doing_ajax() ) {
			add_action( 'admin_init', array( $this, 'maybe_create_table' ), 90 );
		}
		$hooks = array(
			'product'     => array(
				'woocommerce_process_product_meta',
				'woocommerce_new_product',
				'woocommerce_update_product',
				'wp_trash_post',
				'untrashed_post',
				'deleted_post',
			),
			'product_cat' => array(
				'woocommerce_api_create_product_category',
				'woocommerce_api_delete_product_category',
				'woocommerce_api_edit_product_category',
				'created_product_cat',
				'edited_product_cat',
				'delete_product_cat',
			),
			'product_tag' => array(
				'woocommerce_api_create_product_tag',
				'woocommerce_api_delete_product_tag',
				'woocommerce_api_edit_product_tag',
				'created_product_tag',
				'edited_product_tag',
				'delete_product_tag',
			),
		);
		foreach ( $hooks as $type => $list ) {
			foreach ( $list as $action ) {
				add_action( $action, array( $this, 'background_render_' . $type ), 99, 1 );
			}
		}
	}

	public function add_admin_menu() {
		$manage_role = $this->settings->get_user_capability();
		add_menu_page(
			esc_html__( 'Suggestion Engine For WooCommerce', 'woo-suggestion-engine' ),
			esc_html__( 'Suggestion Engine', 'woo-suggestion-engine' ),
			$manage_role,
			'woo-suggestion-engine',
			array( $this, 'settings_callback' ),
			'dashicons-feedback',
			'2'
		);
		add_submenu_page(
			'woo-suggestion-engine',
			esc_html__( 'Search Engine', 'woo-suggestion-engine' ),
			esc_html__( 'Search Engine', 'woo-suggestion-engine' ),
			$manage_role,
			'woo-suggestion-engine',
			array( $this, 'settings_callback' )
		);
	}
    public function viwse_background_processing_status(){
	    $result = array(
		    'status'  => 'error',
		    'message' => '',
		    'onlyajax_visible' =>1,
		    'bg_processing' =>1,
	    );
	    if ( ! current_user_can( $this->settings->get_user_capability() ) ) {
		    wp_send_json( $result );
	    }
	    $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
	    if ( ! $nonce  || ! wp_verify_nonce( $nonce, '_viwse_action_nonce' ) ) {
		    wp_send_json( $result );
	    }
	    if (!empty(get_option('viwse_background_render_processing', ''))){
		    wp_send_json( $result );
	    }
	    $bg_complete = get_option('viwse_background_render_complete', '');
        if (empty($bg_complete)){
	        wp_send_json( $result );
        }
	    $lastbuild = $bg_complete['end'] ??'';
	    if ($lastbuild){
		    $lastbuild =date_i18n($this->settings::get_datetime_format(), $lastbuild);
		    /* translators: %1s: last build time */
		    $message = sprintf(wp_kses_post(__('The search data is built successfully. Last build %1s. Click <a href="admin.php?page=wc-status&tab=logs" target="_blank">here</a> to see log.', 'woo-suggestion-engine')), $lastbuild);
	    }
	    $result['bg_processing'] = '';
	    $result['bg_bt_text'] = esc_html__( 'Rebuild search data', 'woo-suggestion-engine' );
	    $result['message'] = $message ?? wp_kses_post(__('The search data is built successfully. Click <a href="admin.php?page=wc-status&tab=logs" target="_blank">here</a> to see log.', 'woo-suggestion-engine'));
	    $result['status']  = 'success';
	    $result['bg_text'] = $result['message'];
	    wp_send_json( $result );
    }
    public function viwse_background_settings(){
	    $result = array(
		    'status'  => 'error',
	    );
	    if ( ! current_user_can( $this->settings->get_user_capability() ) ) {
		    $result['message'] = esc_html__( 'You miss permission to save settings.', 'woo-suggestion-engine' );
		    wp_send_json( $result );
	    }
	    $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
	    if ( ! $nonce  || ! wp_verify_nonce( $nonce, '_viwse_action_nonce' ) ) {
		    $result['message'] = esc_html__( 'Can not save settings now. Please reload and try again.', 'woo-suggestion-engine' );
		    wp_send_json( $result );
	    }
	    $build_data = isset( $_POST['build_data'] ) ? sanitize_text_field( wp_unslash( $_POST['build_data'] ) ) : '';
        if ($build_data){
	        VIWSE_Render_Search_Table::get_instance()->maybe_handle( true );
	        $viwse_settings = get_option( 'viwse_params', array() );
	        $viwse_settings['search_ajax_enable'] = 0;
	        update_option( 'viwse_params', $viwse_settings );
	        if ( is_plugin_active( 'wp-fastest-cache/wpFastestCache.php' ) ) {
		        $cache = new WpFastestCache();
		        $cache->deleteCache( true );
	        }
	        $result['bg_processing'] = 1;
	        $result['onlyajax_visible'] = 1;
	        $result['message'] = wp_kses_post(__('The search data is building. Don\'t worry, it doesn\'t affect your search engine. Click <a href="admin.php?page=wc-status&tab=logs" target="_blank">here</a> to see log.', 'woo-suggestion-engine'));
	        $result['bg_bt_text'] = esc_html__( 'Cancel processing', 'woo-suggestion-engine' );
        }else{
	        VIWSE_Background_Render_Table::get_instance()->kill_process();
	        delete_option('viwse_background_render_processing');
	        $bg_complete = get_option('viwse_background_render_complete', '');
            if (empty($bg_complete)){
	            $result['bg_bt_text'] = esc_html__( 'Build search data', 'woo-suggestion-engine' );
	            $result['message'] = esc_html__('The search data is not built. Click the \'Build search data\' button to create data to speed up the search.', 'woo-suggestion-engine');
            }else{
                $lastbuild = $bg_complete['end'] ??'';
                if ($lastbuild){
                    $lastbuild =date_i18n(get_option( 'date_format' ) . ', ' . get_option( 'time_format' ), $lastbuild);
	                /* translators: %1s: last build time */
                    $message = sprintf(wp_kses_post(__('The search data is built successfully. Last build %1s. Click <a href="admin.php?page=wc-status&tab=logs" target="_blank">here</a> to see log.', 'woo-suggestion-engine')), $lastbuild);
                }
	            $result['onlyajax_visible'] = 1;
	            $result['bg_bt_text'] = esc_html__( 'Rebuild search data', 'woo-suggestion-engine' );
	            $result['message'] = $message ?? wp_kses_post(__('The search data is built successfully. Click <a href="admin.php?page=wc-status&tab=logs" target="_blank">here</a> to see log.', 'woo-suggestion-engine'));
            }
        }
	    $result['status']  = 'success';
	    $result['bg_text'] = $result['message'];
	    wp_send_json( $result );
    }
    public function viwse_search_ajax_enable(){
	    $result = array(
		    'status'  => 'error',
		    'message' => '',
	    );
	    if ( ! current_user_can( $this->settings->get_user_capability() ) ) {
		    $result['message'] = esc_html__( 'You miss permission to save settings.', 'woo-suggestion-engine' );
		    wp_send_json( $result );
	    }
	    $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
	    if ( ! isset( $_POST['search_ajax_enable'] ) || ! $nonce  || ! wp_verify_nonce( $nonce, '_viwse_action_nonce' ) ) {
		    $result['message'] = esc_html__( 'Can not save settings now. Please reload and try again.', 'woo-suggestion-engine' );
		    wp_send_json( $result );
	    }
	    $viwse_settings = get_option( 'viwse_params', array() );
        $viwse_settings['search_ajax_enable'] = 1;
	    VIWSE_Background_Render_Table::get_instance()->kill_process();
	    delete_option('viwse_background_render_processing');
	    delete_option('viwse_background_render_complete');
	    if ( is_plugin_active( 'wp-fastest-cache/wpFastestCache.php' ) ) {
		    $cache = new WpFastestCache();
		    $cache->deleteCache( true );
	    }
	    update_option( 'viwse_params', $viwse_settings );
	    $result['status']  = 'success';
	    $result['message'] = esc_html__( 'Save settings successfully.', 'woo-suggestion-engine' );
	    $result['bg_text'] = esc_html__('The search data is not built. Click the \'Build search data\' button to create data to speed up the search.', 'woo-suggestion-engine');
	    $result['bg_bt_text'] = esc_html__( 'Build search data', 'woo-suggestion-engine' );
	    VIWSE_Render_Search_Table::get_instance()::delete_tables(array( 'viwse_word_list', 'viwse_product_list', 'viwse_relationships', 'viwse_taxonomy_list' ));
	    wp_send_json( $result );
    }

	public function save_settings() {
		if ( ! current_user_can( $this->settings->get_user_capability() ) ) {
			return;
		}
		if ( ! isset( $_POST['_viwse_nonce'] ) || ! wp_verify_nonce( wc_clean( $_POST['_viwse_nonce'] ), '_viwse_action_nonce' ) ) {
			return;
		}
		if ( ! isset( $_POST['viwse-save'] ) ) {
			return;
		}
		$map_arg1 = array(
			'search_enable',
			'search_fuzzy_enable',
			'search_history_enable',
			'search_product_total',
			'search_category_enable',
			'search_category_total',
			'search_tag_enable',
			'search_tag_total',
			'search_no_result_suggestion',
			'search_no_result_suggestion_total',
		);
		$map_arg2 = array(
			'search_synonyms',
			'search_product_title',
			'search_category_title',
			'search_tag_title',
			'search_no_result_title',
		);
		$map_arg3 = array(
			'search_product_search_in',
			'search_product_show',
			'search_result_sort',
		);
		$arg      = array();
		global $viwse_settings;
		foreach ( $map_arg1 as $item ) {
			$arg[ $item ] = isset( $_POST[ $item ] ) ? wc_clean( wp_unslash( $_POST[ $item ] ) ) : '';
		}
		foreach ( $map_arg2 as $item ) {
			$arg[ $item ] = isset( $_POST[ $item ] ) ? wp_kses_post( wp_unslash( $_POST[ $item ] ) ) : '';
		}
		foreach ( $map_arg3 as $item ) {
			$arg[ $item ] = isset( $_POST[ $item ] ) ? wc_clean( wp_unslash( $_POST[ $item ] ) ) : array();
		}
		if ( ( ( $viwse_settings['search_product_search_in'] ?? '' ) != ( $arg['search_product_search_in'] ?? '' ) )
		     || ( ( $viwse_settings['search_category_enable'] ?? '' ) != ( $arg['search_category_enable'] ?? '' ) )
		     || ( ( $viwse_settings['search_tag_enable'] ?? '' ) != ( $arg['search_tag_enable'] ?? '' ) )
		) {
			$reset_table = true;
		}
		$args = wp_parse_args( $arg, $viwse_settings );
		if ( is_plugin_active( 'wp-fastest-cache/wpFastestCache.php' ) ) {
			$cache = new WpFastestCache();
			$cache->deleteCache( true );
		}
		$viwse_settings = $args;
		update_option( 'viwse_params', $args );
		if ( ! empty( $reset_table ) && empty($viwse_settings['search_ajax_enable']) ) {
			VIWSE_Render_Search_Table::get_instance()->maybe_handle( true );
		}
	}

	public function settings_callback() {
		$this->settings = VIWSE_DATA::get_instance( true );
        $only_ajax = $this->settings->get_params('search_ajax_enable');
        $bg_processing = get_option('viwse_background_render_processing', '');
        $bg_complete = get_option('viwse_background_render_complete', '');
		$bg_bt = !empty($bg_processing )? esc_html__('Cancel processing', 'woo-suggestion-engine') : (!empty($bg_complete) ? esc_html__('Rebuild search data', 'woo-suggestion-engine') : esc_html__('Build search data', 'woo-suggestion-engine'));
		?>
        <div class="wrap">
            <h2><?php esc_html_e( 'Search Engine For WooCommerce', 'woo-suggestion-engine' ); ?></h2>
            <div class="vi-ui raised">
                <div class="vi-ui positive message">
                    <div class="header">
                        <?php esc_attr_e('Speed Search', 'woo-suggestion-engine'); ?>
                    </div>
                    <ul class="list">
                        <li>
	                        <?php
	                        esc_html_e('The plugin uses its own search system to speed up the search on your website. The search data will be automatically generated when installing the plugin and updated automatically when changing settings or product information. You can delete the data and switch to using the plugin\'s AJAX search anytime you want.', 'woo-suggestion-engine');
	                        ?>
                        </li>
                        <li class="viwse-background-render-status">
	                        <?php
	                        if ($bg_processing){
		                        echo wp_kses_post(__('The search data is building. Don\'t worry, it doesn\'t affect your search engine. Click <a href="admin.php?page=wc-status&tab=logs" target="_blank">here</a> to see log.', 'woo-suggestion-engine'));
	                        }elseif($bg_complete){
		                        $lastbuild = $bg_complete['end'] ??'';
		                        if ($lastbuild){
			                        $lastbuild = date_i18n($this->settings::get_datetime_format(), $lastbuild);
			                        /* translators: %1s: last build time */
			                        $message = sprintf(wp_kses_post(__('The search data is built successfully. Last build %1s. Click <a href="admin.php?page=wc-status&tab=logs" target="_blank">here</a> to see log.', 'woo-suggestion-engine')), $lastbuild);
		                        }
                                echo wp_kses_post($message ?? __('The search data is built successfully. Click <a href="admin.php?page=wc-status&tab=logs" target="_blank">here</a> to see log.', 'woo-suggestion-engine'));
	                        }else{
		                        $only_ajax = true;
		                        /* translators: %s: name of button to create data */
		                        printf(esc_html__('The search data is not built. Click the \'%s\' button to create data to speed up the search.', 'woo-suggestion-engine'), esc_html( $bg_bt ) );
	                        }
	                        ?>
                        </li>
                    </ul>
                    <p class="viwse-background-render-button-wrap">
                        <span class="vi-ui inverted green small button viwse-background-render-button"
                              data-bg_complete="<?php echo esc_attr(!empty($bg_complete)? 1 :'') ?>"
                              data-bg_processing="<?php echo esc_attr(!empty($bg_processing)? 1 :'') ?>">
                            <?php
                            echo esc_html( $bg_bt );
                            ?>
                        </span>
                        <?php
                        printf('<span class="vi-ui inverted grey small button viwse-search-ajax-button%1s">%2s</span>',
                            esc_attr($only_ajax ?' viwse-hidden':''),
                            esc_html__('Use Ajax Search only','woo-suggestion-engine'));
                        ?>
                    </p>
                </div>
                <form class="vi-ui form small" method="post">
					<?php wp_nonce_field( '_viwse_action_nonce', '_viwse_nonce' ); ?>
                    <div class="vi-ui top attached tabular menu">
                        <div class="item active" data-tab="general"><?php esc_html_e( 'General', 'woo-suggestion-engine' ) ?></div>
                        <div class="item" data-tab="config"><?php esc_html_e( 'Search config', 'woo-suggestion-engine' ) ?></div>
                    </div>
                    <div class="vi-ui bottom attached tab segment active" data-tab="general">
                        <table class="form-table">
                            <tr>
                                <th><label for="search_enable-checkbox"><?php esc_html_e( 'Enable', 'woo-suggestion-engine' ); ?></label></th>
                                <td>
                                    <div class="vi-ui checkbox toggle">
                                        <input type="hidden" name="search_enable" id="search_enable"
                                               value="<?php echo esc_attr( $search_enable = $this->settings->get_params( 'search_enable' ) ); ?>">
                                        <input type="checkbox" id="search_enable-checkbox" <?php checked( $search_enable, 1 ) ?>>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="search_fuzzy_enable-checkbox"><?php esc_html_e( 'Fuzzy search', 'woo-suggestion-engine' ); ?></label></th>
                                <td>
                                    <div class="vi-ui checkbox toggle">
                                        <input type="hidden" name="search_fuzzy_enable" id="search_fuzzy_enable"
                                               value="<?php echo esc_attr( $search_fuzzy_enable = $this->settings->get_params( 'search_fuzzy_enable' ) ); ?>">
                                        <input type="checkbox" id="search_fuzzy_enable-checkbox" <?php checked( $search_fuzzy_enable, 1 ) ?>>
                                    </div>
                                    <p class="description">
                                        <?php esc_html_e('To use this feature, Speed Search must be enabled.', 'woo-suggestion-engine'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="search_synonyms"><?php esc_html_e( 'Synonyms', 'woo-suggestion-engine' ); ?></label></th>
                                <td>
                                    <textarea name="search_synonyms" id="search_synonyms"
                                              placeholder="<?php
                                              echo wp_kses_post( __( 'Allow returning more relevant search results by using synonyms. You can create multiple lists of different synonyms on each line. Synonyms in the list should be separated by commas. 
Example:
   mobile,google,samsung,apple,xiaomi
   laptop,apple,dell,asus,hp',
	                                              'woo-suggestion-engine' ) ) ?>"><?php echo wp_kses_post( $this->settings->get_params( 'search_synonyms' ) ); ?></textarea>

                                </td>
                            </tr>
                            <!--<tr>
                                <th>
                                    <label for="search_history_enable-checkbox">
										<?php /*esc_html_e( 'Show the previous search when focusing the search input', 'woo-suggestion-engine' ); */ ?>
                                    </label>
                                </th>
                                <td>
                                    <div class="vi-ui checkbox toggle">
                                        <input type="hidden" name="search_history_enable" id="search_history_enable"
                                               value="<?php /*echo esc_attr( $search_history_enable = $this->settings->get_params( 'search_history_enable' ) ); */ ?>">
                                        <input type="checkbox" id="search_history_enable-checkbox" <?php /*checked( $search_history_enable, 1 ) */ ?>>
                                    </div>
                                </td>
                            </tr>-->
                        </table>
                    </div>
                    <div class="vi-ui bottom attached tab segment" data-tab="config">
                        <div class="field">
                            <div class="vi-ui styled fluid accordion">
                                <div class="title active">
									<?php esc_html_e( 'Products', 'woo-suggestion-engine' ); ?>
                                    <i class="dropdown icon"></i>
                                </div>
                                <div class="content active">
                                    <table class="form-table">
                                        <tr>
                                            <th><label for="search_product_title"><?php esc_html_e( 'Title', 'woo-suggestion-engine' ); ?></label></th>
                                            <td>
                                                <input type="text" name="search_product_title" id="search_product_title"
                                                       value="<?php echo wp_kses_post( $this->settings->get_params( 'search_product_title' ) ) ?>">
                                                <p class="description"><?php esc_html_e( 'Enter your label for search results.', 'woo-suggestion-engine' ); ?></p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><label for="search_product_total"><?php esc_html_e( 'Limit', 'woo-suggestion-engine' ); ?></label></th>
                                            <td>
                                                <input type="number" min="1" id="search_product_total" name="search_product_total"
                                                       value="<?php echo esc_attr( $this->settings->get_params( 'search_product_total' ) ) ?>">
                                                <p class="description"><?php esc_html_e( 'Maximum number of suggested products', 'woo-suggestion-engine' ); ?></p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><label for="search_product_search_in"><?php esc_html_e( 'Search in', 'woo-suggestion-engine' ); ?></label></th>
                                            <td>
												<?php
												$product_search_in        = array(
													'sku'  => esc_html__( 'Product SKU', 'woo-suggestion-engine' ),
													'desc' => esc_html__( "Product description", 'woo-suggestion-engine' )
												);
												$search_product_search_in = $this->settings->get_params( 'search_product_search_in' );
												?>
                                                <select name="search_product_search_in[]" id="search_product_search_in" class="vi-ui fluid dropdown" multiple>
													<?php
													foreach ( $product_search_in as $k => $v ) {
														printf( '<option value="%1s" %2s> %3s</option>', esc_attr( $k ),
															wp_kses_post( selected( in_array( $k, $search_product_search_in ) ) ),
															esc_html( $v ) );
													}
													?>
                                                </select>
                                                <p class="description"><?php esc_html_e( 'Allow searching products by some product field with the product\'s name',
														'woo-suggestion-engine' ); ?></p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>
                                                <label for="search_product_show"><?php esc_html_e( 'Result information', 'woo-suggestion-engine' ); ?></label>
                                            </th>
                                            <td>
                                                <select name="search_product_show[]" id="search_product_show" class="vi-ui fluid dropdown" multiple>
													<?php
													$search_product_show_args = array(
//														'name'       => esc_html__( 'Product name', 'woo-suggestion-engine' ),
														'image' => esc_html__( 'Product image', 'woo-suggestion-engine' ),
														'price' => esc_html__( 'Product price', 'woo-suggestion-engine' ),
														'sku'   => esc_html__( 'Product SKU', 'woo-suggestion-engine' ),
//														'short_desc' => esc_html__( 'Product short description', 'woo-suggestion-engine' ),
													);
													$search_product_show      = $this->settings->get_params( 'search_product_show' );
													foreach ( $search_product_show_args as $k => $v ) {
														printf( '<option value="%1s" %2s>%3s</option>', esc_attr( $k ),
															wp_kses_post( selected( in_array( $k, $search_product_show ) ) ), esc_html( $v ) );
													}
													?>
                                                </select>
                                                <p class="description"><?php esc_html_e( 'Please choose the product information you want to show on the results with the product\'s name',
														'woo-suggestion-engine' ); ?></p>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="field">
                            <div class="vi-ui styled fluid accordion">
                                <div class="title active">
									<?php esc_html_e( 'Categories', 'woo-suggestion-engine' ); ?>
                                    <i class="dropdown icon"></i>
                                </div>
                                <div class="content active">
                                    <table class="form-table">
                                        <tr>
                                            <th><label for="search_category_enable-checkbox"><?php esc_html_e( 'Enable', 'woo-suggestion-engine' ); ?></label></th>
                                            <td>
                                                <div class="vi-ui checkbox toggle">
                                                    <input type="hidden" id="search_category_enable" name="search_category_enable"
                                                           value="<?php echo esc_attr( $search_category_enable = $this->settings->get_params( 'search_category_enable' ) ) ?>">
                                                    <input type="checkbox" id="search_category_enable-checkbox" <?php checked( $search_category_enable, 1 ) ?>>
                                                </div>
                                                <p class="description"><?php esc_html_e( 'Allow showing product categories if found in the results',
														'woo-suggestion-engine' ); ?></p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><label for="search_category_title"><?php esc_html_e( 'Title', 'woo-suggestion-engine' ); ?></label></th>
                                            <td>
                                                <input type="text" name="search_category_title" id="search_category_title"
                                                       value="<?php echo wp_kses_post( $this->settings->get_params( 'search_category_title' ) ) ?>">
                                                <p class="description"><?php esc_html_e( 'Enter your label for search results.', 'woo-suggestion-engine' ); ?></p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><label for="search_category_total"><?php esc_html_e( 'Limit', 'woo-suggestion-engine' ); ?></label></th>
                                            <td>
                                                <input type="number" min="1" id="search_category_total" name="search_category_total"
                                                       value="<?php echo esc_attr( $this->settings->get_params( 'search_category_total' ) ) ?>">
                                                <p class="description"><?php esc_html_e( 'Maximum number of suggested categories', 'woo-suggestion-engine' ); ?></p>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="field">
                            <div class="vi-ui styled fluid accordion">
                                <div class="title active">
									<?php esc_html_e( 'Tags', 'woo-suggestion-engine' ); ?>
                                    <i class="dropdown icon"></i>
                                </div>
                                <div class="content active">
                                    <table class="form-table">
                                        <tr>
                                            <th><label for="search_tag_enable-checkbox"><?php esc_html_e( 'Enable', 'woo-suggestion-engine' ); ?></label></th>
                                            <td>
                                                <div class="vi-ui checkbox toggle">
                                                    <input type="hidden" id="search_tag_enable" name="search_tag_enable"
                                                           value="<?php echo esc_attr( $search_tag_enable = $this->settings->get_params( 'search_tag_enable' ) ) ?>">
                                                    <input type="checkbox" id="search_tag_enable-checkbox" <?php checked( $search_tag_enable, 1 ) ?>>
                                                </div>
                                                <p class="description"><?php esc_html_e( 'Allow showing product tags if found in the results', 'woo-suggestion-engine' ); ?></p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><label for="search_tag_title"><?php esc_html_e( 'Title', 'woo-suggestion-engine' ); ?></label></th>
                                            <td>
                                                <input type="text" name="search_tag_title" id="search_tag_title"
                                                       value="<?php echo wp_kses_post( $this->settings->get_params( 'search_tag_title' ) ) ?>">
                                                <p class="description"><?php esc_html_e( 'Enter your label for search results.', 'woo-suggestion-engine' ); ?></p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><label for="search_tag_total"><?php esc_html_e( 'Limit', 'woo-suggestion-engine' ); ?></label></th>
                                            <td>
                                                <input type="number" min="1" id="search_tag_total" name="search_tag_total"
                                                       value="<?php echo esc_attr( $this->settings->get_params( 'search_tag_total' ) ) ?>">
                                                <p class="description"><?php esc_html_e( 'Maximum number of suggested tags', 'woo-suggestion-engine' ); ?></p>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="field">
                            <div class="vi-ui fluid styled accordion">
                                <div class="title active">
									<?php esc_html_e( 'Search result', 'woo-suggestion-engine' ); ?>
                                    <i class="dropdown icon"></i>
                                </div>
                                <div class="content active">
                                    <table class="form-table">
                                        <tr class="viwse-search-result-sort">
                                            <th><label><?php esc_html_e( 'Result position', 'woo-suggestion-engine' ); ?></label></th>
                                            <td>
                                                <div class="viwse-search-result-position">
													<?php
													$search_result_sort      = (array) $this->settings->get_params( 'search_result_sort' );
													$search_result_sort_args = array(
														'product_cat' => esc_html__( 'Categories', 'woo-suggestion-engine' ),
														'product_tag' => esc_html__( 'Tags', 'woo-suggestion-engine' ),
														'product'     => esc_html__( 'Products', 'woo-suggestion-engine' ),
													);
													foreach ( $search_result_sort as $k ) {
														?>
                                                        <div class="viwse-search-result-item-wrap">
                                                            <div class="viwse-search-result-item viwse-search-result-<?php echo esc_attr( $k ) ?>">
                                                                <i class="expand arrows alternate icon"></i>
                                                                <input type="hidden" name="search_result_sort[]" value="<?php echo esc_attr( $k ) ?>">
                                                                <span><?php echo esc_html( $search_result_sort_args[ $k ] ); ?></span>
                                                            </div>
                                                        </div>
														<?php
													}
													?>
                                                </div>
                                                <p class="description"><?php esc_html_e( 'Drag & drop to set the position for each item on the search result',
														'woo-suggestion-engine' ); ?></p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><label for="search_no_result_title"><?php esc_html_e( 'Message if no result', 'woo-suggestion-engine' ); ?></label></th>
                                            <td>
                                                <input type="text" id="search_no_result_title" name="search_no_result_title"
                                                       value="<?php echo wp_kses_post( $this->settings->get_params( 'search_no_result_title' ) ) ?>">
                                            </td>
                                        </tr>
                                        <tr>
                                            <th>
                                                <label for="search_no_result_suggestion-checkbox">
													<?php esc_html_e( 'Show suggestion', 'woo-suggestion-engine' ); ?>
                                                </label>
                                            </th>
                                            <td>
                                                <select name="search_no_result_suggestion" id="search_no_result_suggestion" class="vi-ui fluid dropdown search_no_result_suggestion">
													<?php
													$search_no_result_suggestion      = $this->settings->get_params( 'search_no_result_suggestion' );
													$search_no_result_suggestion_args = array(
														0              => esc_html__( 'None', 'woo-suggestion-engine' ),
														'best_selling' => esc_html__( 'Best Selling', 'woo-suggestion-engine' ),
														'latest'       => esc_html__( 'Latest', 'woo-suggestion-engine' ),
														'is_sale'      => esc_html__( 'Is on sale off', 'woo-suggestion-engine' ),
													);
													foreach ( $search_no_result_suggestion_args as $k => $v ) {
														printf( '<option value="%1s" %2s>%3s</option>', esc_attr( $k ),
															wp_kses_post( selected( $k, $search_no_result_suggestion ) ), esc_html( $v ) );
													}
													?>
                                                </select>
                                                <p class="description"><?php esc_html_e( 'Show suggested products if no result', 'woo-suggestion-engine' ); ?></p>
                                            </td>
                                        </tr>
                                        <tr class="search_no_result_suggestion_enable<?php echo esc_attr($search_no_result_suggestion ? '' : ' viwse-hidden')?>">
                                            <th><label for="search_no_result_suggestion_total"><?php esc_html_e( 'Limit', 'woo-suggestion-engine' ); ?></label></th>
                                            <td>
                                                <input type="number" min="1" id="search_no_result_suggestion_total" name="search_no_result_suggestion_total"
                                                       value="<?php echo esc_attr( $this->settings->get_params( 'search_no_result_suggestion_total' ) ) ?>">
                                                <p class="description"><?php esc_html_e( 'Maximum number of suggested products', 'woo-suggestion-engine' ); ?></p>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <p class="viwse-button-action-wrap">
                        <button type="submit" name="viwse-save" class="vi-ui primary button viwse-button-save"><?php esc_html_e( 'Save', 'woo-suggestion-engine' ) ?></button>
                    </p>
                </form>
				<?php do_action( 'villatheme_support_woo-suggestion-engine' ); ?>
            </div>
        </div>
		<?php
	}

	public function admin_enqueue_scripts() {
		if ( isset( $_REQUEST['_viwse_nonce'] ) && ! wp_verify_nonce( sanitize_key( $_REQUEST['_viwse_nonce'] ), '_viwse_action_nonce' ) ) {
		    return;
        }
		$page = isset( $_REQUEST['page'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['page'] ) ) : '';
		if ( ! in_array( $page, [ 'woo-suggestion-engine', 'viwse-suggestion' ] ) ) {
			return;
		}
		$this->settings::remove_other_script();
		$this->settings::enqueue_style(
			array(
				'semantic-ui-accordion',
				'semantic-ui-button',
				'semantic-ui-checkbox',
				'semantic-ui-dropdown',
				'semantic-ui-message',
				'semantic-ui-segment',
				'semantic-ui-form',
				'semantic-ui-label',
				'semantic-ui-input',
				'semantic-ui-icon',
				'semantic-ui-popup',
				'semantic-ui-menu',
				'semantic-ui-tab',
				'semantic-ui-table',
				'transition',
				'select2'
			),
			array( 'accordion', 'button', 'checkbox', 'dropdown', 'message', 'segment', 'form', 'label', 'input', 'icon', 'popup', 'menu', 'tab', 'table', 'transition', 'select2' ),
			array( 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1 )
		);
		$this->settings::enqueue_style(
			array( 'viwse-admin-settings', 'villatheme-show-message' ),
			array( 'admin-settings', 'villatheme-show-message' ),
			array( 0 )
		);
		$this->settings::enqueue_script(
			array( 'semantic-ui-accordion', 'semantic-ui-address', 'semantic-ui-checkbox', 'semantic-ui-dropdown', 'semantic-ui-tab', 'transition', 'select2' ),
			array( 'accordion', 'address', 'checkbox', 'dropdown', 'tab', 'transition', 'select2' ),
			array( 1, 1, 1, 1, 1, 1, 1 )
		);
		$this->settings::enqueue_script(
			array( 'viwse-admin-settings', 'villatheme-show-message' ),
			array( 'admin-settings', 'villatheme-show-message' ),
			array( 0 ),
			array( array( 'jquery', 'jquery-ui-sortable' ) )
		);
		$viwse_params = array(
			'current_page'                 => $page,
			'ajax_url'                     => admin_url( 'admin-ajax.php' ),
			'background_render_processing'      => get_option('viwse_background_render_processing', '') ? 1: '',
			'search_ajax_enable'      => esc_html__( 'The speed of search may be low than Speed Search. And you can not search fuzziness.', 'woo-suggestion-engine' ),
			'remove_suggestion_title'      => esc_html__( 'Are you sure to remove this suggestion?', 'woo-suggestion-engine' ),
			'edit_title'                   => esc_html__( 'Edit', 'woo-suggestion-engine' ),
			'remove_title'                 => esc_html__( 'Remove', 'woo-suggestion-engine' ),
			'disable_title'                => esc_html__( 'Disable', 'woo-suggestion-engine' ),
			'enable_title'                 => esc_html__( 'Enable', 'woo-suggestion-engine' ),
			'shortcode_title'              => esc_html__( 'Title', 'woo-suggestion-engine' ),
			'shortcode_suggestion'         => esc_html__( 'Suggestion from', 'woo-suggestion-engine' ),
			'shortcode_include_products'   => esc_html__( 'Include products', 'woo-suggestion-engine' ),
			'shortcode_include_categories' => esc_html__( 'Include categories', 'woo-suggestion-engine' ),
			'shortcode_cols'               => esc_html__( 'Number of columns', 'woo-suggestion-engine' ),
			'shortcode_cols_desktop'       => esc_html__( 'Desktop', 'woo-suggestion-engine' ),
			'shortcode_cols_mobile'        => esc_html__( 'Mobile', 'woo-suggestion-engine' ),
			'shortcode_out_of_stock'       => esc_html__( 'Show out-of-stock products', 'woo-suggestion-engine' ),
			'shortcode_limit'              => esc_html__( 'Maximum number of suggested products', 'woo-suggestion-engine' ),
			'shortcode_is_slide'           => esc_html__( 'Show suggested products as slide', 'woo-suggestion-engine' ),
			'popup_shortcode_header'       => esc_html__( 'Shortcode', 'woo-suggestion-engine' ),
			'popup_suggestion_header'      => esc_html__( 'Products Suggestion', 'woo-suggestion-engine' ),
			'popup_sug_search_page'        => esc_html__( 'Show on the search results page', 'woo-suggestion-engine' ),
			'popup_sug_shop_page'          => esc_html__( 'Show on the shop page', 'woo-suggestion-engine' ),
			'popup_sug_cat_page'           => esc_html__( 'Show on the categories page', 'woo-suggestion-engine' ),
			'popup_sug_single_page'        => esc_html__( 'Show on the single product page', 'woo-suggestion-engine' ),
			'popup_sug_cart_page'          => esc_html__( 'Show on the cart page', 'woo-suggestion-engine' ),
			'popup_sug_checkout_page'      => esc_html__( 'Show on the checkout page', 'woo-suggestion-engine' ),
			'shortcode_suggestion_arg'     => array(
				'best_selling' => esc_html__( 'Best selling products', 'woo-suggestion-engine' ),
				'feature'      => esc_html__( 'Featured products', 'woo-suggestion-engine' ),
				'latest'       => esc_html__( 'Latest products', 'woo-suggestion-engine' ),
				'on_sale'      => esc_html__( 'On-sale products', 'woo-suggestion-engine' ),
				'top_rated'    => esc_html__( 'Top-rated products', 'woo-suggestion-engine' ),
				'top_searched' => esc_html__( 'Top-searched products', 'woo-suggestion-engine' ),
			),
			'popup_sug_checkout_arg'       => array(
				'0'                   => esc_html__( 'None', 'woo-suggestion-engine' ),
				'before_content'      => esc_html__( 'Before content', 'woo-suggestion-engine' ),
				'before_billing'      => esc_html__( 'Before billing details', 'woo-suggestion-engine' ),
				'before_order_review' => esc_html__( 'Before order review', 'woo-suggestion-engine' ),
				'after_payment'       => esc_html__( 'After payment gateways', 'woo-suggestion-engine' ),
				'after_content'       => esc_html__( 'After content', 'woo-suggestion-engine' ),
			),
			'popup_sug_cart_arg'           => array(
				'0'              => esc_html__( 'None', 'woo-suggestion-engine' ),
				'before_content' => esc_html__( 'Before content', 'woo-suggestion-engine' ),
				'after_table'    => esc_html__( 'After the table of products', 'woo-suggestion-engine' ),
				'after_content'  => esc_html__( 'After content', 'woo-suggestion-engine' ),
			),
			'popup_sug_single_arg'         => array(
				'0'              => esc_html__( 'None', 'woo-suggestion-engine' ),
				'before_content' => esc_html__( 'Before content', 'woo-suggestion-engine' ),
				'after_summary'  => esc_html__( 'After the product summary section', 'woo-suggestion-engine' ),
				'after_content'  => esc_html__( 'After content', 'woo-suggestion-engine' ),
			),
			'popup_sug_loop_arg'           => array(
				'0'              => esc_html__( 'None', 'woo-suggestion-engine' ),
				'before_content' => esc_html__( 'Before content', 'woo-suggestion-engine' ),
				'before_loop'    => esc_html__( 'Before the list of products', 'woo-suggestion-engine' ),
				'after_loop'     => esc_html__( 'After the list of products', 'woo-suggestion-engine' ),
				'after_content'  => esc_html__( 'After content', 'woo-suggestion-engine' ),
			),
		);
		wp_localize_script( 'viwse-admin-settings', 'viwse_params', $viwse_params );
	}

	public function background_process() {
		$background = VIWSE_Background_Render_Table::get_instance();
//        if (!wp_doing_ajax() && !$this->settings->get_params('search_ajax_enable') &&
//            !$background->is_process_running() && !$background->is_queue_empty()){
//	        $background->dispatch();
//        }
	}

	public function background_render_product_tag( $id ) {
		$tax = get_term( $id );
		if (!$this->settings->get_params('search_ajax_enable') &&
            $tax && $tax->taxonomy === 'product_tag' &&
            VIWSE_DATA::get_instance()->get_params( 'search_tag_enable' )&&
		     ! in_array( $tax->term_id, self::$bg_render_product_tags )
		) {
			global $viwse_background_render_tags;
			if ( ! $viwse_background_render_tags ) {
				$viwse_background_render_tags = array();
			}
			$viwse_background_render_tags[] = $tax->term_id;
			self::$bg_render_product_tags[] = $tax->term_id;
			VIWSE_Render_Search_Table::get_instance()->maybe_handle( false, 'product_tag' );
		}
	}

	public function background_render_product_cat( $id ) {
		$tax = get_term( $id );
		if ( !$this->settings->get_params('search_ajax_enable') && $tax && $tax->taxonomy === 'product_cat'
		     && VIWSE_DATA::get_instance()->get_params( 'search_category_enable' )
		     &&
		     ! in_array( $tax->term_id, self::$bg_render_product_cats )
		) {
			global $viwse_background_render_product_cats;
			if ( ! $viwse_background_render_product_cats ) {
				$viwse_background_render_product_cats = array();
			}
			$viwse_background_render_product_cats[] = $tax->term_id;
			self::$bg_render_product_cats[]         = $tax->term_id;
			VIWSE_Render_Search_Table::get_instance()->maybe_handle( false, 'product_cat' );
		}
	}

	public function background_render_product( $product ) {
		$product_t = wc_get_product( $product );
		if ( !$this->settings->get_params('search_ajax_enable') && $product_t && ! $product_t->get_parent_id()
		     && ! $product_t->is_type( 'variation' )
		     && $product_t->get_status( 'edit' ) !== 'draft'
		) {
			if ( ! VIWSE_Tables::get_instance()::check_exist( 'viwse_word_list' )
			     &&
			     ! VIWSE_Background_Render_Table::get_instance()->is_process_running()
			     && VIWSE_Background_Render_Table::get_instance()->is_queue_empty()
			) {
				VIWSE_Render_Search_Table::get_instance()->maybe_handle( true );
			} elseif ( ! in_array( $product_t->get_id(), self::$background_render_product_ids ) ) {
				global $viwse_background_render_product;
				if ( ! $viwse_background_render_product ) {
					$viwse_background_render_product = array();
				}
				$viwse_background_render_product[]     = $product_t->get_id();
				self::$background_render_product_ids[] = $product_t->get_id();
				VIWSE_Render_Search_Table::get_instance()->maybe_handle( false, 'product' );
			}
		}
	}

	public function maybe_create_table() {
		if ( defined( 'IFRAME_REQUEST' ) && IFRAME_REQUEST ) {
			return;
		}
		if ( ! get_option( 'viwse_params', '' ) || $this->settings->get_params('search_ajax_enable') ||
             VIWSE_Tables::get_instance()::check_exist( 'viwse_word_list' ) ) {
			return;
		}
		if ( get_option( 'vi_wse_woo_suggestion_engine_version', '' ) ) {
			global $wpdb;
			$old_table = $wpdb->prefix . 'wse_woocommerce_history';
			$wpdb->query( "DROP TABLE IF EXISTS {$old_table};" );// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange
			delete_option( 'vi_wse_woo_suggestion_engine_version' );
			delete_option( 'vi_wse_woocommerce_searched' );
			delete_option( 'vi_wse_woocommerce_viewed' );
			delete_option( 'vi_wse_woocommerce_product_atc' );
			delete_option( 'vi_wse_woo_suggestion_engine_params' );
		}
		update_option('viwse_background_render_complete','');
		VIWSE_Render_Search_Table::get_instance()->maybe_handle( true );
	}
}