<?php
/**
 * Test Suite for Email Notification Bug
 * Tests admin email notifications when attendants are not assigned
 */

class TestEmailNotifications
{
    private $plugin;
    private $original_settings;
    
    public function __construct()
    {
        $this->plugin = SLN_Plugin::getInstance();
        $this->original_settings = $this->plugin->getSettings();
    }
    
    /**
     * Run all tests
     */
    public function runAllTests()
    {
        echo "🧪 Starting Email Notification Tests...\n\n";
        
        $this->testAutoAssignmentScenario();
        $this->testDisabledAttendantsScenario();
        $this->testSkipAttendantsScenario();
        $this->testBackendOnlyAttendantsScenario();
        $this->testModifiedBookingScenario();
        $this->testRescheduledBookingScenario();
        
        echo "\n✅ All tests completed!\n";
    }
    
    /**
     * Test 1: Auto-Assignment Scenario
     */
    public function testAutoAssignmentScenario()
    {
        echo "🔍 Test 1: Auto-Assignment Scenario\n";
        
        // Setup: Enable attendant emails, create booking with auto-assignment
        $this->setupTestEnvironment([
            'attendant_email' => true,
            'attendants_enabled' => true,
            'skip_attendants_enabled' => false,
            'attendants_enabled_only_backend' => false
        ]);
        
        // Create booking with auto-assignment (no attendant)
        $booking = $this->createTestBooking([
            'attendant_auto' => true,
            'services' => [
                ['service' => 1, 'attendant' => 0] // No attendant assigned
            ]
        ]);
        
        // Test email behavior
        $result = $this->testAdminEmailSending($booking);
        
        if ($result['admin_email_sent']) {
            echo "   ✅ PASS: Admin email sent correctly\n";
        } else {
            echo "   ❌ FAIL: Admin email NOT sent (BUG CONFIRMED)\n";
            echo "   📧 Email recipients: " . $result['recipients'] . "\n";
        }
        
        $this->cleanupTestBooking($booking);
        echo "\n";
    }
    
    /**
     * Test 2: Disabled Attendants Scenario
     */
    public function testDisabledAttendantsScenario()
    {
        echo "🔍 Test 2: Disabled Attendants Scenario\n";
        
        // Setup: Enable attendant emails, disable attendants for service
        $this->setupTestEnvironment([
            'attendant_email' => true,
            'attendants_enabled' => true
        ]);
        
        // Create service with attendants disabled
        $service = $this->createTestService(['attendants' => false]);
        
        // Create booking with service that doesn't require attendants
        $booking = $this->createTestBooking([
            'services' => [
                ['service' => $service->getId(), 'attendant' => 0]
            ]
        ]);
        
        // Test email behavior
        $result = $this->testAdminEmailSending($booking);
        
        if ($result['admin_email_sent']) {
            echo "   ✅ PASS: Admin email sent correctly\n";
        } else {
            echo "   ❌ FAIL: Admin email NOT sent (BUG CONFIRMED)\n";
            echo "   📧 Email recipients: " . $result['recipients'] . "\n";
        }
        
        $this->cleanupTestBooking($booking);
        $this->cleanupTestService($service);
        echo "\n";
    }
    
    /**
     * Test 3: Skip Attendants Scenario
     */
    public function testSkipAttendantsScenario()
    {
        echo "🔍 Test 3: Skip Attendants Scenario\n";
        
        // Setup: Enable skip attendants setting
        $this->setupTestEnvironment([
            'attendant_email' => true,
            'attendants_enabled' => true,
            'skip_attendants_enabled' => true
        ]);
        
        // Create booking (attendant step will be skipped)
        $booking = $this->createTestBooking([
            'services' => [
                ['service' => 1, 'attendant' => 0]
            ]
        ]);
        
        // Test email behavior
        $result = $this->testAdminEmailSending($booking);
        
        if ($result['admin_email_sent']) {
            echo "   ✅ PASS: Admin email sent correctly\n";
        } else {
            echo "   ❌ FAIL: Admin email NOT sent (BUG CONFIRMED)\n";
            echo "   📧 Email recipients: " . $result['recipients'] . "\n";
        }
        
        $this->cleanupTestBooking($booking);
        echo "\n";
    }
    
