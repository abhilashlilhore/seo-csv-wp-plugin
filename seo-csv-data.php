<?php

/**
 * Plugin Name: CSV SEO Data
 * Description: This plugin update meta data and title
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

    ////////////we can create table

    // global $wpdb, $table_prefix;

    // $wpdb_table = $table_prefix . '_seo_csv';

    // $query = "CREATE TABLE IF NOT EXISTS $wpdb_table ( id INT AUTO_INCREMENT PRIMARY KEY,
    // data TEXT,
    // url VARCHAR(2000)) ";

    // $wpdb->query($query);
    // $query = "INSERT INTO $wpdb_table (data, url)VALUES (" . '{"name":"Test User","email":"test@example.com"}' . "," . 'https://fake-source.com/webhook' . ")";

    // $wpdb->query($query);

    $url = '*';

    update_option('allow_access_origin', $url);
}

register_activation_hook(__FILE__, 'activetion_seo_csv_plugin');

function deactive_seo_csv_plugin()
{

    // global $wpdb, $table_prefix;

    // $wpdb_table = $table_prefix . '_seo_csv';
    // $query = "TRUNCATE TABLE $wpdb_table ";
    // $wpdb->query($query);
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
        'CSV-SEO Settings',        // Page Title
        'CSV-SEO settings',        // Menu Title
        'manage_options',          // Capability
        'csv-seo-settings',       // Menu Slug
        'seo_detector_settings_page'   // Callback Function
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
        $url = esc_url_raw(trim($_POST['allowed_origin_url']));
        update_option('allow_access_origin', $url);
        echo '<div class="updated"><p>Origin URL saved!</p></div>';
    }

    $saved_url = esc_url(get_option('allow_access_origin', ''));
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
        'permission_callback' => 'seo_csv_check_auth', // Allow public access
    ]);
});

function read_seo_sheet_csv($url)
{
    $csv = file_get_contents($url);
    if ($csv === false) {
        return 'Error fetching CSV';
    }

    $rows = array_map("str_getcsv", explode("\n", $csv));
    return $rows;
}


function seo_csv_handle_webhook($request)
{

    global $wpdb, $table_prefix;
    $wpdb_table = $table_prefix . '_seo_csv';


    $data = $request->get_json_params();
    $json_data = json_encode($data);

    //$array_res=json_decode($data);

    $csv_url = $data['csv_url'];

    $data = read_seo_sheet_csv($csv_url); // No need for quotes inside function call

    $updated_data = [];

    foreach ($data as $value) {
        if (empty($value[0])) continue; // Skip if URL is empty

        $url = trim($value[0]);
        $new_meta_title = trim($value[1] ?? '');
        $new_meta_description = trim($value[2] ?? '');

        $post_id = url_to_postid($url);
        $status = 0;

        if ($post_id) {

            if ($_SESSION['seo_plugins']['yoast']) {
                update_post_meta($post_id, '_yoast_wpseo_title', $new_meta_title);
                $res = update_post_meta($post_id, '_yoast_wpseo_metadesc', $new_meta_description);
            } elseif ($_SESSION['seo_plugins']['rankmath']) {
                update_post_meta($post_id, 'rank_math_title', $new_meta_title);
                $res = update_post_meta($post_id, 'rank_math_description', $new_meta_description);
            }

            // update_post_meta($post_id, '_yoast_wpseo_title', $new_meta_title);
            // $res = update_post_meta($post_id, '_yoast_wpseo_metadesc', $new_meta_description);

            // error_log($res);

            // if ($res) {
            $status = 1;
            // }
        }

        // Add status as a 4th column in the same row
        $value['status'] = $status;
        $updated_data[] = $value;
    }


    $url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'unknown';


    $wpdb->insert($wpdb_table, [
        'data' => $json_data,
        'url'  => $url,
    ]);


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
// Only run if Yoast SEO is active

// if (defined('WPSEO_VERSION')) {

   
//https://docs.google.com/spreadsheets/d/1x2kzy_iazOkNWoBSWchwhAqY4NDLgL5xLtbCmJp0JXk/edit?usp=sharing


    // add_filter('wpseo_title', 'myplugin_override_yoast_title');

    // function myplugin_override_yoast_title($title)
    // {

    //      error_log('process started .....');
    //     // Example: change title for Page ID 7
    //     if (is_page(3)) {
    //         return 'My Custom Meta Title for Page 7';
    //     } else {
    //         return 'My Custom Meta Title for Page 7 fail ';
    //     }

    //     // Example: change title for a post with slug 'about'
    //     if (is_singular('post') && get_post_field('post_name', get_the_ID()) === 'about') {
    //         return 'Custom About Page Title';
    //     } else {
    //         return 'Custom About Page Title fail';
    //     }

    //     return $title; // Default title from Yoast
    // }
// }


// function prefix_filter_description_example( $description ) {

//      error_log('process started .....');

//   if ( is_page( 3 ) ) {
//     $description = 'My custom custom meta description';
//   }else{
//     $description = 'My custom custom meta description `12333';

//   }
//   return $description;
// }
// add_filter( 'wpseo_metadesc', 'prefix_filter_description_example' );


// add_action('rest_api_init', function () {
//     register_rest_route('seo-csv-data/v1', '/static-data', [
//         'methods'  => 'GET',
//         'callback' => 'myplugin_get_static_data',
//         'permission_callback' => 'myplugin_check_jwt_auth', // Publicly accessible
//     ]);
// });

// function myplugin_get_static_data($request) {
//     $data = [
//         'name' => 'Abhilash',
//         'email' => 'abhilash@example.com',
//         'message' => 'This is a static response from the WordPress REST API.',
//     ];

//     return new WP_REST_Response($data, 200);
// }
// function myplugin_check_jwt_auth() {
//     return is_user_logged_in(); // This works only if JWT plugin has authenticated the user
// }
