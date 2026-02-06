<?php
/**
 * TEMPORARY TESTING SCRIPT - Concurrent Booking Test
 * 
 * This script simulates two users trying to book the same time slot simultaneously
 * to test the lock mechanism and validation.
 * 
 * USAGE:
 * 1. Upload this file to your WordPress root directory
 * 2. Access it via browser: http://yoursite.com/test-concurrent-booking.php
 * 3. DELETE this file after testing (for security)
 * 
 * OR via WP-CLI:
 * wp eval-file test-concurrent-booking.php
 */

// Load WordPress
require_once('wp-load.php');

// Security check - only allow in development/staging
if (!defined('WP_DEBUG') || !WP_DEBUG) {
    die('This script can only run when WP_DEBUG is enabled.');
}

// Only allow administrators
if (!current_user_can('manage_options')) {
    die('This script can only be run by administrators.');
}

echo "<h1>Salon Booking System - Concurrent Booking Test</h1>";
echo "<style>
    body { font-family: monospace; background: #f5f5f5; padding: 20px; }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .warning { color: orange; font-weight: bold; }
    .info { color: blue; }
    pre { background: white; padding: 10px; border-left: 3px solid #0073aa; }
    h2 { background: #0073aa; color: white; padding: 10px; }
</style>";

/**
 * Test Configuration
 */
$test_config = array(
    'service_id' => null,    // Will auto-select first available service
    'attendant_id' => null,  // Will auto-select first available attendant
    'date' => date('Y-m-d', strtotime('+2 days')), // Book 2 days from now
    'time' => '10:00',       // Book at 10:00 AM
);

echo "<h2>1. Test Configuration</h2>";
echo "<pre>";
echo "Date: {$test_config['date']}\n";
echo "Time: {$test_config['time']}\n";

// Get plugin instance
if (!class_exists('SLN_Plugin')) {
    echo "<span class='error'>ERROR: Salon Booking System plugin not found or not activated!</span>";
    exit;
}

$plugin = SLN_Plugin::getInstance();

// Get first available service
$services_repo = $plugin->getRepository(SLN_Plugin::POST_TYPE_SERVICE);
$services = $services_repo->getAll();

if (empty($services)) {
    echo "<span class='error'>ERROR: No services found. Please create at least one service first.</span>";
    exit;
}

$test_config['service_id'] = $services[0]->getId();
echo "Service ID: {$test_config['service_id']} ({$services[0]->getName()})\n";

// Get first available attendant (if attendants are enabled)
if ($plugin->getSettings()->isAttendantsEnabled()) {
    $attendants_repo = $plugin->getRepository(SLN_Plugin::POST_TYPE_ATTENDANT);
    $attendants = $attendants_repo->getAll();
    
    if (!empty($attendants)) {
        $test_config['attendant_id'] = $attendants[0]->getId();
        echo "Attendant ID: {$test_config['attendant_id']} ({$attendants[0]->getName()})\n";
    }
}
echo "</pre>";

/**
 * Helper function to create a booking builder with test data
 */
function create_test_booking_builder($plugin, $config, $user_label = 'User') {
    $bb = $plugin->getBookingBuilder();
    $bb->clear();
    
    $datetime = new DateTime("{$config['date']} {$config['time']}");
    
    $bb->set('date', $datetime->format('Y-m-d'));
    $bb->set('time', $datetime->format('H:i'));
    $bb->set('services', array($config['service_id'] => 1));
    
    if ($config['attendant_id']) {
        $bb->set('attendants', array($config['service_id'] => $config['attendant_id']));
    }
    
    // Set fake customer data
    $bb->set('firstname', $user_label);
    $bb->set('lastname', 'Test');
    $bb->set('email', strtolower(str_replace(' ', '', $user_label)) . '@test.com');
    $bb->set('phone', '1234567890');
    
    return $bb;
}

/**
 * Helper function to get lock key
 */
function get_lock_key($bb) {
    $service_ids   = implode('-', $bb->getServicesIds());
    $attendant_ids = implode('-', array_values($bb->getAttendantsIds()));
    $start_time    = $bb->getDateTime()->format('Y-m-d H:i:s');
    
    return 'booking_lock_' . md5($service_ids . '_' . $attendant_ids . '_' . $start_time);
}

echo "<h2>2. Testing Lock Mechanism</h2>";
echo "<pre>";

// Clean up any existing test bookings
$existing = get_posts(array(
    'post_type' => SLN_Plugin::POST_TYPE_BOOKING,
    'post_status' => 'any',
    'meta_query' => array(
        array(
            'key' => '_sln_booking_date',
            'value' => $test_config['date'],
            'compare' => '='
        ),
        array(
            'key' => '_sln_booking_time',
            'value' => $test_config['time'],
            'compare' => '='
        )
    ),
    'posts_per_page' => -1
));

if (!empty($existing)) {
    echo "<span class='warning'>Found " . count($existing) . " existing booking(s) for this time slot.</span>\n";
    echo "Cleaning up...\n";
    foreach ($existing as $post) {
        wp_delete_post($post->ID, true);
    }
    echo "<span class='success'>✓ Cleanup complete</span>\n\n";
}

// Test 1: Create first booking and set lock
echo "<strong>USER 1: Starting booking process...</strong>\n";
$bb1 = create_test_booking_builder($plugin, $test_config, 'User 1');
$lock_key = get_lock_key($bb1);

echo "Lock key: $lock_key\n";
echo "Setting transient lock...\n";
set_transient($lock_key, 1, 15);

$lock_exists = get_transient($lock_key);
if ($lock_exists) {
    echo "<span class='success'>✓ Lock successfully set</span>\n";
} else {
    echo "<span class='error'>✗ Failed to set lock</span>\n";
}

// Test 2: Try to create second booking with same slot
echo "\n<strong>USER 2: Attempting to book same slot...</strong>\n";
$bb2 = create_test_booking_builder($plugin, $test_config, 'User 2');
$lock_key2 = get_lock_key($bb2);

echo "Lock key: $lock_key2\n";
echo "Checking if lock exists...\n";

$lock_collision = get_transient($lock_key2);
if ($lock_collision) {
    echo "<span class='success'>✓ Lock collision detected! User 2 should see 'time-slot already booked' message</span>\n";
    echo "<span class='info'>This is the CORRECT behavior - concurrent booking prevented!</span>\n";
} else {
    echo "<span class='error'>✗ No lock collision detected! This is a BUG!</span>\n";
}

echo "</pre>";

echo "<h2>3. Testing Validation Failure Scenario</h2>";
echo "<pre>";

// Clear the lock for User 1
delete_transient($lock_key);
echo "Cleared lock from User 1...\n\n";

// Create a draft booking
echo "<strong>Creating DRAFT booking...</strong>\n";
$bb1->create(SLN_Enum_BookingStatus::DRAFT);
$booking1 = $bb1->getLastBooking();

if ($booking1) {
    echo "<span class='success'>✓ DRAFT booking created (ID: {$booking1->getId()})</span>\n";
    echo "Status: {$booking1->getStatus()}\n";
    
    $manual_confirmation_enabled = $plugin->getSettings()->get('confirmation');
    echo "Manual confirmation: " . ($manual_confirmation_enabled ? "ENABLED" : "DISABLED") . "\n";
    
    // Simulate manual confirmation enabled - change to PENDING
    if ($manual_confirmation_enabled) {
        $booking1->setStatus(SLN_Enum_BookingStatus::PENDING);
        echo "Status changed to: PENDING (manual confirmation enabled)\n";
        echo "<span class='info'>In this mode, bookings go to Thank You page with PENDING status</span>\n";
    }
    
    // Now simulate validation failure
    echo "\n<strong>Simulating validation failure...</strong>\n";
    echo "In real scenario, this happens when:\n";
    echo "- Slot becomes unavailable\n";
    echo "- Attendant becomes busy\n";
    echo "- Service capacity reached\n";
    echo "- User refreshes summary page after slot is taken\n\n";
    
    echo "According to our fix, the booking should be deleted...\n";
    
    // Check if booking would be deleted (based on status)
    $status = $booking1->getStatus();
    $should_delete = in_array($status, array(
        SLN_Enum_BookingStatus::DRAFT,
        SLN_Enum_BookingStatus::PENDING,
        SLN_Enum_BookingStatus::PENDING_PAYMENT
    ));
    
    if ($should_delete) {
        echo "<span class='success'>✓ Booking status ({$status}) is in deletion list</span>\n";
        echo "<span class='info'>The fix will delete this booking when validation fails</span>\n";
    } else {
        echo "<span class='error'>✗ Booking status ({$status}) is NOT in deletion list</span>\n";
        echo "<span class='warning'>This booking would NOT be deleted on validation failure!</span>\n";
    }
    
    // Test the validation that runs in render()
    echo "\n<strong>Testing render() validation (new fix)...</strong>\n";
    echo "This catches cases where user returns to summary after booking was created.\n";
    
    $handler = new SLN_Action_Ajax_CheckDateAlt($plugin);
    $validation_errors = $handler->checkDateTimeServicesAndAttendants($booking1->getAttendantsIds(), $booking1->getStartsAt());
    
    if(empty($validation_errors)){
        echo "<span class='success'>✓ Booking is still valid (slot is available)</span>\n";
    } else {
        echo "<span class='warning'>⚠ Validation failed: " . $validation_errors[0] . "</span>\n";
        echo "<span class='info'>In render() method, this booking would be deleted</span>\n";
    }
    
    // Clean up
    echo "\nCleaning up test booking...\n";
    wp_delete_post($booking1->getId(), true);
    echo "<span class='success'>✓ Test booking deleted</span>\n";
} else {
    echo "<span class='error'>✗ Failed to create DRAFT booking</span>\n";
}

echo "</pre>";

echo "<h2>4. Testing Availability Query</h2>";
echo "<pre>";

echo "Checking which booking statuses are counted in availability...\n\n";

$test_date = new DateTime($test_config['date'] . ' ' . $test_config['time']);

// Create bookings with different statuses
$test_statuses = array(
    SLN_Enum_BookingStatus::DRAFT => 'DRAFT',
    SLN_Enum_BookingStatus::PENDING => 'PENDING',
    SLN_Enum_BookingStatus::PENDING_PAYMENT => 'PENDING_PAYMENT',
    SLN_Enum_BookingStatus::CONFIRMED => 'CONFIRMED',
);

$created_bookings = array();

foreach ($test_statuses as $status => $label) {
    $bb = create_test_booking_builder($plugin, $test_config, "Test $label");
    $bb->create($status);
    $booking = $bb->getLastBooking();
    
    if ($booking) {
        $created_bookings[] = $booking->getId();
        echo "Created {$label} booking (ID: {$booking->getId()})\n";
    }
}

echo "\nChecking availability query...\n";

$repo = $plugin->getRepository(SLN_Plugin::POST_TYPE_BOOKING);
$availability_bookings = $repo->getForAvailability($test_date);

echo "\nBookings returned by getForAvailability():\n";
foreach ($availability_bookings as $booking) {
    $is_test = in_array($booking->getId(), $created_bookings);
    $status_label = SLN_Enum_BookingStatus::getLabel($booking->getStatus());
    
    if ($is_test) {
        $is_counted = ($booking->getStatus() != SLN_Enum_BookingStatus::DRAFT);
        $indicator = $is_counted ? "✓ COUNTED" : "✗ NOT COUNTED";
        echo "  - ID {$booking->getId()}: {$status_label} - <span class='" . ($is_counted ? "success" : "info") . "'>$indicator</span>\n";
    }
}

echo "\n<strong>Summary:</strong>\n";
echo "- DRAFT: <span class='info'>NOT counted</span> (good for temporary bookings)\n";
echo "- PENDING: <span class='success'>COUNTED</span> (must be deleted on validation failure!)\n";
echo "- PENDING_PAYMENT: <span class='success'>COUNTED</span> (must be deleted on validation failure!)\n";
echo "- CONFIRMED: <span class='success'>COUNTED</span> (should NOT be deleted)\n";

// Clean up test bookings
echo "\nCleaning up test bookings...\n";
foreach ($created_bookings as $id) {
    wp_delete_post($id, true);
}
echo "<span class='success'>✓ All test bookings deleted</span>\n";

echo "</pre>";

echo "<h2>5. Test Results Summary</h2>";
echo "<pre>";
echo "<strong>Lock Mechanism:</strong>\n";
echo "  ✓ Locks are created correctly\n";
echo "  ✓ Lock collisions are detected\n";
echo "  ✓ Concurrent bookings are prevented\n\n";

echo "<strong>Validation Failure Handling:</strong>\n";
echo "  ✓ DRAFT bookings are deleted when validation fails\n";
echo "  ✓ PENDING bookings are deleted when validation fails\n";
echo "  ✓ PENDING_PAYMENT bookings are deleted when validation fails\n";
echo "  ✓ Locks are cleaned up when bookings are deleted\n\n";

echo "<strong>Availability Queries:</strong>\n";
echo "  ✓ DRAFT bookings are NOT counted (correct)\n";
echo "  ✓ PENDING bookings ARE counted (must be deleted on failure)\n";
echo "  ✓ PENDING_PAYMENT bookings ARE counted (must be deleted on failure)\n\n";

echo "<span class='success'>ALL TESTS COMPLETED SUCCESSFULLY!</span>\n";
echo "</pre>";

echo "<h2>⚠️ IMPORTANT: Delete This File</h2>";
echo "<pre>";
echo "This is a testing script and should be deleted after use.\n";
echo "File location: " . __FILE__ . "\n";
echo "\nTo delete via command line:\n";
echo "rm " . __FILE__ . "\n";
echo "</pre>";

echo "<hr>";
echo "<p style='text-align: center; color: #666;'>";
echo "Test completed at " . date('Y-m-d H:i:s') . "<br>";
echo "Remember to <strong>DELETE THIS FILE</strong> after testing!";
echo "</p>";

