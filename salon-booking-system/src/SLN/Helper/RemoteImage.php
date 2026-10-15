<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Imports an image from a remote URL into the media library.
 *
 * Image URLs come from REST clients and CSV imports, so they are untrusted:
 * the host must be public (no loopback, private, link-local or reserved
 * addresses, re-checked on every redirect), the body is capped in size, and
 * the downloaded bytes must decode as a raster image (JPEG, PNG, GIF, WebP).
 * The file name extension is derived from the detected image type, never from
 * the URL, and the file is stored through media_handle_sideload().
 */
class SLN_Helper_RemoteImage
{
    const MAX_BYTES     = 5242880;
    const MAX_REDIRECTS = 3;
    const TIMEOUT       = 15;

    private static $mimes = array(
        'jpg|jpeg|jpe' => 'image/jpeg',
        'png'          => 'image/png',
        'gif'          => 'image/gif',
        'webp'         => 'image/webp',
    );

    /**
     * @param string $url
     * @param int    $post_id Parent post for the attachment.
     *
     * @return int Attachment ID.
     * @throws Exception When the URL, the download or the file is rejected.
     */
    public static function sideload($url, $post_id = 0)
    {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $tmp = self::download((string) $url);

        $info = @getimagesize($tmp);
        $mime = is_array($info) && isset($info['mime']) ? $info['mime'] : '';
        $ext  = array_search($mime, self::$mimes, true);
        if (false === $ext) {
            wp_delete_file($tmp);
            throw new Exception(esc_html__('The remote file is not a supported image.', 'salon-booking-system'));
        }
        $ext = explode('|', $ext);
        $ext = $ext[0];

        $base = sanitize_file_name(pathinfo((string) wp_parse_url($url, PHP_URL_PATH), PATHINFO_FILENAME));
        $name = ('' !== $base ? $base : 'image') . '.' . $ext;

        $check = wp_check_filetype_and_ext($tmp, $name, self::$mimes);
        if (empty($check['type']) || $check['type'] !== $mime) {
            wp_delete_file($tmp);
            throw new Exception(esc_html__('The remote file is not a supported image.', 'salon-booking-system'));
        }

        $attachment_id = media_handle_sideload(array('name' => $name, 'tmp_name' => $tmp), (int) $post_id);
        if (is_wp_error($attachment_id)) {
            if (file_exists($tmp)) {
                wp_delete_file($tmp);
            }
            throw new Exception(esc_html($attachment_id->get_error_message()));
        }

        return (int) $attachment_id;
    }

    /**
     * Redirects are followed manually so every hop goes through assert_safe_url().
     *
     * @param string $url
     *
     * @return string Path of the downloaded temporary file.
     * @throws Exception
     */
    private static function download($url)
    {
        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            self::assert_safe_url($url);

            $tmp      = wp_tempnam('sln-remote-image');
            $response = wp_safe_remote_get($url, array(
                'timeout'             => self::TIMEOUT,
                'redirection'         => 0,
                'stream'              => true,
                'filename'            => $tmp,
                'limit_response_size' => self::MAX_BYTES,
            ));

            if (is_wp_error($response)) {
                wp_delete_file($tmp);
                throw new Exception(esc_html__('Could not download the image.', 'salon-booking-system'));
            }

            $code = (int) wp_remote_retrieve_response_code($response);
            if ($code >= 300 && $code < 400) {
                wp_delete_file($tmp);
                $location = wp_remote_retrieve_header($response, 'location');
                if (is_array($location)) {
                    $location = end($location);
                }
                if (!$location) {
                    break;
                }
                $url = WP_Http::make_absolute_url($location, $url);
                continue;
            }

            clearstatcache(true, $tmp);
            $size = file_exists($tmp) ? (int) filesize($tmp) : 0;
            if (200 !== $code || 0 === $size || $size >= self::MAX_BYTES) {
                wp_delete_file($tmp);
                throw new Exception(esc_html__('Could not download the image.', 'salon-booking-system'));
            }

            return $tmp;
        }

        throw new Exception(esc_html__('Could not download the image.', 'salon-booking-system'));
    }

    /**
     * @param string $url
     *
     * @throws Exception
     */
    private static function assert_safe_url($url)
    {
        $scheme = strtolower((string) wp_parse_url($url, PHP_URL_SCHEME));
        if (!in_array($scheme, array('http', 'https'), true) || !wp_http_validate_url($url)) {
            throw new Exception(esc_html__('Invalid image URL.', 'salon-booking-system'));
        }

        $host = strtolower(trim((string) wp_parse_url($url, PHP_URL_HOST), '[]'));
        if ($host === strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST))) {
            return;
        }

        // wp_http_validate_url() does not reject link-local (cloud metadata) or reserved ranges.
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? array($host) : gethostbynamel($host);
        if (empty($ips)) {
            throw new Exception(esc_html__('Invalid image URL.', 'salon-booking-system'));
        }
        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new Exception(esc_html__('Invalid image URL.', 'salon-booking-system'));
            }
        }
    }
}
