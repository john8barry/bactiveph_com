<?php

namespace Bactive\Brevo;

defined('ABSPATH') || exit;

final class Store
{
    public const SCHEMA = 1;

    public static function table(string $name): string
    {
        global $wpdb;
        if (!in_array($name, ['contacts', 'carts', 'outbox', 'controls'], true)) throw new \InvalidArgumentException('Unknown table');
        return $wpdb->prefix . 'bactive_brevo_' . $name;
    }

    public static function install(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $contacts = self::table('contacts');
        $carts = self::table('carts');
        $outbox = self::table('outbox');
        $controls = self::table('controls');
        dbDelta("CREATE TABLE $contacts (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            email_hash char(64) NOT NULL,
            email varchar(254) NOT NULL,
            state varchar(24) NOT NULL DEFAULT 'pending',
            reason varchar(64) NOT NULL DEFAULT '',
            source varchar(20) NOT NULL,
            provider_id bigint unsigned NOT NULL DEFAULT 0,
            marker char(64) NOT NULL DEFAULT '',
            confirmation_token_hash char(64) NOT NULL DEFAULT '',
            session_token_hash char(64) NOT NULL DEFAULT '',
            pending_until bigint unsigned NOT NULL DEFAULT 0,
            confirmed_at bigint unsigned NOT NULL DEFAULT 0,
            verified_at bigint unsigned NOT NULL DEFAULT 0,
            session_until bigint unsigned NOT NULL DEFAULT 0,
            created_at bigint unsigned NOT NULL,
            updated_at bigint unsigned NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY email_hash (email_hash),
            KEY confirmation_token (confirmation_token_hash),
            KEY session_token (session_token_hash),
            KEY provider_id (provider_id)
        ) ENGINE=InnoDB $charset;");
        dbDelta("CREATE TABLE $carts (
            cart_key char(64) NOT NULL,
            email_hash char(64) NOT NULL,
            session_key varchar(100) NOT NULL,
            fingerprint char(64) NOT NULL,
            state varchar(24) NOT NULL DEFAULT 'active',
            items longtext NOT NULL,
            total decimal(18,4) NOT NULL DEFAULT 0,
            currency varchar(3) NOT NULL,
            mode varchar(8) NOT NULL,
            site varchar(100) NOT NULL,
            created_at bigint unsigned NOT NULL,
            updated_at bigint unsigned NOT NULL,
            expires_at bigint unsigned NOT NULL,
            PRIMARY KEY  (cart_key),
            KEY contact_state (email_hash,state),
            KEY expires_at (expires_at)
        ) ENGINE=InnoDB $charset;");
        dbDelta("CREATE TABLE $outbox (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            dedupe_key char(64) NOT NULL,
            delivery_key char(64) NOT NULL,
            email_hash char(64) NOT NULL,
            event_name varchar(64) NOT NULL,
            stage varchar(24) NOT NULL,
            entity_kind varchar(16) NOT NULL,
            entity_id varchar(64) NOT NULL,
            mode varchar(8) NOT NULL,
            site varchar(100) NOT NULL,
            due_at bigint unsigned NOT NULL,
            state varchar(24) NOT NULL DEFAULT 'pending',
            attempts int unsigned NOT NULL DEFAULT 0,
            lease_until bigint unsigned NOT NULL DEFAULT 0,
            error_code varchar(64) NOT NULL DEFAULT '',
            receipt_at bigint unsigned NOT NULL DEFAULT 0,
            created_at bigint unsigned NOT NULL,
            updated_at bigint unsigned NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY dedupe_key (dedupe_key),
            UNIQUE KEY delivery_key (delivery_key),
            KEY due (state,due_at),
            KEY entity (entity_kind,entity_id),
            KEY contact_state (email_hash,state)
        ) ENGINE=InnoDB $charset;");
        dbDelta("CREATE TABLE $controls (
            control_key char(64) NOT NULL,
            value bigint unsigned NOT NULL DEFAULT 1,
            expires_at bigint unsigned NOT NULL,
            PRIMARY KEY  (control_key),
            KEY expires_at (expires_at)
        ) ENGINE=InnoDB $charset;");
        if (self::ready()) update_option('bactive_brevo_schema', self::SCHEMA, false);
    }

