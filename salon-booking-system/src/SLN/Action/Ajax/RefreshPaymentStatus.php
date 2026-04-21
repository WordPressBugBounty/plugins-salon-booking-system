<?php

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

        $apiKey = $this->plugin->getSettings()->get( 'pay_stripe_apiKey' );
        if ( empty( $apiKey ) ) {
            return array( 'success' => false, 'gateway' => 'stripe', 'message' => __( 'Stripe API key is not configured. Please check the plugin payment settings.', 'salon-booking-system' ) );
        }
        \Stripe\Stripe::setApiKey( $apiKey );

        try {
            $session = \Stripe\Checkout\Session::retrieve( $sessionId );

            if ( $session->payment_status === 'paid' ) {
                if ( empty( $session->payment_intent ) ) {
                    return array(
                        'success' => false,
                        'gateway' => 'stripe',
                        'message' => __( 'This Stripe session has no associated PaymentIntent (free or setup-mode session). No changes made.', 'salon-booking-system' ),
                    );
                }

                $paymentIntent = \Stripe\PaymentIntent::retrieve( array(
                    'id'     => $session->payment_intent,
                    'expand' => array( 'latest_charge', 'payment_method' ),
                ) );

                if ( $paymentIntent->status === 'succeeded' ) {
                    // Prefer latest_charge (current Stripe API); fall back to the
                    // deprecated charges list for older API versions.
                    $charge        = $paymentIntent->latest_charge ?? ( $paymentIntent->charges->data[0] ?? null );
                    $transactionId = $charge ? $charge->balance_transaction : $paymentIntent->id;

                    // ---- Build and cache rich payment details ----
                    $paymentDetails = $this->extractStripePaymentDetails( $paymentIntent, $charge );
                    if ( ! empty( $paymentDetails ) ) {
                        update_post_meta( $booking->getId(), '_sln_booking_payment_details', $paymentDetails );
                    }

                    $alreadyPaid = in_array( $booking->getStatus(), array( SLN_Enum_BookingStatus::PAID, SLN_Enum_BookingStatus::CONFIRMED ), true );

                    SLN_Plugin::addLog( sprintf(
                        'RefreshPaymentStatus: Stripe payment confirmed for booking #%d. Transaction: %s. Was already paid: %s',
                        $booking->getId(),
                        $transactionId,
                        $alreadyPaid ? 'yes' : 'no'
                    ) );

                    if ( $alreadyPaid ) {
                        // If the booking is already paid but has no meaningful transaction ID recorded, save it now.
                        // array_filter strips nulls/empty strings left by the old broken markPaid(null) calls.
                        $existingTxn = array_filter( $booking->getTransactionId() );
                        if ( empty( $existingTxn ) && ! empty( $transactionId ) ) {
                            $booking->setMeta( 'transaction_id', array( $transactionId ) );
                            SLN_Plugin::addLog( sprintf(
                                'RefreshPaymentStatus: Saved missing transaction ID %s for already-paid booking #%d.',
                                $transactionId,
                                $booking->getId()
                            ) );
                        }
                        return array(
                            'success'         => true,
                            'status_updated'  => false,
                            'gateway'         => 'stripe',
                            'transaction_id'  => $transactionId,
                            'payment_details' => $paymentDetails,
                            'message'         => __( 'Payment verified via Stripe. This booking was already marked as paid.', 'salon-booking-system' ),
                        );
                    }

                    $booking->markPaid( $transactionId, 0 );

                    return array(
                        'success'         => true,
                        'status_updated'  => true,
                        'gateway'         => 'stripe',
                        'transaction_id'  => $transactionId,
                        'payment_details' => $paymentDetails,
                        'new_status'      => SLN_Enum_BookingStatus::getLabel( SLN_Enum_BookingStatus::PAID ),
                        'message'         => __( 'Payment confirmed via Stripe. Booking status has been updated to Paid.', 'salon-booking-system' ),
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

    /**
     * Extract rich payment details from a Stripe PaymentIntent + Charge for caching and display.
     *
     * @param \Stripe\PaymentIntent $paymentIntent
     * @param \Stripe\Charge|null   $charge
     * @return array
     */
    private function extractStripePaymentDetails( $paymentIntent, $charge )
    {
        $details = array();

        // Card brand, last4, wallet type
        $pm = isset( $paymentIntent->payment_method ) ? $paymentIntent->payment_method : null;
        if ( $pm && isset( $pm->card ) ) {
            $details['card_brand'] = $pm->card->brand ?? '';
            $details['card_last4'] = $pm->card->last4 ?? '';
            if ( isset( $pm->card->wallet ) && $pm->card->wallet ) {
                $details['wallet_type'] = $pm->card->wallet->type ?? '';
            }
        }

        // Charge-level data
        if ( $charge ) {
            $details['receipt_url']     = $charge->receipt_url ?? '';
            $details['charge_country']  = $charge->billing_details->address->country ?? '';
            $details['charge_date']     = ! empty( $charge->created )
                ? gmdate( 'Y-m-d H:i:s', $charge->created )
                : '';
            $details['charge_amount']   = $charge->amount ?? 0;
            $details['refunded']        = (bool) ( $charge->refunded ?? false );
            $details['amount_refunded'] = $charge->amount_refunded ?? 0;
        }

        return $details;
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
