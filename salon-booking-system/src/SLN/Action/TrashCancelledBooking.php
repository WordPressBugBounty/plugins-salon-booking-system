<?php

/**
 * Auto-trash cancelled bookings
 *
 * This class handles the automatic trashing of bookings when they are cancelled,
 * if the 'auto_trash_cancelled' setting is enabled.
 *
 * @since 10.31.0
 */
class SLN_Action_TrashCancelledBooking
{
    /** @var SLN_Plugin */
    private $plugin;

    /**
     * Constructor
     *
     * @param SLN_Plugin $plugin
     */
    public function __construct(SLN_Plugin $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * Execute the trash action for a cancelled booking
     *
     * @param SLN_Wrapper_Booking $booking The booking object to trash
     * @return bool True if trashed, false otherwise
     */
    public function execute($booking)
    {
        // Check if auto-trash is enabled in settings
        if (!$this->plugin->getSettings()->get('auto_trash_cancelled')) {
            return false;
        }

        // Verify booking is actually cancelled
        if ($booking->getStatus() !== SLN_Enum_BookingStatus::CANCELED) {
            return false;
        }

        // Get booking ID
        $booking_id = $booking->getId();

        // Validate booking ID
        if (!$booking_id) {
            return false;
        }

        // Log the action for debugging
        $this->plugin->addLog(sprintf(
            'Auto-trashing cancelled booking #%d (customer: %s)',
            $booking_id,
            $booking->getDisplayName()
        ));

        // Move to trash using WordPress native function
        // This is reversible - admin can restore from trash if needed
        $result = wp_trash_post($booking_id);

        if ($result) {
            // Fire action hook for extensibility
            // Extensions or add-ons can hook into this
            do_action('sln.booking.auto_trashed', $booking_id, $booking);

            $this->plugin->addLog(sprintf(
                'Successfully auto-trashed booking #%d',
                $booking_id
            ));

            return true;
        } else {
            $this->plugin->addLog(sprintf(
                'Failed to auto-trash booking #%d',
                $booking_id
            ));

            return false;
        }
    }
}

