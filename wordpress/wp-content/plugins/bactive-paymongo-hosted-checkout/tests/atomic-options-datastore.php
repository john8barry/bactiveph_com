<?php

use BActive\PayMongo\Order_Lock;
use BActive\PayMongo\Gateway;
use BActive\PayMongo\Readiness;
use BActive\PayMongo\Webhook;

if (PHP_SAPI !== 'cli' || DB_NAME !== 'bactive_payment_integration'
    || get_option('home') !== 'https://bactive-payment-integration.invalid'
    || !in_array(getenv('BACTIVE_INTEGRATION_STORE'), array('hpos', 'cpt'), true)) {
    throw new RuntimeException('Disposable atomic-option fixture identity required.');
}
$checks = 0;
$assert = static function (bool $pass, string $message) use (&$checks): void {
    ++$checks;
    if (!$pass) { throw new RuntimeException($message); }
};
$read = static function (string $option): ?string {
    global $wpdb;
    $row = $wpdb->get_row($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $option));
    if ($wpdb->last_error !== '') { throw new RuntimeException('Fixture SQL read failed.'); }
    return $row === null ? null : $row->option_value;
};
$actor = getenv('BACTIVE_ATOMIC_ACTOR');
if ($actor === 'self') {
    global $wpdb;
    // Exact SQL bytes must follow native option serialization, including strings
    // that themselves look serialized. A duplicate never changes those bytes.
    $values = array('plain', 's:2:"no";', array('nested' => array('text' => "quote'\\slash", 'integer' => 42)), 123, false);
    foreach ($values as $index => $value) {
        $option = 'bactive_paymongo_atomic_fixture_' . $index;
        delete_option($option);
        $assert(get_option($option, null) === null, 'Fixture absence was not cached.');
        $assert(Order_Lock::insert_option($option, $value), 'Fresh native insert failed for value index ' . $index . '.');
        $assert($read($option) === (string) maybe_serialize($value), 'Native insert changed serialized bytes.');
        $raw = $read($option);
        // Contaminate every native option cache surface independently of SQL.
        wp_cache_set($option, 'stale-option', 'options');
        wp_cache_set('alloptions', array($option => 'stale-autoload'), 'options');
        wp_cache_set('notoptions', array($option => true), 'options');
        $assert(!Order_Lock::insert_option($option, array('loser' => true)), 'Duplicate insert reported ownership.');
        foreach (array($option, 'alloptions', 'notoptions') as $cache_key) {
            wp_cache_get($cache_key, 'options', false, $found);
            $assert(!$found, 'Duplicate did not invalidate an option cache surface.');
        }
        $assert($read($option) === $raw, 'Duplicate replaced the winner bytes.');
        $assert(maybe_serialize(get_option($option)) === maybe_serialize(maybe_unserialize($raw)), 'Duplicate cache invalidation did not expose the durable winner.');
        delete_option($option);
    }
    $option = 'bactive_paymongo_atomic_fixture_cache_success';
    delete_option($option);
    wp_cache_set($option, 'stale-positive', 'options');
    wp_cache_set('alloptions', array($option => 'stale-positive'), 'options');
    wp_cache_set('notoptions', array($option => true), 'options');
    $assert(Order_Lock::insert_option($option, array('winner' => true)), 'Stale positive cache blocked absent SQL insert.');
    foreach (array($option, 'alloptions', 'notoptions') as $cache_key) {
        wp_cache_get($cache_key, 'options', false, $found);
        $assert(!$found, 'Success did not invalidate an option cache surface.');
    }
    delete_option($option);
    // Use native wpdb query failure, not a mocked boolean return. Suppression is
    // confined to this disposable database and restored before reporting.
    $option = 'bactive_paymongo_atomic_fixture_failure';
    $broken_insert = static function (string $sql) use ($option): string {
        return str_contains($sql, $option) && preg_match('/^INSERT\s/i', $sql)
            ? 'INSERT INTO bactive_disposable_missing_table (missing) VALUES (1)' : $sql;
    };
    $suppressed = $wpdb->suppress_errors(true);
    add_filter('query', $broken_insert, PHP_INT_MAX);
    try {
        $assert(!Order_Lock::insert_option($option, array('winner' => true)), 'Database failure granted insertion ownership.');
    } finally {
        remove_filter('query', $broken_insert, PHP_INT_MAX);
        $wpdb->suppress_errors($suppressed);
    }
    $assert($read($option) === null, 'Failed insert left a durable row.');
    $option = 'bactive_paymongo_settings_write_lock';
    $assert(Order_Lock::insert_option($option, ''), 'Could not store malformed empty lock fixture.');
    $assert(Order_Lock::settings_write_active(), 'An existing empty lock was mistaken for absence.');
    delete_option($option);
    $broken_read = static function (string $sql) use ($option): string {
        return str_contains($sql, $option) && preg_match('/^SELECT\s/i', $sql)
            ? 'SELECT * FROM bactive_disposable_missing_table' : $sql;
    };
    $suppressed = $wpdb->suppress_errors(true);
    add_filter('query', $broken_read, PHP_INT_MAX);
    try {
        $assert(Order_Lock::settings_write_active(), 'Failed settings lock read was mistaken for absence.');
    } finally {
        remove_filter('query', $broken_read, PHP_INT_MAX);
        $wpdb->suppress_errors($suppressed);
    }
    // Complete a stale processing claim in another SQL write at the precise
    // takeover DELETE boundary. The loser must preserve those newer bytes.
    $claim = new ReflectionMethod(Webhook::class, 'claim');
    $claim->setAccessible(true);
    $claim_name = new ReflectionMethod(Webhook::class, 'claim_option');
    $claim_name->setAccessible(true);
    $identity = 'atomic-stale-claim-completion-fixture';
    $option = $claim_name->invoke(null, 'event', $identity, 'test');
    $expired = array('status' => 'processing', 'claimed_at' => time() - 3600,
        'kind' => 'event', 'identity' => $identity, 'mode' => 'test');
    $processed = $expired;
    $processed['status'] = 'processed';
    $processed['claimed_at'] = time();
    $assert(Order_Lock::insert_option($option, $expired), 'Stale claim fixture insertion failed.');
    $completed_during_delete = false;
    $complete_before_delete = static function (string $sql) use ($wpdb, $option, $processed, &$completed_during_delete): string {
        if (!$completed_during_delete && preg_match('/^DELETE\s/i', $sql) && str_contains($sql, $option)) {
            if (!str_contains($sql, 'AND option_value =')) {
                throw new RuntimeException('Stale claim deletion omitted its exact value predicate.');
            }
            $completed_during_delete = true;
            $wpdb->query($wpdb->prepare("UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s",
                maybe_serialize($processed), $option));
            if ($wpdb->last_error !== '') { throw new RuntimeException('Concurrent fixture completion failed.'); }
        }
        return $sql;
    };
    add_filter('query', $complete_before_delete, PHP_INT_MAX);
    try {
        $assert($claim->invoke(null, 'event', $identity, 'test') === 'busy', 'Stale claimant acquired ownership after a concurrent completion.');
        $assert($completed_during_delete, 'Stale claim regression did not exercise the exact DELETE boundary.');
        $assert($read($option) === maybe_serialize($processed), 'Stale takeover deleted or replaced the completed claim bytes.');
    } finally {
        remove_filter('query', $complete_before_delete, PHP_INT_MAX);
        delete_option($option);
    }

    // A completed effect must not become armed again when INSERT throws and
    // the process cache still holds the old armed record.
    $arm = new ReflectionMethod(Webhook::class, 'arm_effects');
    $arm->setAccessible(true);
    $effects_name = new ReflectionMethod(Webhook::class, 'effects_option');
    $effects_name->setAccessible(true);
    $identity = 'atomic-thrown-insert-fixture';
    $option = $effects_name->invoke(null, 'payment', $identity, 'test');
    $transition = array('from' => 'pending', 'to' => 'processing');
    $assert($arm->invoke(null, 'payment', $identity, 'test', 987650, $transition) === 'armed', 'Effect fixture could not be armed.');
    $armed = get_option($option);
    $done = $armed;
    $done['status'] = 'done';
    $wpdb->query($wpdb->prepare("UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s", maybe_serialize($done), $option));
    wp_cache_set($option, $armed, 'options');
    $throw_insert = static function (string $sql) use ($option): string {
        if (str_contains($sql, $option) && preg_match('/^INSERT\s/i', $sql)) {
            throw new RuntimeException('Disposable INSERT exception.');
        }
        return $sql;
    };
    add_filter('query', $throw_insert, PHP_INT_MAX);
    try {
        $assert($arm->invoke(null, 'payment', $identity, 'test', 987650, $transition) === 'done', 'Thrown insert resurrected a cached armed effect.');
    } finally { remove_filter('query', $throw_insert, PHP_INT_MAX); }
    $assert($read($option) === maybe_serialize($done), 'Thrown insert changed completed effect bytes.');
    delete_option($option);

    // A failed installation UUID insert must stop before any webhook POST.
    $option = 'bactive_paymongo_installation_id';
    $installation = get_option($option, null);
    delete_option($option);
    $creates = 0;
    $provider_fixture = static function ($pre, array $args, string $url) use (&$creates) {
        $path = wp_parse_url($url, PHP_URL_PATH);
        if (($args['method'] ?? 'GET') === 'POST') {
            ++$creates;
            return new WP_Error('fixture_post_forbidden');
        }
        if ($path === '/v1/merchants/capabilities/payment_methods') {
            $body = array('qrph', 'paymaya', 'shopee_pay', 'dob', 'dob_ubp');
        } elseif ($path === '/v1/webhooks') { $body = array('data' => array()); }
        else { return new WP_Error('fixture_external_http_blocked'); }
        return array('headers' => array(), 'body' => wp_json_encode($body), 'response' => array('code' => 200), 'cookies' => array());
    };
    add_filter('pre_http_request', $provider_fixture, PHP_INT_MAX, 3);
    $reject_identity = static function (string $sql): string {
        if (str_contains($sql, 'bactive_paymongo_installation_id') && preg_match('/^INSERT\s/i', $sql)) {
            throw new RuntimeException('Disposable UUID INSERT exception.');
        }
        return $sql;
    };
    add_filter('query', $reject_identity, PHP_INT_MAX);
    try {
        $result = Readiness::verify_and_provision(new Gateway(), false);
        $assert(is_wp_error($result) && $result->get_error_code() === 'paymongo_installation_identity_unavailable', 'Missing durable UUID did not stop readiness.');
        $assert($creates === 0, 'Missing durable UUID attempted webhook creation.');
        $assert($read($option) === null, 'Failed UUID insert unexpectedly persisted an identity.');
    } finally {
        remove_filter('query', $reject_identity, PHP_INT_MAX);
        remove_filter('pre_http_request', $provider_fixture, PHP_INT_MAX);
        if ($installation !== null) { Order_Lock::insert_option($option, $installation); }
    }
    echo wp_json_encode(array('actor' => 'self', 'checks' => $checks)) . "\n";
    return;
}
$family = getenv('BACTIVE_ATOMIC_FAMILY');
$assert(in_array($actor, array('A', 'B'), true), 'Fixture actor required.');
$assert(in_array($family, array('order', 'checkout', 'settings', 'event', 'payment', 'effects'), true), 'Fixture family required.');
$dir = ABSPATH . 'atomic-proof/' . $family . '/';
$signal = static function (string $name, array $value) use ($dir): void {
    $path = $dir . $name . '.json';
    $handle = fopen($path . '.pending', 'x');
    if (!$handle) { throw new RuntimeException('Fixture signal already exists.'); }
    fwrite($handle, wp_json_encode($value));
    fclose($handle);
    if (!rename($path . '.pending', $path)) { throw new RuntimeException('Fixture signal publication failed.'); }
};
$wait = static function (string $name) use ($dir): array {
    $deadline = microtime(true) + 40;
    while (microtime(true) < $deadline) {
        $path = $dir . $name . '.json';
        if (is_file($path)) {
            $value = json_decode(file_get_contents($path), true);
            if (is_array($value)) { return $value; }
        }
        usleep(25000);
    }
    throw new RuntimeException('Bounded fixture coordination timed out.');
};
$identity = 'atomic-native-fixture';
$fingerprint = hash('sha256', $identity);
$claim_name = new ReflectionMethod(Webhook::class, 'claim_option');
$claim_name->setAccessible(true);
$effects_name = new ReflectionMethod(Webhook::class, 'effects_option');
$effects_name->setAccessible(true);
$claim = new ReflectionMethod(Webhook::class, 'claim');
$claim->setAccessible(true);
$option = match ($family) {
    'order' => 'bactive_paymongo_order_lock_987654',
    'checkout' => 'bactive_paymongo_checkout_lock_' . hash_hmac('sha256', $identity, wp_salt('auth')),
    'settings' => 'bactive_paymongo_settings_write_lock',
    'event', 'payment' => $claim_name->invoke(null, $family, $identity, 'test'),
    'effects' => $effects_name->invoke(null, 'payment', $identity, 'test'),
};
$record = array('status' => 'armed', 'winner' => $actor, 'identity' => $identity);
$acquire = static fn(): bool => match ($family) {
    'order' => Order_Lock::acquire(987654),
    'checkout' => Order_Lock::acquire_checkout($identity),
    'settings' => Order_Lock::acquire_settings($fingerprint),
    'event', 'payment' => $claim->invoke(null, $family, $identity, 'test') === 'claimed',
    'effects' => Order_Lock::insert_option($option, $record),
};
$renew = static fn(): bool => match ($family) {
    'order' => Order_Lock::renew(987654),
    'checkout' => Order_Lock::renew_checkout(),
    'settings' => Order_Lock::renew_settings($fingerprint),
    default => true,
};
if ($actor === 'B') {
    $assert(get_option($option, null) === null, 'Contender did not cache absent row before owner acquisition.');
    $signal('B-ready', array('cached_absence' => true));
    $wait('A-guard');
    $before = $read($option);
    $assert(is_string($before), 'Owner row missing before contention.');
    $acquired = $acquire();
    $after = $read($option);
    $cached_winner = maybe_serialize(get_option($option)) === maybe_serialize(maybe_unserialize($before));
    // Signal even a regression so the owner need not wait for a timeout.
    $signal('B-result', array('acquired' => $acquired, 'winner_preserved' => $before === $after));
    $assert(!$acquired, 'Stale negative cache contender acquired an existing lock or claim.');
    $assert($before === $after, 'Contender overwrote durable owner bytes.');
    $assert($cached_winner, 'Contender retained a stale option cache after conflict.');
    $wait('A-result');
} else {
    $wait('B-ready');
    $assert($acquire(), 'Owner acquisition failed.');
    $assert($renew(), 'Owner renewal before contention failed.');
    $before = $read($option);
    $signal('A-guard', array('owner_acquired' => true));
    $other = $wait('B-result');
    $assert(!$other['acquired'], 'Both independent processes acquired the same owner key.');
    $assert($before === $read($option), 'Owner bytes changed after losing contender.');
    $assert($renew(), 'Owner lost renewable lease after contender returned.');
    if ($family === 'order') { Order_Lock::release(987654); }
    elseif ($family === 'checkout') { Order_Lock::release_checkout(); }
    elseif ($family === 'settings') { Order_Lock::release_settings(); }
    else { delete_option($option); }
    $assert($read($option) === null, 'Owner cleanup failed.');
    $signal('A-result', array('owner_retained' => true));
}
echo wp_json_encode(array('actor' => $actor, 'family' => $family, 'checks' => $checks,
    'ownership_exclusion' => 'passed')) . "\n";
