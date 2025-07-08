<?php


if(!defined('WP_UNINSTALL_PLUGIN')){
    header("Location:/");
    die('Direct access not allowed');
}

// global $wpdb, $table_prefix;

//     $wpdb_table = $table_prefix . '_seo_csv';

//     $query = "DROP TABLE IF EXISTS $wpdb_table";

//     $wpdb->query($query);

delete_option('allow_access_origin');
