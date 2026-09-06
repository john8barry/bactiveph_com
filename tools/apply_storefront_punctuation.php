<?php
/** Private WP-CLI include, never a public HTTP endpoint. Manifest is reviewed public-copy edits. */
if ( ! defined('WP_CLI') || ! WP_CLI ) { exit(1); }

function bactive_punctuation_record( $item ) {
    if ( $item['kind'] === 'post' ) {
        $post = get_post($item['id']);
        if ( ! $post || $post->post_status !== 'publish' || ! in_array($post->post_type, array('post', 'page', 'product', 'product_variation'), true)
            || $post->post_name !== $item['slug'] || ! current_user_can('edit_post', $post->ID) ) {
            throw new RuntimeException('Post identity or authorization changed');
        }
        if ( $post->post_type === 'product_variation' ) {
            $parent = $post->post_parent > 0 ? get_post($post->post_parent) : null;
            if ( ! $parent || $parent->post_type !== 'product' || $parent->post_status !== 'publish' ) {
                throw new RuntimeException('Variation parent is not a published product');
            }
        }
        $out = array();
        foreach ( $item['fields'] as $key => $change ) {
            if ( ! in_array($key, array('post_title', 'post_content', 'post_excerpt'), true) ) { throw new RuntimeException('Unsupported post field'); }
            $out[$key] = $post->$key;
        }
        return $out;
    }
    if ( $item['kind'] === 'option' && in_array($item['name'], array('blogname', 'blogdescription'), true) ) {
        return array('value' => get_option($item['name']));
    }
    if ( $item['kind'] === 'term' && in_array($item['taxonomy'], get_taxonomies(array('public' => true)), true) ) {
        $term = get_term($item['id'], $item['taxonomy']);
        $taxonomy = get_taxonomy($item['taxonomy']);
        if ( ! $term || is_wp_error($term) || ! current_user_can($taxonomy->cap->edit_terms) ) { throw new RuntimeException('Term identity or authorization changed'); }
        $out = array();
        foreach ( $item['fields'] as $key => $change ) {
            if ( ! in_array($key, array('name', 'description'), true) ) { throw new RuntimeException('Unsupported term field'); }
            $out[$key] = $term->$key;
        }
        return $out;
    }
    throw new RuntimeException('Unsupported object');
}

function bactive_punctuation_prepare( $item, $current, $reverse ) {
    $after = array();
    foreach ( $item['fields'] as $key => $change ) {
        $from = $reverse ? 'after_sha256' : 'before_sha256';
        $to = $reverse ? 'before_sha256' : 'after_sha256';
        if ( ! isset($current[$key]) || ! is_string($current[$key]) || ! hash_equals($change[$from], hash('sha256', $current[$key])) ) {
            throw new RuntimeException('Content hash changed');
        }
        $text = $current[$key];
        foreach ( $reverse ? array_reverse($change['replacements']) : $change['replacements'] as $replacement ) {
            $old = $replacement[$reverse ? 'new' : 'old'];
            $new = $replacement[$reverse ? 'old' : 'new'];
            if ( ! is_string($old) || $old === '' || ! is_string($new) || substr_count($text, $old) !== $replacement['count'] ) {
                throw new RuntimeException('Replacement count changed');
            }
            $text = str_replace($old, $new, $text);
        }
        if ( ! hash_equals($change[$to], hash('sha256', $text)) ) { throw new RuntimeException('Result hash mismatch'); }
        $after[$key] = $text;
    }
    return $after;
}

function bactive_apply_punctuation_manifest( $manifest, $mode = 'check' ) {
    if ( get_option('home') !== 'https://bactiveph.com' || get_option('siteurl') !== 'https://bactiveph.com'
        || DB_NAME !== 'waypmvhk_bactwp' || get_stylesheet() !== 'blocksy-child'
        || ! current_user_can('manage_options') || ! in_array($mode, array('check', 'apply', 'rollback'), true)
        || ($manifest['version'] ?? null) !== 1 || ! is_array($manifest['items'] ?? null) ) {
        throw new RuntimeException('Site, capability, mode or manifest mismatch');
    }
    $reverse = $mode === 'rollback';
    $items = $reverse ? array_reverse($manifest['items']) : $manifest['items'];
    // Validate every destination before the first mutation.
    $seen = array();
    foreach ( $items as $item ) {
        $identity = $item['kind'] . ':' . ($item['id'] ?? $item['name']);
        if ( isset($seen[$identity]) || empty($item['fields']) ) { throw new RuntimeException('Duplicate or empty object'); }
        $seen[$identity] = true;
        $current = bactive_punctuation_record($item);
        $prepared = bactive_punctuation_prepare($item, $current, $reverse);
        if ( bactive_punctuation_prepare($item, $prepared, ! $reverse) !== $current ) {
            throw new RuntimeException('Rollback round trip failed');
        }
    }
    $result = array('mode' => $mode, 'complete' => false, 'changed' => array());
    if ( $mode === 'check' ) { $result['complete'] = true; return $result; }
    foreach ( $items as $index => $item ) {
        try {
            $after = bactive_punctuation_prepare($item, bactive_punctuation_record($item), $reverse);
            if ( $item['kind'] === 'post' ) {
                $saved = wp_update_post(wp_slash(array_merge(array('ID' => $item['id']), $after)), true);
                if ( is_wp_error($saved) ) { throw new RuntimeException('Post write failed'); }
                clean_post_cache($item['id']);
            } elseif ( $item['kind'] === 'option' ) {
                if ( ! update_option($item['name'], $after['value']) ) { throw new RuntimeException('Option write failed'); }
            } else {
                $saved = wp_update_term($item['id'], $item['taxonomy'], wp_slash($after));
                if ( is_wp_error($saved) ) { throw new RuntimeException('Term write failed'); }
            }
            $actual = bactive_punctuation_record($item);
            foreach ( $after as $key => $value ) {
                if ( $actual[$key] !== $value ) { throw new RuntimeException('Write readback mismatch'); }
            }
            $result['changed'][] = array('index' => $index, 'kind' => $item['kind'], 'id' => $item['id'] ?? $item['name']);
        } catch ( Throwable $error ) {
            // Report exact progress, without database errors or payloads. Never retry an uncertain write.
            $result['stopped_at'] = $index;
            $result['error'] = 'Stopped; inspect destination before any further write';
            return $result;
        }
    }
    $result['complete'] = true;
    return $result;
}
