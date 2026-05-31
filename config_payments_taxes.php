<?php
require_once 'wp-load.php';

global $wpdb;

// 1. Enable BACS and set details
$bacs_settings = get_option('woocommerce_bacs_settings', array());
$bacs_settings['enabled'] = 'yes';
$bacs_settings['title'] = 'Direct bank transfer';
$bacs_settings['description'] = 'Make your payment directly into our bank account. Please use your Order ID as the payment reference. Your order will not be shipped until the funds have cleared in our account.';
$bacs_settings['account_details'] = array(
    array(
        'account_name' => 'B Active',
        'account_number' => '[Bank name / account — to confirm]',
        'sort_code' => '',
        'bank_name' => '',
        'iban' => '',
        'bic' => ''
    )
);
update_option('woocommerce_bacs_settings', $bacs_settings);

// 2. Disable PayMongo gateways and remove keys
$paymongo_settings = get_option('woocommerce_paymongo_settings', array());
$paymongo_settings['enabled'] = 'no';
$paymongo_settings['public_key_test'] = '';
$paymongo_settings['secret_key_test'] = '';
update_option('woocommerce_paymongo_settings', $paymongo_settings);

$gateways = array('paymongo_gcash', 'paymongo_paymaya', 'paymongo_grab_pay', 'paymongo_atome');
foreach($gateways as $gateway) {
    $settings = get_option('woocommerce_' . $gateway . '_settings', array());
    $settings['enabled'] = 'no';
    update_option('woocommerce_' . $gateway . '_settings', $settings);
}

// 3. Tax Settings
update_option('woocommerce_calc_taxes', 'yes');
update_option('woocommerce_prices_include_tax', 'yes');
update_option('woocommerce_tax_display_shop', 'incl');
update_option('woocommerce_tax_display_cart', 'incl');
update_option('woocommerce_tax_total_display', 'itemized');

// Check if 12% PH Tax Rate exists, if not, create it
$tax_rate_exists = $wpdb->get_var("SELECT tax_rate_id FROM {$wpdb->prefix}woocommerce_tax_rates WHERE tax_rate_country = 'PH' AND tax_rate = '12.0000'");

if ( ! $tax_rate_exists ) {
    $wpdb->insert(
        "{$wpdb->prefix}woocommerce_tax_rates",
        array(
            'tax_rate_country' => 'PH',
            'tax_rate_state' => '',
            'tax_rate' => '12.0000',
            'tax_rate_name' => 'VAT',
            'tax_rate_priority' => 1,
            'tax_rate_compound' => 0,
            'tax_rate_shipping' => 1,
            'tax_rate_order' => 1,
            'tax_rate_class' => ''
        )
    );
}

// Ensure COD is enabled
$cod_settings = get_option('woocommerce_cod_settings', array());
$cod_settings['enabled'] = 'yes';
update_option('woocommerce_cod_settings', $cod_settings);

echo "Payment gateways and Taxes configured.\n";
