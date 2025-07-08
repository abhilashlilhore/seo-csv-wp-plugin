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

// remove this url from other auth jwt plugins
add_filter('jwt_auth_whitelist', function ($endpoints) {
    $endpoints[] = '/wp-json/seo_csv/v1/token';
    $endpoints[] = '/wp-json/seo-csv-data/v1/webhook';
    return $endpoints;
});
// remove this url from other auth jwt plugins


add_action('rest_api_init', function () {
    register_rest_route('seo_csv/v1', '/token', [
        'methods' => 'POST',
        'callback' => 'seo_csv_generate_token',
        'permission_callback' => '__return_true', // allow public
    ]);
});

function base64url_encode($data)
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function check_allowed_content_origin()
{
    $allowed_origin = get_option('allow_access_origin');

    if ($allowed_origin != '*') {
        $request_origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        if ($allowed_origin && $request_origin !== $allowed_origin) {
            return new WP_REST_Response(['error' => 'Forbidden: Origin not allowed'], 403);
        }
    }
}

function seo_csv_generate_token($request)
{

    check_allowed_content_origin();

    $params = $request->get_json_params();
    $username = $params['username'] ?? '';
    $password = $params['password'] ?? '';

    $user = wp_authenticate($username, $password);

    if (is_wp_error($user) || !user_can($user, 'administrator')) {
        return new WP_REST_Response(['error' => 'Invalid credentials or not admin'], 403);
    }

    $secret_key = 'savior-pro'; // Keep this private and strong
    $issuedAt = time();
    $expiration = $issuedAt + 3600; // 1 hour token

    $payload = [
        'user_id' => $user->ID,
        'iat' => $issuedAt,
        'exp' => $expiration,
    ];

    $header = base64url_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $body = base64url_encode(json_encode($payload));
    $signature = base64url_encode(hash_hmac('sha256', "$header.$body", $secret_key, true));

    $token = "$header.$body.$signature";

    return new WP_REST_Response([
        'token' => $token,
        'expires_in' => 3600,
        'user_id' => $user->ID,
    ]);
}


function base64url_decode($data)
{
    return base64_decode(strtr($data, '-_', '+/'));
}

function seo_csv_check_auth()
{
    $headers = getallheaders();
    $auth = $headers['Authorization'] ?? '';

    check_allowed_content_origin();

    if (!$auth || strpos($auth, 'Bearer ') !== 0) return false;

    $jwt = str_replace('Bearer ', '', $auth);
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) return false;

    list($header, $payload, $signature) = $parts;
    $secret_key = 'savior-pro';
    $expected_signature = base64url_encode(hash_hmac('sha256', "$header.$payload", $secret_key, true));

    if (!hash_equals($expected_signature, $signature)) return false;

    $decoded_payload = json_decode(base64url_decode($payload), true);
    if (!$decoded_payload || time() > $decoded_payload['exp']) return false;

    $user_id = $decoded_payload['user_id'];
    if (!get_userdata($user_id)) return false;

    wp_set_current_user($user_id);
    return true;
}
