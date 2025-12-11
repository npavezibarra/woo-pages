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

        // Enqueue cart scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_cart_scripts'), 20);

        // Inject Shipping Comuna field
        add_action('woocommerce_cart_totals_before_shipping', array($this, 'inject_shipping_comuna_field'));

        // Override WooCommerce templates
        add_filter('woocommerce_locate_template', array($this, 'override_woocommerce_template'), 10, 3);
    }

    /**
     * Inject the Shipping Comuna field into the cart totals.
     */
    public function inject_shipping_comuna_field()
    {
        // Only show if Woo Check's comunas script is registered (implies plugin is active)
        if (!wp_script_is('woo-check-comunas-chile', 'registered')) {
            return;
        }
        ?>
        <div class="villegas-cart-comuna-wrapper" style="margin-bottom: 15px;">
            <label for="villegas_cart_comuna" style="display:block; margin-bottom:5px; font-weight:bold;">Calcula envío</label>
            <input type="text" id="villegas_cart_comuna" class="input-text" placeholder="Escribe tu comuna..."
                style="width:100%;" autocomplete="off" />
        </div>
        <?php
    }

    /**
     * Enqueue scripts for the cart page.
     */
    public function enqueue_cart_scripts()
    {
        if (is_cart()) {
            wp_enqueue_script('jquery-ui-autocomplete');

            // Ensure Comunas data is available (from WooCheck plugin)
            if (wp_script_is('woo-check-comunas-chile', 'registered')) {
                wp_enqueue_script('woo-check-comunas-chile');
            }

            wp_enqueue_script(
                'woo-pages-cart',
                WOO_PAGES_URL . 'assets/js/woo-pages-cart.js',
                array('jquery', 'jquery-ui-autocomplete', 'woo-check-comunas-chile'),
                WOO_PAGES_VERSION,
                true
            );
        }
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
        } elseif (is_cart()) {
            // Load custom cart template if selected
            $cart_template_option = get_option('woo_pages_cart_template');
            if ('villegas-cart-one' === $cart_template_option) {
                $cart_template = WOO_PAGES_PATH . 'templates/villegas-cart-one.php';
                if (file_exists($cart_template)) {
                    return $cart_template;
                }
            }
        }
        return $template;
    }

    /**
     * Override WooCommerce templates.
     *
     * @param string $template      Template.
     * @param string $template_name Template name.
     * @param string $template_path Template path.
     * @return string
     */
    public function override_woocommerce_template($template, $template_name, $template_path)
    {
        $plugin_path = WOO_PAGES_PATH . 'templates/woocommerce/';

        if (file_exists($plugin_path . $template_name)) {
            $template = $plugin_path . $template_name;
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
        // Debug logging disabled to prevent memory exhaustion
        // Uncomment only when debugging is needed
        /*
        $log_file = WOO_PAGES_PATH . 'debug.log';
        error_log("[" . date('Y-m-d H:i:s') . "] ===== apply_custom_product_order START =====\n", 3, $log_file);
        error_log("Query type: " . get_class($query) . "\n", 3, $log_file);

        $shop_template = get_option('woo_pages_shop_template');
        error_log("Shop template: " . $shop_template . "\n", 3, $log_file);
        error_log("is_admin: " . (is_admin() ? 'yes' : 'no') . "\n", 3, $log_file);
        error_log("is_main_query: " . ($query->is_main_query() ? 'yes' : 'no') . "\n", 3, $log_file);
        $post_type = $query->get('post_type');
        error_log("post_type: " . (is_array($post_type) ? print_r($post_type, true) : $post_type) . "\n", 3, $log_file);
        error_log("is_shop (might be unreliable): " . (is_shop() ? 'yes' : 'no') . "\n", 3, $log_file);
        error_log("is_post_type_archive product: " . (is_post_type_archive('product') ? 'yes' : 'no') . "\n", 3, $log_file);
        */

        // FIXED: Only apply to shop page, not all queries with empty post_type
        // Check if it's the main query AND specifically the shop page
        if (!is_admin() && $query->is_main_query() && (is_shop() || is_post_type_archive('product'))) {
            $shop_template = get_option('woo_pages_shop_template');

            if ('villegas-shop-one' === $shop_template) {
                // error_log(">>> ENTERING FILTER LOGIC (SHOP PAGE DETECTED) <<<\n", 3, $log_file);

                $custom_order = get_option('woo_pages_product_order', array());
                // error_log("Custom order: " . print_r($custom_order, true) . "\n", 3, $log_file);

                // Get all published products
                $all_products = get_posts(array(
                    'post_type' => 'product',
                    'posts_per_page' => -1,
                    'post_status' => 'publish',
                    'fields' => 'ids',
                    'suppress_filters' => true
                ));

                // error_log("All products found: " . count($all_products) . "\n", 3, $log_file);

                // Build final product list: custom order first, then new products
                $final_product_ids = array();

                if (!empty($custom_order)) {
                    // Add visible products from custom order first
                    foreach ($custom_order as $product_id) {
                        $is_visible = get_post_meta($product_id, '_woo_pages_visible', true);
                        // error_log("Product ID $product_id visibility: '$is_visible'\n", 3, $log_file);
                        if ($is_visible !== '0' && in_array($product_id, $all_products)) {
                            $final_product_ids[] = $product_id;
                        }
                    }
                }

                // Add any new products that aren't in custom order yet (and are visible by default)
                foreach ($all_products as $product_id) {
                    if (!in_array($product_id, $final_product_ids)) {
                        $is_visible = get_post_meta($product_id, '_woo_pages_visible', true);
                        // New products are visible by default (empty meta = visible)
                        if ($is_visible !== '0') {
                            $final_product_ids[] = $product_id;
                        }
                    }
                }

                // error_log("Final product IDs in order: " . implode(', ', $final_product_ids) . "\n", 3, $log_file);

                if (!empty($final_product_ids)) {
                    // FORCIBLY set post type to product
                    $query->set('post_type', 'product');
                    // Set the specific product IDs
                    $query->set('post__in', $final_product_ids);
                    // Ensure the order is respected
                    $query->set('orderby', 'post__in');
                    // Remove any other ordering
                    $query->set('order', '');

                    // error_log("FORCED post_type to: product\n", 3, $log_file);
                    // error_log("FORCED post__in to: " . print_r($final_product_ids, true) . "\n", 3, $log_file);
                    // error_log("FORCED orderby to: post__in\n", 3, $log_file);
                }
                // error_log("===== apply_custom_product_order END =====\n\n", 3, $log_file);
            }
        }
    }

    /**
     * Alternative hook for woocommerce_product_query
     */
    public function apply_custom_product_order_wc($query)
    {
        // Debug logging disabled to prevent memory exhaustion
        // $log_file = WOO_PAGES_PATH . 'debug.log';
        // error_log("[" . date('Y-m-d H:i:s') . "] ===== WC_PRODUCT_QUERY HOOK FIRED =====\n", 3, $log_file);
        // error_log("WC Query type: " . get_class($query) . "\n", 3, $log_file);

        $shop_template = get_option('woo_pages_shop_template');
        if ('villegas-shop-one' !== $shop_template) {
            // error_log("Not using villegas-shop-one template, skipping\n\n", 3, $log_file);
            return;
        }

        // error_log(">>> APPLYING CUSTOM ORDER VIA WC HOOK <<<\n", 3, $log_file);

        // Get all published products
        $all_products_query = new WP_Query(array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'fields' => 'ids'
        ));
        $all_product_ids = $all_products_query->posts;

        $custom_order = get_option('woo_pages_product_order', array());

        // Build final product list
        $final_product_ids = array();

        if (!empty($custom_order)) {
            // Add visible products from custom order first
            foreach ($custom_order as $product_id) {
                $is_visible = get_post_meta($product_id, '_woo_pages_visible', true);
                if ($is_visible !== '0' && in_array($product_id, $all_product_ids)) {
                    $final_product_ids[] = $product_id;
                }
            }
        }

        // Add new products not in custom order
        foreach ($all_product_ids as $product_id) {
            if (!in_array($product_id, $final_product_ids)) {
                $is_visible = get_post_meta($product_id, '_woo_pages_visible', true);
                if ($is_visible !== '0') {
                    $final_product_ids[] = $product_id;
                }
            }
        }

        // error_log("Final product IDs: " . implode(', ', $final_product_ids) . "\n", 3, $log_file);

        if (!empty($final_product_ids)) {
            $query->set('post__in', $final_product_ids);
            $query->set('orderby', 'post__in');
            // error_log("Set post__in and orderby via WC hook\n\n", 3, $log_file);
        }
    }

    /**
     * Last resort: Intercept posts before query runs
     */
    public function filter_products_pre_query($posts, $query)
    {
        // Debug logging disabled to prevent memory exhaustion
        // $log_file = WOO_PAGES_PATH . 'debug.log';

        // Only apply on shop page for products
        if (!is_admin() && $query->is_main_query() && (is_shop() || is_post_type_archive('product'))) {
            $shop_template = get_option('woo_pages_shop_template');

            if ('villegas-shop-one' === $shop_template) {
                // error_log("[" . date('Y-m-d H:i:s') . "] ===== POSTS_PRE_QUERY HOOK FIRED =====\n", 3, $log_file);

                // Get all published products
                $all_product_ids = get_posts(array(
                    'post_type' => 'product',
                    'posts_per_page' => -1,
                    'post_status' => 'publish',
                    'fields' => 'ids',
                    'suppress_filters' => true
                ));

                $custom_order = get_option('woo_pages_product_order', array());

                // Build final product list
                $final_product_ids = array();

                if (!empty($custom_order)) {
                    // Add visible products from custom order first
                    foreach ($custom_order as $product_id) {
                        $is_visible = get_post_meta($product_id, '_woo_pages_visible', true);
                        if ($is_visible !== '0' && in_array($product_id, $all_product_ids)) {
                            $final_product_ids[] = $product_id;
                        }
                    }
                }

                // Add new products not in custom order
                foreach ($all_product_ids as $product_id) {
                    if (!in_array($product_id, $final_product_ids)) {
                        $is_visible = get_post_meta($product_id, '_woo_pages_visible', true);
                        if ($is_visible !== '0') {
                            $final_product_ids[] = $product_id;
                        }
                    }
                }

                if (!empty($final_product_ids)) {
                    // error_log("Fetching products directly: " . implode(', ', $final_product_ids) . "\n", 3, $log_file);

                    // Manually get the posts in our custom order
                    $posts = get_posts(array(
                        'post_type' => 'product',
                        'post__in' => $final_product_ids,
                        'orderby' => 'post__in',
                        'posts_per_page' => -1,
                        'suppress_filters' => true
                    ));

                    // error_log("Retrieved " . count($posts) . " posts\n\n", 3, $log_file);
                    return $posts;
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
