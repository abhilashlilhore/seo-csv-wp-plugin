<?php

/**
 * Plugin Name: CSV SEO Data
 * Description: This plugin updates meta data for the Rank Math and Yoast SEO plugins. 
 * Version: 1.0
 * Author: Savior marketing pvt. ltd.
 * Author URI:https://savior.im/
 * 
 */


if (!defined("ABSPATH")) {

    header("Location:/");
    die('Direct access not allowed');
}

include_once(plugin_dir_path(__FILE__) . 'api-authentication.php');


function activetion_seo_csv_plugin()
{


    global $wpdb, $table_prefix;

    $table_name = $table_prefix . "seo_csv_logs";

    $query = "CREATE TABLE IF NOT EXISTS $table_name( 
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        column_id BIGINT,
        csv_file_id BIGINT,
        website_id BIGINT,
        post_url TEXT,
        csv_url TEXT,
        meta_title TEXT,
        meta_description TEXT,
        status TINYINT,      
        created_at DATETIME) ";

    $wpdb->query($query);


    $url = '*'; ///allow all origin 
    update_option('allow_access_origin', $url);
}

register_activation_hook(__FILE__, 'activetion_seo_csv_plugin');

function deactive_seo_csv_plugin()
{
    ///allow all origin
    global $wpdb, $table_prefix;

    $wpdb_table = $table_prefix . 'seo_csv_logs';
    $query = "TRUNCATE TABLE $wpdb_table ";
    $wpdb->query($query);

    delete_option('allow_access_origin');
}
register_deactivation_hook(__FILE__, 'deactive_seo_csv_plugin');

add_action('admin_menu', 'myplugin_add_settings_page');

function myplugin_add_settings_page()
{
    add_options_page(
        'CSV-SEO Settings',
        'CSV-SEO settings',
        'manage_options',
        'csv-seo-settings',
        'seo_detector_settings_page'
    );
}

// Add settings link on plugin page
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'myplugin_settings_link');

function myplugin_settings_link($links)
{
    $settings_link = '<a href="options-general.php?page=csv-seo-settings">Settings</a>';
    array_unshift($links, $settings_link); // Put it first
    return $links;
}


function seo_detector_detect_seo_plugins()
{
    include_once(ABSPATH . 'wp-admin/includes/plugin.php');

    $plugins = [
        'rankmath' => is_plugin_active('seo-by-rank-math/rank-math.php'),
        'yoast'    => is_plugin_active('wordpress-seo/wp-seo.php'),
    ];

    return $plugins;
}

function seo_detector_settings_page()
{
    $_SESSION['seo_plugins'] = seo_detector_detect_seo_plugins();

    if (isset($_POST['allowed_origin_url'])) {
        $url = trim($_POST['allowed_origin_url']);
        update_option('allow_access_origin', $url);
        echo '<div class="updated"><p>Origin URL saved!</p></div>';
    }

    $saved_url = get_option('allow_access_origin', '');
?>
    <div class="wrap">
        <h1>SEO Plugin Detection</h1>
        <form>
            <label><strong>Active SEO Plugins:</strong></label><br><br>

            <input type="checkbox" disabled <?php checked($_SESSION['seo_plugins']['rankmath']); ?>> Rank Math SEO<br>
            <input type="checkbox" disabled <?php checked($_SESSION['seo_plugins']['yoast']); ?>> Yoast SEO<br>

            <?php
            $none_of_the_above = false;
            if (!$_SESSION['seo_plugins']['rankmath'] && !$_SESSION['seo_plugins']['yoast']) {
                $none_of_the_above = true;
            }
            ?>
            <input type="checkbox" disabled <?php checked($none_of_the_above, true); ?>> None of the above<br>

        </form>
    </div>

    <div class="wrap">
        <h2>Set Allowed Origin URL</h2>
        <form method="post">
            <label for="allowed_origin_url">Allowed Origin URL:</label>
            <input type="" name="allowed_origin_url" id="allowed_origin_url" value="<?php echo esc_attr($saved_url); ?>" required style="width: 400px;" />
            <br><br>
            <input type="submit" value="Save" class="button button-primary" />
        </form>
    </div>


    <h2 style="margin-top:40px;">All uploaded CSV files</h2>


    <?php
    global $wpdb, $table_prefix;
    $table_name = $table_prefix . "seo_csv_logs";

    $csv_files = $wpdb->get_results("SELECT DISTINCT csv_file_id, csv_url FROM $table_name ORDER BY id DESC");

    if ($csv_files) {
        echo '<table class="widefat striped">';
        echo '<thead><tr><th>CSV File ID</th><th>CSV URL</th><th>Action</th></tr></thead><tbody>';

        foreach ($csv_files as $file) {
            echo '<tr>';
            echo '<td>' . esc_html($file->csv_file_id) . '</td>';
            echo '<td><a href="' . esc_url($file->csv_url) . '" target="_blank">' . esc_html($file->csv_url) . '</a></td>';
            echo '<td><a class="button" href="' . admin_url('admin.php?page=seo-csv-view&csv_file_id=' . esc_attr($file->csv_file_id)) . '">View</a></td>';

            echo '</tr>';
        }

        echo '</tbody></table>';
    } else {
        echo '<p>No CSV file records found.</p>';
    }
}


