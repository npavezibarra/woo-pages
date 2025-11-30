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
                // Override the global WordPress query with our custom product order
                global $wp_query;

                // Get current page for pagination
                $paged = (get_query_var('paged')) ? get_query_var('paged') : 1;

                $args = array(
                    'post_type' => 'product',
                    'post__in' => $visible_ids,
                    'orderby' => 'post__in',
                    'posts_per_page' => 32, // 32 products per page
                    'paged' => $paged,
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
        
            // Custom Persistent Cart Notice
            if (function_exists('WC') && WC()->cart) {
                // Filter out "Undo" notices to show our persistent notice instead
                $all_success_notices = wc_get_notices('success');
                $has_undo = false;
                $other_notices = array();

                if (!empty($all_success_notices)) {
                    foreach ($all_success_notices as $notice) {
                        // Check for "restore-item" class which WooCommerce adds to the undo link
                        if (strpos($notice['notice'], 'restore-item') !== false) {
                            $has_undo = true;
                        } else {
                            $other_notices[] = $notice;
                        }
                    }

                    if ($has_undo) {
                        // Clear all success notices
                        wc_clear_notices();
                        // Re-add the ones we want to keep (if any)
                        foreach ($other_notices as $notice) {
                            wc_add_notice($notice['notice'], 'success');
                        }
                    }
                }

                // Now check if we should show the persistent notice
                // We show it if cart is not empty AND there are no other success notices (like "Added to cart")
                if (!WC()->cart->is_empty()) {
                    $current_notices = wc_get_notices('success');
                    if (empty($current_notices)) {
                        $cart_url = wc_get_cart_url();
                        ?>
                        <div class="wc-block-components-notice-banner is-success" role="alert" tabindex="-1">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true"
                                focusable="false">
                                <path d="M16.7 7.1l-6.3 8.5-3.3-2.5-.9 1.2 4.5 3.4L17.9 8z"></path>
                            </svg>
                            <div class="wc-block-components-notice-banner__content">
                                Tienes productos en tu carrito. <a href="<?php echo esc_url($cart_url); ?>"
                                    class="button wc-forward wp-element-button">Ver carrito</a>
                            </div>
                        </div>
                        <?php
                    }
                }
            }

            woocommerce_output_all_notices();

            // Remove default loop hooks
            remove_action('woocommerce_before_shop_loop_item', 'woocommerce_template_loop_product_link_open', 10);
            remove_action('woocommerce_after_shop_loop_item', 'woocommerce_template_loop_product_link_close', 5);
            remove_action('woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10);

            // Re-open link for title and price
            add_action('woocommerce_shop_loop_item_title', 'woocommerce_template_loop_product_link_open', 5);
            add_action('woocommerce_after_shop_loop_item', 'woocommerce_template_loop_product_link_close', 5);

            // Add custom hooks for image wrapper and button
            add_action('woocommerce_before_shop_loop_item', function () {
                global $product;
                $product_id = $product->get_id();

                // Find if product is in cart and get its key and remove URL
                $cart_item_key = '';
                $remove_url = '';

                if (WC()->cart) {
                    foreach (WC()->cart->get_cart() as $key => $item) {
                        if ($item['product_id'] == $product_id) {
                            $cart_item_key = $key;
                            $remove_url = wc_get_cart_remove_url($key);
                            break;
                        }
                    }
                }

                $display_style = $cart_item_key ? '' : 'display: none;';

                echo '<div class="villegas-image-wrapper">';
                echo '<div class="villegas-cart-indicator" data-product-id="' . esc_attr($product_id) . '" style="' . $display_style . '">';
                echo '<div class="villegas-cart-badge">';
                echo '<span>En carrito</span>';
                echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512" fill="currentColor" width="14" height="14"><path d="M0 24C0 10.7 10.7 0 24 0H69.5c22 0 41.5 12.8 50.6 32h411c26.3 0 45.5 25 38.6 50.4l-41 152.3c-8.5 31.4-37 53.3-69.5 53.3H170.7l5.4 28.5c2.2 11.3 12.1 19.5 23.6 19.5H488c13.3 0 24 10.7 24 24s-10.7 24-24 24H199.7c-34.6 0-64.3-24.6-70.7-58.5L77.4 54.5c-.7-3.8-4-6.5-7.9-6.5H24C10.7 48 0 37.3 0 24zM128 464a48 48 0 1 1 96 0 48 48 0 1 1 -96 0zm336-48a48 48 0 1 1 0 96 48 48 0 1 1 0-96z"/></svg>';
                echo '</div>';
                // Store the native remove URL in the data attribute
                echo '<button class="villegas-remove-from-cart" data-remove-url="' . esc_url($remove_url) . '" aria-label="Remove from cart">';
                echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512" fill="currentColor" width="12" height="12"><path d="M342.6 150.6c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L192 210.7 86.6 105.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3L146.7 256 41.4 361.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0L192 301.3 297.4 406.6c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L237.3 256 342.6 150.6z"/></svg>';
                echo '</button>';
                echo '</div>';
            }, 5);

            add_action('woocommerce_before_shop_loop_item', 'woocommerce_template_loop_product_link_open', 10);
            add_action('woocommerce_before_shop_loop_item_title', 'woocommerce_template_loop_product_link_close', 11);
            add_action('woocommerce_before_shop_loop_item_title', 'woocommerce_template_loop_add_to_cart', 12);
            add_action('woocommerce_before_shop_loop_item_title', function () {
                echo '</div>';
            }, 13);

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

            /**
             * Pagination
             */
            woocommerce_pagination();
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

    <script type="text/javascript">
        jQuery(function ($) {
            // Cart indicator functionality
            function updateCartIndicators() {
                // Get cart contents from WooCommerce
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'get_cart_product_ids'
                    },
                    success: function (response) {
                        if (response.success) {
                            var cartProductIds = response.data.product_ids;

                            // Hide all indicators first
                            $('.villegas-cart-indicator').hide();

                            // Show indicators for products in cart
                            cartProductIds.forEach(function (productId) {
                                $('.villegas-cart-indicator[data-product-id="' + productId + '"]').show();
                            });
                        }
                    }
                });
            }

            // Update indicators on page load
            updateCartIndicators();

            // Update indicators after adding to cart
            $(document.body).on('added_to_cart', function () {
                updateCartIndicators();
            });

            // Handle remove from cart button click
            $(document).on('click', '.villegas-remove-from-cart', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var $button = $(this);
                var removeUrl = $button.data('remove-url');
                
                if (removeUrl) {
                    console.log('Redirecting to remove URL:', removeUrl);
                    // Disable button to prevent multiple clicks
                    $button.prop('disabled', true);
                    // Redirect to the native WooCommerce remove URL
                    window.location.href = removeUrl;
                } else {
                    console.error('No remove URL found for this item.');
                }
            });
        });
    </script>

    <?php wp_footer(); ?>
</body>

</html>