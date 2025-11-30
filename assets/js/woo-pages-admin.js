jQuery(document).ready(function ($) {
    // Initialize sortable on the product list
    $('#woo-pages-product-list').sortable({
        handle: '.woo-pages-drag-handle',
        placeholder: 'woo-pages-placeholder',
        update: function (event, ui) {
            // Get the new order
            var productOrder = [];
            $('#woo-pages-product-list li').each(function () {
                productOrder.push($(this).data('product-id'));
            });

            console.log('Saving product order:', productOrder);

            // Save the order via AJAX
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'woo_pages_save_product_order',
                    nonce: wooPagesAdmin.nonce,
                    product_order: productOrder
                },
                beforeSend: function () {
                    $('.woo-pages-save-status').html('<span class="woo-pages-saving">Saving...</span>');
                },
                success: function (response) {
                    console.log('Save response:', response);
                    if (response.success) {
                        $('.woo-pages-save-status').html('<span class="woo-pages-success">✓ Order saved successfully</span>');
                    } else {
                        $('.woo-pages-save-status').html('<span class="woo-pages-error">✗ Failed to save order</span>');
                    }
                    setTimeout(function () {
                        $('.woo-pages-save-status').html('');
                    }, 3000);
                },
                error: function (xhr, status, error) {
                    console.error('Save error:', error);
                    $('.woo-pages-save-status').html('<span class="woo-pages-error">✗ Error saving order</span>');
                    setTimeout(function () {
                        $('.woo-pages-save-status').html('');
                    }, 3000);
                }
            });
        }
    });

    // Handle visibility toggle
    $('.woo-pages-visibility-toggle').on('change', function () {
        var $toggle = $(this);
        var productId = $toggle.data('product-id');
        var isVisible = $toggle.is(':checked');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'woo_pages_toggle_product_visibility',
                nonce: wooPagesAdmin.nonce,
                product_id: productId,
                is_visible: isVisible
            },
            success: function (response) {
                if (!response.success) {
                    // Revert toggle if failed
                    $toggle.prop('checked', !isVisible);
                }
            },
            error: function () {
                // Revert toggle if failed
                $toggle.prop('checked', !isVisible);
            }
        });
    });

    // Manual save button
    $('#woo-pages-manual-save').on('click', function () {
        var productOrder = [];
        $('#woo-pages-product-list li').each(function () {
            productOrder.push($(this).data('product-id'));
        });

        console.log('Manual save triggered. Product order:', productOrder);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'woo_pages_save_product_order',
                nonce: wooPagesAdmin.nonce,
                product_order: productOrder
            },
            beforeSend: function () {
                $('.woo-pages-save-status').html('<span class="woo-pages-saving">Saving...</span>');
            },
            success: function (response) {
                console.log('Manual save response:', response);
                if (response.success) {
                    $('.woo-pages-save-status').html('<span class="woo-pages-success">✓ Order saved successfully</span>');
                } else {
                    $('.woo-pages-save-status').html('<span class="woo-pages-error">✗ Failed to save order</span>');
                }
                setTimeout(function () {
                    $('.woo-pages-save-status').html('');
                }, 3000);
            },
            error: function (xhr, status, error) {
                console.error('Manual save error:', error, xhr.responseText);
                $('.woo-pages-save-status').html('<span class="woo-pages-error">✗ Error: ' + error + '</span>');
            }
        });
    });
});