    public static function ready(): bool
    {
        global $wpdb;
        $required = ['contacts' => ['email_hash'], 'carts' => ['cart_key'],
            'outbox' => ['dedupe_key', 'delivery_key'], 'controls' => ['control_key']];
        foreach ($required as $name => $columns) {
            $row = $wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS LIKE %s', $wpdb->esc_like(self::table($name))), ARRAY_A);
            if (!is_array($row) || strtolower((string) ($row['Engine'] ?? '')) !== 'innodb') return false;
            $indexes = $wpdb->get_results('SHOW INDEX FROM ' . self::table($name), ARRAY_A) ?: [];
            foreach ($columns as $column) {
                $found = false;
                foreach ($indexes as $index) {
                    if ((int) $index['Non_unique'] === 0 && (int) $index['Seq_in_index'] === 1
                        && $index['Column_name'] === $column) $found = true;
                }
                if (!$found) return false;
            }
        }
        return true;
    }

    public static function maybe_upgrade(): void
    {
        if ((int) get_option('bactive_brevo_schema', 0) < self::SCHEMA) self::install();
    }

    public static function hash(string $value): string
    {
        return hash_hmac('sha256', $value, wp_salt('auth'));
    }

    public static function email_hash(string $email): string
    {
        return self::hash(strtolower(trim($email)));
    }

