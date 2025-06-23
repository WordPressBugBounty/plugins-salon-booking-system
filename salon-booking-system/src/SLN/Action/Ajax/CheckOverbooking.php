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

            $date_clean = SLN_Func::filter($date, 'date');
            $time_clean = SLN_Func::filter($time, 'time');

            $posts = get_posts([
                'post_type' => 'sln_booking',
                'post_status' => [
                    SLN_Enum_BookingStatus::PENDING_PAYMENT,
                    SLN_Enum_BookingStatus::PAID,
                    SLN_Enum_BookingStatus::PAY_LATER,
                    SLN_Enum_BookingStatus::CONFIRMED,
                ],
                'posts_per_page' => 1,
                'fields' => 'ids',
                'meta_query' => [
                    [
                        'key' => '_sln_booking_date',
                        'value' => $date_clean,
                    ],
                    [
                        'key' => '_sln_booking_time',
                        'value' => $time_clean,
                    ],
                ],
            ]);

            return ['success' => empty($posts)];
        } catch (Exception $e) {
            error_log('SLN_Action_Ajax_CheckOverbooking Validation Error: ' . $e->getMessage());

            return [
                'success' => false,
                'error' => 'Invalid date or time provided.',
            ];
        }
    }
}
