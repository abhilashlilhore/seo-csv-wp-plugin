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

    $url = '*';///allow all origin 

    update_option('allow_access_origin', $url);
}

register_activation_hook(__FILE__, 'activetion_seo_csv_plugin');

function deactive_seo_csv_plugin()
{
    ///allow all origin
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



// ✅ Render settings page
function seo_detector_settings_page()
{
    $_SESSION['seo_plugins'] = seo_detector_detect_seo_plugins();

    if (isset($_POST['allowed_origin_url'])) {
        $url =trim($_POST['allowed_origin_url']);
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

                    // Yoast and Rank Math meta keys

                    $meta_title = '-';
                    $meta_desc = '-';

                    if ($_SESSION['seo_plugins']['yoast']) {
                        $meta_title = get_post_meta($post_id, '_yoast_wpseo_title', true);
                        $meta_desc = get_post_meta($post_id, '_yoast_wpseo_metadesc', true);
                    } elseif ($_SESSION['seo_plugins']['rankmath']) {
                        $meta_title = get_post_meta($post_id, 'rank_math_title', true);
                        $meta_desc = get_post_meta($post_id, 'rank_math_description', true);
                    }else{
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
    $csv = file_get_contents($url);
    if ($csv === false) {

        error_log("Error fetching CSV ");
        return 'Error fetching CSV';
        
    }

    $rows = array_map("str_getcsv", explode("\n", $csv));
    return $rows;
}


function seo_csv_handle_webhook($request)
{

    $_SESSION['seo_plugins'] = seo_detector_detect_seo_plugins();

    $data = $request->get_json_params();
    $json_data = json_encode($data);    

    $csv_url = $data['csv_url'];

    $data = read_seo_sheet_csv($csv_url); // No need for quotes inside function call

    $updated_data = [];

    foreach ($data as $value) {
        if (empty($value[0])) continue;

        $url = trim($value[0]);
        $new_meta_title = trim($value[1] ?? '');
        $new_meta_description = trim($value[2] ?? '');

        $post_id = url_to_postid($url);
        $status = 0;

        if ($post_id) {

            if ($_SESSION['seo_plugins']['yoast']) {
                update_post_meta($post_id, '_yoast_wpseo_title', $new_meta_title);
                update_post_meta($post_id, '_yoast_wpseo_metadesc', $new_meta_description);
                $status = 1;
                error_log("SEO data updated for $url ");

            } elseif ($_SESSION['seo_plugins']['rankmath']) {
                update_post_meta($post_id, 'rank_math_title', $new_meta_title);
                update_post_meta($post_id, 'rank_math_description', $new_meta_description);
                $status = 1;
                error_log("SEO data updated for $url ");
            }else{
                error_log('SEO plugin not found ');
            }            
            
        }

        $value['status'] = $status;
        $updated_data[] = $value;
    }

    $fp = fopen(plugin_dir_path(__FILE__) . '/output.csv', 'w');

    foreach ($updated_data as $row) {
        fputcsv($fp, $row);
    }

    fclose($fp);

    return new WP_REST_Response([
        'status'   => 'success',
        'message'  => 'Webhook data stored.',
        'received' => $updated_data,
    ], 200);
}
