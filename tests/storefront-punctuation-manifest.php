<?php
/** Standalone PHP CLI regression harness. Never load through WordPress or deploy publicly. */
if ( PHP_SAPI !== 'cli' || defined('ABSPATH') ) { exit(1); }
define('WP_CLI', true);
define('DB_NAME', 'waypmvhk_bactwp');

class BactiveTestWriteError {}
function is_wp_error($value) { return $value instanceof BactiveTestWriteError; }
function current_user_can($capability, ...$args) { return ! in_array($capability, $GLOBALS['state']['denied'], true); }
function get_option($name) { return $GLOBALS['state']['options'][$name] ?? false; }
function get_stylesheet() { return $GLOBALS['state']['stylesheet']; }
function get_post($id) {
    return isset($GLOBALS['state']['posts'][$id]) ? (object) $GLOBALS['state']['posts'][$id] : null;
}
function get_post_status($id) {
    $post = get_post($id);
    return $post ? $post->post_status : false;
}
function get_taxonomies($args) { return array('category' => 'category'); }
function get_taxonomy($name) { return (object) array('cap' => (object) array('edit_terms' => 'manage_categories')); }
function get_term($id, $taxonomy) {
    return isset($GLOBALS['state']['terms'][$taxonomy][$id]) ? (object) $GLOBALS['state']['terms'][$taxonomy][$id] : null;
}
function wp_slash($value) {
    if ( is_array($value) ) { return array_map('wp_slash', $value); }
    return is_string($value) ? addslashes($value) : $value;
}
function wp_unslash($value) {
    if ( is_array($value) ) { return array_map('wp_unslash', $value); }
    return is_string($value) ? stripslashes($value) : $value;
}
function clean_post_cache($id) {}
function wp_update_post($args, $wp_error = false) {
    $id = $args['ID'];
    $GLOBALS['state']['writes'][] = array('kind' => 'post', 'id' => $id, 'args' => $args);
    if ( $GLOBALS['state']['fail_post'] === $id ) { return new BactiveTestWriteError(); }
    $values = wp_unslash($args);
    unset($values['ID']);
    $GLOBALS['state']['posts'][$id] = array_merge($GLOBALS['state']['posts'][$id], $values);
    if ( $GLOBALS['state']['corrupt_post'] === $id ) { $GLOBALS['state']['posts'][$id]['post_content'] .= ' changed by hook'; }
    return $id;
}
function wp_update_term($id, $taxonomy, $args) {
    $GLOBALS['state']['writes'][] = array('kind' => 'term', 'id' => $id, 'args' => $args);
    // Match WordPress taxonomy.php's expected-slashed name/description contract.
    $GLOBALS['state']['terms'][$taxonomy][$id] = array_merge($GLOBALS['state']['terms'][$taxonomy][$id], wp_unslash($args));
    return array('term_id' => $id);
}
function update_option($name, $value) {
    $GLOBALS['state']['writes'][] = array('kind' => 'option', 'id' => $name, 'value' => $value);
    $changed = get_option($name) !== $value;
    $GLOBALS['state']['options'][$name] = $value;
    return $changed;
}
function check($condition, $message) {
    if ( ! $condition ) { throw new RuntimeException($message); }
}
function fresh_state() {
    $GLOBALS['state'] = array(
        'options' => array('home' => 'https://bactiveph.com', 'siteurl' => 'https://bactiveph.com', 'blogname' => 'B Active'),
        'stylesheet' => 'blocksy-child', 'denied' => array(), 'writes' => array(),
        'posts' => array(), 'terms' => array(), 'fail_post' => null, 'corrupt_post' => null,
    );
}
function seed_post($id, $before, $after) {
    $GLOBALS['state']['posts'][$id] = array(
        'ID' => $id, 'post_status' => 'publish', 'post_type' => 'page', 'post_name' => 'page-' . $id,
        'post_content' => $before, 'post_title' => 'Unchanged title', 'post_excerpt' => 'Unchanged excerpt',
    );
    return array('kind' => 'post', 'id' => $id, 'slug' => 'page-' . $id, 'fields' => array('post_content' => field_change($before, $after)));
}
function field_change($before, $after) {
    return array(
        'before_sha256' => hash('sha256', $before), 'after_sha256' => hash('sha256', $after),
        'replacements' => array(array('old' => $before, 'new' => $after, 'count' => 1)),
    );
}
function manifest($items) { return array('version' => 1, 'items' => $items); }
function rejects_without_writes($candidate, $message) {
    $caught = false;
    try { bactive_apply_punctuation_manifest($candidate, 'apply'); }
    catch (Throwable $error) { $caught = true; }
    check($caught && count($GLOBALS['state']['writes']) === 0, $message);
}

