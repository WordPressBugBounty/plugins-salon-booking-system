<?php

/**
 * Cache Warmer Scheduler
 * 
 * Manages automatic cache warming using WordPress cron as a fallback
 * when external cron is not configured.
 * 
 * @since 10.30.12
 */
class SLN_Helper_CacheWarmerScheduler
{
    const CRON_HOOK = 'sln_cache_warmer_cron';
    const OPTION_EXTERNAL_CRON = 'sln_cache_warmer_external_cron';
    
    private $plugin;
    
    public function __construct(SLN_Plugin $plugin)
    {
        $this->plugin = $plugin;
        
        // Register cron hook
        add_action(self::CRON_HOOK, array($this, 'runCacheWarmer'));
        
        // Schedule cron if not already scheduled
        add_action('init', array($this, 'maybeScheduleCron'));
        
        // Clean up on plugin deactivation
        register_deactivation_hook(SLN_PLUGIN_BASENAME, array($this, 'deactivate'));
    }
    
    /**
     * Schedule WordPress cron if external cron is not configured
     */
    public function maybeScheduleCron()
    {
        // Check if external cron is configured
        $external_cron = get_option(self::OPTION_EXTERNAL_CRON, false);
        
        // If external cron is configured, don't use WordPress cron
        if ($external_cron) {
            // Unschedule WordPress cron if it exists
            if (wp_next_scheduled(self::CRON_HOOK)) {
                wp_clear_scheduled_hook(self::CRON_HOOK);
                
                if (SLN_Plugin::isDebugEnabled()) {
                    SLN_Plugin::addLog('[CacheWarmerScheduler] External cron detected, WordPress cron disabled');
                }
            }
            return;
        }
        
        // External cron not configured - use WordPress cron
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            // Schedule to run every 25 minutes (before 30-min cache expires)
            wp_schedule_event(time(), 'sln_25min', self::CRON_HOOK);
            
            if (SLN_Plugin::isDebugEnabled()) {
                SLN_Plugin::addLog('[CacheWarmerScheduler] WordPress cron scheduled (every 25 minutes)');
            }
        }
    }
    
    /**
     * Run the cache warmer
     */
    public function runCacheWarmer()
    {
        if (SLN_Plugin::isDebugEnabled()) {
            SLN_Plugin::addLog('[CacheWarmerScheduler] WordPress cron triggered');
        }
        
        try {
            $warmer = new SLN_Helper_CacheWarmer($this->plugin);
            $result = $warmer->warmCache();
            
            if (SLN_Plugin::isDebugEnabled()) {
                SLN_Plugin::addLog(sprintf(
                    '[CacheWarmerScheduler] Cache warmed via WordPress cron: %s',
                    json_encode($result)
                ));
            }
            
        } catch (Exception $e) {
            if (SLN_Plugin::isDebugEnabled()) {
                SLN_Plugin::addLog('[CacheWarmerScheduler] Error: ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Mark external cron as configured
     * This disables WordPress cron in favor of external cron
     */
    public static function markExternalCronConfigured()
    {
        update_option(self::OPTION_EXTERNAL_CRON, true);
        
        // Unschedule WordPress cron
        wp_clear_scheduled_hook(self::CRON_HOOK);
        
        if (SLN_Plugin::isDebugEnabled()) {
            SLN_Plugin::addLog('[CacheWarmerScheduler] External cron configured, WordPress cron disabled');
        }
    }
    
    /**
     * Mark external cron as not configured
     * This re-enables WordPress cron
     */
    public static function markExternalCronNotConfigured()
    {
        delete_option(self::OPTION_EXTERNAL_CRON);
        
        if (SLN_Plugin::isDebugEnabled()) {
            SLN_Plugin::addLog('[CacheWarmerScheduler] External cron removed, WordPress cron will be re-enabled');
        }
    }
    
    /**
     * Check if external cron is configured
     */
    public static function isExternalCronConfigured()
    {
        return (bool) get_option(self::OPTION_EXTERNAL_CRON, false);
    }
    
    /**
     * Get next scheduled run time
     */
    public static function getNextRun()
    {
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        
        if (!$timestamp) {
            return false;
        }
        
        return new DateTime('@' . $timestamp);
    }
    
    /**
     * Clean up on plugin deactivation
     */
    public function deactivate()
    {
        wp_clear_scheduled_hook(self::CRON_HOOK);
        delete_option(self::OPTION_EXTERNAL_CRON);
    }
}


