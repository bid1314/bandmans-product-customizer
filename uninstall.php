<?php
// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete custom post types and their data
$post_types = array('configurable_product', 'product_rfq');

foreach ($post_types as $post_type) {
    $posts = get_posts(array(
        'post_type' => $post_type,
        'post_status' => 'any',
        'numberposts' => -1,
        'fields' => 'ids'
    ));

    foreach ($posts as $post_id) {
        wp_delete_post($post_id, true);
    }
}

// Drop custom tables
global $wpdb;
$tables = array(
    $wpdb->prefix . 'product_layers'
);

foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS $table");
}

// Delete plugin options
delete_option('custom_product_configurator_version');

// Remove capabilities from admin role
$admin_role = get_role('administrator');
if ($admin_role) {
    $capabilities = array(
        'edit_product_rfq',
        'read_product_rfq',
        'delete_product_rfq',
        'edit_product_rfqs',
        'edit_others_product_rfqs',
        'publish_product_rfqs',
        'read_private_product_rfqs'
    );

    foreach ($capabilities as $cap) {
        $admin_role->remove_cap($cap);
    }
}

// Clear any cached data
wp_cache_flush();
