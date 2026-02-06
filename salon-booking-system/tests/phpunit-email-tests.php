<?php
/**
 * PHPUnit Tests for Email Notification Bug
 * Comprehensive unit tests for the admin email notification issue
 */

use PHPUnit\Framework\TestCase;

class EmailNotificationBugTest extends TestCase
{
    private $plugin;
    private $original_settings;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Initialize plugin
        $this->plugin = SLN_Plugin::getInstance();
        $this->original_settings = $this->plugin->getSettings();
        
        // Mock WordPress functions
        $this->mockWordPressFunctions();
    }
    
    protected function tearDown(): void
    {
        // Restore original settings
        if ($this->original_settings) {
            // Restore settings if needed
        }
        
        parent::tearDown();
    }
    
    /**
     * Test that admin emails are sent when attendants are assigned
     */
    public function testAdminEmailSentWithAttendants()
    {
        // Setup: Enable attendant emails, assign attendant
        $this->setupTestEnvironment([
            'attendant_email' => true,
            'attendants_enabled' => true
        ]);
        
        // Create booking with attendant
        $booking = $this->createTestBooking([
            'services' => [
                ['service' => 1, 'attendant' => 123] // Attendant assigned
            ]
        ]);
        
        // Test email sending
        $result = $this->testEmailSending($booking);
        
        $this->assertTrue($result['admin_email_sent'], 'Admin email should be sent when attendant is assigned');
        $this->assertContains('admin@salon.com', $result['recipients'], 'Admin email should be in recipients');
    }
    
    /**
     * Test that admin emails are NOT sent when no attendants (BUG)
     */
    public function testAdminEmailNotSentWithoutAttendants()
    {
        // Setup: Enable attendant emails, no attendant assigned
        $this->setupTestEnvironment([
            'attendant_email' => true,
            'attendants_enabled' => true
        ]);
        
        // Create booking without attendant
        $booking = $this->createTestBooking([
            'services' => [
                ['service' => 1, 'attendant' => 0] // No attendant assigned
            ]
        ]);
        
        // Test email sending
        $result = $this->testEmailSending($booking);
        
        // This should fail (bug confirmed)
        $this->assertFalse($result['admin_email_sent'], 'BUG: Admin email should be sent even without attendants');
        $this->assertNotContains('admin@salon.com', $result['recipients'], 'BUG: Admin email should be in recipients');
    }
    
    /**
     * Test auto-assignment scenario
     */
    public function testAutoAssignmentScenario()
    {
        // Setup: Enable attendant emails, auto-assignment
        $this->setupTestEnvironment([
            'attendant_email' => true,
            'attendants_enabled' => true,
            'skip_attendants_enabled' => false
        ]);
        
        // Create booking with auto-assignment
        $booking = $this->createTestBooking([
            'services' => [
                ['service' => 1, 'attendant' => 0] // Auto-assignment, no attendant
            ],
            'attendant_auto' => true
        ]);
        
        // Test email sending
        $result = $this->testEmailSending($booking);
        
        // This should fail (bug confirmed)
        $this->assertFalse($result['admin_email_sent'], 'BUG: Admin email should be sent with auto-assignment');
    }
    
    /**
     * Test disabled attendants scenario
     */
    public function testDisabledAttendantsScenario()
    {
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
        
        // Test email sending
        $result = $this->testEmailSending($booking);
        
        // This should fail (bug confirmed)
        $this->assertFalse($result['admin_email_sent'], 'BUG: Admin email should be sent when service has attendants disabled');
    }
    
    /**
     * Test skip attendants scenario
     */
    public function testSkipAttendantsScenario()
    {
        // Setup: Enable skip attendants
        $this->setupTestEnvironment([
            'attendant_email' => true,
            'attendants_enabled' => true,
            'skip_attendants_enabled' => true
        ]);
        
        // Create booking (attendant step skipped)
        $booking = $this->createTestBooking([
            'services' => [
                ['service' => 1, 'attendant' => 0]
            ]
        ]);
        
        // Test email sending
        $result = $this->testEmailSending($booking);
        
        // This should fail (bug confirmed)
        $this->assertFalse($result['admin_email_sent'], 'BUG: Admin email should be sent when attendants are skipped');
    }
    
    /**
     * Test backend-only attendants scenario
     */
    public function testBackendOnlyAttendantsScenario()
    {
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
        
        // Test email sending
        $result = $this->testEmailSending($booking);
        
        // This should fail (bug confirmed)
        $this->assertFalse($result['admin_email_sent'], 'BUG: Admin email should be sent with backend-only attendants');
    }
    
    /**
     * Test that modified bookings work correctly
     */
    public function testModifiedBookingWorks()
    {
        // Setup: Enable attendant emails
        $this->setupTestEnvironment([
            'attendant_email' => true,
            'attendants_enabled' => true
        ]);
        
        // Create booking without attendant
        $booking = $this->createTestBooking([
            'services' => [
                ['service' => 1, 'attendant' => 0]
            ]
        ]);
        
        // Test modified booking email
        $result = $this->testModifiedBookingEmail($booking);
        
        // This should work (modified bookings have correct logic)
        $this->assertTrue($result['admin_email_sent'], 'Modified booking admin email should be sent');
    }
    
    /**
     * Test that rescheduled bookings work correctly
     */
    public function testRescheduledBookingWorks()
    {
        // Setup: Enable attendant emails
        $this->setupTestEnvironment([
            'attendant_email' => true,
            'attendants_enabled' => true
        ]);
        
        // Create booking without attendant
        $booking = $this->createTestBooking([
            'services' => [
                ['service' => 1, 'attendant' => 0]
            ]
        ]);
        
        // Test rescheduled booking email
        $result = $this->testRescheduledBookingEmail($booking);
        
        // This should work (rescheduled bookings have correct logic)
        $this->assertTrue($result['admin_email_sent'], 'Rescheduled booking admin email should be sent');
    }
    
    /**
     * Test the specific bug in summary_admin.php
     */
    public function testSummaryAdminTemplateBug()
    {
        // Test the exact buggy logic from summary_admin.php
        $attendantEmailOption = true;
        $booking = $this->createMockBooking();
        
        // This is the buggy condition from line 70-73
        $data = [];
        
        if ($attendantEmailOption && ($attendants = $booking->getAttendants())) {
            // This block is skipped when no attendants
            foreach ($attendants as $attendant) {
                // Process attendant emails
            }
        }
        
        // The bug: if no attendants, $data['to'] is not set
        $this->assertFalse(isset($data['to']), 'BUG: $data[\'to\'] should be set even without attendants');
        
        // The fix: always set admin email
        if (!isset($data['to'])) {
            $data['to'] = 'admin@salon.com';
        }
        
        $this->assertEquals('admin@salon.com', $data['to'], 'Admin email should always be set');
    }
    
    /**
     * Setup test environment
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
     * Create mock booking for testing
     */
    private function createMockBooking()
    {
        $booking = $this->createMock('SLN_Wrapper_Booking');
        $booking->method('getAttendants')->willReturn([]); // No attendants
        $booking->method('getId')->willReturn(999);
        $booking->method('getStatus')->willReturn('pending');
        $booking->method('getDate')->willReturn('2024-12-20');
        $booking->method('getTime')->willReturn('10:00');
        $booking->method('getEmail')->willReturn('test@example.com');
        $booking->method('getDisplayName')->willReturn('Test Customer');
        
        return $booking;
    }
    
    /**
     * Test email sending
     */
    private function testEmailSending($booking)
    {
        $captured_recipients = [];
        $admin_email_sent = false;
        
        // Mock email sending
        add_filter('wp_mail', function($args) use (&$captured_recipients, &$admin_email_sent) {
            $captured_recipients[] = $args['to'];
            
            $admin_email = $this->plugin->getSettings()->getSalonEmail();
            if (strpos($args['to'], $admin_email) !== false) {
                $admin_email_sent = true;
            }
            
            return $args;
        });
        
        // Trigger email sending
        $messages = $this->plugin->messages();
        $messages->sendByStatus($booking, $booking->getStatus());
        
        // Remove filter
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
     * Mock WordPress functions
     */
    private function mockWordPressFunctions()
    {
        // Mock wp_mail function
        if (!function_exists('wp_mail')) {
            function wp_mail($to, $subject, $message, $headers = '') {
                // Mock implementation
                return true;
            }
        }
        
        // Mock other WordPress functions as needed
    }
}

