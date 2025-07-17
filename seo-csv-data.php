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
        colom_id BIGINT,
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

add_shortcode('seo-csv', 'show_seo_plugin');

function show_seo_plugin()
{

    return "This is seo csv plugin";
}

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

    <h2 style="margin-top:40px;">All Posts with Meta Title and Description</h2>

    <table class="widefat fixed striped">
        <thead>
            <tr>
                <th>Post Title</th>
                <th>Post URL</th>
                <th>Meta Title</th>
                <th>Meta Description</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $args = [
                'post_type' => 'post',
                'posts_per_page' => -1,
                'post_status' => 'publish',
            ];
            $query = new WP_Query($args);

            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    $post_id = get_the_ID();
                    $post_url = get_permalink($post_id);
                    $post_title = get_the_title();

                    $meta_title = '-';
                    $meta_desc = '-';

                    if ($_SESSION['seo_plugins']['yoast']) {
                        $meta_title = get_post_meta($post_id, '_yoast_wpseo_title', true);
                        $meta_desc = get_post_meta($post_id, '_yoast_wpseo_metadesc', true);
                    } elseif ($_SESSION['seo_plugins']['rankmath']) {
                        $meta_title = get_post_meta($post_id, 'rank_math_title', true);
                        $meta_desc = get_post_meta($post_id, 'rank_math_description', true);
                    } else {
                        error_log('SEO plugin not found ');
                    }

                    echo '<tr>';
                    echo '<td>' . esc_html($post_title) . '</td>';
                    echo '<td><a href="' . esc_url($post_url) . '" target="_blank">' . esc_url($post_url) . '</a></td>';
                    echo '<td>' . esc_html($meta_title ?: '—') . '</td>';
                    echo '<td>' . esc_html($meta_desc ?: '—') . '</td>';
                    echo '</tr>';
                }
                wp_reset_postdata();
            } else {
                echo '<tr><td colspan="4">No posts found.</td></tr>';
            }
            ?>
        </tbody>
    </table>
<?php
}

//////////////////////////// create a webhook end point ///////////

add_action('rest_api_init', function () {
    register_rest_route('seo-csv-data/v1', '/webhook', [
        'methods'  => 'POST',
        'callback' => 'seo_csv_handle_webhook',
        'permission_callback' => 'seo_csv_check_auth',
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
    global $wpdb;
    $table_name = $wpdb->prefix . 'seo_csv_logs';
    $data = $request->get_json_params();

    // Validate required fields
    $required_fields = ['csv_url', 'responce_hook_url', 'website_id', 'csv_file_id'];
    foreach ($required_fields as $field) {
        if (empty($data[$field])) {
            return new WP_REST_Response([
                'status'  => 'error',
                'message' => "Missing required field: $field",
            ], 400);
        }
    }

    // Extract input
    $csv_url = $data['csv_url'];
    $responce_hook_url = $data['responce_hook_url'];
    $website_id = $data['website_id'];
    $csv_id = $data['csv_file_id'];
    $session_seo_plugins = seo_detector_detect_seo_plugins();

    // Prepare directory
    $base_dir = WP_CONTENT_DIR . "/seo-csv-data/{$website_id}/{$csv_id}/";
    wp_mkdir_p($base_dir);
    $csv_file_path = $base_dir . "{$csv_id}.csv";

    // Fetch CSV content
    $csv_content = read_seo_sheet_csv($csv_url);
    if ($csv_content === false) {
        return new WP_Error('csv_error', 'Failed to download CSV file.', ['status' => 500]);
    }

    file_put_contents($csv_file_path, $csv_content);
    chmod($csv_file_path, 0644);

    // Respond to client immediately
    $response_data = ['status' => 'accepted', 'message' => 'Processing started.'];
    $response = new WP_REST_Response($response_data, 202);
    echo wp_json_encode($response);
    //     if (function_exists('fastcgi_finish_request')) {
    //         //fastcgi_finish_request();
    // //         $response_data = ['status' => 'completed', 'message' => 'Webhook sent.'];
    // // return new WP_REST_Response($response_data, 200);
    //     } else {
    //         ignore_user_abort(true);
    //         ob_start();
    //         header('Content-Type: application/json');
    //         header($_SERVER["SERVER_PROTOCOL"] . ' 202 Accepted');
    //         header('Content-Length: ' . ob_get_length());
    //         ob_end_flush();
    //         flush();
    //     }

    // Begin background processing
    $csv_rows = array_map('str_getcsv', explode("\n", trim($csv_content)));

    foreach ($csv_rows as $row) {
        if (empty($row[0])) continue;



        $colom_id = trim($row[0]);
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
            } else {
                error_log("No SEO plugin found for $post_url");
            }
        }

        // Insert into log table
        $inserted =   $wpdb->insert($table_name, [
            'post_url' => $post_url,
            'csv_url' => $csv_url,
            'meta_title' => $new_meta_title,
            'meta_description' => $new_meta_description,
            'status' => $status,
            'website_id' => $website_id_new,
            'colom_id' => $colom_id,
            'csv_file_id' => $csv_file_id,
            'created_at' => current_time('mysql'),
        ]);
    }

    // Regenerate CSV with status
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT colom_id, csv_file_id, website_id, post_url, meta_title, meta_description, status 
             FROM {$table_name} 
             WHERE csv_file_id = %d AND website_id = %d",
            $csv_id,
            $website_id_new
        ),
        ARRAY_A
    );


    $csv_final = "\"colom_id\",\"csv_file_id\",\"website_id\",\"page_url\",\"meta_title\",\"meta_description\",\"status\"\n";
    foreach ($rows as $row) {
        $escaped_row = array_map(function ($field) {
            $field = str_replace('"', '""', $field);
            return "\"$field\"";
        }, $row);
        $csv_final .= implode(",", $escaped_row) . "\n";
    }

    file_put_contents($csv_file_path, $csv_final);

    // Build public URL
    $relative_path = "wp-content/seo-csv-data/{$website_id}/{$csv_id}/{$csv_id}.csv";
    $public_url = site_url($relative_path);

    // Send webhook
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

    // Build CSV file path
    $base_dir = WP_CONTENT_DIR . "/seo-csv-data/{$website_id}/{$csv_id}/";
    $csv_file_path = $base_dir . "{$csv_id}.csv";

    // Delete file if exists
    if (file_exists($csv_file_path)) {
        unlink($csv_file_path);
        error_log("CSV file deleted: $csv_file_path");

        return new WP_REST_Response([
            'status'  => 'success',
            'message' => "CSV file deleted: $csv_file_path"
        ], 200);
    } else {
        error_log("CSV file not found for deletion: $csv_file_path");

        return new WP_REST_Response([
            'status'  => 'warning',
            'message' => "CSV file not found: $csv_file_path"
        ], 404);
    }
}