    /**
     * Test 4: Backend-Only Attendants Scenario
     */
    public function testBackendOnlyAttendantsScenario()
    {
        echo "🔍 Test 4: Backend-Only Attendants Scenario\n";
        
        // Setup: Enable backend-only attendants
        $this->setupTestEnvironment([
            'attendant_email' => true,
            'attendants_enabled' => true,
            'attendants_enabled_only_backend' => true
        ]);
        
        // Create booking (no attendant selection on frontend)
        $booking = $this->createTestBooking([
            'services' => [
                ['service' => 1, 'attendant' => 0]
            ]
        ]);
        
        // Test email behavior
        $result = $this->testAdminEmailSending($booking);
        
        if ($result['admin_email_sent']) {
            echo "   ✅ PASS: Admin email sent correctly\n";
        } else {
            echo "   ❌ FAIL: Admin email NOT sent (BUG CONFIRMED)\n";
            echo "   📧 Email recipients: " . $result['recipients'] . "\n";
        }
        
        $this->cleanupTestBooking($booking);
        echo "\n";
    }
    
    /**
     * Test 5: Modified Booking Scenario (Should Work)
     */
    public function testModifiedBookingScenario()
    {
        echo "🔍 Test 5: Modified Booking Scenario (Should Work)\n";
        
        // Setup: Enable attendant emails
        $this->setupTestEnvironment([
            'attendant_email' => true,
            'attendants_enabled' => true
        ]);
        
        // Create booking with no attendant
        $booking = $this->createTestBooking([
            'services' => [
                ['service' => 1, 'attendant' => 0]
            ]
        ]);
        
        // Test modified booking email (should work)
        $result = $this->testModifiedBookingEmail($booking);
        
        if ($result['admin_email_sent']) {
            echo "   ✅ PASS: Modified booking admin email sent correctly\n";
        } else {
            echo "   ❌ FAIL: Modified booking admin email NOT sent\n";
        }
        
        $this->cleanupTestBooking($booking);
        echo "\n";
    }
    
    /**
     * Test 6: Rescheduled Booking Scenario (Should Work)
     */
    public function testRescheduledBookingScenario()
    {
        echo "🔍 Test 6: Rescheduled Booking Scenario (Should Work)\n";
        
        // Setup: Enable attendant emails
        $this->setupTestEnvironment([
            'attendant_email' => true,
            'attendants_enabled' => true
        ]);
        
        // Create booking with no attendant
        $booking = $this->createTestBooking([
            'services' => [
                ['service' => 1, 'attendant' => 0]
            ]
        ]);
        
        // Test rescheduled booking email (should work)
        $result = $this->testRescheduledBookingEmail($booking);
        
        if ($result['admin_email_sent']) {
            echo "   ✅ PASS: Rescheduled booking admin email sent correctly\n";
        } else {
            echo "   ❌ FAIL: Rescheduled booking admin email NOT sent\n";
        }
        
        $this->cleanupTestBooking($booking);
        echo "\n";
    }
    
    /**
     * Setup test environment with specific settings
     */
    private function setupTestEnvironment($settings)
    {
        foreach ($settings as $key => $value) {
            $this->plugin->getSettings()->set($key, $value);
        }
    }
    
    /**
     * Create test booking
     */
    private function createTestBooking($data)
    {
        $bb = new SLN_Wrapper_Booking_Builder($this->plugin);
        
        // Set basic booking data
        $bb->setDate('2024-12-20');
        $bb->setTime('10:00');
        $bb->set('firstname', 'Test');
        $bb->set('lastname', 'Customer');
        $bb->set('email', 'test@example.com');
        $bb->set('phone', '1234567890');
        
        // Set services and attendants
        if (isset($data['services'])) {
            $bb->set('services', $data['services']);
        }
        
        // Create booking
        $bb->create();
        return $bb->getLastBooking();
    }
    
