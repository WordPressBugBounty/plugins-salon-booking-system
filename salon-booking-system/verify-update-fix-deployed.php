<?php
/**
 * Verify if "Check for Updates" Fix is Deployed
 * 
 * This script checks if the production site has the updated Manager.php
 * 
 * USAGE:
 * 1. Upload to site root
 * 2. Visit: https://salon.salonbooking.it/verify-update-fix-deployed.php
 * 3. DELETE this file after checking!
 */

require_once(__DIR__ . '/wp-load.php');

if (!current_user_can('manage_options')) {
    die('Unauthorized');
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Verify Update Fix Deployed</title>
    <style>
        body { font-family: sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 5px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { background: #d4edda; padding: 15px; margin: 10px 0; border: 1px solid #c3e6cb; color: #155724; font-weight: bold; }
        .error { background: #f8d7da; padding: 15px; margin: 10px 0; border: 1px solid #f5c6cb; color: #721c24; font-weight: bold; }
        .info { background: #d1ecf1; padding: 15px; margin: 10px 0; border: 1px solid #bee5eb; color: #0c5460; }
        pre { background: #f5f5f5; padding: 10px; border: 1px solid #ddd; overflow-x: auto; font-size: 12px; }
        code { background: #f5f5f5; padding: 2px 5px; font-family: monospace; }
    </style>
</head>
<body>

<div class="container">
    <h1>🔍 Verify "Check for Updates" Fix Deployed</h1>
    
    <?php
    $manager_file = WP_CONTENT_DIR . '/plugins/salon-booking-plugin-pro/src/SLN/Update/Manager.php';
    
    if (!file_exists($manager_file)) {
        echo '<div class="error">❌ Manager.php file not found!</div>';
        exit;
    }
    
    $content = file_get_contents($manager_file);
    
    // Check 1: Does the file contain the OLD broken code?
    $old_code_present = strpos($content, "if ('plugins.php' == \$pagenow || 'plugin-install.php' == \$pagenow)") !== false;
    
    // Check 2: Does the file contain the comment from the fix?
    $new_comment_present = strpos($content, "Initialize processor on ALL admin pages") !== false;
    
    // Check 3: Is the processor always initialized (no if statement)?
    $always_initialized = !$old_code_present && strpos($content, '$this->processor = new SLN_Update_Processor($this);') !== false;
    
    echo '<h2>Check 1: Old Broken Code</h2>';
    if ($old_code_present) {
        echo '<div class="error">❌ FOUND OLD CODE - Fix NOT deployed!</div>';
        echo '<p>The file still contains the page-specific check that breaks update detection.</p>';
    } else {
        echo '<div class="success">✅ Old broken code not present</div>';
    }
    
    echo '<h2>Check 2: New Fix Comment</h2>';
    if ($new_comment_present) {
        echo '<div class="success">✅ Fix comment found - Fix IS deployed!</div>';
    } else {
        echo '<div class="error">❌ Fix comment not found - Fix NOT deployed</div>';
    }
    
    echo '<h2>Check 3: Processor Always Initialized</h2>';
    if ($always_initialized) {
        echo '<div class="success">✅ Processor is always initialized</div>';
    } else {
        echo '<div class="error">❌ Processor still has conditional initialization</div>';
    }
    
    echo '<hr>';
    
    if ($new_comment_present && $always_initialized && !$old_code_present) {
        echo '<div class="success">';
        echo '<h2>🎉 SUCCESS!</h2>';
        echo '<p>The "Check for Updates" fix IS deployed to production!</p>';
        echo '<p><strong>Next steps:</strong></p>';
        echo '<ol>';
        echo '<li>Clear all caches: <code>wp transient delete --all</code></li>';
        echo '<li>Go to license page and click "Check for Updates"</li>';
        echo '<li>Should now show: "Update from 10.29.6 to 10.30.5"</li>';
        echo '</ol>';
        echo '</div>';
    } else {
        echo '<div class="error">';
        echo '<h2>❌ FIX NOT DEPLOYED</h2>';
        echo '<p>The production file still has the old broken code.</p>';
        echo '<p><strong>You need to:</strong></p>';
        echo '<ol>';
        echo '<li>Upload the fixed Manager.php from your local dev environment</li>';
        echo '<li>Local file: <code>/Users/macbookpro/Desktop/Salon Booking System/development/bitbucket/src/SLN/Update/Manager.php</code></li>';
        echo '<li>Upload to: <code>' . $manager_file . '</code></li>';
        echo '<li>Then refresh this page to verify</li>';
        echo '</ol>';
        echo '</div>';
    }
    
    echo '<hr>';
    
    echo '<h2>File Details</h2>';
    echo '<div class="info">';
    echo '<strong>File:</strong> ' . $manager_file . '<br>';
    echo '<strong>Size:</strong> ' . size_format(filesize($manager_file)) . '<br>';
    echo '<strong>Modified:</strong> ' . date('Y-m-d H:i:s', filemtime($manager_file)) . '<br>';
    echo '</div>';
    
    echo '<h2>Relevant Code Section</h2>';
    echo '<pre>';
    
    // Extract the hook_admin_init function
    $start = strpos($content, 'public function hook_admin_init()');
    if ($start !== false) {
        $end = strpos($content, '}', $start + 200);
        $function_code = substr($content, $start, $end - $start + 1);
        echo htmlspecialchars($function_code);
    } else {
        echo 'Function not found in file!';
    }
    
    echo '</pre>';
    
    ?>
    
    <hr>
    
    <div class="info">
        <strong>⚠️ DELETE THIS FILE after verification!</strong><br>
        File: <code><?php echo __FILE__; ?></code>
    </div>

</div>

</body>
</html>

