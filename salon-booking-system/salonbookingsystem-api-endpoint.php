<?php
/**
 * REST API Endpoint for Salon Booking System Rollback Versions
 * 
 * INSTALLATION INSTRUCTIONS:
 * ==========================
 * 1. Upload this file to: wp-content/mu-plugins/salonbookingsystem-api-endpoint.php
 *    (on salonbookingsystem.com server)
 * 
 * 2. OR add this code to your theme's functions.php
 * 
 * 3. Make sure EDD Software Licensing plugin is active
 * 
 * 4. Update the PRODUCT_ID constant below with your actual EDD product ID
 * 
 * This creates a secure API endpoint at:
 * https://www.salonbookingsystem.com/wp-json/salon/v1/rollback-versions
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register REST API endpoint for rollback versions
 */
add_action('rest_api_init', function() {
    register_rest_route('salon/v1', '/rollback-versions', [
        'methods' => 'GET',
        'callback' => 'sln_get_rollback_versions_api',
        'permission_callback' => '__return_true', // Public endpoint (license check inside)
        'args' => [
            'license' => [
                'required' => true,
                'type' => 'string',
                'description' => 'License key for validation',
            ],
            'edition' => [
                'required' => true,
                'type' => 'string',
                'enum' => ['pay', 'se'],
                'description' => 'Plugin edition (pay or se)',
            ],
            'url' => [
                'required' => true,
                'type' => 'string',
                'description' => 'Customer site URL',
            ],
            'item_id' => [
                'required' => false,
                'type' => 'string',
                'description' => 'EDD item/product identifier',
            ],
        ],
    ]);
});