add_action('admin_menu', function () {
    add_menu_page('SEO CSV Main', 'SEO CSV', 'manage_options', 'seo-csv-main', 'seo_csv_main_page');

    // Use the same slug as the parent
    add_submenu_page(
        'seo-csv-main',              // parent slug
        'CSV View',                  // page title
        '',                          // empty menu label (won’t show in menu)
        'manage_options',
        'seo-csv-view',
        'seo_csv_view_page'
    );
});



function seo_csv_view_page()
{
    // if (!current_user_can('manage_options')) {
    //     return;
    // }

    global $wpdb, $table_prefix;
    $table_name = $table_prefix . "seo_csv_logs";

    $csv_file_id = isset($_GET['csv_file_id']) ? intval($_GET['csv_file_id']) : 0;

    if (!$csv_file_id) {
        echo '<div class="notice notice-error"><p>Invalid CSV File ID</p></div>';
        return;
    }

    echo '<div class="wrap"><h1>CSV File Details: ID ' . esc_html($csv_file_id) . '</h1>';

    $rows = $wpdb->get_results(
        $wpdb->prepare("SELECT * FROM $table_name WHERE csv_file_id = %d", $csv_file_id)
    );

    if ($rows) {
        echo '<table class="widefat striped">';
        echo '<thead><tr><th>Post URL</th><th>Meta Title</th><th>Meta Description</th><th>Status</th><th>Created At</th></tr></thead><tbody>';

        foreach ($rows as $row) {
            echo '<tr>';
            echo '<td><a href="' . esc_url($row->post_url) . '" target="_blank">' . esc_html($row->post_url) . '</a></td>';
            echo '<td>' . esc_html($row->meta_title ?: '—') . '</td>';
            echo '<td>' . esc_html($row->meta_description ?: '—') . '</td>';
            echo '<td>' . esc_html($row->status ? '✅' : '❌') . '</td>';
            echo '<td>' . esc_html($row->created_at) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    } else {
        echo '<p>No data found for this CSV file.</p>';
    }

    echo '<p><a href="' . admin_url('admin.php?page=seo-csv-main') . '" class="button">← Back to CSV List</a></p>';
    echo '</div>';
}

//////////////////////////// create a webhook end point ///////////

add_action('rest_api_init', function () {
    register_rest_route('seo-csv-data/v1', '/webhook', [
        'methods'  => 'POST',
        'callback' => 'seo_csv_handle_webhook',
        'permission_callback' => 'seo_csv_check_auth',
    ]);

    register_rest_route('seo-csv-data/v1', '/background-process', [
        'methods'  => 'POST',
        'callback' => 'seo_csv_background_process',
        'permission_callback' => '__return_true',
    ]);
});

function read_seo_sheet_csv($url)
{
    $attempts = 3;
    $csv = false;

    for ($i = 0; $i < $attempts; $i++) {
        $csv = @file_get_contents($url);
        if ($csv !== false) break;
        sleep(2);
    }

    if ($csv === false) {
        $message = "Failed to fetch CSV from URL after $attempts attempts: $url";
        error_log($message);

        wp_mail(
            get_option('admin_email'),
            'SEO Plugin Error: CSV Fetch Failed',
            $message,
            ['Content-Type: text/html; charset=UTF-8']
        );

        return false;
    }

    return $csv;
}

function seo_csv_handle_webhook(WP_REST_Request $request)
{
    check_allowed_content_origin();

    $data = $request->get_json_params();
    $required_fields = ['csv_url', 'responce_hook_url', 'website_id', 'csv_file_id'];

    foreach ($required_fields as $field) {
        if (empty($data[$field])) {
            return new WP_REST_Response([
                'status' => 'error',
                'message' => "Missing required field: $field"
            ], 400);
        }
    }

    $website_id = $data['website_id'];
    $csv_id = $data['csv_file_id'];

    // Define path to CSV file
    $base_dir = WP_CONTENT_DIR . "/seo-csv-data/{$website_id}/{$csv_id}/";
    $csv_file_path = $base_dir . "{$csv_id}.csv";

    // If file already exists, stop and return a message
    if (file_exists($csv_file_path)) {
        return new WP_REST_Response([
            'status' => 'error',
            'message' => 'CSV file already exists. Skipping processing.',
        ], 200);
    }

    // Return response quickly
    $response = ['status' => 'accepted', 'message' => 'Processing started.'];
    wp_remote_post(home_url('/wp-json/seo-csv-data/v1/background-process'), [
        'timeout' => 0.01,
        'blocking' => false,
        'body' => json_encode($data),
        'headers' => [
            'Content-Type' => 'application/json'
        ],
    ]);

    $response_data = ['status' => 'accepted', 'message' => 'Processing started.'];
    $response = new WP_REST_Response($response_data, 202);
    echo wp_json_encode($response);
}

function seo_csv_background_process(WP_REST_Request $request)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'seo_csv_logs';
    $data = $request->get_json_params();

    $csv_url = $data['csv_url'];
    $responce_hook_url = $data['responce_hook_url'];
    $website_id = $data['website_id'];
    $csv_id = $data['csv_file_id'];

    $session_seo_plugins = seo_detector_detect_seo_plugins();

    $base_dir = WP_CONTENT_DIR . "/seo-csv-data/{$website_id}/{$csv_id}/";
    wp_mkdir_p($base_dir);
    $csv_file_path = $base_dir . "{$csv_id}.csv";

    $csv_content = read_seo_sheet_csv($csv_url);
    if ($csv_content === false) {
        error_log("Failed to download CSV file.");
        return new WP_REST_Response(['status' => 'error'], 500);
    }

    file_put_contents($csv_file_path, $csv_content);
    chmod($csv_file_path, 0644);

    $csv_rows = array_map('str_getcsv', explode("\n", trim($csv_content)));

    foreach ($csv_rows as $row) {
        if (empty($row[0])) continue;

        $column_id = trim($row[0]);
        $csv_file_id = trim($row[1] ?? '');
        $website_id_new = trim($row[2] ?? '');
        $post_url = trim($row[3] ?? '');
        $new_meta_title = trim($row[4] ?? '');
        $new_meta_description = trim($row[5] ?? '');
        $post_id = url_to_postid($post_url);
        $status = 0;

        if ($post_id) {
            if ($session_seo_plugins['yoast']) {
                update_post_meta($post_id, '_yoast_wpseo_title', $new_meta_title);
                update_post_meta($post_id, '_yoast_wpseo_metadesc', $new_meta_description);
                $status = 1;
            } elseif ($session_seo_plugins['rankmath']) {
                update_post_meta($post_id, 'rank_math_title', $new_meta_title);
                update_post_meta($post_id, 'rank_math_description', $new_meta_description);
                $status = 1;
            }
        }

        $wpdb->insert($table_name, [
            'post_url' => $post_url,
            'csv_url' => $csv_url,
            'meta_title' => $new_meta_title,
            'meta_description' => $new_meta_description,
            'status' => $status,
            'website_id' => $website_id_new,
            'column_id' => $column_id,
            'csv_file_id' => $csv_file_id,
            'created_at' => current_time('mysql'),
        ]);
    }

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT column_id, csv_file_id, website_id, post_url, meta_title, meta_description, status 
             FROM {$table_name} 
             WHERE csv_file_id = %d AND website_id = %d",
            $csv_id,
            $website_id
        ),
        ARRAY_A
    );

    $csv_final = "\"id\",\"csv_file_id\",\"website_id\",\"page_url\",\"meta_title\",\"meta_description\",\"status\"\n";
    foreach ($rows as $row) {
        $escaped_row = array_map(function ($field) {
            $field = str_replace('"', '""', $field);
            return "\"$field\"";
        }, $row);
        $csv_final .= implode(",", $escaped_row) . "\n";
    }

    file_put_contents($csv_file_path, $csv_final);

    $relative_path = "wp-content/seo-csv-data/{$website_id}/{$csv_id}/{$csv_id}.csv";
    $public_url = site_url($relative_path);

    $webhook_payload = [
        'website_id' => $website_id,
        'csv_id'     => $csv_id,
        'csv_url'    => $public_url,
        'status'     => 'completed',
        'message'    => 'SEO metadata updated and CSV file generated.',
    ];
    $response = wp_remote_post($responce_hook_url, [
        'method'  => 'POST',
        'headers' => ['Content-Type' => 'application/json'],
        'body'    => json_encode($webhook_payload),
        'timeout' => 15,
    ]);

    if (is_wp_error($response)) {
        error_log('Webhook error: ' . $response->get_error_message());
    } else {
        error_log('Webhook sent successfully: ' . wp_remote_retrieve_body($response));
    }

    return new WP_REST_Response(['status' => 'processed'], 200);
}
////////////adding view button 
// Hook to add the "View Details" link

