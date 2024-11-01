<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( empty( $product_ids ) || ! is_array( $product_ids ) ) {
	return;
}
$class         = array(
	'viwse-suggestion-products-wrap',
	'viwse-suggestion-products-cols-' . $cols,
	'viwse-suggestion-products-cols_mobile-' . $cols_mobile,
);
$slide_data    = array(
	'cols'        => $cols,
	'cols_mobile' => $cols_mobile,
);
$class_content = array( 'viwse-suggestion-content' );
if ( ! empty( $is_slide ) ) {
	$class_content[] = 'viwse-slide';
	wp_enqueue_script( 'flexslider' );
}
wp_enqueue_style( 'viwse-suggestion' );
wp_enqueue_script( 'viwse-suggestion' );
?>
<div class="viwse-suggestion-wrap <?php echo esc_attr( 'viwse-suggestion-wrap-' . current_time( 'timestamp' ) ); ?>">
	<?php
	if ( ! empty( $title ) ) {
		printf( '<div class="viwse-suggestion-title"><h3>%1s</h3></div>', wp_kses_post( $title ) );
	}
	?>
    <div class="<?php echo esc_attr( implode( ' ', $class_content ) ); ?>"
         data-slide_options="<?php echo esc_attr( empty( $slide_data ) ? '{}' : villatheme_json_encode( $slide_data ) ) ?>">
        <div class="<?php echo esc_attr( implode( ' ', $class ) ); ?>">
			<?php
			add_filter( 'woocommerce_post_class', array( 'VI_WOO_SUGGESTION_ENGINE_Frontend_Suggestion', 'add_sug_product_class' ), PHP_INT_MAX, 1 );
			foreach ( $product_ids as $id ) {
				printf( '<div class="viwse-suggestion-product-wrap" data-id="%1s">%2s</div>', esc_attr( $id ), do_shortcode( "[products class='viwse-suggestion-product' ids='$id']" ) );
			}
			remove_filter( 'woocommerce_post_class', array( 'VI_WOO_SUGGESTION_ENGINE_Frontend_Suggestion', 'add_sug_product_class' ), PHP_INT_MAX );
			?>
        </div>
    </div>
</div>
