<?php

/**
 * Centralised authorization gate for resolving a booking from a
 * (potentially attacker-controlled) request value.
 *
 * Booking IDs travel through the public booking wizard and several AJAX
 * endpoints. Historically these accepted a bare numeric post ID and loaded
 * the booking with NO ownership check, which let any visitor read or tamper
 * with arbitrary bookings simply by enumerating the numeric ID (IDOR).
 *
 * This helper returns a booking ONLY when the current request is entitled to
 * it, via one of:
 *
 *   1. A valid per-booking secure token "{id}-{hash}"
 *      (SLN_Wrapper_Booking::getUniqueId()). This is what the email pay/cancel
 *      links, the wizard forms and the payment-gateway return URLs now carry.
 *      The hash is unguessable, so IDs can no longer be enumerated.
 *   2. The booking currently stored in the visitor's own booking-builder
 *      session (the normal in-flow wizard case).
 *   3. A privileged user (can manage the salon) or the booking's own author
 *      (a logged-in customer viewing their own booking).
 *
 * A bare numeric ID with none of the above is rejected (returns null).
 */
class SLN_Helper_BookingAccess
{
    /**
     * Resolve a booking the current visitor is authorized to act on.
     *
     * @param SLN_Plugin $plugin
     * @param mixed      $raw    Raw request value: a plain numeric id or a
     *                           secure "{id}-{hash}" token.
     * @return SLN_Wrapper_Booking|null
     */
    public static function resolve(SLN_Plugin $plugin, $raw)
    {
        if (!isset($raw)) {
            return null;
        }
        if (is_string($raw)) {
            $raw = trim($raw);
        }
        if ($raw === '' || $raw === null) {
            return null;
        }

        // 1) Secure token "{id}-{hash}". createBooking() validates the hash and
        //    throws when it does not match, so a forged token is rejected.
        if (is_string($raw) && strpos($raw, '-') !== false) {
            try {
                $booking = $plugin->createBooking($raw);
                return ($booking && $booking->getId()) ? $booking : null;
            } catch (Exception $e) {
                return null;
            }
        }

        $id = intval($raw);
        if ($id <= 0) {
            return null;
        }

        // 2) The booking currently held in the visitor's own session.
        try {
            $last = $plugin->getBookingBuilder()->getLastBooking();
            if ($last && intval($last->getId()) === $id) {
                return $last;
            }
        } catch (Exception $e) {
            // fall through to the privileged / owner checks
        }

        // 3) Privileged users, or the booking's own author.
        try {
            $booking = $plugin->createBooking($id);
        } catch (Exception $e) {
            return null;
        }
        if (!$booking || !$booking->getId()) {
            return null;
        }
        if (self::currentUserCanManage() || self::currentUserOwns($booking)) {
            return $booking;
        }

        return null;
    }

    /**
     * Extract and resolve a booking from the payment "op" parameter, whose
     * format is "<action>-<id>" (legacy) or "<action>-<id>-<hash>" (secure).
     *
     * @param SLN_Plugin $plugin
     * @param string     $op
     * @return SLN_Wrapper_Booking|null
     */
    public static function resolveFromOp(SLN_Plugin $plugin, $op)
    {
        if (!is_string($op) || $op === '') {
            return null;
        }
        $parts = explode('-', $op);
        array_shift($parts); // drop the action segment (success/cancel/notify/x/...)
        if (empty($parts)) {
            return null;
        }
        $candidate = implode('-', $parts); // "<id>" or "<id>-<hash>"

        return self::resolve($plugin, $candidate);
    }

    /**
     * Whether the current user can manage salon bookings (admin / shop manager).
     *
     * @return bool
     */
    public static function currentUserCanManage()
    {
        return current_user_can('manage_options') || current_user_can('manage_salon');
    }

    /**
     * Whether the current (logged-in) user is the author of the booking.
     *
     * @param SLN_Wrapper_Booking $booking
     * @return bool
     */
    protected static function currentUserOwns(SLN_Wrapper_Booking $booking)
    {
        $userId = get_current_user_id();

        return $userId > 0 && intval($booking->getUserId()) === intval($userId);
    }
}