add_filter('plugin_row_meta', 'seo_csv_data_add_modal_link', 10, 2);
function seo_csv_data_add_modal_link($links, $file)
{
    if ($file === 'seo-csv-data/seo-csv-data.php') {
        $links[] = '<a href="#" class="seo-csv-details-trigger">View details</a>';
    }
    return $links;
}

add_action('admin_footer', 'seo_csv_data_modal_markup');
function seo_csv_data_modal_markup()
{
    $screen = get_current_screen();
    if ($screen->id !== 'plugins') return;
    ?>
    <div id="seo-csv-details-modal" style="display:none; position: fixed; top: 10%; left: 50%; transform: translateX(-50%);
        background: #fff; border: 1px solid #ccc; padding: 20px; width: 600px; z-index: 9999; box-shadow: 0 4px 12px rgba(0,0,0,0.2);">
        <h2>SEO CSV Plugin Details</h2>
        <p><strong>Version:</strong> 1.0.0</p>
        <p><strong>Author:</strong> Savior marketing pvt. ltd.</p>
        <p><strong>Description:</strong>This plugin allows you to bulk update SEO meta titles and descriptions from a CSV file. Supports Yoast & Rank Math integration.</p>
        <p><strong>API-Document:</strong></p>
        <ul>
            <li>Bearer Token-based authentication for all endpoints</li>
            <li>Get Token via endpoint: <code>/wp-json/seo-csv-data/v1/token</code></li>
            <li>Submit CSV URL to process and update SEO data using: <code>/wp-json/seo-csv-data/v1/webhook</code></li>
            <li>Supports CSV</li>
            <li>Optional <code>response_hook_url</code> to receive processing results via POST callback</li>
            <li>Confirms completion with: <code>/wp-json/seo-csv-data/v1/csv-reading-completed</code></li>
            <li>CSV output includes updated SEO meta titles and descriptions</li>
            <li>Compatible with Yoast SEO and Rank Math plugins</li>
            <li>Error handling, logging, and status response included</li>
        </ul>

        <button id="seo-csv-close-modal" class="button">Close</button>
    </div>
    <div id="seo-csv-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%;
        background: rgba(0,0,0,0.5); z-index: 9998;"></div>
<?php
}
add_action('admin_footer', 'seo_csv_data_modal_script');
function seo_csv_data_modal_script()
{
    $screen = get_current_screen();
    if ($screen->id !== 'plugins') return;
?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const trigger = document.querySelector('.seo-csv-details-trigger');
            const modal = document.getElementById('seo-csv-details-modal');
            const overlay = document.getElementById('seo-csv-overlay');
            const close = document.getElementById('seo-csv-close-modal');

            if (trigger) {
                trigger.addEventListener('click', function(e) {
                    e.preventDefault();
                    modal.style.display = 'block';
                    overlay.style.display = 'block';
                });
            }

            if (close) {
                close.addEventListener('click', function() {
                    modal.style.display = 'none';
                    overlay.style.display = 'none';
                });
            }

            overlay.addEventListener('click', function() {
                modal.style.display = 'none';
                this.style.display = 'none';
            });
        });
    </script>