require __DIR__ . '/../tools/apply_storefront_punctuation.php';

// All objects must pass preflight before the first write, even when the first is valid.
fresh_state();
$first = seed_post(10, 'First — sentence.', 'First sentence.');
$second = seed_post(20, 'Second — sentence.', 'Second sentence.');
$GLOBALS['state']['posts'][20]['post_content'] = 'Concurrent edit';
rejects_without_writes(manifest(array($first, $second)), 'Later stale object allowed an earlier write');
check($GLOBALS['state']['posts'][10]['post_content'] === 'First — sentence.', 'Preflight changed the first object');

// Reversible punctuation must preserve apostrophes, literal backslashes, and unrelated fields.
fresh_state();
$before = "Player's \\kit — court-ready.";
$after = "Player's \\kit is court-ready.";
$post = seed_post(10, $before, $after);
$original = $GLOBALS['state']['posts'][10];
$plan = manifest(array($post));
$result = bactive_apply_punctuation_manifest($plan, 'check');
check($result['complete'] && $result['changed'] === array() && $GLOBALS['state']['writes'] === array(), 'Check mode wrote state');
$result = bactive_apply_punctuation_manifest($plan, 'apply');
check($result['complete'] && count($result['changed']) === 1, 'Post apply failed');
check($GLOBALS['state']['posts'][10]['post_content'] === $after, 'Post apostrophe/backslash bytes changed');
check($GLOBALS['state']['writes'][0]['args']['post_content'] === wp_slash($after), 'Post API did not receive slashed text');
check($GLOBALS['state']['posts'][10]['post_title'] === $original['post_title'], 'Unselected post field changed');
$result = bactive_apply_punctuation_manifest($plan, 'rollback');
check($result['complete'] && $GLOBALS['state']['posts'][10] === $original, 'Post rollback did not restore exact bytes');

// Only published variations of published parents qualify as public copy.
fresh_state();
seed_post(10, 'Parent content', 'Unused parent replacement');
$GLOBALS['state']['posts'][10]['post_type'] = 'product';
$variation = seed_post(11, 'Unchanged variation content', 'Unused variation replacement');
$GLOBALS['state']['posts'][11]['post_type'] = 'product_variation';
$GLOBALS['state']['posts'][11]['post_parent'] = 10;
$GLOBALS['state']['posts'][11]['post_excerpt'] = 'Waist 62–66 cm';
$variation['fields'] = array('post_excerpt' => field_change('Waist 62–66 cm', 'Waist 62 to 66 cm'));
$plan = manifest(array($variation));
$original = $GLOBALS['state']['posts'][11];
foreach (array('draft', 'private', 'trash') as $parent_status) {
    $GLOBALS['state']['posts'][10]['post_status'] = $parent_status;
    rejects_without_writes($plan, 'Non-public variation parent allowed a write: ' . $parent_status);
}
$GLOBALS['state']['posts'][10]['post_status'] = 'publish';
$GLOBALS['state']['posts'][11]['post_parent'] = 999;
rejects_without_writes($plan, 'Missing variation parent allowed a write');
$GLOBALS['state']['posts'][11]['post_parent'] = 10;
$GLOBALS['state']['posts'][11]['post_status'] = 'private';
rejects_without_writes($plan, 'Non-public variation allowed a write');
$GLOBALS['state']['posts'][11]['post_status'] = 'publish';
$result = bactive_apply_punctuation_manifest($plan, 'apply');
check($result['complete'] && $GLOBALS['state']['posts'][11]['post_excerpt'] === 'Waist 62 to 66 cm', 'Public variation description failed to update');
check($GLOBALS['state']['posts'][11]['post_content'] === $original['post_content'], 'Variation edit changed an unselected field');
$result = bactive_apply_punctuation_manifest($plan, 'rollback');
check($result['complete'] && $GLOBALS['state']['posts'][11] === $original, 'Variation rollback failed to restore exact bytes');

