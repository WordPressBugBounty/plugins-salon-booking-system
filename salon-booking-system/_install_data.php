<?php
// Helper function to get next available booking date based on configured availability
function getNextAvailableDate($availableDays) {
    $date = new DateTime();
    $maxAttempts = 8; // Check up to 7 days forward (8 iterations: skip day 0, check days 1-7)
    
    for ($i = 0; $i < $maxAttempts; $i++) {
        $dayOfWeek = (int)$date->format('w'); // 0 (Sunday) through 6 (Saturday)
        
        // ✅ FIX: Skip today to avoid creating bookings with past times
        // Sample bookings use times like 09:30, 10:30, 12:30
        // If it's currently later than these times, today's date would create invalid bookings
        if ($i === 0) {
            // First iteration is today - skip to tomorrow
            $date->modify('+1 day');
            continue;
        }
        
        // Check if current day is in available days
        if (isset($availableDays[$dayOfWeek]) && $availableDays[$dayOfWeek] == 1) {
            return $date->format('Y-m-d');
        }
        
        // Move to next day
        $date->modify('+1 day');
    }
    
    // Fallback: Find the next occurrence of any available day
    // If we reach here, none of the next 7 days are available
    if (!empty($availableDays)) {
        // Find the first day of week that is actually marked as available (value == 1)
        $firstAvailableDayOfWeek = null;
        foreach ($availableDays as $dayOfWeek => $isAvailable) {
            if ($isAvailable == 1) {
                $firstAvailableDayOfWeek = (int)$dayOfWeek;
                break;
            }
        }
        
        // If we found an available day of week, calculate next occurrence
        if ($firstAvailableDayOfWeek !== null) {
            $date = new DateTime();
            $date->modify('+1 day'); // ✅ FIX: Start from tomorrow
            $todayDayOfWeek = (int)$date->format('w');
            
            // Calculate days to add from TOMORROW to reach first available day
            $daysToAdd = ($firstAvailableDayOfWeek - $todayDayOfWeek + 7) % 7;
            
            // If $daysToAdd = 0, we're already on that day of week
            if ($daysToAdd === 0) {
                return $date->format('Y-m-d');
            }
            
            $date->modify("+{$daysToAdd} days");
            return $date->format('Y-m-d');
        }
    }
    
    // Ultimate fallback if no available days configured (shouldn't happen)
    // Return 7 days from today as a safe default
    return (new DateTime())->modify('+7 days')->format('Y-m-d');
}

