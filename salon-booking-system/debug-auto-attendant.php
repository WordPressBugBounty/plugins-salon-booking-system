<?php
/**
 * Auto-Attendant Feature Debug Script
 * 
 * Usage:
 * 1. Place this file in your WordPress root directory
 * 2. Access it via: https://yoursite.com/debug-auto-attendant.php
 * 3. Or run from command line: php debug-auto-attendant.php
 * 
 * Security: Remove this file after debugging!
 */

// Load WordPress
require_once(__DIR__ . '/wp-load.php');

// Security check
if (!current_user_can('manage_options')) {
    die('Access denied. Admin privileges required.');
}

header('Content-Type: text/plain');

echo "=== AUTO-ATTENDANT FEATURE DEBUG ===\n\n";

// Check if plugin is active
if (!class_exists('SLN_Plugin')) {
    die("ERROR: Salon Booking System plugin not found!\n");
}

$plugin = SLN_Plugin::getInstance();
$settings = $plugin->getSettings();

echo "1. FEATURE FLAG STATUS\n";
echo "   -------------------\n";
$isEnabled = $settings->isAutoAttendantCheckEnabled();
echo "   Auto-Attendant Check: " . ($isEnabled ? "✅ ENABLED" : "❌ DISABLED") . "\n";
echo "   Raw setting value: " . var_export($settings->get('auto_attendant_check_enabled'), true) . "\n\n";

echo "2. RELATED SETTINGS\n";
echo "   ----------------\n";
echo "   Change Order (Alt Flow): " . ($settings->isFormStepsAltOrder() ? "✅ YES" : "❌ NO") . "\n";
echo "   Attendants Enabled: " . ($settings->get('attendant_enabled') ? "✅ YES" : "❌ NO") . "\n";
echo "   Multiple Attendants: " . ($settings->get('m_attendant_enabled') ? "✅ YES" : "❌ NO") . "\n";
echo "   Choose Attendant Disabled: " . ($settings->get('choose_attendant_for_me_disabled') ? "❌ YES" : "✅ NO") . "\n\n";

echo "3. LOGGER CLASS\n";
echo "   ------------\n";
echo "   Logger class exists: " . (class_exists('SLN_Helper_AutoAttendant_Logger') ? "✅ YES" : "❌ NO") . "\n";
echo "   Debug constant defined: " . (defined('SLN_AUTO_ATTENDANT_DEBUG') ? "✅ YES" : "❌ NO") . "\n";
if (defined('SLN_AUTO_ATTENDANT_DEBUG')) {
    echo "   Debug constant value: " . (SLN_AUTO_ATTENDANT_DEBUG ? "✅ TRUE" : "❌ FALSE") . "\n";
}
echo "\n";

echo "4. SERVICES & ATTENDANTS\n";
echo "   ---------------------\n";

// Get services
$services = get_posts(array(
    'post_type' => SLN_Plugin::POST_TYPE_SERVICE,
    'posts_per_page' => -1,
    'post_status' => 'publish'
));

echo "   Total Services: " . count($services) . "\n\n";

foreach ($services as $post) {
    $service = new SLN_Wrapper_Service($post);
    echo "   Service: " . $service->getName() . " (ID: " . $service->getId() . ")\n";
    echo "      Attendants Enabled: " . ($service->isAttendantsEnabled() ? "✅ YES" : "❌ NO") . "\n";
    
    if ($service->isAttendantsEnabled()) {
        $attendants = $service->getAttendants();
        echo "      Assigned Attendants: " . count($attendants) . "\n";
        foreach ($attendants as $att) {
            echo "         - " . $att->getName() . " (ID: " . $att->getId() . ")\n";
        }
    }
    echo "\n";
}

echo "5. RECENT BOOKINGS (Last 5)\n";
echo "   ------------------------\n";
$bookings = get_posts(array(
    'post_type' => SLN_Plugin::POST_TYPE_BOOKING,
    'posts_per_page' => 5,
    'post_status' => 'any',
    'orderby' => 'date',
    'order' => 'DESC'
));

foreach ($bookings as $post) {
    $booking = new SLN_Wrapper_Booking($post);
    echo "   Booking #" . $booking->getId() . "\n";
    echo "      Date/Time: " . $booking->getDate()->format('Y-m-d H:i') . "\n";
    echo "      Status: " . $booking->getStatus() . "\n";
    
    $services = $booking->getServicesIds();
    echo "      Services: " . count($services) . "\n";
    
    $attendants = $booking->getAttendantsIds();
    echo "      Attendants: " . (!empty($attendants) ? implode(', ', $attendants) : 'None') . "\n";
    echo "\n";
}

echo "6. CHECKDATEALT CLASS\n";
echo "   ------------------\n";
echo "   Class exists: " . (class_exists('SLN_Action_Ajax_CheckDateAlt') ? "✅ YES" : "❌ NO") . "\n";
if (class_exists('SLN_Action_Ajax_CheckDateAlt')) {
    $reflection = new ReflectionClass('SLN_Action_Ajax_CheckDateAlt');
    $hasMethod = $reflection->hasMethod('checkAutoAttendantAvailability');
    echo "   Has checkAutoAttendantAvailability method: " . ($hasMethod ? "✅ YES" : "❌ NO") . "\n";
}
echo "\n";

echo "7. RECOMMENDATIONS\n";
echo "   ---------------\n";

$recommendations = array();

if (!$isEnabled) {
    $recommendations[] = "⚠️  Feature is DISABLED. Enable it in Salon → Settings → General";
}

if (!$settings->isFormStepsAltOrder()) {
    $recommendations[] = "⚠️  Alternative order is NOT enabled. Feature only works with alt order.";
}

if ($settings->get('choose_attendant_for_me_disabled')) {
    $recommendations[] = "⚠️  'Choose attendant for me' is DISABLED in settings.";
}

if (!defined('SLN_AUTO_ATTENDANT_DEBUG')) {
    $recommendations[] = "💡 Enable debug logging: define('SLN_AUTO_ATTENDANT_DEBUG', true) in wp-config.php";
}

if (!defined('WP_DEBUG_LOG') || !WP_DEBUG_LOG) {
    $recommendations[] = "💡 Enable WordPress debug log: define('WP_DEBUG_LOG', true) in wp-config.php";
}

if (count($services) === 0) {
    $recommendations[] = "❌ No services found! Create services first.";
}

foreach ($services as $post) {
    $service = new SLN_Wrapper_Service($post);
    if ($service->isAttendantsEnabled() && count($service->getAttendants()) === 0) {
        $recommendations[] = "⚠️  Service '{$service->getName()}' has attendants enabled but none assigned!";
    }
}

if (empty($recommendations)) {
    echo "   ✅ Everything looks good!\n";
} else {
    foreach ($recommendations as $rec) {
        echo "   " . $rec . "\n";
    }
}

echo "\n";
echo "=== END OF DEBUG INFO ===\n";
echo "\n";
echo "🔒 SECURITY REMINDER: Delete this file after debugging!\n";