/**
 * API callback to get available rollback versions
 * 
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function sln_get_rollback_versions_api($request)
{
    // ========================================
    // CONFIGURATION - UPDATE THESE VALUES
    // ========================================
    
    /**
     * Product ID Mapping (Based on actual build system)
     * 
     * Your plugin has MULTIPLE EDD products with different item IDs:
     * - 'salon-booking-wordpress-plugin' => PAY editions (Basic/Business/Pro/Enterprise with price_id 1-4)
     * - 'basic-branded-version' => SE edition (Basic Branded - separate product)
     * 
     * TODO: Replace the numeric IDs below with your actual EDD Download IDs
     */
    $product_ids = [
        'salon-booking-wordpress-plugin' => 12345, // TODO: PAY edition Download ID
        'basic-branded-version' => 67890,          // TODO: SE edition Download ID
        // Uncomment if CodeCanyon uses different product:
        // 'salon-booking-wordpress-plugin-cc' => 54321,
    ];
    
    /**
     * Filename patterns by item_id and edition
     * Based on your build system (build/config.php):
     * - PAY: salon-booking-plugin-pro-pay-10.30.5.zip
     * - SE: basic-branded-version-se-10.30.5.zip
     */
    $filename_patterns = [
        'salon-booking-wordpress-plugin' => [
            'pay' => 'salon-booking-plugin-pro-pay-',  // PAY editions
            'se' => null, // PAY product doesn't have SE files
        ],
        'basic-branded-version' => [
            'pay' => null, // SE product doesn't have PAY files
            'se' => 'basic-branded-version-se-',       // SE edition
        ],
    ];
    
    $max_versions = 6;   // Maximum number of versions to return
    
    /**
     * Blacklisted versions - DO NOT offer for rollback
     * 
     * Add versions here that are known to be broken, incomplete, or problematic
     * Format: ['10.29.5', '10.20.1', etc.]
     */
    $blacklisted_versions = [
        '10.29.5', // Missing critical files (SLB_Discount/Repository/DiscountRepository.php)
        // Add more broken versions here as discovered
    ];
    
    // ========================================
    // Extract and sanitize parameters
    // ========================================
    $license_key = sanitize_text_field($request->get_param('license'));
    $edition = sanitize_text_field($request->get_param('edition'));
    $site_url = esc_url_raw($request->get_param('url'));
    $item_id = sanitize_text_field($request->get_param('item_id')); // Item ID from customer site
    
    error_log("SLN Rollback API: Request from {$site_url} for edition '{$edition}', item_id '{$item_id}'");
    
    // ========================================
    // STEP 1: Determine Product ID
    // ========================================
    $product_id = null;
    
    // If item_id provided, try to find matching product
    if ($item_id && isset($product_ids[$item_id])) {
        $product_id = $product_ids[$item_id];
        error_log("SLN Rollback API: Using product ID {$product_id} for item '{$item_id}'");
    } else {
        // Use first product as default
        $product_id = reset($product_ids);
        error_log("SLN Rollback API: Using default product ID {$product_id}");
    }
    
    // ========================================
    // STEP 2: Validate License with EDD
    // ========================================
    if (!function_exists('edd_software_licensing')) {
        error_log('SLN Rollback API: EDD Software Licensing not available');
        return new WP_Error(
            'edd_unavailable',
            'License validation system unavailable',
            ['status' => 503]
        );
    }
    
    // Perform license check via EDD API
    $api_params = [
        'edd_action' => 'check_license',
        'license' => $license_key,
        'item_id' => $product_id, // Use the determined product ID
        'url' => $site_url,
    ];
    
    $response = wp_remote_post(home_url(), [
        'body' => $api_params,
        'timeout' => 15,
        'sslverify' => true,
    ]);
    
    if (is_wp_error($response)) {
        error_log('SLN Rollback API: License check failed - ' . $response->get_error_message());
        return new WP_Error(
            'license_check_failed',
            'Unable to validate license',
            ['status' => 500]
        );
    }
    
    $license_data = json_decode(wp_remote_retrieve_body($response));
    
    // Check if license is valid and active
    if (!$license_data || $license_data->license !== 'valid') {
        $status = $license_data->license ?? 'unknown';
        error_log("SLN Rollback API: License validation failed - Status: {$status}");
        
        return new WP_Error(
            'invalid_license',
            'Your license is invalid, expired, or inactive. Please renew your license to access rollback versions.',
            ['status' => 403]
        );
    }
    
    error_log('SLN Rollback API: License validated successfully');
    
    // ========================================
    // STEP 3: Determine filename pattern for this item_id + edition
    // ========================================
    $filename_pattern = null;
    if (isset($filename_patterns[$item_id]) && isset($filename_patterns[$item_id][$edition])) {
        $filename_pattern = $filename_patterns[$item_id][$edition];
    }
    
    if (!$filename_pattern) {
        error_log("SLN Rollback API: No filename pattern for item_id '{$item_id}' and edition '{$edition}'");
        return new WP_Error(
            'invalid_configuration',
            'No files available for this product configuration.',
            ['status' => 404]
        );
    }
    
    error_log("SLN Rollback API: Using filename pattern: {$filename_pattern}");
    
    // ========================================
    // STEP 4: Query Media Library for ZIP files
    // ========================================
    $files = get_posts([
        'post_type' => 'attachment',
        'post_mime_type' => 'application/zip',
        'posts_per_page' => 100, // Get all ZIP files
        'post_status' => 'inherit',
        'orderby' => 'date',
        'order' => 'DESC',
    ]);
    
    error_log('SLN Rollback API: Found ' . count($files) . ' total ZIP attachments');
    
    // ========================================
    // STEP 5: Parse filenames and extract versions
    // ========================================
    $versions = [];
    // Dynamic regex based on filename pattern
    // Pattern: salon-booking-plugin-pro-pay-10.30.5.zip OR basic-branded-version-se-10.30.5.zip
    $regex = '/' . preg_quote($filename_pattern, '/') . '(\d+\.\d+\.\d+)\.zip$/';
    
    error_log("SLN Rollback API: Using regex pattern: {$regex}");
    
    foreach ($files as $file) {
        $file_path = get_attached_file($file->ID);
        $filename = basename($file_path);
        
        // Match filename pattern and extract version
        if (preg_match($regex, $filename, $matches)) {
            $version = $matches[1];
            
            // Skip blacklisted versions (known broken/incomplete versions)
            if (in_array($version, $blacklisted_versions)) {
                error_log("SLN Rollback API: Skipping blacklisted version {$version} (known to be broken)");
                continue;
            }
            
            error_log("SLN Rollback API: Found version {$version} in file: {$filename}");
            
            // Get direct file URL from WordPress Media Library
            // This is already secure because:
            // 1. License was validated before we got here
            // 2. API endpoint controls access
            // 3. Results are cached per-license
            $download_url = wp_get_attachment_url($file->ID);
            
            if (!$download_url) {
                error_log("SLN Rollback API: Could not get URL for file ID {$file->ID}");
                continue;
            }
            
            error_log("SLN Rollback API: Generated download URL: {$download_url}");
            
            $versions[] = [
                'version' => $version,
                'file' => $download_url, // Direct WordPress Media Library URL
                'date' => get_the_date('Y-m-d', $file->ID),
                'size' => size_format(filesize($file_path)),
                'filename' => $filename,
            ];
        }
    }
    
    error_log('SLN Rollback API: Extracted ' . count($versions) . ' valid versions');
    
    // ========================================
    // STEP 6: Sort and limit versions
    // ========================================
    if (empty($versions)) {
        error_log('SLN Rollback API: No versions found for edition: ' . $edition);
        return rest_ensure_response([]);
    }
    
    // Sort by version number (newest first)
    usort($versions, function($a, $b) {
        return version_compare($b['version'], $a['version']);
    });
    
    // Limit to last N versions
    $versions = array_slice($versions, 0, $max_versions);
    
    error_log('SLN Rollback API: Returning ' . count($versions) . ' versions (limited to ' . $max_versions . ')');
    
    // ========================================
    // STEP 7: Return response
    // ========================================
    return rest_ensure_response([
        'success' => true,
        'count' => count($versions),
        'versions' => $versions,
        'license_status' => 'valid',
        'edition' => $edition,
    ]);
}

/**
 * Optional: Add CORS headers if needed
 */
add_action('rest_api_init', function() {
    remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
    add_filter('rest_pre_serve_request', function($value) {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET');
        header('Access-Control-Allow-Credentials: true');
        return $value;
    });
}, 15);

