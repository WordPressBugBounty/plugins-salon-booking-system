<?php
/**
 * Cache Warmer Tool
 * 
 * Displays cache warming controls and statistics in the Tools section
 */

// Get cache warmer URL and key
if (!class_exists('SLN_Action_Ajax_CacheWarmer')) {
    return;
}

$url = SLN_Action_Ajax_CacheWarmer::getCacheWarmerUrl();
$key = SLN_Action_Ajax_CacheWarmer::getSecurityKey();

// Get cache warmer helper to check stats
$warmer = new SLN_Helper_CacheWarmer($plugin);
$stats = $warmer->getStats();
?>

<div class="sln-tab" id="sln-tab-cache-warmer">
    <div class="sln-box sln-box--main sln-box--haspanel">
        <h2 class="sln-box-title">
            ⚡ <?php esc_html_e('Cache Warmer', 'salon-booking-system'); ?>
        </h2>
        
        <div class="row">
            <div class="col-xs-12">
                <p class="help-block">
                    <?php esc_html_e('The cache warmer pre-calculates availability data to eliminate delays on your booking form. It runs automatically every 25 minutes via WordPress cron.', 'salon-booking-system'); ?>
                </p>
            </div>
        </div>
        
        <!-- Cache Status -->
        <div class="row" style="margin-bottom: 20px;">
            <div class="col-xs-12 col-md-6">
                <div style="background: #f0f0f1; padding: 15px; border-radius: 4px;">
                    <h4 style="margin-top: 0;"><?php esc_html_e('Cache Status', 'salon-booking-system'); ?></h4>
                    
                    <?php if (isset($stats['cache_populated']) && $stats['cache_populated']): ?>
                        <p style="color: #46b450; font-weight: bold; margin: 10px 0;">
                            ✅ <?php esc_html_e('Cache Populated', 'salon-booking-system'); ?>
                        </p>
                    <?php else: ?>
                        <p style="color: #d63638; font-weight: bold; margin: 10px 0;">
                            ❌ <?php esc_html_e('Cache Not Populated', 'salon-booking-system'); ?>
                        </p>
                    <?php endif; ?>
                    
                    <?php if (isset($stats['total_services'])): ?>
                        <p style="margin: 5px 0;">
                            <strong><?php esc_html_e('Total Services:', 'salon-booking-system'); ?></strong> 
                            <?php echo esc_html($stats['total_services']); ?>
                        </p>
                    <?php endif; ?>
                    
                    <?php if (isset($stats['services_warmed'])): ?>
                        <p style="margin: 5px 0;">
                            <strong><?php esc_html_e('Services Warmed:', 'salon-booking-system'); ?></strong> 
                            <?php echo esc_html($stats['services_warmed']); ?>
                        </p>
                    <?php endif; ?>
                    
                    <?php if (isset($stats['progress_percent'])): ?>
                        <p style="margin: 5px 0;">
                            <strong><?php esc_html_e('Progress:', 'salon-booking-system'); ?></strong> 
                            <?php echo esc_html($stats['progress_percent']); ?>%
                        </p>
                        
                        <!-- Progress Bar -->
                        <div style="background: #ddd; height: 20px; border-radius: 10px; overflow: hidden; margin-top: 10px;">
                            <div style="background: #46b450; height: 100%; width: <?php echo esc_attr($stats['progress_percent']); ?>%; transition: width 0.3s;"></div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($stats['status'])): ?>
                        <p style="margin: 10px 0 0 0; font-style: italic; color: #666;">
                            <?php echo esc_html($stats['status']); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="col-xs-12 col-md-6">
                <div style="background: #f0f0f1; padding: 15px; border-radius: 4px;">
                    <h4 style="margin-top: 0;"><?php esc_html_e('Manual Control', 'salon-booking-system'); ?></h4>
                    
                    <p class="help-block" style="margin-bottom: 15px;">
                        <?php esc_html_e('Click the button below to manually warm the cache. This is useful for testing or if you need immediate cache population.', 'salon-booking-system'); ?>
                    </p>
                    
                    <button type="button" class="sln-btn sln-btn--main sln-btn--big" onclick="slnTestCacheWarmer('<?php echo esc_js($url); ?>')">
                        <?php esc_html_e('Warm Cache Now', 'salon-booking-system'); ?>
                    </button>
                    
                    <div id="sln-cache-warmer-test-result" style="margin-top: 15px;"></div>
                </div>
            </div>
        </div>
        
        <!-- Advanced: External Cron Setup -->
        <div class="row">
            <div class="col-xs-12">
                <details style="margin-top: 20px;">
                    <summary style="cursor: pointer; font-weight: bold; padding: 10px; background: #f0f0f1; border-radius: 4px;">
                        <?php esc_html_e('⚙️ Advanced: External Cron Setup (Optional)', 'salon-booking-system'); ?>
                    </summary>
                    
                    <div style="padding: 15px; border: 1px solid #ddd; border-top: none; background: #fff;">
                        <p class="help-block">
                            <?php esc_html_e('For high-traffic sites, WordPress cron is sufficient. However, if you experience issues with WordPress cron, you can set up an external cron service.', 'salon-booking-system'); ?>
                        </p>
                        
                        <h4><?php esc_html_e('Your Cache Warmer URL:', 'salon-booking-system'); ?></h4>
                        <input type="text" readonly value="<?php echo esc_attr($url); ?>" onclick="this.select();" style="width: 100%; padding: 8px; font-family: monospace; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
                        
                        <h4 style="margin-top: 20px;"><?php esc_html_e('Setup Instructions (cron-job.org):', 'salon-booking-system'); ?></h4>
                        <ol>
                            <li><?php esc_html_e('Sign up at', 'salon-booking-system'); ?> <a href="https://console.cron-job.org/signup" target="_blank">cron-job.org</a></li>
                            <li><?php esc_html_e('Create a new cron job', 'salon-booking-system'); ?></li>
                            <li><?php esc_html_e('Set URL to the cache warmer URL above', 'salon-booking-system'); ?></li>
                            <li><?php esc_html_e('Set schedule to: Every 25 minutes', 'salon-booking-system'); ?></li>
                            <li><?php esc_html_e('Save and enable the cron job', 'salon-booking-system'); ?></li>
                        </ol>
                        
                        <p class="help-block" style="margin-top: 15px;">
                            <strong><?php esc_html_e('Note:', 'salon-booking-system'); ?></strong>
                            <?php esc_html_e('The cache warmer uses incremental warming, so it will warm as many services as possible within 25 seconds, then continue in the next run. A full cycle typically completes in 2-3 runs.', 'salon-booking-system'); ?>
                        </p>
                    </div>
                </details>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
