<?php
/**
 * seo-csv-data plugin file
 */

if(!defined('WP_UNINSTALL_PLUGIN')){
    header("Location:/");
    die('Direct access not allowed');
}

global $wpdb, $table_prefix;

    $wpdb_table = $table_prefix . 'seo_csv_logs';

    $query = "DROP TABLE IF EXISTS $wpdb_table";

    $wpdb->query($query);

delete_option('allow_access_origin');

$seo_base_dir = WP_CONTENT_DIR . "/seo-csv-data/";

// Recursive deletion function
function delete_directory_recursive($dir)
{
    if (!file_exists($dir)) return;

    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item == '.' || $item == '..') continue;

        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            delete_directory_recursive($path);
        } else {
            unlink($path);
        }
    }

    rmdir($dir);
}

// Delete the main folder
if (is_dir($seo_base_dir)) {
    delete_directory_recursive($seo_base_dir);
}
