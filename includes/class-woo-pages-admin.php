<?php
/**
 * Woo Pages Admin Class.
 *
 * @package Woo_Pages
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Woo_Pages_Admin class.
 */
class Woo_Pages_Admin
{

    /**
     * Constructor.
     */
    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_ajax_woo_pages_save_product_order', array($this, 'save_product_order'));
        add_action('wp_ajax_woo_pages_toggle_product_visibility', array($this, 'toggle_product_visibility'));
    }

    /**
     * Register settings.
     */
    public function register_settings()
    {
        register_setting('woo_pages_options', 'woo_pages_shop_template');
        register_setting('woo_pages_options', 'woo_pages_cart_template');
        register_setting('woo_pages_options', 'woo_pages_product_template');
    }

    /**
     * Add admin menu.
     */
    public function add_admin_menu()
    {
        add_menu_page(
            __('Woo Pages', 'woo-pages'),
            __('Woo Pages', 'woo-pages'),
            'manage_options',
            'woo-pages',
            array($this, 'output_pages_page'),
            'dashicons-layout',
            58
        );

        add_submenu_page(
            'woo-pages',
            __('Pages', 'woo-pages'),
            __('Pages', 'woo-pages'),
            'manage_options',
            'woo-pages',
            array($this, 'output_pages_page')
        );

        add_submenu_page(
            'woo-pages',
            __('Products', 'woo-pages'),
            __('Products', 'woo-pages'),
            'manage_options',
            'woo-pages-products',
            array($this, 'output_products_page')
        );
    }

    /**
     * Output the Pages settings page.
     */
    public function output_pages_page()
    {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Woo Pages Settings', 'woo-pages'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('woo_pages_options');
                do_settings_sections('woo-pages');

                $shop_template = get_option('woo_pages_shop_template');
                $cart_template = get_option('woo_pages_cart_template');
                $product_template = get_option('woo_pages_product_template');
                ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('Shop Page Template', 'woo-pages'); ?></th>
                        <td>
                            <select name="woo_pages_shop_template">
                                <option value=""><?php esc_html_e('Default', 'woo-pages'); ?></option>
                                <option value="villegas-shop-one" <?php selected($shop_template, 'villegas-shop-one'); ?>>
                                    <?php esc_html_e('Villegas Shop One', 'woo-pages'); ?>
                                </option>
                            </select>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('Cart Page Template', 'woo-pages'); ?></th>
                        <td>
                            <select name="woo_pages_cart_template">
                                <option value=""><?php esc_html_e('Default', 'woo-pages'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e('Product Page Template', 'woo-pages'); ?></th>
                        <td>
                            <select name="woo_pages_product_template">
                                <option value=""><?php esc_html_e('Default', 'woo-pages'); ?></option>
                            </select>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Output the Products settings page.
     */
    public function output_products_page()
    {
        // Enqueue scripts and styles
        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_script(
            'woo-pages-admin',
            WOO_PAGES_URL . 'assets/js/woo-pages-admin.js',
            array('jquery', 'jquery-ui-sortable'),
            WOO_PAGES_VERSION,
            true
        );
        wp_localize_script('woo-pages-admin', 'wooPagesAdmin', array(
            'nonce' => wp_create_nonce('woo_pages_product_order')
        ));

        // Get all products
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'orderby' => 'menu_order',
            'order' => 'ASC'
        );

        // Get custom order if exists
        $custom_order = get_option('woo_pages_product_order', array());
        if (!empty($custom_order)) {
            $args['post__in'] = $custom_order;
            $args['orderby'] = 'post__in';
        }

        $products = get_posts($args);

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Products Order', 'woo-pages'); ?></h1>
            <p><?php esc_html_e('Drag and drop products to set the order they will appear in the shop template.', 'woo-pages'); ?>
            </p>
            <div class="woo-pages-save-status"></div>
            <button type="button" id="woo-pages-manual-save" class="button button-primary" style="margin-bottom: 15px;">Save
                Current Order</button>

            <style>
                #woo-pages-product-list {
                    list-style: none;
                    padding: 0;
                    max-width: 600px;
                }

                #woo-pages-product-list li {
                    background: #fff;
                    border: 1px solid #ddd;
                    padding: 12px;
                    margin-bottom: 8px;
                    cursor: move;
                    display: flex;
                    align-items: center;
                    gap: 12px;
                }

                #woo-pages-product-list li:hover {
                    background: #f9f9f9;
                }

                .woo-pages-drag-handle {
                    font-size: 18px;
                    color: #666;
                }

                .woo-pages-placeholder {
                    background: #f0f0f0;
                    border: 2px dashed #ccc;
                    height: 50px;
                }

                .woo-pages-save-status {
                    margin: 10px 0;
                }

                .woo-pages-success {
                    color: #46b450;
                }

                .woo-pages-error {
                    color: #dc3232;
                }

                .woo-pages-saving {
                    color: #0073aa;
                }

                .woo-pages-toggle {
                    position: relative;
                    display: inline-block;
                    width: 50px;
                    height: 24px;
                    margin-left: 12px;
                }

                .woo-pages-toggle input {
                    opacity: 0;
                    width: 0;
                    height: 0;
                }

                .woo-pages-toggle-slider {
                    position: absolute;
                    cursor: pointer;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background-color: #ccc;
                    transition: .4s;
                    border-radius: 24px;
                }

                .woo-pages-toggle-slider:before {
                    position: absolute;
                    content: "";
                    height: 18px;
                    width: 18px;
                    left: 3px;
                    bottom: 3px;
                    background-color: white;
                    transition: .4s;
                    border-radius: 50%;
                }

                .woo-pages-toggle input:checked+.woo-pages-toggle-slider {
                    background-color: #46b450;
                }

                .woo-pages-toggle input:checked+.woo-pages-toggle-slider:before {
                    transform: translateX(26px);
                }
            </style>

            <ul id="woo-pages-product-list">
                <?php foreach ($products as $product):
                    $is_visible = get_post_meta($product->ID, '_woo_pages_visible', true);
                    $is_visible = ($is_visible === '' || $is_visible === '1') ? true : false;
                    ?>
                    <li data-product-id="<?php echo esc_attr($product->ID); ?>">
                        <span class="woo-pages-drag-handle">☰</span>
                        <strong><?php echo esc_html($product->post_title); ?></strong>
                        <span style="margin-left: auto; color: #666;">#<?php echo esc_html($product->ID); ?></span>
                        <label class="woo-pages-toggle">
                            <input type="checkbox" class="woo-pages-visibility-toggle"
                                data-product-id="<?php echo esc_attr($product->ID); ?>" <?php checked($is_visible, true); ?>>
                            <span class="woo-pages-toggle-slider"></span>
                        </label>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php
    }

    /**
     * AJAX handler to save product order.
     */
    public function save_product_order()
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'woo_pages_product_order')) {
            wp_send_json_error('Invalid nonce');
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        // Get and sanitize product order
        $product_order = isset($_POST['product_order']) ? array_map('intval', $_POST['product_order']) : array();

        // Save to options
        update_option('woo_pages_product_order', $product_order);

        wp_send_json_success('Order saved');
    }

    /**
     * AJAX handler to toggle product visibility.
     */
    public function toggle_product_visibility()
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'woo_pages_product_order')) {
            wp_send_json_error('Invalid nonce');
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        // Get product ID and visibility state
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $is_visible = isset($_POST['is_visible']) ? $_POST['is_visible'] === 'true' : false;

        if (!$product_id) {
            wp_send_json_error('Invalid product ID');
        }

        // Save visibility state
        update_post_meta($product_id, '_woo_pages_visible', $is_visible ? '1' : '0');

        wp_send_json_success('Visibility updated');
    }
}

new Woo_Pages_Admin();
