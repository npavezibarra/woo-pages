<?php
/**
 * Template Name: Villegas Cart One
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

<body <?php body_class('villegas-cart-one'); ?>>

    <?php
    echo do_blocks('<!-- wp:template-part {"slug":"header","area":"header","tagName":"header"} /-->');
    ?>

    <header class="villegas-shop-header">
        <div class="villegas-shop-header-inner">
            <div class="villegas-shop-breadcrumb">
                <?php woocommerce_breadcrumb(); ?>
            </div>
            <div class="villegas-shop-title-row">
                <h1><?php esc_html_e('Carrito', 'woo-pages'); ?></h1>
            </div>
        </div>
    </header>

    <div class="villegas-shop-content">
        <?php
        /**
         * Hook: woocommerce_before_main_content.
         */
        do_action('woocommerce_before_main_content');

        if (have_posts()) {
            while (have_posts()) {
                the_post();
                the_content();
            }
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