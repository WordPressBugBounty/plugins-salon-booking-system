<?php

/**
 * Cache Warmer Helper
 * 
 * Warms the intervals cache in the background to prevent 40-second delays
 * for users. Designed to be called by external cron service every 14 minutes.
 * 
 * @since 10.30.11
 */
class SLN_Helper_CacheWarmer
{
    private $plugin;
    
    public function __construct(SLN_Plugin $plugin)
    {
        $this->plugin = $plugin;
    }
    
    /**
     * Warm the intervals cache
     * 
     * This method calculates intervals for the booking form and stores them
     * in transient cache. Should be called every 14 minutes by external cron
     * to ensure cache is always warm when users visit.
     * 
     * For alternative booking flow, we warm cache for all active services
     * since each service combination has its own cache key.
     * 
     * @return array Status information
     */
    public function warmCache()
    {
        $start_time = microtime(true);
        $results = array();
        
        try {
            $settings = $this->plugin->getSettings();
            $alt_order = $settings->get('form_steps_alt_order');
            
            if ($alt_order) {
                // Alternative booking flow - warm CheckDateAlt cache for all services
                $results['mode'] = 'alternative';
                $results['caches_warmed'] = $this->warmCheckDateAltCacheForAllServices();
            } else {
                // Standard booking flow - warm DateStep cache
                $results['mode'] = 'standard';
                $results['cache_warmed'] = $this->warmDateStepCache();
                $results['caches_warmed'] = 1;
            }
            
            $duration = (microtime(true) - $start_time) * 1000;
            $results['success'] = true;
            $results['duration_ms'] = round($duration, 2);
            $results['timestamp'] = date('Y-m-d H:i:s');
            
            if (SLN_Plugin::isDebugEnabled()) {
                SLN_Plugin::addLog(sprintf(
                    '[CacheWarmer] Cache warmed successfully in %.2fms (mode: %s, caches: %d)',
                    $duration,
                    $results['mode'],
                    $results['caches_warmed']
                ));
            }
            
        } catch (Exception $e) {
            $results['success'] = false;
            $results['error'] = $e->getMessage();
            
            if (SLN_Plugin::isDebugEnabled()) {
                SLN_Plugin::addLog('[CacheWarmer] ERROR: ' . $e->getMessage());
            }
        }
        
        return $results;
    }
    