    public static function contact(string $hash): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table('contacts') . ' WHERE email_hash=%s', $hash), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    public static function contact_for_token(string $token, bool $session = false): ?array
    {
        global $wpdb;
        if (!preg_match('/^[a-f0-9]{64}$/D', $token)) return null;
        $column = $session ? 'session_token_hash' : 'confirmation_token_hash';
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table('contacts') . " WHERE $column=%s", self::hash($token)), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    public static function pending(string $email, string $source, string $token, string $marker): bool
    {
        global $wpdb;
        $table = self::table('contacts');
        $hash = self::email_hash($email);
        $old = self::contact($hash);
        $data = [
            'email' => $email, 'state' => 'pending', 'reason' => '', 'source' => $source,
            'marker' => $marker, 'confirmation_token_hash' => self::hash($token),
            'session_token_hash' => '', 'session_until' => 0,
            'pending_until' => time() + 30 * DAY_IN_SECONDS, 'updated_at' => time(),
        ];
        if ($old) {
            // Existing confirmed identities cannot be replaced by a public signup request.
            if (in_array($old['state'], ['confirmed', 'review_required'], true) || ($old['state'] === 'pending' && (int) $old['pending_until'] > time())) return false;
            if (in_array($old['reason'], ['hard_bounce', 'spam', 'complaint'], true)) return false;
            return (bool) $wpdb->update($table, $data, [
                'email_hash' => $hash, 'state' => $old['state'], 'reason' => $old['reason'],
                'marker' => $old['marker'], 'confirmation_token_hash' => $old['confirmation_token_hash'],
                'updated_at' => $old['updated_at'],
            ]);
        }
        $data['email_hash'] = $hash;
        $data['created_at'] = time();
        return (bool) $wpdb->insert($table, $data);
    }

    public static function update_contact(string $hash, array $data): bool
    {
        global $wpdb;
        $allowed = ['state', 'reason', 'provider_id', 'verified_at', 'session_token_hash', 'session_until'];
        $data = array_intersect_key($data, array_flip($allowed));
        if (!$data) return false;
        $data['updated_at'] = time();
        return $wpdb->update(self::table('contacts'), $data, ['email_hash' => $hash]) !== false;
    }

    public static function confirm(array $pending, int $provider_id, string $session_token): bool
    {
        global $wpdb;
        return $wpdb->query($wpdb->prepare(
            'UPDATE ' . self::table('contacts') . " SET state='confirmed',reason='',provider_id=%d,verified_at=%d,
            confirmed_at=IF(confirmed_at=0,%d,confirmed_at),confirmation_token_hash='',pending_until=0,
            session_token_hash=%s,session_until=%d,updated_at=%d
            WHERE email_hash=%s AND state='pending' AND marker=%s AND confirmation_token_hash=%s AND pending_until>=%d",
            $provider_id, time(), time(), self::hash($session_token), time() + 30 * DAY_IN_SECONDS,
            time(), $pending['email_hash'], $pending['marker'], $pending['confirmation_token_hash'], time()
        )) === 1;
    }

    public static function suppress(string $hash, string $reason): bool
    {
        global $wpdb;
        $contact = self::update_contact($hash, ['state' => 'suppressed', 'reason' => $reason, 'session_token_hash' => '', 'session_until' => 0]);
        $cart = $wpdb->update(self::table('carts'), ['state' => 'suppressed', 'updated_at' => time()], ['email_hash' => $hash]);
        $jobs = $wpdb->query($wpdb->prepare('UPDATE ' . self::table('outbox') . " SET state=IF(state='sending','review_required','suppressed'),error_code='consent_withdrawn',updated_at=%d WHERE email_hash=%s AND state IN ('pending','sending')", time(), $hash));
        return $contact && $cart !== false && $jobs !== false;
    }

    public static function suppress_email(string $email, string $reason): bool
    {
        global $wpdb;
        $email = strtolower(trim($email));
        $hash = self::email_hash($email);
        $created = $wpdb->query($wpdb->prepare('INSERT IGNORE INTO ' . self::table('contacts') . " (email_hash,email,state,reason,source,created_at,updated_at) VALUES (%s,%s,'suppressed',%s,'webhook',%d,%d)", $hash, $email, $reason, time(), time()));
        return $created !== false && self::suppress($hash, $reason);
    }

    public static function cart(string $key): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table('carts') . ' WHERE cart_key=%s', $key), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    public static function save_cart(array $data): bool
    {
        global $wpdb;
        $old = self::cart($data['cart_key']);
        $data['updated_at'] = time();
        $data['expires_at'] = time() + 7 * DAY_IN_SECONDS;
        if ($old) {
            return $wpdb->update(self::table('carts'), $data, ['cart_key' => $data['cart_key'], 'state' => 'active']) !== false;
        } else {
            $data['created_at'] = time();
            return $wpdb->insert(self::table('carts'), $data) !== false;
        }
    }

    /** Recover only missing local jobs; previously accepted/ambiguous stages retain their dedupe row. */
    public static function repair_cart_jobs(): void
    {
        global $wpdb;
        $carts = self::table('carts');
        $jobs = self::table('outbox');
        $contacts = self::table('contacts');
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT c.* FROM $carts c WHERE c.state='active' AND c.mode=%s AND c.site=%s AND c.expires_at>=%d
            AND c.created_at>=%d AND EXISTS (SELECT 1 FROM $contacts p WHERE p.email_hash=c.email_hash AND p.state='confirmed')
            AND (NOT EXISTS (SELECT 1 FROM $jobs j WHERE j.entity_kind='cart' AND j.entity_id=c.cart_key AND j.stage='2h')
                OR NOT EXISTS (SELECT 1 FROM $jobs j WHERE j.entity_kind='cart' AND j.entity_id=c.cart_key AND j.stage='24h'))
            ORDER BY c.updated_at LIMIT 5", Config::mode(), rtrim(home_url(), '/'), time(), (int) Config::get('launch_cutoff')
        ), ARRAY_A) ?: [];
        foreach ($rows as $row) {
            self::queue($row['email_hash'], 'ba_cart_reminder_ready', '2h', 'cart', $row['cart_key'], (int) $row['updated_at'] + 2 * HOUR_IN_SECONDS);
            self::queue($row['email_hash'], 'ba_cart_reminder_ready', '24h', 'cart', $row['cart_key'], (int) $row['updated_at'] + DAY_IN_SECONDS);
        }
    }

    public static function cancel_cart(string $key, string $reason): void
    {
        global $wpdb;
        $wpdb->update(self::table('carts'), ['state' => 'cancelled', 'updated_at' => time()], ['cart_key' => $key]);
        $wpdb->query($wpdb->prepare('UPDATE ' . self::table('outbox') . " SET state=IF(state='sending','review_required','suppressed'),error_code=%s,updated_at=%d WHERE entity_kind='cart' AND entity_id=%s AND state IN ('pending','sending')", $reason, time(), $key));
    }

    public static function cancel_carts_for_contact(string $hash, string $reason): void
    {
        global $wpdb;
        $keys = $wpdb->get_col($wpdb->prepare('SELECT cart_key FROM ' . self::table('carts') . " WHERE email_hash=%s AND state='active'", $hash));
        foreach ($keys as $key) self::cancel_cart($key, $reason);
    }

    public static function queue(string $hash, string $event, string $stage, string $kind, string $entity, int $due, bool $move_pending = false): bool
    {
        global $wpdb;
        $table = self::table('outbox');
        $mode = Config::mode();
        $site = rtrim(home_url(), '/');
        $dedupe = self::hash(implode('|', [$site, $mode, $hash, $event, $stage, $kind, $entity]));
        $sql = "INSERT INTO $table (dedupe_key,delivery_key,email_hash,event_name,stage,entity_kind,entity_id,mode,site,due_at,created_at,updated_at)
                VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%d,%d,%d) ON DUPLICATE KEY UPDATE ";
        $sql .= $move_pending ? "due_at=IF(state='pending',VALUES(due_at),due_at),updated_at=IF(state='pending',VALUES(updated_at),updated_at)" : 'dedupe_key=dedupe_key';
        $ok = $wpdb->query($wpdb->prepare($sql, $dedupe, bin2hex(random_bytes(32)), $hash, $event, $stage, $kind, $entity, $mode, $site, $due, time(), time())) !== false;
        if (!$ok) update_option('bactive_brevo_storage_error', ['code' => 'queue_failed', 'at' => time()], false);
        return $ok;
    }

    public static function cancel_order(int $id, string $reason): void
    {
        global $wpdb;
        $wpdb->query($wpdb->prepare('UPDATE ' . self::table('outbox') . " SET state=IF(state='sending','review_required','suppressed'),error_code=%s,updated_at=%d WHERE entity_kind='order' AND entity_id=%s AND state IN ('pending','sending')", $reason, time(), (string) $id));
    }

    public static function review_order(int $id, string $reason): void
    {
        global $wpdb;
        $wpdb->query($wpdb->prepare('UPDATE ' . self::table('outbox') . " SET state='review_required',error_code=%s,updated_at=%d WHERE entity_kind='order' AND entity_id=%s AND state IN ('pending','sending')", $reason, time(), (string) $id));
    }

    public static function cancel_old_winback(string $hash, int $new_order): void
    {
        global $wpdb;
        $wpdb->query($wpdb->prepare('UPDATE ' . self::table('outbox') . " SET state=IF(state='sending','review_required','suppressed'),error_code='new_purchase_cycle',updated_at=%d WHERE email_hash=%s AND event_name='ba_winback_ready' AND entity_id<>%s AND state IN ('pending','sending')", time(), $hash, (string) $new_order));
    }

    public static function due(int $limit = 20): array
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . self::table('outbox') . " WHERE state='pending' AND due_at<=%d AND mode=%s AND site=%s ORDER BY due_at,id LIMIT %d", time(), Config::mode(), rtrim(home_url(), '/'), min(20, max(1, $limit))), ARRAY_A) ?: [];
    }

    public static function claim(int $id): bool
    {
        global $wpdb;
        return $wpdb->query($wpdb->prepare('UPDATE ' . self::table('outbox') . " SET state='sending',attempts=attempts+1,lease_until=%d,updated_at=%d WHERE id=%d AND state='pending' AND due_at<=%d AND mode=%s AND site=%s", time() + 120, time(), $id, time(), Config::mode(), rtrim(home_url(), '/'))) === 1;
    }

    public static function finish(int $id, string $state, string $code = '', int $due = 0): void
    {
        global $wpdb;
        if (!in_array($state, ['pending', 'accepted', 'workflow_received', 'review_required', 'suppressed', 'failed'], true)) throw new \InvalidArgumentException('Unknown delivery state');
        $data = ['state' => $state, 'error_code' => substr($code, 0, 64), 'lease_until' => 0, 'updated_at' => time()];
        if ($due) $data['due_at'] = $due;
        // A webhook can suppress an in-flight request. Never overwrite its decision.
        $wpdb->update(self::table('outbox'), $data, ['id' => $id, 'state' => 'sending']);
    }

    public static function delivery(string $key): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table('outbox') . ' WHERE delivery_key=%s', $key), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    public static function receipt(string $key): bool
    {
        global $wpdb;
        $row = self::delivery($key);
        if (!$row || $row['site'] !== rtrim(home_url(), '/') || $row['mode'] !== Config::mode()) return false;
        $contact = self::contact($row['email_hash']);
        $state = $contact && $contact['state'] === 'confirmed' && $row['error_code'] !== 'consent_withdrawn'
            ? 'workflow_received' : 'review_required';
        return $wpdb->query($wpdb->prepare('UPDATE ' . self::table('outbox') . " SET receipt_at=%d,state=%s,updated_at=%d WHERE id=%d AND state IN ('accepted','sending','review_required')", time(), $state, time(), $row['id'])) === 1;
    }

    /** Atomic counter. The key contains only a salted digest; expiry makes rate data short-lived. */
    public static function reserve(string $key, int $limit, int $expires): bool
    {
        global $wpdb;
        $key = self::hash($key);
        $table = self::table('controls');
        $result = $wpdb->query($wpdb->prepare("INSERT INTO $table (control_key,value,expires_at) VALUES (%s,1,%d) ON DUPLICATE KEY UPDATE value=value+1", $key, $expires));
        return $result !== false && (int) $wpdb->get_var($wpdb->prepare("SELECT value FROM $table WHERE control_key=%s", $key)) <= $limit;
    }

    public static function once(string $key, int $expires): bool
    {
        global $wpdb;
        return $wpdb->query($wpdb->prepare('INSERT IGNORE INTO ' . self::table('controls') . ' (control_key,value,expires_at) VALUES (%s,1,%d)', self::hash($key), $expires)) === 1;
    }

    public static function cleanup(): void
    {
        global $wpdb;
        // A worker lost after POST must never be picked up and blindly replayed.
        $wpdb->query($wpdb->prepare('UPDATE ' . self::table('outbox') . " SET state='review_required',error_code='worker_interrupted',updated_at=%d WHERE state='sending' AND lease_until<%d", time(), time()));
        $wpdb->query($wpdb->prepare('DELETE FROM ' . self::table('controls') . ' WHERE expires_at<%d LIMIT 500', time()));
        $wpdb->query($wpdb->prepare('DELETE FROM ' . self::table('carts') . ' WHERE expires_at<%d LIMIT 100', time()));
        $wpdb->query($wpdb->prepare('UPDATE ' . self::table('contacts') . " SET confirmation_token_hash='',marker='',state='expired',updated_at=%d WHERE state='pending' AND pending_until<%d", time(), time()));
    }

    public static function pause(): void
    {
        $settings = get_option(Config::OPTION, []);
        $settings = is_array($settings) ? $settings : [];
        $settings['enabled'] = false;
        update_option(Config::OPTION, $settings, false);
        delete_option('bactive_brevo_cron_evidence');
    }

    public static function status(): array
    {
        global $wpdb;
        $out = ['schema' => (int) get_option('bactive_brevo_schema', 0), 'contacts' => [], 'outbox' => [],
            'storage_error' => get_option('bactive_brevo_storage_error', []),
            'last_cli_tick' => (int) (get_option('bactive_brevo_cron_evidence', [])['last'] ?? 0)];
        foreach (['contacts', 'outbox'] as $table) {
            foreach (($wpdb->get_results('SELECT state,COUNT(*) AS count FROM ' . self::table($table) . ' GROUP BY state', ARRAY_A) ?: []) as $row) {
                $out[$table][$row['state']] = (int) $row['count'];
            }
        }
        return $out;
    }
}
