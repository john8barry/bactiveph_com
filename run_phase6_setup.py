import os
import ftplib
import requests
import uuid
from dotenv import load_dotenv

load_dotenv()

php_script = """<?php
require 'wp-load.php';

// 1. Enable guest checkout
update_option('woocommerce_enable_checkout_login_reminder', 'no');
update_option('woocommerce_enable_guest_checkout', 'yes');
update_option('woocommerce_enable_signup_and_login_from_checkout', 'yes');

// 2. COD Setup
$cod_settings = get_option('woocommerce_cod_settings', array());
$cod_settings['enabled'] = 'yes';
$cod_settings['title'] = 'Cash on Delivery';
update_option('woocommerce_cod_settings', $cod_settings);

// 3. PayMongo settings (Enable Gateways)
$gateways = ['paymongo', 'paymongo_gcash', 'paymongo_grab_pay', 'paymongo_maya', 'paymongo_atome'];
foreach ($gateways as $gw) {
    $gw_settings = get_option('woocommerce_' . $gw . '_settings', array());
    $gw_settings['enabled'] = 'yes';
    $gw_settings['testmode'] = 'yes';
    $gw_settings['public_key_test'] = 'pk_test_bactive123';
    $gw_settings['secret_key_test'] = 'sk_test_bactive123';
    update_option('woocommerce_' . $gw . '_settings', $gw_settings);
}

// 4. Update blocksy child functions.php for Minimal Address Fields, COD Fee/Cap, Reassurance Row, Cart Text
$functions_path = get_stylesheet_directory() . '/functions.php';
$functions_content = file_get_contents($functions_path);

$snippets = <<<'SNIPPET'
// BEGIN PHASE 6 SNIPPETS
// Minimal Address Fields
add_filter( 'woocommerce_checkout_fields' , 'bactive_custom_override_checkout_fields' );
function bactive_custom_override_checkout_fields( $fields ) {
    unset($fields['billing']['billing_company']);
    unset($fields['billing']['billing_address_2']);
    return $fields;
}

// COD Fee
add_action( 'woocommerce_cart_calculate_fees', 'bactive_add_cod_fee', 20, 1 );
function bactive_add_cod_fee( $cart ) {
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;
    $chosen_gateway = WC()->session->get( 'chosen_payment_method' );
    if ( 'cod' === $chosen_gateway ) {
        $fee = 50;
        $cart->add_fee( 'COD Fee', $fee, true, 'standard' );
    }
}
// Force checkout update on payment method change to apply fee
add_action( 'wp_footer', 'bactive_checkout_update_script' );
function bactive_checkout_update_script() {
    if ( is_checkout() && ! is_wc_endpoint_url() ) {
        echo '<script type="text/javascript">
            jQuery(document).ready(function($){
                $(document.body).on("change", "input[name=\'payment_method\']", function() {
                    $("body").trigger("update_checkout");
                });
            });
        </script>';
    }
}

// Hide COD over 2500
add_filter( 'woocommerce_available_payment_gateways', 'bactive_hide_cod_over_cap' );
function bactive_hide_cod_over_cap( $available_gateways ) {
    if ( is_admin() ) return $available_gateways;
    if ( isset( $available_gateways['cod'] ) && WC()->cart ) {
        if ( WC()->cart->get_cart_contents_total() > 2500 ) {
            unset( $available_gateways['cod'] );
        }
    }
    return $available_gateways;
}

// Reassurance Row
add_action( 'woocommerce_review_order_after_submit', 'bactive_checkout_reassurance', 10 );
function bactive_checkout_reassurance() {
    echo '<div style="text-align:center; font-size:13px; margin-top:20px; color:#2B2A28;">Secure checkout &middot; GCash &middot; Maya &middot; Cards &middot; COD &middot; 7-day size-exchange guarantee</div>';
}

// Slide-out Cart Drawer Text
add_action( 'woocommerce_widget_shopping_cart_before_buttons', 'bactive_cart_drawer_text', 10 );
function bactive_cart_drawer_text() {
    echo '<div style="text-align:center; font-style:italic; margin-bottom:15px; color:#5E6E54;">Thank you for choosing quality.</div>';
}

// Rename Checkout Button
add_filter( 'woocommerce_order_button_text', 'bactive_custom_button_text' );
function bactive_custom_button_text() {
    return 'Checkout securely';
}
// END PHASE 6 SNIPPETS
SNIPPET;

if (strpos($functions_content, 'BEGIN PHASE 6 SNIPPETS') === false) {
    file_put_contents($functions_path, "\n" . $snippets . "\n", FILE_APPEND);
    echo "Added Phase 6 snippets to functions.php\n";
} else {
    echo "Phase 6 snippets already exist in functions.php\n";
}

echo "Phase 6 script completed.\n";
?>"""

script_name = f"setup_{uuid.uuid4().hex[:8]}.php"

with open(script_name, 'w') as f:
    f.write(php_script)

ftp = ftplib.FTP()
try:
    ftp.connect(os.getenv('FTP_HOST', 'ftp.bactiveph.com'), int(os.getenv('FTP_PORT', 21)))
    ftp.login(os.environ['CPANEL_USER'], os.environ['CPANEL_PASSWORD'])
    ftp.cwd('staging.bactiveph.com')
    with open(script_name, 'rb') as f:
        ftp.storbinary(f'STOR {script_name}', f)
    
    response = requests.get(f'https://staging.bactiveph.com/{script_name}?nocache=1', verify=False, auth=('bactive_team', 'BActive_Stg_2026!'))
    print(response.text)
    
    ftp.delete(script_name)
except Exception as e:
    print("Error:", e)
finally:
    try:
        ftp.quit()
    except:
        pass
    os.remove(script_name)
