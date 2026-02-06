<?php
// phpcs:ignoreFile WordPress.Security.NonceVerification.Missing

class SLN_Action_Ajax_CheckOverbooking extends SLN_Action_Ajax_Abstract
{
    /**
     * Checks for existing bookings with the same date and time to prevent overbooking.
     *
     * Retrieves sanitized date and time from the POST request, validates them,
     * and queries for existing bookings in specific statuses. If a conflict is found,
     * returns success = false. If input is invalid or an error occurs, it also returns
     * a failure with an error message.
     *
     * @return array{success: bool, error?: string}
     * Returns `success: true` if no conflicting booking exists,
     * otherwise `success: false` and an optional error message.
     */
    public function execute(): array
    {
        try {
            $date = sanitize_text_field(wp_unslash($_POST['sln']['date'] ?? ''));
            $time = sanitize_text_field(wp_unslash($_POST['sln']['time'] ?? ''));
            // get assistant ID - check all possible locations
            $assistant_id = $this->getAssistantId();
            $date_clean = SLN_Func::filter($date, 'date');
            $time_clean = SLN_Func::filter($time, 'time');
            // get settings
            $settings = SLN_Plugin::getInstance()->getSettings();
            $attendant_enabled = $settings->get('attendant_enabled');
            $parallel = $settings->get('parallels_hour');

            // if assistants are enabled and a specific assistant is selected

            if ($attendant_enabled && $assistant_id > 0 && $parallel <= 1) {
                // check only conflicts for this assistant
                $args = [
                    'post_type' => 'sln_booking',
                    'post_status' => [
                        SLN_Enum_BookingStatus::PENDING_PAYMENT,
                        SLN_Enum_BookingStatus::PAID,
                        SLN_Enum_BookingStatus::PAY_LATER,
                        SLN_Enum_BookingStatus::CONFIRMED
                    ],
                    'posts_per_page' => -1,
                    'meta_query' => [
                        'relation' => 'AND',
                        [
                            'key' => '_sln_booking_date',
                            'value' => $date_clean
                        ],
                        [
                            'key' => '_sln_booking_time',
                            'value' => $time_clean
                        ]
                    ]
                ];
                $bookings = get_posts($args);
                // check each booking for conflict with selected assistant
                foreach ($bookings as $booking) {
                    // check _sln_booking_attendants field (array)
                    $booking_attendants = get_post_meta($booking->ID, '_sln_booking_attendants', true);
                    if (is_array($booking_attendants)) {
                        if (in_array($assistant_id, array_map('intval', $booking_attendants))) {
                            return [
                                'success' => false
                            ];
                        }
                    } else {
                        // if not an array, might be serialized data
                        $unserialized = @unserialize($booking_attendants);
                        if ($unserialized !== false && is_array($unserialized)) {
                            if (in_array($assistant_id, array_map('intval', $unserialized))) {
                                return [
                                    'success' => false
                                ];
                            }
                        }
                    }
                    // also check single _sln_booking_attendant field
                    $single_attendant = get_post_meta($booking->ID, '_sln_booking_attendant', true);
                    if (intval($single_attendant) === $assistant_id) {
                        return [
                            'success' => false
                        ];
                    }
                }
                // if no conflicts found with this assistant
                return [
                    'success' => true
                ];
            } else if ($attendant_enabled && $assistant_id === 0) {
                return [
                    'success' => true
                ];
            } else {
                $posts = get_posts([
                    'post_type' => 'sln_booking',
                    'post_status' => [
                        SLN_Enum_BookingStatus::PENDING_PAYMENT,
                        SLN_Enum_BookingStatus::PAID,
                        SLN_Enum_BookingStatus::PAY_LATER,
                        SLN_Enum_BookingStatus::CONFIRMED
                    ],
                    'posts_per_page' => 1,
                    'fields' => 'ids',
                    'meta_query' => [
                        ['key' => '_sln_booking_date', 'value' => $date_clean],
                        ['key' => '_sln_booking_time', 'value' => $time_clean]
                    ]
                ]);
                return ['success' => $parallel <= 1 ? empty($posts) : true];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Error: ' . $e->getMessage()
            ];
        }
    }

    private function getAssistantId(): int
    {
        // direct check for sln[attendant]
        if (!empty($_POST['sln']['attendant'])) {
            return intval($_POST['sln']['attendant']);
        }
        // check sln[attendants] as array
        if (!empty($_POST['sln']['attendants'])) {
            if (is_array($_POST['sln']['attendants'])) {
                $first = reset($_POST['sln']['attendants']);
                return intval($first);
            }
            return intval($_POST['sln']['attendants']);
        }
        // check alternative format (direct POST parameters)
        if (!empty($_POST['sln[attendant]'])) {
            return intval($_POST['sln[attendant]']);
        }
        if (!empty($_POST['sln[attendants]'])) {
            if (is_array($_POST['sln[attendants]'])) {
                $first = reset($_POST['sln[attendants]']);
                return intval($first);
            }
            return intval($_POST['sln[attendants]']);
        }
        return 0;
    }
}
