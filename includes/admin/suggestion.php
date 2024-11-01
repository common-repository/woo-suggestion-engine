<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VI_WOO_SUGGESTION_ENGINE_Admin_Suggestion {
	protected $settings;

	public function __construct() {
		$this->settings = VIWSE_DATA::get_instance();
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'wp_ajax_viwse_save_suggestion', array( $this, 'viwse_save_suggestion' ) );
		add_action( 'wp_ajax_viwse_search_product', array( $this, 'viwse_search_product' ) );
		add_action( 'wp_ajax_viwse_search_category', array( $this, 'viwse_search_category' ) );
	}

	public function add_admin_menu() {
		$manage_role = $this->settings->get_user_capability();
		add_submenu_page(
			'woo-suggestion-engine',
			esc_html__( 'Products suggestion', 'woo-suggestion-engine' ),
			esc_html__( 'Products suggestion', 'woo-suggestion-engine' ),
			$manage_role,
			'viwse-suggestion',
			array( $this, 'settings_callback' )
		);
	}

	public function viwse_search_category() {
		check_ajax_referer( '_viwse_action_nonce', 'nonce' );
		if ( ! current_user_can( $this->settings->get_user_capability() ) ) {
			return;
		}
		$keyword = isset( $_REQUEST['keyword'] ) ? sanitize_text_field( $_REQUEST['keyword'] ) : '';
		if ( empty( $keyword ) ) {
			die();
		}
		$categories = get_terms(
			array(
				'taxonomy' => 'product_cat',
				'orderby'  => 'name',
				'order'    => 'ASC',
				'search'   => $keyword,
				'number'   => 100
			)
		);
		$items      = array();
		if ( is_array( $categories ) && count( $categories ) ) {
			foreach ( $categories as $category ) {
				$item    = array(
					'id'   => $category->slug,
					'text' => $category->name
				);
				$items[] = $item;
			}
		}
		wp_send_json( $items );
	}

	public function viwse_search_product() {
		check_ajax_referer( '_viwse_action_nonce', 'nonce' );
		if ( ! current_user_can( $this->settings->get_user_capability() ) ) {
			return;
		}
		$keyword = isset( $_REQUEST['keyword'] ) ? sanitize_text_field( $_REQUEST['keyword'] ) : '';
		if ( empty( $keyword ) ) {
			die();
		}
		$products       = wc_get_products( array(
			's'              => $keyword,
			'post_status'    => 'publish',
			'posts_per_page' => 50,
		) );
		$found_products = array();
		if ( is_array( $products ) && count( $products ) ) {
			foreach ( $products as $product ) {
				if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
					continue;
				}
				$found_products[ $product->get_id() ] = array(
					'id'   => $product->get_id(),
					'text' => $product->get_name() . '( #' . $product->get_id() . ')',
				);
				if ( $product->has_child() ) {
					$children = $product->get_children();
					foreach ( $children as $id ) {
						$product_child         = wc_get_product( $id );
						$found_products[ $id ] = array(
							'id'   => $id,
							'text' => $product_child->get_name() . '( #' . $id . ')',
						);
					}
				}
			}
		}
		wp_send_json( array_values( $found_products ) );
	}

	public function viwse_save_suggestion() {
		check_ajax_referer( '_viwse_action_nonce', 'nonce' );
		$result = array(
			'status'  => 'error',
			'message' => '',
		);
		if ( ! current_user_can( $this->settings->get_user_capability() ) ) {
			$result['message'] = esc_html__( 'You miss permission to save settings.', 'woo-suggestion-engine' );
			wp_send_json( $result );
		}
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! isset( $nonce ) || ! wp_verify_nonce( $nonce, '_viwse_action_nonce' ) ) {
			$result['message'] = esc_html__( 'Can not save settings now. Please reload and try again.', 'woo-suggestion-engine' );
			wp_send_json( $result );
		}
		$map_arg1       = array(
			'suggest_related_product'
		);
		$map_arg3       = array(
			'suggested_products'
		);
		$viwse_settings = get_option( 'viwse_params', array() );
		foreach ( $map_arg1 as $item ) {
			$viwse_settings[ $item ] = isset( $_POST[ $item ] ) ? wc_clean( wp_unslash( $_POST[ $item ] ) ) : '';
		}
		foreach ( $map_arg3 as $item ) {
			$viwse_settings[ $item ] = isset( $_POST[ $item ] ) ? villatheme_sanitize_kses( wp_unslash( $_POST[ $item ] ) ) : array();
		}
		if ( is_plugin_active( 'wp-fastest-cache/wpFastestCache.php' ) ) {
			$cache = new WpFastestCache();
			$cache->deleteCache( true );
		}
		update_option( 'viwse_params', $viwse_settings );
		$result['status']  = 'success';
		$result['message'] = esc_html__( 'Save settings successfully.', 'woo-suggestion-engine' );
		wp_send_json( $result );
	}

	public function settings_callback() {
		$this->settings = VIWSE_DATA::get_instance( true );
		?>
        <div class="wrap">
            <h2><?php esc_html_e( 'Suggested products For WooCommerce', 'woo-suggestion-engine' ); ?></h2>
            <div class="vi-ui raised">
                <form method="post" class="vi-ui form small">
					<?php wp_nonce_field( '_viwse_action_nonce', '_viwse_nonce' ); ?>
                    <div class="vi-ui top attached tabular menu">
                        <div class="item active" data-tab="general"><?php esc_html_e( 'General', 'woo-suggestion-engine' ) ?></div>
                        <div class="item" data-tab="suggestion"><?php esc_html_e( 'Products suggestion for pages', 'woo-suggestion-engine' ) ?></div>
                    </div>
                    <div class="vi-ui bottom attached tab segment active" data-tab="general">
                        <table class="form-table">
                            <tr>
                                <th>
                                    <label for="suggest_related_product-checkbox">
										<?php esc_html_e( 'Hide related products', 'woo-suggestion-engine' ); ?>
                                    </label>
                                </th>
                                <td>
                                    <div class="vi-ui checkbox toggle">
                                        <input type="hidden" name="suggest_related_product" id="suggest_related_product"
                                               value="<?php echo esc_attr( $suggest_related_product = $this->settings->get_params( 'suggest_related_product' ) ); ?>">
                                        <input type="checkbox" id="search_history_enable-checkbox" <?php checked( $suggest_related_product, 1 ) ?>>
                                    </div>
                                    <p class="description"><?php esc_html_e( 'Hide related products on the single product pages', 'woo-suggestion-engine' ); ?></p>
                                </td>
                            </tr>
                        </table>
                        <div class="vi-ui segment">
                            <strong><?php esc_html_e( 'Shortcode to display the list of suggested products:', 'woo-suggestion-engine' ); ?></strong>
                            <p>
                                <span class="viwse-shortcode-info" data-tooltip="<?php esc_attr_e( 'Click to copy the shortcode', 'woo-suggestion-engine' ); ?>">
                                    [viwse_suggestion title='' suggestion='best_selling' include_products='' include_categories='' out_of_stock='1'
                                    limit='20' cols='4' cols_mobile='2' is_slide='1']
                                </span>
                            </p>
                            <p>
                                <button type="button" class="vi-ui button small green viwse-button-create-shortcode">
									<?php esc_html_e( 'Create shortcode', 'woo-suggestion-engine' ); ?>
                                </button>
                            </p>
                        </div>
                    </div>
                    <div class="vi-ui bottom attached tab segment" data-tab="suggestion">
                        <table class="vi-ui small celled striped table">
                            <thead>
                            <tr>
                                <th><?php esc_html_e( 'Title', 'woo-suggestion-engine' ); ?></th>
                                <th><?php esc_html_e( 'Suggestion', 'woo-suggestion-engine' ); ?></th>
                                <th><?php esc_html_e( 'Position', 'woo-suggestion-engine' ); ?></th>
                                <th><?php esc_html_e( 'Action', 'woo-suggestion-engine' ); ?></th>
                            </tr>
                            </thead>
                            <tbody class="viwse-suggestion-for-pages"></tbody>
                        </table>
						<?php
						$suggested_products = $this->settings->get_params( 'suggested_products' );
						$products           = $categories = array();
						foreach ( $suggested_products as $id => $item ) {
							$include_products = $this->settings->get_current_setting_by_subtitle( 'suggested_products', $id, 'include_products' );
							$include_cats     = $this->settings->get_current_setting_by_subtitle( 'suggested_products', $id, 'include_categories' );
							if ( is_array( $include_products ) && count( $include_products ) ) {
								foreach ( $include_products as $product_id ) {
									if ( ! in_array( $product_id, $products ) && ( $product = wc_get_product( $product_id ) ) ) {
										$products[ $product_id ] = $product->get_name() . '( #' . $product_id . ')';
									}
								}
							}
							if ( is_array( $include_cats ) && count( $include_cats ) ) {
								foreach ( $include_cats as $cat ) {
									if ( ! in_array( $cat, $categories ) && ( $category = get_term_by( 'slug', $cat, 'product_cat' ) ) ) {
										$categories[ $cat ] = $category->name;
									}
								}
							}
						}
						?>
                        <input type="hidden" name="products_info" value="<?php echo esc_attr( empty( $products ) ? '{}' : villatheme_json_encode( $products ) ); ?>">
                        <input type="hidden" name="categories_info" value="<?php echo esc_attr( empty( $categories ) ? '{}' : villatheme_json_encode( $categories ) ); ?>">
                        <input type="hidden" name="suggested_products"
                               value="<?php echo esc_attr( empty( $suggested_products ) ? '{}' : villatheme_json_encode( $suggested_products ) ); ?>">
                        <p>
                            <button type="button" class="vi-ui button small green viwse-button-add-suggested-products">
								<?php esc_html_e( 'Add suggested products', 'woo-suggestion-engine' ); ?>
                            </button>
                        </p>
                    </div>
                    <p class="viwse-button-action-wrap">
                        <button type="button" name="viwse-suggestion-save" class="vi-ui primary button viwse-button-save viwse-suggestion-save">
							<?php esc_html_e( 'Save', 'woo-suggestion-engine' ) ?>
                        </button>
                    </p>
                </form>
				<?php do_action( 'villatheme_support_woo-suggestion-engine' ); ?>
            </div>
        </div>
		<?php
	}
}