// Term writes also require slashed input; the stub explicitly unslashes like WordPress.
fresh_state();
$before = "Women's \\sizes 80–84";
$after = "Women's \\sizes 80 to 84";
$GLOBALS['state']['terms']['category'][30] = array('term_id' => 30, 'name' => 'Sizing', 'description' => $before);
$term = array('kind' => 'term', 'id' => 30, 'taxonomy' => 'category', 'fields' => array('description' => field_change($before, $after)));
$result = bactive_apply_punctuation_manifest(manifest(array($term)), 'apply');
check($result['complete'] && $GLOBALS['state']['terms']['category'][30]['description'] === $after, 'Term bytes changed during write');
check($GLOBALS['state']['writes'][0]['args']['description'] === wp_slash($after), 'Term API did not receive slashed text');
$result = bactive_apply_punctuation_manifest(manifest(array($term)), 'rollback');
check($result['complete'] && $GLOBALS['state']['terms']['category'][30]['description'] === $before, 'Term rollback failed');

// A forward-valid replacement that is not invertible must fail before mutation.
fresh_state();
$post = seed_post(10, 'A — B. A to B.', 'A to B. A to B.');
$post['fields']['post_content']['replacements'] = array(array('old' => 'A — B.', 'new' => 'A to B.', 'count' => 1));
rejects_without_writes(manifest(array($post)), 'Ambiguous inverse replacement passed preflight');

fresh_state();
$post = seed_post(10, 'Text — copy.', 'Text copy.');
rejects_without_writes(manifest(array($post, $post)), 'Duplicate target passed preflight');
$post['fields'] = array();
rejects_without_writes(manifest(array($post)), 'Empty target passed preflight');

// Site and per-object permissions must stop writes.
fresh_state();
$post = seed_post(10, 'Text — copy.', 'Text copy.');
$GLOBALS['state']['options']['home'] = 'https://other.example';
rejects_without_writes(manifest(array($post)), 'Wrong site allowed a write');
$GLOBALS['state']['options']['home'] = 'https://bactiveph.com';
$GLOBALS['state']['denied'] = array('manage_options');
rejects_without_writes(manifest(array($post)), 'Missing management capability allowed a write');
$GLOBALS['state']['denied'] = array('edit_post');
rejects_without_writes(manifest(array($post)), 'Missing object capability allowed a write');

// A failed second write reports only verified progress and never attempts the third.
fresh_state();
$first = seed_post(10, 'One — copy.', 'One copy.');
$second = seed_post(20, 'Two — copy.', 'Two copy.');
$third = seed_post(30, 'Three — copy.', 'Three copy.');
$GLOBALS['state']['fail_post'] = 20;
$result = bactive_apply_punctuation_manifest(manifest(array($first, $second, $third)), 'apply');
check(! $result['complete'] && $result['stopped_at'] === 1, 'Partial failure reported completion or wrong target');
check($result['changed'] === array(array('index' => 0, 'kind' => 'post', 'id' => 10)), 'Partial failure receipt overstated progress');
check(count($GLOBALS['state']['writes']) === 2 && $GLOBALS['state']['posts'][30]['post_content'] === 'Three — copy.', 'Writer continued after failure');
check($result['error'] === 'Stopped; inspect destination before any further write', 'Failure receipt exposed provider error data');
// After independent readback confirms the failed object stayed unchanged, rollback only the verified subset.
$result = bactive_apply_punctuation_manifest(manifest(array($first)), 'rollback');
check($result['complete'] && $GLOBALS['state']['posts'][10]['post_content'] === 'One — copy.', 'Reviewed subset rollback failed');

