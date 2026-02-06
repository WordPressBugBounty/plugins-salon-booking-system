<?php
/**
 * Clear Rollback Cache Script
 * 
 * Upload this to salon.salonbooking.it and run it once via browser:
 * https://salon.salonbooking.it/clear-rollback-cache.php
 * 
 * Or run via wp-cli:
 * wp eval-file clear-rollback-cache.php
 * 
 * Then DELETE this file after running!
 */

// Load WordPress
require_once(__DIR__ . '/wp-load.php');

// Clear the transients
$cleared_pay = delete_transient('sln_rollback_versions_pay');
$cleared_se = delete_transient('sln_rollback_versions_se');

echo "<h1>Rollback Cache Cleared</h1>";
echo "<p>PAY edition cache: " . ($cleared_pay ? "✅ Cleared" : "❌ Not found or already cleared") . "</p>";
echo "<p>SE edition cache: " . ($cleared_se ? "✅ Cleared" : "❌ Not found or already cleared") . "</p>";
echo "<hr>";
echo "<p><strong>✅ Done! Now try the rollback again.</strong></p>";
echo "<p><em>Remember to delete this file after use!</em></p>";

// Log it
error_log('Rollback cache manually cleared - PAY: ' . ($cleared_pay ? 'yes' : 'no') . ', SE: ' . ($cleared_se ? 'yes' : 'no'));

