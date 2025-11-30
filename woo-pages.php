<?php
/**
 * Plugin Name: Woo Pages
 * Plugin URI: https://github.com/npavezibarra/woo-pages
 * Description: A plugin to create new templates for WooCommerce pages.
 * Version: 1.0.0
 * Author: Nicolás Pavez
 * Author URI: https://github.com/npavezibarra
 * Text Domain: woo-pages
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Define plugin constants.
define('WOO_PAGES_VERSION', '1.0.0');
define('WOO_PAGES_PATH', plugin_dir_path(__FILE__));
define('WOO_PAGES_URL', plugin_dir_url(__FILE__));

/**
 * Main Woo Pages Class.
 *
 * @class Woo_Pages
 */
class Woo_Pages
{

    /**
     * Instance of the class.
     *
     * @var Woo_Pages
     */
    protected static $instance = null;

    /**
     * Get instance of the class.
     *
     * @return Woo_Pages
     */
    public static function instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->includes();
        $this->init_hooks();
    }

    /**
     * Include required files.
     */
    public function includes()
    {
        if (is_admin()) {
            include_once WOO_PAGES_PATH . 'includes/class-woo-pages-admin.php';
        }
        include_once WOO_PAGES_PATH . 'includes/class-woo-pages-loader.php';
    }

    /**
     * Initialize hooks.
     */
    public function init_hooks()
    {
        add_action('plugins_loaded', array($this, 'on_plugins_loaded'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    /**
     * Enqueue scripts and styles.
     */
    public function enqueue_scripts()
    {
        wp_enqueue_style('woo-pages-css', WOO_PAGES_URL . 'assets/css/woo-pages.css', array(), WOO_PAGES_VERSION);
    }

    /**
     * Action to run when plugins are loaded.
     */
    public function on_plugins_loaded()
    {
        // Check if WooCommerce is active.
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
    }

    /**
     * Display notice if WooCommerce is not active.
     */
    public function woocommerce_missing_notice()
    {
        ?>
        <div class="error">
            <p><?php esc_html_e('Woo Pages requires WooCommerce to be installed and active.', 'woo-pages'); ?></p>
        </div>
        <?php
    }
}

/**
 * Returns the main instance of Woo_Pages.
 *
 * @return Woo_Pages
 */
function WOO_PAGES()
{
    return Woo_Pages::instance();
}

// Initialize the plugin.
WOO_PAGES();
