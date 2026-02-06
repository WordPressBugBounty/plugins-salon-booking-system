<?php

/**
 * AJAX handler for manual performance index installation
 */
class SLN_Action_Ajax_InstallPerformanceIndexes extends SLN_Action_Ajax_Abstract
{
    public function execute()
    {
        // Security check
        if (!current_user_can('manage_options')) {
            return array(
                'success' => false,
                'message' => __('You do not have permission to perform this action.', 'salon-booking-system'),
            );
        }
        
        // Install indexes
        $result = SLN_Helper_PerformanceIndexManager::installIndexes();
        
        // Log the action
        if (class_exists('SLN_Plugin') && method_exists('SLN_Plugin', 'addLog')) {
            SLN_Plugin::addLog('[Performance Indexes] Manual installation triggered. Result: ' . json_encode($result));
        }
        
        return $result;
    }
}