    /**
     * Warm cache for alternative booking flow for all active services
     * 
     * In alternative flow, users select services BEFORE date/time, so each
     * service combination has its own cache key. We warm cache for all
     * published services to cover most user scenarios.
     * 
     * OPTIMIZED: Only warms cache for "no service selected" scenario,
     * which is the most common (initial page load before service selection).
     * Individual service caches will be warmed on-demand by actual users.
     * 
     * @return int Number of caches warmed
     */
    public function warmCheckDateAltCacheForAllServices()
    {
        // INCREMENTAL CACHE WARMING
        // Warms as many services as possible within 25 seconds
        // Saves progress and continues in next cron run
        // Prioritizes most popular services (most bookings)
        
        $start_time = microtime(true);
        $max_execution_time = 25; // seconds (safety margin for 30s timeout)
        $warmed_count = 0;
        $date = new SLN_DateTime('now');
        
        try {
            // Get all primary services (not secondary, not hidden on frontend)
            $repo = $this->plugin->getRepository(SLN_Plugin::POST_TYPE_SERVICE);
            $all_services = $repo->getAllPrimary();
            $active_services = array();
            
            foreach ($all_services as $service) {
                if (!$service->isHideOnFrontend()) {
                    $active_services[] = $service;
                }
            }
            
            if (empty($active_services)) {
                if (SLN_Plugin::isDebugEnabled()) {
                    SLN_Plugin::addLog('[CacheWarmer] No active services found');
                }
                return 0;
            }
            
            // Sort services by popularity (most bookings first)
            $services_sorted = $this->sortServicesByPopularity($active_services);
            
            // Get last warmed service index (for incremental warming)
            $last_index = get_transient('sln_cache_warmer_last_index');
            $last_index = ($last_index !== false) ? (int)$last_index : 0;
            
            // Start from where we left off
            $total_services = count($services_sorted);
            $services_to_warm = array_slice($services_sorted, $last_index);
            
            if (SLN_Plugin::isDebugEnabled()) {
                SLN_Plugin::addLog(sprintf(
                    '[CacheWarmer] Incremental warming: Starting from service %d/%d (max 25 seconds)',
                    $last_index + 1,
                    $total_services
                ));
            }
            
            // Warm cache for each service until time limit reached
            $current_index = $last_index;
            foreach ($services_to_warm as $service) {
                // Check if we're approaching time limit
                $elapsed = microtime(true) - $start_time;
                if ($elapsed > $max_execution_time) {
                    // Save progress and exit gracefully
                    set_transient('sln_cache_warmer_last_index', $current_index, 3600);
                    
                    if (SLN_Plugin::isDebugEnabled()) {
                        SLN_Plugin::addLog(sprintf(
                            '[CacheWarmer] Time limit reached (%.1fs). Warmed %d services. Will continue in next run.',
                            $elapsed,
                            $warmed_count
                        ));
                    }
                    
                    break;
                }
                try {
                    $bb = $this->plugin->getBookingBuilder();
                    $bb->clear();
                    
                    // Add the service with attendant set to false (matches "Choose assistant for me")
                    // This is critical: when users select "Choose assistant for me", 
                    // AttendantAltStep.php sets attendants to false (line 83)
                    // We must match this exactly for cache key consistency
                    $bb->setServicesAndAttendants([
                        $service->getId() => false
                    ]);
                    
                    // DEBUG: Log booking builder state
                    $attendants_state = $bb->getAttendantsIds();
                    $attendants_debug = empty($attendants_state) ? '(empty array)' : implode(',', $attendants_state);
                    SLN_Plugin::addLog(sprintf(
                        "[CacheWarmer] Service: %s (ID: %s) | Attendants: %s | Cache key will match 'Choose assistant for me'",
                        $service->getName(),
                        $service->getId(),
                        $attendants_debug
                    ));
                    
                    // Create CheckDateAlt object
                    $obj = new SLN_Action_Ajax_CheckDateAlt($this->plugin);
                    $obj->setDate(SLN_Func::filter($date, 'date'));
                    $obj->setTime(SLN_Func::filter($date, 'time'));
                    
                    // Get cache key using reflection to see what key is being created
                    $reflection = new ReflectionClass($obj);
                    $method = $reflection->getMethod('getIntervalsCacheKey');
                    $method->setAccessible(true);
                    $cache_key = $method->invoke($obj, $bb, '');
                    
                    // DEBUG: Log cache key to WordPress debug log
                    SLN_Plugin::addLog(sprintf(
                        "[CacheWarmer] Cache key created: %s",
                        $cache_key
                    ));
                    
                    // Warm cache for this service
                    $intervalsArray = $obj->getIntervalsArray('');
                    
                    if (!empty($intervalsArray)) {
                        $warmed_count++;
                        $current_index++;
                        
                        // Verify cache was actually saved
                        $cached_check = get_transient($cache_key);
                        $cache_status = ($cached_check !== false) ? 'SAVED' : 'FAILED TO SAVE';
                        
                        SLN_Plugin::addLog(sprintf(
                            '[CacheWarmer] ✓ Service: %s | Cache status: %s | Intervals count: %d | Progress: %d/%d',
                            $service->getName(),
                            $cache_status,
                            count($intervalsArray),
                            $current_index,
                            $total_services
                        ));
                        
                        if (SLN_Plugin::isDebugEnabled()) {
                            SLN_Plugin::addLog(sprintf(
                                '[CacheWarmer] ✓ Warmed cache for service: %s (ID: %d) | Status: %s | Progress: %d/%d',
                                $service->getName(),
                                $service->getId(),
                                $cache_status,
                                $current_index,
                                $total_services
                            ));
                        }
                    }
                    
                } catch (Exception $e) {
                    $current_index++;
                    
                    if (SLN_Plugin::isDebugEnabled()) {
                        SLN_Plugin::addLog(sprintf(
                            '[CacheWarmer] ✗ Failed to warm cache for service %s: %s',
                            $service->getName(),
                            $e->getMessage()
                        ));
                    }
                    // Continue with next service
                }
            }
            
            // Check if we completed all services
            if ($current_index >= $total_services) {
                // Reset to start for next cycle
                delete_transient('sln_cache_warmer_last_index');
                
                if (SLN_Plugin::isDebugEnabled()) {
                    SLN_Plugin::addLog(sprintf(
                        '[CacheWarmer] ✓ Full cycle completed! All %d services warmed. Resetting to start.',
                        $total_services
                    ));
                }
            } else {
                // Save progress for next run
                set_transient('sln_cache_warmer_last_index', $current_index, 3600);
                
                if (SLN_Plugin::isDebugEnabled()) {
                    SLN_Plugin::addLog(sprintf(
                        '[CacheWarmer] Progress saved: %d/%d services warmed. Will continue in next run.',
                        $current_index,
                        $total_services
                    ));
                }
            }
            
            $total_duration = microtime(true) - $start_time;
            if (SLN_Plugin::isDebugEnabled()) {
                SLN_Plugin::addLog(sprintf(
                    '[CacheWarmer] Completed in %.1fs: %d services warmed',
                    $total_duration,
                    $warmed_count
                ));
            }
            
        } catch (Exception $e) {
            if (SLN_Plugin::isDebugEnabled()) {
                SLN_Plugin::addLog('[CacheWarmer] Critical error: ' . $e->getMessage());
            }
        }
        
        return $warmed_count;
    }
    
