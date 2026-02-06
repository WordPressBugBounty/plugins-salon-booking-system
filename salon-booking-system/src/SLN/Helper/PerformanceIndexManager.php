<?php

/**
 * Performance Index Manager
 * Handles installation, verification, and status of database indexes
 */
class SLN_Helper_PerformanceIndexManager
{
    /**
     * Check if indexes are installed
     * 
     * @return bool
     */
    public static function areIndexesInstalled()
    {
        return get_option('sln_performance_indexes_installed', false);
    }
    
    /**
     * Get installation status
     * 
     * @return string 'success', 'error', 'pending', or 'not_started'
     */
    public static function getStatus()
    {
        if (self::areIndexesInstalled()) {
            return 'success';
        }
        
        return get_option('sln_performance_indexes_status', 'not_started');
    }
    
    /**
     * Get status message
     * 
     * @return string
     */
    public static function getMessage()
    {
        return get_option('sln_performance_indexes_message', '');
    }
    
    /**
     * Verify indexes exist in database
     * 
     * @return array Array of index statuses
     */
    public static function verifyIndexes()
    {
        global $wpdb;
        
        $indexes = array(
            'idx_sln_booking_date',
            'idx_sln_booking_status',
            'idx_sln_booking_date_composite',
        );
        
        $results = array();
        
        foreach ($indexes as $index_name) {
            // Check postmeta indexes
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM information_schema.statistics 
                WHERE table_schema = %s 
                AND table_name = %s 
                AND index_name = %s",
                DB_NAME,
                $wpdb->postmeta,
                $index_name
            ));
            
            if ($exists === null) {
                // Try posts table
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM information_schema.statistics 
                    WHERE table_schema = %s 
                    AND table_name = %s 
                    AND index_name = %s",
                    DB_NAME,
                    $wpdb->posts,
                    $index_name
                ));
            }
            
            $results[$index_name] = ($exists > 0);
        }
        
        return $results;
    }
    
    /**
     * Install indexes manually
     * 
     * @return array Result with success status and message
     */
    public static function installIndexes()
    {
        global $wpdb;
        
        $results = array();
        $errors = array();
        
        try {
            // Index 1: Booking date
            $result = $wpdb->query("
                CREATE INDEX idx_sln_booking_date
                ON {$wpdb->postmeta} (meta_key(191), meta_value(191))
            ");
            $results['idx_sln_booking_date'] = ($result !== false);
            if ($result === false) {
                $errors[] = $wpdb->last_error;
            }
            
            // Index 2: Post type and status
            $result = $wpdb->query("
                CREATE INDEX idx_sln_booking_status
                ON {$wpdb->posts} (post_type(20), post_status(20))
            ");
            $results['idx_sln_booking_status'] = ($result !== false);
            if ($result === false) {
                $errors[] = $wpdb->last_error;
            }
            
            // Index 3: Composite index
            $result = $wpdb->query("
                CREATE INDEX idx_sln_booking_date_composite
                ON {$wpdb->postmeta} (meta_key(191), meta_value(191), post_id)
            ");
            $results['idx_sln_booking_date_composite'] = ($result !== false);
            if ($result === false) {
                $errors[] = $wpdb->last_error;
            }
            
            // Check if all succeeded
            $all_success = !in_array(false, $results, true);
            
            if ($all_success) {
                update_option('sln_performance_indexes_installed', true);
                update_option('sln_performance_indexes_status', 'success');
                update_option('sln_performance_indexes_message', __('Performance indexes installed successfully.', 'salon-booking-system'));
                
                return array(
                    'success' => true,
                    'message' => __('Performance indexes installed successfully!', 'salon-booking-system'),
                    'results' => $results,
                );
            } else {
                update_option('sln_performance_indexes_status', 'error');
                $error_message = __('Some indexes failed to install: ', 'salon-booking-system') . implode(', ', $errors);
                update_option('sln_performance_indexes_message', $error_message);
                
                return array(
                    'success' => false,
                    'message' => $error_message,
                    'results' => $results,
                    'errors' => $errors,
                );
            }
            
        } catch (Exception $e) {
            $error_message = __('Error installing indexes: ', 'salon-booking-system') . $e->getMessage();
            update_option('sln_performance_indexes_status', 'error');
            update_option('sln_performance_indexes_message', $error_message);
            
            return array(
                'success' => false,
                'message' => $error_message,
            );
        }
    }
    
    /**
     * Dismiss the notice
     */
    public static function dismissNotice()
    {
        update_option('sln_performance_indexes_notice_dismissed', true);
    }
    
    /**
     * Check if notice was dismissed
     * 
     * @return bool
     */
    public static function isNoticeDismissed()
    {
        return get_option('sln_performance_indexes_notice_dismissed', false);
    }
    
    /**
     * Reset status (for testing)
     */
    public static function resetStatus()
    {
        delete_option('sln_performance_indexes_installed');
        delete_option('sln_performance_indexes_status');
        delete_option('sln_performance_indexes_message');
        delete_option('sln_performance_indexes_notice_dismissed');
    }
}

