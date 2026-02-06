<?php
/**
 * Emergency Restore Script
 * 
 * This script helps recover from a broken rollback
 * 
 * USAGE:
 * 1. Upload to site root
 * 2. Visit: https://salon.salonbooking.it/emergency-restore.php
 * 3. Follow the instructions
 * 4. DELETE this file after use!
 */

require_once(__DIR__ . '/wp-config.php');

// Don't load full WordPress - we might be in a broken state
// Just need database access

if (!defined('DB_NAME')) {
    die('Could not load WordPress config');
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Emergency Restore</title>
    <style>
        body { font-family: sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 5px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #dc3232; }
        .step { background: #f0f0f1; padding: 20px; margin: 20px 0; border-left: 4px solid #2271b1; }
        .command { background: #23282d; color: #f0f0f1; padding: 15px; margin: 10px 0; font-family: monospace; overflow-x: auto; }
        .warning { background: #fff3cd; padding: 15px; margin: 10px 0; border: 1px solid #ffeeba; color: #856404; }
        .success { background: #d4edda; padding: 15px; margin: 10px 0; border: 1px solid #c3e6cb; color: #155724; }
        ul { line-height: 1.8; }
    </style>
</head>
<body>

<div class="container">
    <h1>🆘 Emergency Restore After Broken Rollback</h1>
    
    <div class="warning">
        <strong>⚠️ Your site is broken because version 10.29.5 is missing critical files.</strong><br>
        Follow these steps to recover immediately.
    </div>

    <div class="step">
        <h2>Option 1: Restore from Automatic Backup (FASTEST)</h2>
        <p>The rollback system should have created a backup before installing 10.29.5.</p>
        
        <h3>Step 1: Check for backup</h3>
        <div class="command">ls -lh <?php echo WP_CONTENT_DIR; ?>/sln_backups/</div>
        
        <h3>Step 2: Restore from backup</h3>
        <div class="command">cd <?php echo WP_CONTENT_DIR; ?>/plugins/<br>
rm -rf salon-booking-plugin-pro<br>
cp -r ../sln_backups/salon-booking-system-backup-* salon-booking-plugin-pro</div>
        
        <h3>Step 3: Verify</h3>
        <div class="command">ls -lh <?php echo WP_CONTENT_DIR; ?>/plugins/salon-booking-plugin-pro/salon.php</div>
    </div>

    <div class="step">
        <h2>Option 2: Rollback to Working Version (10.29.6)</h2>
        <p>If backup doesn't exist, rollback to version 10.29.6 (known working).</p>
        
        <div class="warning">
            <strong>⚠️ Your site might still be broken, so you may need to use wp-cli directly:</strong>
        </div>
        
        <div class="command">cd /home/salonsb/web/salon.salonbooking.it/public_html<br>
wp plugin deactivate salon-booking-system --path=.<br>
# Download 10.29.6 manually<br>
curl -o /tmp/plugin-10.29.6.zip "https://www.salonbookingsystem.com/wp-content/uploads/edd/2025/11/salon-booking-plugin-pro-pay-10.29.6.zip"<br>
# Extract<br>
unzip -q /tmp/plugin-10.29.6.zip -d /tmp/<br>
# Replace plugin<br>
rm -rf wp-content/plugins/salon-booking-plugin-pro<br>
mv /tmp/salon-booking-plugin-pro wp-content/plugins/<br>
# Reactivate<br>
wp plugin activate salon-booking-system --path=.</div>
    </div>

    <div class="step">
        <h2>Option 3: Re-upload 10.30.5 via FTP</h2>
        <ol>
            <li>Download 10.30.5 from your local build: <code><?php echo dirname(dirname(__DIR__)); ?>/build/releases/salon-booking-plugin-pro-pay-10.30.5.zip</code></li>
            <li>Extract locally</li>
            <li>Upload <code>salon-booking-plugin-pro/</code> folder via FTP to: <code>wp-content/plugins/</code></li>
            <li>Overwrite all files</li>
        </ol>
    </div>

    <div class="step">
        <h2>After Recovery: Prevent This From Happening Again</h2>
        
        <div class="success">
            <strong>✅ Fixes Already Applied:</strong>
            <ul>
                <li>Version 10.29.5 has been <strong>blacklisted</strong> in the API endpoint</li>
                <li>Version 10.29.5 has been <strong>removed</strong> from fallback list</li>
                <li>Future rollback requests will <strong>skip 10.29.5 automatically</strong></li>
            </ul>
        </div>
        
        <p><strong>What to do:</strong></p>
        <ol>
            <li>Upload updated <code>salonbookingsystem-api-endpoint.php</code> to salonbookingsystem.com</li>
            <li>Clear rollback cache on customer site (force-update-check.php)</li>
            <li>Version 10.29.5 will no longer appear in rollback list</li>
        </ol>
    </div>

    <hr>
    
    <div class="warning">
        <strong>⚠️ CRITICAL: Delete this file immediately after recovery!</strong><br>
        File: <code><?php echo __FILE__; ?></code>
    </div>

</div>

</body>
</html>

