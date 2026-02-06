<?php
/**
 * Database update for version 10.30.11
 * Installs performance optimization indexes
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

$indexes_installed = get_option('sln_performance_indexes_installed', false);

// Only install if not already done
if (!$indexes_installed) {
    $results = array();
    
    // Index 1: Booking date for availability queries
    $index_name = 'idx_sln_booking_date';
    $result = $wpdb->query("
        CREATE INDEX {$index_name}
        ON {$wpdb->postmeta} (meta_key(191), meta_value(191))
    ");
    $results[] = array('index' => $index_name, 'success' => ($result !== false));
    
    // Index 2: Post type and status for booking queries
    $index_name = 'idx_sln_booking_status';
    $result = $wpdb->query("
        CREATE INDEX {$index_name}
        ON {$wpdb->posts} (post_type(20), post_status(20))
    ");
    $results[] = array('index' => $index_name, 'success' => ($result !== false));
    
    // Index 3: Composite index for date range queries
    $index_name = 'idx_sln_booking_date_composite';
    $result = $wpdb->query("
        CREATE INDEX {$index_name}
        ON {$wpdb->postmeta} (meta_key(191), meta_value(191), post_id)
    ");
    $results[] = array('index' => $index_name, 'success' => ($result !== false));
    
    // Check if all indexes were created successfully
    $all_success = true;
    foreach ($results as $result) {
        if (!$result['success']) {
            $all_success = false;
            break;
        }
    }
    
    if ($all_success) {
        update_option('sln_performance_indexes_installed', true);
        update_option('sln_performance_indexes_status', 'success');
        update_option('sln_performance_indexes_message', __('Performance indexes installed successfully.', 'salon-booking-system'));
    } else {
        update_option('sln_performance_indexes_status', 'error');
        update_option('sln_performance_indexes_message', __('Some indexes failed to install. Please try manual installation.', 'salon-booking-system'));
    }
    
    // Log the results
    if (class_exists('SLN_Plugin') && method_exists('SLN_Plugin', 'addLog')) {
        SLN_Plugin::addLog('[Performance Indexes] Installation results: ' . json_encode($results));
    }
}