    /**
     * Sort services by popularity (most bookings first)
     * 
     * This ensures the most frequently booked services are always cached,
     * even if we don't complete the full warming cycle.
     * 
     * @param array $services Array of SLN_Wrapper_Service objects
     * @return array Sorted array of services
     */
    private function sortServicesByPopularity($services)
    {
        global $wpdb;
        
        // Get booking counts for each service
        $service_counts = array();
        
        foreach ($services as $service) {
            $service_id = $service->getId();
            
            // Count bookings for this service in the last 90 days
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT p.ID)
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = %s
                AND p.post_status IN ('publish', 'paid', 'pay_later', 'confirmed')
                AND pm.meta_key = '_sln_booking_services'
                AND pm.meta_value LIKE %s
                AND p.post_date >= DATE_SUB(NOW(), INTERVAL 90 DAY)",
                SLN_Plugin::POST_TYPE_BOOKING,
                '%' . $wpdb->esc_like('"' . $service_id . '"') . '%'
            ));
            
            $service_counts[$service_id] = (int)$count;
        }
        
        // Sort services by booking count (descending)
        usort($services, function($a, $b) use ($service_counts) {
            $count_a = isset($service_counts[$a->getId()]) ? $service_counts[$a->getId()] : 0;
            $count_b = isset($service_counts[$b->getId()]) ? $service_counts[$b->getId()] : 0;
            
            // Sort descending (most popular first)
            return $count_b - $count_a;
        });
        
        if (SLN_Plugin::isDebugEnabled()) {
            $top_5 = array_slice($services, 0, 5);
            $names = array_map(function($s) use ($service_counts) {
                return $s->getName() . ' (' . $service_counts[$s->getId()] . ' bookings)';
            }, $top_5);
            
            SLN_Plugin::addLog('[CacheWarmer] Top 5 popular services: ' . implode(', ', $names));
        }
        
        return $services;
    }
    
    /**
     * Warm cache for standard booking flow (DateStep)
     * 
     * @return bool Success status
     */
    private function warmDateStepCache()
    {
        $bb = $this->plugin->getBookingBuilder();
        
        // Calculate intervals (this will be cached by DateStep logic)
        $intervals = $this->plugin->getIntervals($bb->getDateTime());
        
        // Also cache it manually to ensure it's stored
        $cache_key = $this->getIntervalsCacheKey($bb);
        set_transient($cache_key, $intervals, 15 * MINUTE_IN_SECONDS);
        
        return $intervals !== null;
    }
    
    /**
     * Get cache key for intervals (matches DateStep logic)
     * 
     * @param SLN_Wrapper_Booking_Builder $bb
     * @return string Cache key
     */
    private function getIntervalsCacheKey($bb)
    {
        $services = implode('_', $bb->getServicesIds());
        $attendants = implode('_', array_values($bb->getAttendantsIds()));
        
        $key_parts = array(
            'intervals',
            $services,
            $attendants,
            'no_date_selected',
            'new'
        );
        
        return 'sln_' . md5(implode('|', $key_parts));
    }
    
    /**
     * Get cache warming statistics (INCREMENTAL)
     * 
     * Shows progress of incremental cache warming:
     * - How many services have been warmed
     * - Which service was last warmed
     * - Progress percentage
     * 
     * @return array Statistics
     */
    public function getStats()
    {
        $settings = $this->plugin->getSettings();
        $alt_order = $settings->get('form_steps_alt_order');
        
        if ($alt_order) {
            // Get all active services
            $repo = $this->plugin->getRepository(SLN_Plugin::POST_TYPE_SERVICE);
            $all_services = $repo->getAllPrimary();
            $active_services = array();
            
            foreach ($all_services as $service) {
                if (!$service->isHideOnFrontend()) {
                    $active_services[] = $service;
                }
            }
            
            if (!empty($active_services)) {
                $total_services = count($active_services);
                
                // Get incremental warming progress
                $last_index = get_transient('sln_cache_warmer_last_index');
                $last_index = ($last_index !== false) ? (int)$last_index : 0;
                
                // Check if first service cache exists (proof that warming is working)
                $first_service = $active_services[0];
                $bb = $this->plugin->getBookingBuilder();
                $bb->clear();
                
                // CRITICAL: Use setServicesAndAttendants with false to match cache warmer
                $bb->setServicesAndAttendants([
                    $first_service->getId() => false
                ]);
                
                $date = new SLN_DateTime('now');
                $obj = new SLN_Action_Ajax_CheckDateAlt($this->plugin);
                $obj->setDate(SLN_Func::filter($date, 'date'));
                $obj->setTime(SLN_Func::filter($date, 'time'));
                
                // Get the actual cache key
                $reflection = new ReflectionClass($obj);
                $method = $reflection->getMethod('getIntervalsCacheKey');
                $method->setAccessible(true);
                $cache_key = $method->invoke($obj, $bb, '');
                
                $cached = get_transient($cache_key);
                
                // Calculate progress
                $progress_pct = $last_index > 0 ? round(($last_index / $total_services) * 100, 1) : 0;
                $status = $last_index === 0 ? 'Full cycle completed' : "In progress ({$last_index}/{$total_services})";
                
                return array(
                    'mode' => 'alternative_incremental',
                    'cache_key' => $cache_key,
                    'service_checked' => $first_service->getName(),
                    'cache_exists' => $cached !== false,
                    'cache_populated' => !empty($cached),
                    'total_services' => $total_services,
                    'services_warmed' => $last_index,
                    'progress_percent' => $progress_pct,
                    'status' => $status,
                );
            }
            
            // No active services found
            return array(
                'mode' => 'alternative',
                'cache_key' => 'none',
                'error' => 'No active services found',
                'cache_exists' => false,
                'cache_populated' => false,
            );
        } else {
            // Standard flow
            $bb = $this->plugin->getBookingBuilder();
            $services = implode('_', $bb->getServicesIds());
            $attendants = implode('_', array_values($bb->getAttendantsIds()));
            $cache_key = 'sln_' . md5("intervals|{$services}|{$attendants}|no_date_selected|new");
            $cached = get_transient($cache_key);
            
            return array(
                'mode' => 'standard',
                'cache_key' => $cache_key,
                'cache_exists' => $cached !== false,
                'cache_populated' => !empty($cached),
            );
        }
    }
}