    /**
     * Create test service
     */
    private function createTestService($settings)
    {
        $service_data = [
            'post_title' => 'Test Service',
            'post_type' => SLN_Plugin::POST_TYPE_SERVICE,
            'post_status' => 'publish'
        ];
        
        $service_id = wp_insert_post($service_data);
        
        if (isset($settings['attendants'])) {
            update_post_meta($service_id, '_sln_attendants', $settings['attendants'] ? 0 : 1);
        }
        
        return $this->plugin->createService($service_id);
    }
    
    /**
     * Test admin email sending for new booking
     */
    private function testAdminEmailSending($booking)
    {
        // Mock the email sending to capture recipients
        $captured_recipients = [];
        $admin_email_sent = false;
        
        // Hook into email sending
        add_filter('wp_mail', function($args) use (&$captured_recipients, &$admin_email_sent) {
            $captured_recipients[] = $args['to'];
            
            // Check if admin email is being sent
            $admin_email = $this->plugin->getSettings()->getSalonEmail();
            if (strpos($args['to'], $admin_email) !== false) {
                $admin_email_sent = true;
            }
            
            return $args;
        });
        
        // Trigger email sending
        $messages = $this->plugin->messages();
        $messages->sendByStatus($booking, $booking->getStatus());
        
        // Remove hook
        remove_all_filters('wp_mail');
        
        return [
            'admin_email_sent' => $admin_email_sent,
            'recipients' => implode(', ', $captured_recipients)
        ];
    }
    
    /**
     * Test modified booking email
     */
    private function testModifiedBookingEmail($booking)
    {
        // Mock the email sending
        $admin_email_sent = false;
        
        add_filter('wp_mail', function($args) use (&$admin_email_sent) {
            $admin_email = $this->plugin->getSettings()->getSalonEmail();
            if (strpos($args['to'], $admin_email) !== false) {
                $admin_email_sent = true;
            }
            return $args;
        });
        
        // Trigger modified booking email
        $messages = $this->plugin->messages();
        $messages->sendBookingModified($booking);
        
        remove_all_filters('wp_mail');
        
        return ['admin_email_sent' => $admin_email_sent];
    }
    
    /**
     * Test rescheduled booking email
     */
    private function testRescheduledBookingEmail($booking)
    {
        // Mock the email sending
        $admin_email_sent = false;
        
        add_filter('wp_mail', function($args) use (&$admin_email_sent) {
            $admin_email = $this->plugin->getSettings()->getSalonEmail();
            if (strpos($args['to'], $admin_email) !== false) {
                $admin_email_sent = true;
            }
            return $args;
        });
        
        // Trigger rescheduled booking email
        $messages = $this->plugin->messages();
        $messages->sendRescheduledMail($booking);
        
        remove_all_filters('wp_mail');
        
        return ['admin_email_sent' => $admin_email_sent];
    }
    
    /**
     * Cleanup test booking
     */
    private function cleanupTestBooking($booking)
    {
        if ($booking && $booking->getId()) {
            wp_delete_post($booking->getId(), true);
        }
    }
    
    /**
     * Cleanup test service
     */
    private function cleanupTestService($service)
    {
        if ($service && $service->getId()) {
            wp_delete_post($service->getId(), true);
        }
    }
    
    /**
     * Restore original settings
     */
    public function __destruct()
    {
        if ($this->original_settings) {
            // Restore original settings if needed
        }
    }
}

// Run tests if called directly
if (php_sapi_name() === 'cli' || (isset($_GET['run_tests']) && $_GET['run_tests'] === '1')) {
    $tester = new TestEmailNotifications();
    $tester->runAllTests();
}

