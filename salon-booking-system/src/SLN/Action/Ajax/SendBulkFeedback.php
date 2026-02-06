<?php

class SLN_Action_Ajax_SendBulkFeedback extends SLN_Action_Ajax_Abstract
{
    public function execute()
    {
        try {
            // Security checks
            if (!current_user_can('manage_salon')) {
                wp_send_json_error(__('Not authorized', 'salon-booking-system'));
                return;
            }

            $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
            if (!wp_verify_nonce($nonce, 'sln_send_bulk_feedback')) {
                wp_send_json_error(__('Invalid request', 'salon-booking-system'));
                return;
            }

            $this->plugin->addLog('Manual feedback trigger started');

            // Get bookings that would receive feedback
            // Note: We use our own logic instead of Feedback::getBookings() 
            // because manual trigger should work regardless of automatic settings
            $bookings = $this->getEligibleBookings();
            $count = count($bookings);
            
            $this->plugin->addLog('Found ' . $count . ' eligible bookings');
            
            // Limit batch size to prevent timeouts and rate limiting
            $batch_limit = apply_filters('sln_bulk_feedback_batch_limit', 50);
            if ($count > $batch_limit) {
                $bookings = array_slice($bookings, 0, $batch_limit);
                $this->plugin->addLog('Limited to first ' . $batch_limit . ' bookings to prevent timeout');
            }
        
        if ($count === 0) {
            wp_send_json_success(array(
                'message' => __('No bookings eligible for feedback. All eligible bookings have already received feedback requests.', 'salon-booking-system'),
                'count' => 0
            ));
            return;
        }
        
            // Increase execution time limit for batch processing
        @set_time_limit(300); // 5 minutes
        
        // Send feedback to each booking
        $sent = 0;
        $errors = 0;
        $error_messages = array();
        $feedback_reminder_mail = $this->plugin->getSettings()->get('feedback_email');
        $feedback_reminder_sms = $this->plugin->getSettings()->get('feedback_sms');
        
        // Check if email sending is properly configured
        if ($feedback_reminder_mail) {
            $this->plugin->addLog('Checking email configuration...');
            
            // Check if WP Mail SMTP or similar plugin is active
            $mailer_info = '';
            if (class_exists('WPMailSMTP\Options')) {
                $mailer_info = 'WP Mail SMTP detected';
            } elseif (function_exists('wp_mail')) {
                $mailer_info = 'Using WordPress default wp_mail()';
            }
            
            $this->plugin->addLog('Email system: ' . $mailer_info);
        }
        
        $this->plugin->addLog('Starting to process ' . count($bookings) . ' bookings');
        
        // First, test if email sending works at all
        if ($feedback_reminder_mail) {
            $this->plugin->addLog('Testing email functionality before processing batch...');
            
            // Test with a simple wp_mail call
            $admin_email = get_option('admin_email');
            $test_subject = 'Salon Booking - Feedback Test';
            $test_message = 'This is a test email to verify email configuration works.';
            
            // Suppress errors and capture result
            $test_result = @wp_mail($admin_email, $test_subject, $test_message);
            
            if ($test_result === false) {
                $this->plugin->addLog('WARNING: Test email failed. WP Mail SMTP may be misconfigured.');
                // Get last error if available
                if (function_exists('error_get_last')) {
                    $last_error = error_get_last();
                    if ($last_error) {
                        $this->plugin->addLog('Last PHP error: ' . $last_error['message']);
                    }
                }
            } else {
                $this->plugin->addLog('Test email sent successfully to ' . $admin_email);
            }
        }
        
        // Use the same Feedback class that the automatic cron uses
        $feedbackAction = new SLN_Action_Feedback($this->plugin);
        
        foreach ($bookings as $index => $booking) {
            try {
                $this->plugin->addLog('Processing booking #' . $booking->getId() . ' (' . ($index + 1) . '/' . count($bookings) . ')');
                
                // Use the same send methods as automatic feedback
                if ($feedback_reminder_mail) {
                    $this->plugin->addLog('Sending email via Feedback class to booking #' . $booking->getId());
                    
                    // Log booking details before attempting send
                    $customer_email = $booking->getEmail();
                    $customer_name = trim($booking->getFirstname() . ' ' . $booking->getLastname());
                    $customer_phone = $booking->getPhone();
                    
                    $this->plugin->addLog('  Customer: ' . ($customer_name ? $customer_name : 'NO NAME'));
                    $this->plugin->addLog('  Email: ' . ($customer_email ? $customer_email : 'EMPTY'));
                    $this->plugin->addLog('  Phone: ' . ($customer_phone ? $customer_phone : 'EMPTY'));
                    $this->plugin->addLog('  Status: ' . $booking->getStatus());
                    $this->plugin->addLog('  Date: ' . $booking->getDate()->format('Y-m-d H:i'));
                    
                    // Check if email address exists
                    if (empty($customer_email)) {
                        $this->plugin->addLog('  ERROR: Customer email is empty, cannot send email');
                        $error_messages[] = 'Booking #' . $booking->getId() . ': No email address';
                        $errors++;
                        continue;
                    }
                    
                    // Test if wp_mail is functional before attempting
                    if (!function_exists('wp_mail')) {
                        throw new Exception('wp_mail() function not available');
                    }
                    
                    // Wrap in try-catch to catch any fatal errors from email sending
                    try {
                        $this->plugin->addLog('  Invoking Feedback class sendMail method...');
                        
                        // Use reflection to call private sendMail method
                        $reflection = new ReflectionClass($feedbackAction);
                        $sendMailMethod = $reflection->getMethod('sendMail');
                        $sendMailMethod->setAccessible(true);
                        
                        // Capture any output or errors
                        ob_start();
                        
                        $sendMailMethod->invoke($feedbackAction, $booking);
                        
                        $output = ob_get_clean();
                        
                        if (!empty($output)) {
                            $this->plugin->addLog('  sendMail produced output: ' . trim($output));
                        }
                        
                        $this->plugin->addLog('  Email sent successfully to booking #' . $booking->getId());
                    } catch (Exception $e) {
                        $this->plugin->addLog('  EXCEPTION during sendMail: ' . $e->getMessage());
                        $this->plugin->addLog('  Exception trace: ' . $e->getTraceAsString());
                        throw new Exception('Feedback sendMail() failed: ' . $e->getMessage());
                    } catch (Error $e) {
                        $this->plugin->addLog('  PHP ERROR during sendMail: ' . $e->getMessage());
                        $this->plugin->addLog('  Error trace: ' . $e->getTraceAsString());
                        throw new Exception('PHP Error in sendMail(): ' . $e->getMessage());
                    }
                }
                
                if ($feedback_reminder_sms) {
                    $this->plugin->addLog('Sending SMS via Feedback class to booking #' . $booking->getId());
                    // Use reflection to call private sendSms method
                    $reflection = new ReflectionClass($feedbackAction);
                    $sendSmsMethod = $reflection->getMethod('sendSms');
                    $sendSmsMethod->setAccessible(true);
                    $sendSmsMethod->invoke($feedbackAction, $booking);
                    $this->plugin->addLog('SMS sent successfully to booking #' . $booking->getId());
                }
                
                // Mark as sent (same as automatic feedback)
                $booking->setMeta('feedback', true);
                $this->plugin->addLog('Marked booking #' . $booking->getId() . ' as feedback sent');
                $sent++;
                
            } catch (Exception $e) {
                $errors++;
                $error_message = 'Booking #' . $booking->getId() . ': ' . $e->getMessage();
                $error_messages[] = $error_message;
                $this->plugin->addLog('Manual feedback failed for ' . $error_message);
                
                // Continue to next booking instead of stopping
                continue;
            }
        }
        
        $this->plugin->addLog('Batch processing completed - Sent: ' . $sent . ', Errors: ' . $errors);
        
        $batch_limit = apply_filters('sln_bulk_feedback_batch_limit', 50);
        $has_more = $count > $batch_limit;
        $remaining = $has_more ? ($count - $batch_limit) : 0;
        
        $message = sprintf(
            _n(
                'Feedback sent to %1$d booking.',
                'Feedback sent to %1$d bookings.',
                $sent,
                'salon-booking-system'
            ),
            $sent
        );
        
        if ($errors > 0) {
            $message .= ' ' . sprintf(
                _n(
                    '%d error occurred.',
                    '%d errors occurred.',
                    $errors,
                    'salon-booking-system'
                ),
                $errors
            );
        }
        
        if ($has_more) {
            $message .= ' ' . sprintf(
                __('%d more bookings remain. Click "Send" again to continue.', 'salon-booking-system'),
                $remaining
            );
        }
        
            $this->plugin->addLog('Manual feedback trigger completed - Sent: ' . $sent . ', Errors: ' . $errors);

            $response_data = array(
                'message' => $message,
                'sent' => $sent,
                'errors' => $errors,
                'total' => $count
            );
            
            // Include error details if any
            if (!empty($error_messages)) {
                $response_data['error_details'] = $error_messages;
                $this->plugin->addLog('Error details: ' . implode('; ', $error_messages));
            }

            wp_send_json_success($response_data);
            
        } catch (Exception $e) {
            $error_message = $e->getMessage();
            $this->plugin->addLog('Manual feedback trigger failed: ' . $error_message);
            $this->plugin->addLog('Stack trace: ' . $e->getTraceAsString());
            
            wp_send_json_error(array(
                'message' => sprintf(
                    __('Failed to send feedback: %s', 'salon-booking-system'),
                    $error_message
                ),
                'error' => $error_message,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ));
        }
    }
    