function slnTestCacheWarmer(url) {
    var resultDiv = document.getElementById('sln-cache-warmer-test-result');
    resultDiv.innerHTML = '<p style="color: #0073aa;">⏳ <?php esc_html_e('Testing... This may take up to 30 seconds...', 'salon-booking-system'); ?></p>';
    
    fetch(url)
        .then(response => {
            // Get the raw text first to see what we're receiving
            return response.text().then(text => {
                // Try to parse as JSON
                try {
                    return JSON.parse(text);
                } catch (e) {
                    // If JSON parsing fails, show the first part of the response for debugging
                    throw new Error('<?php esc_html_e('Invalid JSON response. First 200 chars:', 'salon-booking-system'); ?> ' + text.substring(0, 200));
                }
            });
        })
        .then(data => {
            if (data.success) {
                var statusHtml = '';
                if (data.stats) {
                    statusHtml += '<br><strong><?php esc_html_e('Total Services:', 'salon-booking-system'); ?></strong> ' + (data.stats.total_services || 'N/A');
                    statusHtml += '<br><strong><?php esc_html_e('Services Warmed:', 'salon-booking-system'); ?></strong> ' + (data.stats.services_warmed || 'N/A');
                    statusHtml += '<br><strong><?php esc_html_e('Progress:', 'salon-booking-system'); ?></strong> ' + (data.stats.progress_percent || '0') + '%';
                    statusHtml += '<br><strong><?php esc_html_e('Status:', 'salon-booking-system'); ?></strong> ' + (data.stats.status || 'Unknown');
                    statusHtml += '<br><strong><?php esc_html_e('Cache Populated:', 'salon-booking-system'); ?></strong> ' + (data.stats.cache_populated ? '<?php esc_html_e('Yes', 'salon-booking-system'); ?>' : '<?php esc_html_e('No', 'salon-booking-system'); ?>');
                }
                
                resultDiv.innerHTML = '<div style="background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 10px; border-radius: 4px;">' +
                    '<strong>✅ <?php esc_html_e('Success!', 'salon-booking-system'); ?></strong><br>' +
                    '<?php esc_html_e('Cache warmed in', 'salon-booking-system'); ?> ' + data.duration_ms + 'ms' +
                    statusHtml +
                    '</div>';
                    
                // Reload page after 2 seconds to update stats
                setTimeout(function() {
                    location.reload();
                }, 2000);
            } else {
                resultDiv.innerHTML = '<div style="background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 10px; border-radius: 4px;">' +
                    '<strong>❌ <?php esc_html_e('Error:', 'salon-booking-system'); ?></strong><br>' + data.error +
                    '</div>';
            }
        })
        .catch(error => {
            resultDiv.innerHTML = '<div style="background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 10px; border-radius: 4px;">' +
                '<strong>❌ <?php esc_html_e('Request failed:', 'salon-booking-system'); ?></strong><br>' + error.message +
                '</div>';
        });
}
</script>