<?php
}

add_action('rest_api_init', function () {
    register_rest_route('seo-csv-data/v1', '/csv-reading-completed', [
        'methods'  => 'POST',
        'callback' => 'delete_seo_csv_file',
        'permission_callback' => 'seo_csv_check_auth',
    ]);
});

function delete_seo_csv_file(WP_REST_Request $request)
{
    $data = $request->get_json_params();

    $required_fields = ['website_id', 'csv_file_id'];
    foreach ($required_fields as $field) {
        if (empty($data[$field])) {
            return new WP_REST_Response([
                'status'  => 'error',
                'message' => "Missing required field: $field",
            ], 400);
        }
    }

    $website_id = $data['website_id'];
    $csv_id     = $data['csv_file_id'];

    $base_dir = WP_CONTENT_DIR . "/seo-csv-data/{$website_id}/{$csv_id}/";
    $csv_file_path = $base_dir . "{$csv_id}.csv";

    // Delete CSV file if it exists
    if (file_exists($csv_file_path)) {
        unlink($csv_file_path);
        error_log("CSV file deleted: $csv_file_path");

        // Now try to delete the folder (only if empty)
        if (is_dir($base_dir) && count(scandir($base_dir)) === 2) { // only '.' and '..'
            rmdir($base_dir);
            error_log("CSV folder deleted: $base_dir");
        }

        return new WP_REST_Response([
            'status'  => 'success',
            'message' => "CSV file and folder deleted."
        ], 200);
    } else {
        error_log("CSV file not found for deletion: $csv_file_path");

        return new WP_REST_Response([
            'status'  => 'warning',
            'message' => "CSV file not found: $csv_file_path"
        ], 404);
    }
}

