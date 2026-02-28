<?php
// phpcs:ignoreFile WordPress.Security.NonceVerification.Missing

class SLN_Action_Ajax_RefreshPaymentStatus extends SLN_Action_Ajax_Abstract
{
    public function execute()
    {
        if ( ! current_user_can( 'administrator' ) ) {
            return array( 'success' => false, 'message' => __( 'Permission denied.', 'salon-booking-system' ) );
        }

        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'sln_refresh_payment_status' ) ) {
            return array( 'success' => false, 'message' => __( 'Security check failed.', 'salon-booking-system' ) );
        }

        $bookingId = isset( $_POST['booking_id'] ) ? intval( $_POST['booking_id'] ) : 0;
        if ( ! $bookingId ) {
            return array( 'success' => false, 'message' => __( 'Invalid booking ID.', 'salon-booking-system' ) );
        }

        $booking = $this->plugin->createBooking( $bookingId );
        if ( ! $booking ) {
            return array( 'success' => false, 'message' => __( 'Booking not found.', 'salon-booking-system' ) );
        }

        $stripeSessionId = $booking->getMeta( 'stripe_session_id' );
        if ( $stripeSessionId ) {
            return $this->refreshStripe( $booking, $stripeSessionId );
        }

        $paypalReturnData = get_post_meta( $bookingId, '_sln_paypal_return_data', true );
        if ( $paypalReturnData ) {
            return $this->refreshPaypal( $booking );
        }

        return array(
            'success' => false,
            'message' => __( 'No online payment data found for this booking. The booking may use a non-trackable payment method (e.g. cash / pay later).', 'salon-booking-system' ),
        );
    }

    private function refreshStripe( $booking, $sessionId )
    {
        if ( ! class_exists( 'Stripe\Stripe' ) ) {
            $autoload = __DIR__ . '/../../PaymentMethod/_stripe/vendor/autoload.php';
            if ( ! file_exists( $autoload ) ) {
                return array( 'success' => false, 'message' => __( 'Stripe library not found. Please check the plugin installation.', 'salon-booking-system' ) );
            }
            require_once $autoload;
        }

        \Stripe\Stripe::setApiKey( $this->plugin->getSettings()->get( 'pay_stripe_apiKey' ) );

        try {
            $session = \Stripe\Checkout\Session::retrieve( $sessionId );

            if ( $session->payment_status === 'paid' ) {
                $paymentIntent = \Stripe\PaymentIntent::retrieve( $session->payment_intent );

                if ( $paymentIntent->status === 'succeeded' ) {
                    $transactionId  = $paymentIntent->charges->data[0]->balance_transaction;
                    $alreadyPaid    = in_array( $booking->getStatus(), array( SLN_Enum_BookingStatus::PAID, SLN_Enum_BookingStatus::CONFIRMED ), true );

                    $booking->markPaid( $transactionId, 0 );

                    SLN_Plugin::addLog( sprintf(
                        'RefreshPaymentStatus: Stripe payment confirmed for booking #%d. Transaction: %s. Was already paid: %s',
                        $booking->getId(),
                        $transactionId,
                        $alreadyPaid ? 'yes' : 'no'
                    ) );

                    if ( $alreadyPaid ) {
                        return array(
                            'success'        => true,
                            'status_updated' => false,
                            'gateway'        => 'stripe',
                            'transaction_id' => $transactionId,
                            'message'        => __( 'Payment verified via Stripe. This booking was already marked as paid — no changes made.', 'salon-booking-system' ),
                        );
                    }

                    return array(
                        'success'        => true,
                        'status_updated' => true,
                        'gateway'        => 'stripe',
                        'transaction_id' => $transactionId,
                        'new_status'     => SLN_Enum_BookingStatus::getLabel( SLN_Enum_BookingStatus::PAID ),
                        'message'        => __( 'Payment confirmed via Stripe. Booking status has been updated to Paid.', 'salon-booking-system' ),
                    );
                }

                return array(
                    'success' => false,
                    'gateway' => 'stripe',
                    'message' => sprintf(
                        /* translators: %s: Stripe payment intent status */
                        __( 'Stripe session is marked paid but payment intent status is "%s". No changes made.', 'salon-booking-system' ),
                        esc_html( $paymentIntent->status )
                    ),
                );
            }

            if ( $session->payment_status === 'unpaid' ) {
                return array(
                    'success' => false,
                    'gateway' => 'stripe',
                    'message' => __( 'Payment not completed. The customer has not finished the payment on Stripe.', 'salon-booking-system' ),
                );
            }

            return array(
                'success' => false,
                'gateway' => 'stripe',
                'message' => sprintf(
                    /* translators: %s: Stripe session payment_status value */
                    __( 'Stripe session payment status: "%s". No changes made.', 'salon-booking-system' ),
                    esc_html( $session->payment_status )
                ),
            );

        } catch ( Exception $e ) {
            SLN_Plugin::addLog( 'RefreshPaymentStatus Stripe error for booking #' . $booking->getId() . ': ' . $e->getMessage() );
            return array(
                'success' => false,
                'gateway' => 'stripe',
                'message' => sprintf(
                    /* translators: %s: Stripe API error message */
                    __( 'Stripe API error: %s', 'salon-booking-system' ),
                    esc_html( $e->getMessage() )
                ),
            );
        }
    }

    private function refreshPaypal( $booking )
    {
        // PayPal Standard relies on IPN (server push) — there is no pull API to query status.
        // The best we can do is report what IPN data we already have.
        $existingTxns = $booking->getTransactionId();

        if ( ! empty( $existingTxns ) ) {
            $alreadyPaid = in_array( $booking->getStatus(), array( SLN_Enum_BookingStatus::PAID, SLN_Enum_BookingStatus::CONFIRMED ), true );
            return array(
                'success'        => true,
                'status_updated' => false,
                'gateway'        => 'paypal',
                'transaction_id' => implode( ', ', $existingTxns ),
                'message'        => $alreadyPaid
                    ? sprintf(
                        /* translators: %s: PayPal transaction ID(s) */
                        __( 'PayPal transaction found (%s) and booking is already marked as paid.', 'salon-booking-system' ),
                        esc_html( implode( ', ', $existingTxns ) )
                    )
                    : sprintf(
                        /* translators: %s: PayPal transaction ID(s) */
                        __( 'PayPal transaction ID found (%s) but booking status has not been updated. Please update the booking status manually.', 'salon-booking-system' ),
                        esc_html( implode( ', ', $existingTxns ) )
                    ),
            );
        }

        return array(
            'success' => false,
            'gateway' => 'paypal',
            'message' => __( 'PayPal IPN has not been received yet for this booking. PayPal uses a push-notification system — automatic status refresh is not available. Please check your PayPal dashboard and update the booking status manually if the payment was completed.', 'salon-booking-system' ),
        );
    }
}