    /**
     * Get eligible bookings for manual feedback sending
     * 
     * Uses more permissive logic than automatic cron because:
     * - Admin is manually triggering, so automatic settings don't apply
     * - Should work even if automatic feedback is disabled
     * - Allows sending to all eligible bookings in last 30 days (not just 7)
     * 
     * @return SLN_Wrapper_Booking[]
     */
    private function getEligibleBookings()
    {
        // Look at bookings from the last 30 days (more generous than automatic 7 days)
        $start_day = new SLN_DateTime( '-30 days' );
        $end_day = new SLN_DateTime( 'now' ); // Include today

        $statuses = array( 
            SLN_Enum_BookingStatus::PAID, 
            SLN_Enum_BookingStatus::CONFIRMED, 
            SLN_Enum_BookingStatus::PAY_LATER 
        );

        /** @var SLN_Repository_BookingRepository $repo */
        $repo = $this->plugin->getRepository( SLN_Plugin::POST_TYPE_BOOKING );
        $tmp = $repo->get(
            array(
                'post_status' => $statuses,
                'day@min'     => $start_day,
                'day@max'     => $end_day,
            )
        );
        
        $ret = array();
        $now = new SLN_DateTime('now');
        $custom_feedback_url = $this->plugin->getSettings()->get( 'custom_feedback_url' );
        
        // For manual trigger: check what will actually be sent
        $feedback_reminder_mail = $this->plugin->getSettings()->get( 'feedback_email' );
        $feedback_reminder_sms = $this->plugin->getSettings()->get( 'feedback_sms' );
        
        // Log settings for debugging
        $this->plugin->addLog('Manual feedback - Email enabled: ' . ($feedback_reminder_mail ? 'yes' : 'no'));
        $this->plugin->addLog('Manual feedback - SMS enabled: ' . ($feedback_reminder_sms ? 'yes' : 'no'));
        $this->plugin->addLog('Manual feedback - Custom URL: ' . ($custom_feedback_url ? $custom_feedback_url : 'not set'));
        $this->plugin->addLog('Manual feedback - Found ' . count($tmp) . ' bookings in date range');
        
        foreach ( $tmp as $booking ) {
            $done = $booking->getMeta('feedback');
            
            // Skip if feedback already sent
            if ($done) {
                continue;
            }
            
            // Check if booking was at least 1 day ago
            $booking_date = $booking->getDate();
            $days_since_booking = $now->diff($booking_date)->days;
            
            if ($days_since_booking < 1) {
                continue; // Too recent
            }

            $isRegisteredCustomer = SLN_Wrapper_Customer::isCustomer( $booking->getUserId() );
            
            // Registered customers can always receive feedback (they can use customer login hash)
            if ( $isRegisteredCustomer ) {
                $ret[] = $booking;
                $this->plugin->addLog('Manual feedback - Including registered customer booking #' . $booking->getId());
            }
            // Guest bookings: more permissive logic for manual trigger
            else {
                $hasEmail = !empty($booking->getEmail());
                $hasPhone = !empty($booking->getPhone());
                
                // For manual trigger: include guest if they have contact info
                // AND either email/SMS will be sent OR custom URL is configured
                $canReceiveEmail = $feedback_reminder_mail && $hasEmail;
                $canReceiveSms = $feedback_reminder_sms && $hasPhone;
                $hasCustomUrl = !empty($custom_feedback_url);
                
                // Include if: (can receive email OR SMS) AND (has custom URL OR is registered)
                // For guests: must have custom URL to submit feedback
                if ( ($canReceiveEmail || $canReceiveSms) && $hasCustomUrl ) {
                    $ret[] = $booking;
                    $this->plugin->addLog('Manual feedback - Including guest booking #' . $booking->getId());
                } else {
                    $reason = '';
                    if (!$hasCustomUrl) {
                        $reason = 'no custom feedback URL (required for guests)';
                    } elseif (!$canReceiveEmail && !$canReceiveSms) {
                        $reason = 'email/SMS not enabled or no contact info';
                    }
                    $this->plugin->addLog('Manual feedback - Skipping guest booking #' . $booking->getId() . ' - ' . $reason);
                }
            }
        }
        
        $this->plugin->addLog('Manual feedback - Total eligible bookings: ' . count($ret));
        return $ret;
    }
}
