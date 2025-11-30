<?php
/**
 * Template Name: Villegas Shop One
 *
 * @package Woo_Pages
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php wp_title('|', true, 'right'); ?></title>
    <?php wp_head(); ?>
</head>

<body <?php body_class('villegas-shop-one'); ?>>

    <?php
    echo do_blocks('<!-- wp:template-part {"slug":"header","area":"header","tagName":"header"} /-->');
    ?>

    <header class="villegas-shop-header">
        <div class="villegas-shop-header-inner">
            <div class="villegas-shop-breadcrumb">
                <?php woocommerce_breadcrumb(); ?>
            </div>
            <div class="villegas-shop-title-row">
                <h1><?php esc_html_e('Tienda', 'woo-pages'); ?></h1>
                <?php woocommerce_catalog_ordering(); ?>
            </div>
            <div class="villegas-shop-result-count">
                <?php woocommerce_result_count(); ?>
            </div>
        </div>
    </header>

    <div class="villegas-shop-content">
        <?php
        /**
         * Hook: woocommerce_before_main_content.
         *
         * Removed: woocommerce_breadcrumb - 20
         */
        remove_action('woocommerce_before_main_content', 'woocommerce_breadcrumb', 20, 0);
        do_action('woocommerce_before_main_content');

        // CUSTOM PRODUCT ORDERING & VISIBILITY
        $custom_order = get_option('woo_pages_product_order', array());

        if (!empty($custom_order)) {
            // Filter for visible products only
            $visible_ids = array();
            foreach ($custom_order as $product_id) {
                $is_visible = get_post_meta($product_id, '_woo_pages_visible', true);
                if ($is_visible !== '0') {
                    $visible_ids[] = $product_id;
                }
            }

            if (!empty($visible_ids)) {
                // Override the global products WordPress query ($GLOBALS['wp_query'])
                global $wp_query;
                $args = array(
                    'post_type' => 'product',
                    'post__in' => $visible_ids,
                    'orderby' => 'post__in',
                    'posts_per_page' => -1,
                    'post_status' => 'publish',
                );
                $wp_query = new WP_Query($args);

                // Setup WooCommerce loop properties
                wc_setup_loop(array(
                    'total' => $wp_query->found_posts,
                    'per_page' => $wp_query->query_vars['posts_per_page'],
                    'current_page' => max(1, $wp_query->get('paged', 1)),
                    'total_pages' => $wp_query->max_num_pages,
                ));
            }
        }

        if (woocommerce_product_loop()) {

            /**
             * Hook: woocommerce_before_shop_loop.
             *
             * Removed: woocommerce_output_all_notices - 10
             * Removed: woocommerce_result_count - 20
             * Removed: woocommerce_catalog_ordering - 30
             */
            // do_action( 'woocommerce_before_shop_loop' );
            woocommerce_output_all_notices();

            // Remove default loop hooks
            remove_action('woocommerce_before_shop_loop_item', 'woocommerce_template_loop_product_link_open', 10);
            remove_action('woocommerce_after_shop_loop_item', 'woocommerce_template_loop_product_link_close', 5);
            remove_action('woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10);

            // Add custom hooks for image wrapper and button
            add_action('woocommerce_before_shop_loop_item', function () {
                echo '<div class="villegas-image-wrapper">';
            }, 5);
            add_action('woocommerce_before_shop_loop_item', 'woocommerce_template_loop_product_link_open', 10);
            add_action('woocommerce_before_shop_loop_item_title', 'woocommerce_template_loop_product_link_close', 11);
            add_action('woocommerce_before_shop_loop_item_title', 'woocommerce_template_loop_add_to_cart', 12);
            add_action('woocommerce_before_shop_loop_item_title', function () {
                echo '</div>';
            }, 13);

            // Re-open link for title and price
            add_action('woocommerce_shop_loop_item_title', 'woocommerce_template_loop_product_link_open', 5);
            add_action('woocommerce_after_shop_loop_item', 'woocommerce_template_loop_product_link_close', 5);

            woocommerce_product_loop_start();

            if (wc_get_loop_prop('total')) {
                while (have_posts()) {
                    the_post();

                    /**
                     * Hook: woocommerce_shop_loop.
                     */
                    do_action('woocommerce_shop_loop');

                    wc_get_template_part('content', 'product');
                }
            }

            woocommerce_product_loop_end();

            /**
             * Hook: woocommerce_after_shop_loop.
             */
            do_action('woocommerce_after_shop_loop');
        } else {
            /**
             * Hook: woocommerce_no_products_found.
             */
            do_action('woocommerce_no_products_found');
        }

        /**
         * Hook: woocommerce_after_main_content.
         */
        do_action('woocommerce_after_main_content');
        ?>
    </div>

    <?php wp_footer(); ?>
</body>

</html>