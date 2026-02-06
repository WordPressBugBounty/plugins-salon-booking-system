<?php

/**
 * AJAX handler for dismissing performance indexes notice
 */
class SLN_Action_Ajax_DismissPerformanceIndexesNotice extends SLN_Action_Ajax_Abstract
{
    public function execute()
    {
        SLN_Helper_PerformanceIndexManager::dismissNotice();
        
        return array(
            'success' => true,
        );
    }
}

