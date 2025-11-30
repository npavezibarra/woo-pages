<?php
/**
 * Woo Pages Loader Class.
 *
 * @package Woo_Pages
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Woo_Pages_Loader class.
 */
class Woo_Pages_Loader
{

    /**
     * Constructor.
     */
    public function __construct()
    {
        add_filter('template_include', array($this, 'template_loader'));
        // Try multiple hooks to catch the product query
        add_action('pre_get_posts', array($this, 'apply_custom_product_order'));
        add_action('woocommerce_product_query', array($this, 'apply_custom_product_order_wc'), 10, 1);
        add_filter('posts_pre_query', array($this, 'filter_products_pre_query'), 10, 2);

        // AJAX handlers for cart indicator
        add_action('wp_ajax_get_cart_product_ids', array($this, 'ajax_get_cart_product_ids'));
        add_action('wp_ajax_nopriv_get_cart_product_ids', array($this, 'ajax_get_cart_product_ids'));
    }

    /**
     * Load custom templates.
     *
     * @param string $template Template path.
     * @return string Template path.
     */
    public function template_loader($template)
    {
        if (is_shop()) {
            $shop_template = get_option('woo_pages_shop_template');
            if ('villegas-shop-one' === $shop_template) {
                $new_template = WOO_PAGES_PATH . 'templates/villegas-shop-one.php';
                if (file_exists($new_template)) {
                    // Enforce AJAX add to cart
                    add_filter('option_woocommerce_enable_ajax_add_to_cart', '__return_true');
                    // Disable redirect after add to cart
                    add_filter('option_woocommerce_cart_redirect_after_add', '__return_false');
                    // Disable WooCommerce default sorting (we use custom order)
                    remove_action('woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 30);
                    // Remove orderby query string to prevent user sorting
                    add_filter('woocommerce_get_catalog_ordering_args', array($this, 'remove_default_sorting'), 20);
                    return $new_template;
                }
            }
        }
        return $template;
    }


    /**
     * Remove default WooCommerce sorting to use custom order.
     *
     * @param array $args Ordering args.
     * @return array Modified ordering args.
     */
    public function remove_default_sorting($args)
    {
        // Only apply on shop page with our template
        $shop_template = get_option('woo_pages_shop_template');
        if (is_shop() && 'villegas-shop-one' === $shop_template) {
            // Remove any orderby parameters
            unset($args['orderby']);
            unset($args['order']);
        }
        return $args;
    }

    /**
     * Apply custom product order.
     *
     * @param WP_Query $query The WordPress query object.
     */
    public function apply_custom_product_order($query)
    {
        // Log entry to debug file
        $log_file = WOO_PAGES_PATH . 'debug.log';
        error_log("[" . date('Y-m-d H:i:s') . "] ===== apply_custom_product_order START =====\n", 3, $log_file);
        error_log("Query type: " . get_class($query) . "\n", 3, $log_file);

        $shop_template = get_option('woo_pages_shop_template');
        error_log("Shop template: " . $shop_template . "\n", 3, $log_file);
        error_log("is_admin: " . (is_admin() ? 'yes' : 'no') . "\n", 3, $log_file);
        error_log("is_main_query: " . ($query->is_main_query() ? 'yes' : 'no') . "\n", 3, $log_file);
        error_log("post_type: " . $query->get('post_type') . "\n", 3, $log_file);
        error_log("is_shop (might be unreliable): " . (is_shop() ? 'yes' : 'no') . "\n", 3, $log_file);
        error_log("is_post_type_archive product: " . (is_post_type_archive('product') ? 'yes' : 'no') . "\n", 3, $log_file);

        // FIXED: Only apply to shop page, not all queries with empty post_type
        // Check if it's the main query AND specifically the shop page
        if (!is_admin() && $query->is_main_query() && (is_shop() || is_post_type_archive('product'))) {

            if ('villegas-shop-one' === $shop_template) {
                error_log(">>> ENTERING FILTER LOGIC (SHOP PAGE DETECTED) <<<\n", 3, $log_file);

                $custom_order = get_option('woo_pages_product_order', array());
                error_log("Custom order: " . print_r($custom_order, true) . "\n", 3, $log_file);

                if (!empty($custom_order)) {
                    // Get all products
                    $all_products = get_posts(array(
                        'post_type' => 'product',
                        'posts_per_page' => -1,
                        'post_status' => 'publish',
                        'fields' => 'ids',
                        'suppress_filters' => true
                    ));

                    error_log("All products found: " . count($all_products) . "\n", 3, $log_file);

                    // Filter for visible products only, maintaining custom order
                    $visible_ids = array();
                    foreach ($custom_order as $product_id) {
                        $is_visible = get_post_meta($product_id, '_woo_pages_visible', true);
                        error_log("Product ID $product_id visibility: '$is_visible'\n", 3, $log_file);
                        if ($is_visible !== '0') {
                            $visible_ids[] = $product_id;
                        }
                    }

                    error_log("Visible product IDs in order: " . implode(', ', $visible_ids) . "\n", 3, $log_file);

                    if (!empty($visible_ids)) {
                        // FORCIBLY set post type to product
                        $query->set('post_type', 'product');
                        // Set the specific product IDs
                        $query->set('post__in', $visible_ids);
                        // Ensure the order is respected
                        $query->set('orderby', 'post__in');
                        // Remove any other ordering
                        $query->set('order', '');

                        // Log what was actually set
                        error_log("FORCED post_type to: product\n", 3, $log_file);
                        error_log("FORCED post__in to: " . print_r($visible_ids, true) . "\n", 3, $log_file);
                        error_log("FORCED orderby to: post__in\n", 3, $log_file);
                    } else {
                        error_log("No visible products found!\n", 3, $log_file);
                    }
                } else {
                    error_log("No custom order set\n", 3, $log_file);
                }

                error_log("===== apply_custom_product_order END =====\n\n", 3, $log_file);
            } else {
                error_log("Not using villegas-shop-one template\n\n", 3, $log_file);
            }
        } else {
            error_log("Filter conditions not met - skipping\n\n", 3, $log_file);
        }
    }

    /**
     * Alternative hook for woocommerce_product_query
     */
    public function apply_custom_product_order_wc($query)
    {
        $log_file = WOO_PAGES_PATH . 'debug.log';
        error_log("[" . date('Y-m-d H:i:s') . "] ===== WC_PRODUCT_QUERY HOOK FIRED =====\n", 3, $log_file);
        error_log("WC Query type: " . get_class($query) . "\n", 3, $log_file);

        $shop_template = get_option('woo_pages_shop_template');
        if ('villegas-shop-one' !== $shop_template) {
            error_log("Not using v illegas-shop-one template, skipping\n\n", 3, $log_file);
            return;
        }

        error_log(">>> APPLYING CUSTOM ORDER VIA WC HOOK <<<\n", 3, $log_file);

        $custom_order = get_option('woo_pages_product_order', array());
        if (empty($custom_order)) {
            error_log("No custom order set\n\n", 3, $log_file);
            return;
        }

        // Filter for visible products only
        $visible_ids = array();
        foreach ($custom_order as $product_id) {
            $is_visible = get_post_meta($product_id, '_woo_pages_visible', true);
            if ($is_visible !== '0') {
                $visible_ids[] = $product_id;
            }
        }

        error_log("Visible product IDs: " . implode(', ', $visible_ids) . "\n", 3, $log_file);

        if (!empty($visible_ids)) {
            $query->set('post__in', $visible_ids);
            $query->set('orderby', 'post__in');
            error_log("Set post__in and orderby via WC hook\n\n", 3, $log_file);
        }
    }

    /**
     * Last resort: Intercept posts before query runs
     */
    public function filter_products_pre_query($posts, $query)
    {
        $log_file = WOO_PAGES_PATH . 'debug.log';

        // Only apply on shop page for products
        if (!is_admin() && $query->is_main_query() && (is_shop() || is_post_type_archive('product'))) {
            $shop_template = get_option('woo_pages_shop_template');

            if ('villegas-shop-one' === $shop_template) {
                error_log("[" . date('Y-m-d H:i:s') . "] ===== POSTS_PRE_QUERY HOOK FIRED =====\n", 3, $log_file);

                $custom_order = get_option('woo_pages_product_order', array());
                if (!empty($custom_order)) {
                    // Get visible products
                    $visible_ids = array();
                    foreach ($custom_order as $product_id) {
                        $is_visible = get_post_meta($product_id, '_woo_pages_visible', true);
                        if ($is_visible !== '0') {
                            $visible_ids[] = $product_id;
                        }
                    }

                    if (!empty($visible_ids)) {
                        error_log("Fetching products directly: " . implode(', ', $visible_ids) . "\n", 3, $log_file);

                        // Manually get the posts in our custom order
                        $posts = get_posts(array(
                            'post_type' => 'product',
                            'post__in' => $visible_ids,
                            'orderby' => 'post__in',
                            'posts_per_page' => -1,
                            'suppress_filters' => true
                        ));

                        error_log("Retrieved " . count($posts) . " posts\n\n", 3, $log_file);
                        return $posts;
                    }
                }
            }
        }

        return null; // Let WordPress handle it normally
    }

    /**
     * AJAX handler to get product IDs currently in cart
     */
    public function ajax_get_cart_product_ids()
    {
        $product_ids = array();

        if (function_exists('WC') && WC()->cart) {
            foreach (WC()->cart->get_cart() as $cart_item) {
                $product_ids[] = $cart_item['product_id'];
            }
        }

        wp_send_json_success(array('product_ids' => $product_ids));
    }


}

new Woo_Pages_Loader();