return array(
    'settings' => array(
        'date_format'       => 'default',
        'time_format'       => 'default',
        'hours_before_from' => '+1 day',
        'hours_before_to'   => '+1 month',
        'interval'          => 30,
        'availability_mode' => 'advanced',
        'attendant_enabled' => '1',
        'form_steps_alt_order' => '1',
        'disabled_message'  => 'Booking is not available at the moment, please contact us at ' . get_bloginfo('admin_email'),
        'gen_name'         => '',
        'gen_email'        => '',
        'gen_phone'        => '',
        'gen_address'      => '',
        'gen_timetable'    => 'In case of delay we\'ll keep your "seat" for 15 minutes, after that you\'ll loose your priority.',
        'last_step_note'   => 'You will receive a booking confirmation by email.If you do not receive an email in 5 minutes, check your Junk Mail or Spam Folder. If you need to change your reservation, please call <strong>[SALON PHONE]</strong> or send an e-mail to <strong>[SALON EMAIL]</strong>.',
        'soc_facebook'     => 'http://www.facebook.com',
        'soc_twitter'      => 'http://www.twitter.com',
        'soc_google'       => 'http://www.google.it',
        'ajax_enabled'     => true,
        'booking'          => true,
        'thankyou'         => true,
        'availabilities'   => array(
            array(
            "days" => array(
                2 => 1,
                3 => 1,
                4 => 1,
                5 => 1,
                6 => 1
            ),
            "from" => array("08:00", "13:00"),
            "to"   => array("13:00", "20:00")
            )
        ),
        'sms_notification_message' => SLN_Admin_SettingTabs_GeneralTab::getDefaultSmsNotificationMessage(),
        'sms_notification_message_modified' => SLN_Admin_SettingTabs_GeneralTab::getDefaultSmsNotificationMessageModified(),
        'email_subject'    => 'Your booking reminder for [DATE] at [TIME] at [SALON NAME]',
        'booking_update_message' => 'Hi [NAME],\r\ntake note of the details of your reservation at [SALON NAME]',
        'email_nb_subject' => 'Your booking for [DATE] at [TIME] at [SALON NAME]',
        'new_booking_message' => 'Hi [NAME],\r\ntake note of the details of your reservation at [SALON NAME]',
        'follow_up_message' => 'Hi [NAME],\r\nIt\'s been a while since your last visit, would you like to book a new appointment with us?\r\n\r\nWe look forward to seeing you again.',
        'pay_currency'     => 'USD',
        'pay_currency_pos' => 'right',
        'pay_decimal_separator'  => '.',
        'pay_thousand_separator' => ',',
        'pay_paypal_email' => 'test@test.com',
        'pay_paypal_test'  => true,
        'pay_stripe_method' => 'card',
        'parallels_hour'   => 1,
        'sln_db_version' => SLN_VERSION,
        'onesignal_notification_message' => SLN_Admin_SettingTabs_GeneralTab::getDefaultOnesignalNotificationMessage(),
	    'enable_discount_system' => true,
        'debug'                  => false,
    ),
    'posts'    => array(
        array(
            'post' => array(
                'post_title'   => 'Mario',
                'post_excerpt' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer fringilla sollicitudin sapien a finibus. Duis fringilla lobortis aliquam. Cras volutpat risus metus, ut varius nulla pulvinar ut. ',
                'post_status'  => 'publish',
                'post_type'    => 'sln_attendant'
            ),
        ),
        array(
            'post' => array(
                'post_title'   => 'Pablo',
                'post_excerpt' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer fringilla sollicitudin sapien a finibus. Duis fringilla lobortis aliquam. Cras volutpat risus metus, ut varius nulla pulvinar ut. ',
                'post_status'  => 'publish',
                'post_type'    => 'sln_attendant'
            ),
        ),

        array(
            'post' => array(
                'post_title'   => 'Beard Trim',
                'post_excerpt' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer fringilla sollicitudin sapien a finibus. Duis fringilla lobortis aliquam. Cras volutpat risus metus, ut varius nulla pulvinar ut.',
                'post_status'  => 'publish',
                'post_type'    => 'sln_service'
            ),
            'meta' => array(
                '_sln_service_price' => 15,
                '_sln_service_unit'  => 3,
                '_sln_service_duration'   => '00:30',
                '_sln_service_order' => '0',
            )
        ),
        array(
            'post' => array(
                'post_title'   => 'Haircut',
                'post_excerpt' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer fringilla sollicitudin sapien a finibus. Duis fringilla lobortis aliquam. Cras volutpat risus metus, ut varius nulla pulvinar ut. ',
                'post_status'  => 'publish',
                'post_type'    => 'sln_service',
            ),
            'meta' => array(
                '_sln_service_price'      => 10.11,
                '_sln_service_unit'       => 2,
                '_sln_service_duration'   => '00:30',
                '_sln_service_secondary'  => false,
                '_sln_service_notav_from' => '11',
                '_sln_service_notav_to'   => '15',
                '_sln_service_order'      => '0',
            )
        ),
        array(
            'post' => array(
                'post_title'   => 'Shampoo',
                'post_excerpt' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer fringilla sollicitudin sapien a finibus. Duis fringilla lobortis aliquam. Cras volutpat risus metus, ut varius nulla pulvinar ut.',
                'post_status'  => 'publish',
                'post_type'    => 'sln_service',
            ),
            'meta' => array(
                '_sln_service_price'      => 29.99,
                '_sln_service_unit'       => 2,
                '_sln_service_duration'   => '01:00',
                '_sln_service_secondary'  => true,
                '_sln_service_notav_from' => '11',
                '_sln_service_notav_to'   => '15',
                '_sln_service_order'      => '0',
            )
        ),
        'booking'  => array(
            'post' => array(
                'post_title'     => 'Booking',
                'post_content'   => '[salon/]',
                'post_status'    => 'publish',
                'post_type'      => 'page',
                'comment_status' => 'closed',
                'ping_status'    => 'closed',
            ),
            'meta' => array()
        ),
        'thankyou' => array(
            'post' => array(
                'post_title'     => 'Thank you for booking',
                'post_excerpt'   => 'thank you',
                'post_status'    => 'publish',
                'post_type'      => 'page',
                'comment_status' => 'closed',
                'ping_status'    => 'closed',
            ),
            'meta' => array()
        ),
        'bookingmyaccount'  => array(   // algolplus
            'post' => array(
                'post_title'     => 'Booking My Account',
                'post_content'   => '[salon_booking_my_account]',
                'post_status'    => 'publish',
                'post_type'      => 'page',
                'comment_status' => 'closed',
                'ping_status'    => 'closed',
            ),
            'meta' => array()
        ),
    ),
    'bookings' => array(
        
        array(
            'post' => array(
                'post_title' => 'Booking example 1',
                'post_excerpt' => '',
                'post_status' => 'sln-b-paid',
                'post_type' => 'sln_booking',
            ),
            'meta' => array(
                '_sln_booking_services' => array(
                    array(
                        'attendant' => 0,
                        'service' => 2,  // Beard Trim (array index 2, price $15)
                    )
                ),
                '_sln_booking_services_processed' => 1,
                '_sln_booking_duration' => '00:30',
                '_sln_calendar_attendants_event_id' => array(),
                '_edit_last' => '1',
                '_sln_booking_amount' => 15,
                '_sln_booking_date' => getNextAvailableDate(array(2 => 1, 3 => 1, 4 => 1, 5 => 1, 6 => 1)),
                '_sln_booking_time' => '09:30',
                '_sln_booking_firstname' => 'Carlo',
                '_sln_booking_lastname' => 'Verdi',
                '_sln_booking_email' => 'carlo@verdi.com',
                '_sln_booking_phone' => '324543678',
            )
        ),
        array(
            'post' => array(
                'post_title' => 'Booking example 2',
                'post_excerpt' => '',
                'post_status' => 'sln-b-confirmed',
                'post_type' => 'sln_booking',
            ),
            'meta' => array(
                '_sln_booking_services' => array(
                    array(
                        'attendant' => 1,
                        'service' => 3,  // Haircut (array index 3, price $10.11)
                    )
                ),
                '_sln_booking_services_processed' => 1,
                '_sln_booking_duration' => '00:30',
                '_sln_calendar_attendants_event_id' => array(),
                '_edit_last' => '1',
                '_sln_booking_amount' => 10.11,
                '_sln_booking_date' => getNextAvailableDate(array(2 => 1, 3 => 1, 4 => 1, 5 => 1, 6 => 1)),
                '_sln_booking_time' => '10:30',
                '_sln_booking_firstname' => 'Mario',
                '_sln_booking_lastname' => 'Rossi',
                '_sln_booking_email' => 'mario@rossi.com',
                '_sln_booking_phone' => '324543678',
            )
        ),
        array(
            'post' => array(
                'post_title' => 'Booking example 3',
                'post_excerpt' => '',
                'post_status' => 'sln-b-pending',
                'post_type' => 'sln_booking',
            ),
            'meta' => array(
                '_sln_booking_services' => array(
                    array(
                        'attendant' => 1,
                        'service' => 2,  // Beard Trim (array index 2, price $15)
                    ),
                    array(
                        'attendant' => 1,
                        'service' => 4   // Shampoo (array index 4, price $29.99)
                    )
                ),
                '_sln_booking_services_processed' => 1,
                '_sln_booking_duration' => '01:30',
                '_sln_calendar_attendants_event_id' => array(),
                '_edit_last' => '1',
                '_sln_booking_amount' => 44.99,  // $15 + $29.99 = $44.99 ✓
                '_sln_booking_date' => getNextAvailableDate(array(2 => 1, 3 => 1, 4 => 1, 5 => 1, 6 => 1)),
                '_sln_booking_time' => '12:30',
                '_sln_booking_firstname' => 'Luigi',
                '_sln_booking_lastname' => 'Bianchi',
                '_sln_booking_email' => 'luigi@bianchi.com',
                '_sln_booking_phone' => '324543678',
            )
        )
    )
);
