<?php

/**
 * AJAX Handler for External Cache Warmer
 * 
 * Provides a public AJAX endpoint for external cron services to warm the booking cache.
 * This eliminates the need for a standalone sln-cache-warmer.php file in the website root.
 * 
 * Usage:
 * https://yoursite.com/wp-admin/admin-ajax.php?action=sln_cache_warmer&key=YOUR_SECRET_KEY
 * 
 * @since 10.30.11
 */
class SLN_Action_Ajax_CacheWarmer
{
    private $plugin;

    public function __construct(SLN_Plugin $plugin)
    {
        $this->plugin = $plugin;
        
        // Register public AJAX endpoint (accessible without authentication)
        add_action('wp_ajax_nopriv_sln_cache_warmer', array($this, 'execute'));
        add_action('wp_ajax_sln_cache_warmer', array($this, 'execute'));
        
        // Prevent WordPress from adding extra output (admin bar, etc.) for this AJAX request
        if (isset($_GET['action']) && $_GET['action'] === 'sln_cache_warmer') {
            // Disable admin bar
            add_filter('show_admin_bar', '__return_false');
            // Prevent WordPress from loading unnecessary scripts
            remove_action('wp_head', '_admin_bar_bump_cb');
        }
    }

    /**
     * Execute cache warming
     * 
     * Verifies security key and warms the booking cache
     */
    public function execute()
    {
        // Register shutdown handler to catch fatal errors
        register_shutdown_function(array($this, 'handleFatalError'));
        
        // Suppress PHP warnings/notices to prevent "output too large" errors in cron jobs
        error_reporting(E_ERROR | E_PARSE | E_CORE_ERROR);
        ini_set('display_errors', 0);
        
        // Clean all existing output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Start fresh output buffering to catch any stray output
        ob_start();
        
        $start_time = microtime(true);
        
        try {
            // Verify security key
            $provided_key = isset($_GET['key']) ? sanitize_text_field($_GET['key']) : '';
            $stored_key = get_option('sln_cache_warmer_key');
            
            if (empty($stored_key)) {
                // Generate key if it doesn't exist
                $stored_key = wp_generate_password(32, false);
                update_option('sln_cache_warmer_key', $stored_key);
            }
            
            if ($provided_key !== $stored_key) {
                $this->sendJsonResponse(array(
                    'success' => false,
                    'error' => 'Invalid security key',
                    'hint' => 'Get your key from: WordPress Admin → Salon Booking → Settings → Performance'
                ), 403);
                return;
            }
            
            // Instantiate cache warmer
            if (!class_exists('SLN_Helper_CacheWarmer')) {
                $this->sendJsonResponse(array(
                    'success' => false,
                    'error' => 'CacheWarmer class not found'
                ), 500);
                return;
            }
            
            $warmer = new SLN_Helper_CacheWarmer($this->plugin);
            
            // Check if method exists
            if (!method_exists($warmer, 'warmCheckDateAltCacheForAllServices')) {
                $this->sendJsonResponse(array(
                    'success' => false,
                    'error' => 'Method warmCheckDateAltCacheForAllServices not found'
                ), 500);
                return;
            }
            
            // Warm the cache
            try {
                $result = $warmer->warmCheckDateAltCacheForAllServices();
            } catch (Exception $warmException) {
                $this->sendJsonResponse(array(
                    'success' => false,
                    'error' => 'Cache warming failed: ' . $warmException->getMessage(),
                    'trace' => $warmException->getTraceAsString(),
                    'duration_ms' => round((microtime(true) - $start_time) * 1000, 2)
                ), 500);
                return;
            }
            
            // Mark that external cron is configured
            if (class_exists('SLN_Helper_CacheWarmerScheduler')) {
                SLN_Helper_CacheWarmerScheduler::markExternalCronConfigured();
            }
            
            // Calculate duration
            $duration_ms = (microtime(true) - $start_time) * 1000;
            
            // Get cache stats
            $stats = $warmer->getStats();
            
            // Send success response
            $this->sendJsonResponse(array(
                'success' => true,
                'mode' => 'alternative',
                'caches_warmed' => $result ? 1 : 0,
                'duration_ms' => round($duration_ms, 2),
                'timestamp' => current_time('Y-m-d H:i:s'),
                'stats' => $stats
            ));
            
        } catch (Exception $e) {
            $this->sendJsonResponse(array(
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => isset($e) ? $e->getTraceAsString() : 'No trace available',
                'duration_ms' => round((microtime(true) - $start_time) * 1000, 2)
            ), 500);
        }
    }
    
    /**
     * Handle fatal PHP errors
     */
    public function handleFatalError()
    {
        $error = error_get_last();
        
        if ($error && in_array($error['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR))) {
            // Clean any buffered output
            while (ob_get_level()) {
                ob_end_clean();
            }
            
            // Send error response
            header('Content-Type: application/json');
            echo wp_json_encode(array(
                'success' => false,
                'error' => 'PHP Fatal Error: ' . $error['message'],
                'file' => $error['file'],
                'line' => $error['line'],
                'type' => $error['type']
            ));
            exit;
        }
    }
    
    /**
     * Send JSON response and exit
     * 
     * @param array $data Response data
     * @param int $status_code HTTP status code
     */
    private function sendJsonResponse($data, $status_code = 200)
    {
        // Clean any buffered output
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // Set HTTP status code
        http_response_code($status_code);
        
        // Send JSON response
        header('Content-Type: application/json');
        echo wp_json_encode($data);
        exit;
    }
    
    /**
     * Get the cache warmer security key
     * 
     * @return string
     */
    public static function getSecurityKey()
    {
        $key = get_option('sln_cache_warmer_key');
        
        if (empty($key)) {
            $key = wp_generate_password(32, false);
            update_option('sln_cache_warmer_key', $key);
        }
        
        return $key;
    }
    
    /**
     * Get the cache warmer URL
     * 
     * @return string
     */
    public static function getCacheWarmerUrl()
    {
        $key = self::getSecurityKey();
        return admin_url('admin-ajax.php') . '?action=sln_cache_warmer&key=' . $key;
    }
}

