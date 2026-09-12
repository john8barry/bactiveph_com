<?php

defined('WP_UNINSTALL_PLUGIN') || exit;

delete_option('woocommerce_bactive_paymongo_settings');
delete_option('bactive_paymongo_test_webhook_secret');
delete_option('bactive_paymongo_live_webhook_secret');
delete_option('bactive_paymongo_readiness_test');
delete_option('bactive_paymongo_readiness_live');
delete_option('bactive_paymongo_installation_id');

// Order audit metadata and quarantine records are intentionally retained.
