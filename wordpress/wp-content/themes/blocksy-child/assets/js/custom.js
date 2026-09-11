/* Keep Blocksy's mobile and desktop cart quantity controls in sync. */
(function () {
    'use strict';

    var quantitySelector = '.woocommerce-cart-form .cart_item .quantity input.qty[name]';

    function syncQuantity(input) {
        if (!(input instanceof HTMLInputElement) || input.disabled || !input.matches(quantitySelector)) {
            return;
        }

        var row = input.closest('.cart_item');
        var form = input.form;

        row.querySelectorAll('.quantity input.qty[name]').forEach(function (peer) {
            if (peer !== input && peer.form === form && peer.name === input.name) {
                peer.value = input.value;
            }
        });
    }

    function syncVisibleQuantities(form) {
        form.querySelectorAll('.cart_item .quantity input.qty[name]').forEach(function (input) {
            if (!input.disabled && input.getClientRects().length) {
                syncQuantity(input);
            }
        });
    }

    // Capture before WooCommerce serializes the form. Delegation also survives
    // replacement of the cart HTML after AJAX updates, without lazy imports.
    ['input', 'change'].forEach(function (type) {
        document.addEventListener(type, function (event) {
            syncQuantity(event.target);
        }, true);
    });

    document.addEventListener('submit', function (event) {
        if (event.target instanceof HTMLFormElement && event.target.matches('.woocommerce-cart-form')) {
            syncVisibleQuantities(event.target);
        }
    }, true);

    document.addEventListener('click', function (event) {
        if (!(event.target instanceof Element)) {
            return;
        }

        var button = event.target.closest('.woocommerce-cart-form button[name="update_cart"]');
        if (button && button.form) {
            syncVisibleQuantities(button.form);
        }
    }, true);
})();