// A write that succeeds but reads back differently is uncertain, not counted as verified.
fresh_state();
$post = seed_post(10, 'Text — copy.', 'Text copy.');
$GLOBALS['state']['corrupt_post'] = 10;
$result = bactive_apply_punctuation_manifest(manifest(array($post)), 'apply');
check(! $result['complete'] && $result['stopped_at'] === 0 && $result['changed'] === array(), 'Readback mismatch was counted as success');
check(count($GLOBALS['state']['writes']) === 1, 'Uncertain write was retried');

// The homepage tagline release must remain one exact, reversible, structure-safe edit.
$tagline_path = __DIR__ . '/../content/storefront-homepage-tagline.json';
$tagline = json_decode(file_get_contents($tagline_path), true);
check(json_last_error() === JSON_ERROR_NONE, 'Homepage tagline manifest is not valid JSON');
check(($tagline['version'] ?? null) === 1 && ($tagline['site'] ?? null) === 'https://bactiveph.com' && ($tagline['issue'] ?? null) === 58, 'Homepage tagline manifest identity changed');
check(count($tagline['items'] ?? array()) === 1, 'Homepage tagline manifest must target one object');
$tagline_item = $tagline['items'][0];
check($tagline_item['kind'] === 'post' && $tagline_item['id'] === 14 && $tagline_item['slug'] === 'home', 'Homepage tagline target changed');
check(array_keys($tagline_item['fields'] ?? array()) === array('post_content'), 'Homepage tagline must only change post content');
$tagline_change = $tagline_item['fields']['post_content'] ?? array();
check(($tagline_change['before_sha256'] ?? '') === '1f0a120db564934c968b6f61334188d000cb8c0cf2bee8c7630129bd7d22c7e0', 'Homepage tagline before hash changed');
check(($tagline_change['after_sha256'] ?? '') === 'a7ac16d9cf1e29c70b0faa82bb3fcfc5429758804e09969bf60c79bf93e5524d', 'Homepage tagline after hash changed');
check(count($tagline_change['replacements'] ?? array()) === 1, 'Homepage tagline replacement count changed');
$tagline_replacement = $tagline_change['replacements'][0];
$tagline_old = '<p><p style="color:#D4AF37;font-weight:600;text-transform:uppercase;letter-spacing:1px;margin-bottom:10px">Court-To-café Luxury.</p></p>';
$tagline_new = '<p style="color:#D4AF37;font-weight:600;text-transform:none;letter-spacing:1px;margin-bottom:10px">From the Court to the Café</p>';
check($tagline_replacement === array('old' => $tagline_old, 'new' => $tagline_new, 'count' => 1), 'Homepage tagline copy or markup changed');
check(str_contains($tagline_new, 'text-transform:none'), 'Homepage tagline lost its case and CSS selector hook');
$tagline_sample = 'before' . $tagline_old . 'after';
$tagline_applied = str_replace($tagline_old, $tagline_new, $tagline_sample, $tagline_count);
check($tagline_count === 1 && ! str_contains($tagline_applied, '<p><p') && substr_count($tagline_applied, 'From the Court to the Café') === 1, 'Homepage tagline forward replacement failed');
check(str_replace($tagline_new, $tagline_old, $tagline_applied) === $tagline_sample, 'Homepage tagline rollback is not exact');

echo "Storefront punctuation manifest regression checks passed\n";
