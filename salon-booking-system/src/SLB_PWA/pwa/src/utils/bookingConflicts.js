/**
 * Booking conflicts are warnings, not blockers: like the WordPress back-end, the
 * salon staff can always save a booking. The API refuses a conflicting save with
 * a 409 (code salon_rest_booking_conflict); the PWA shows the conflicts and, if
 * the user confirms, sends the same request again with ignore_conflicts: true.
 */

/**
 * Conflict messages of a failed booking save, or null when the error is not a
 * conflict the current user may override.
 *
 * @param {Error} error Axios error.
 * @returns {string[]|null}
 */
export function getBookingConflicts(error) {
    const response = error && error.response;
    const data = response && response.data;
    if (!response || response.status !== 409 || !data || data.code !== 'salon_rest_booking_conflict') {
        return null;
    }
    if (!data.data || !data.data.can_force) {
        return null;
    }
    const conflicts = Array.isArray(data.data.conflicts) ? data.data.conflicts.filter(Boolean) : [];
    return conflicts.length ? conflicts : [data.message];
}

/**
 * Component mixin (needs getLabel from the main mixin). Pair it with a
 * <ConfirmModal v-model="bookingConflictConfirm.visible"
 *   :message="bookingConflictConfirm.message"
 *   @confirm="resolveBookingConflictConfirm(true)"
 *   @cancel="resolveBookingConflictConfirm(false)" /> in the template.
 */
export const bookingConflictMixin = {
    data() {
        return {
            bookingConflictConfirm: {visible: false, message: '', resolve: null},
        };
    },
    methods: {
        /**
         * Run a booking save; on a conflict ask the user and, if confirmed, run it
         * again with ignore_conflicts. Resolves to the axios response, or null when
         * the user cancelled. Other errors are rethrown.
         *
         * @param {function(Object): Promise} send Called with the extra params to merge into the payload.
         * @param {function(): void} [beforeAsk] Called before the question is shown (e.g. to stop a spinner).
         */
        async saveBookingAllowingConflicts(send, beforeAsk) {
            try {
                return await send({});
            } catch (error) {
                const conflicts = getBookingConflicts(error);
                if (!conflicts) {
                    throw error;
                }
                if (beforeAsk) {
                    beforeAsk();
                }
                if (!(await this.askBookingConflictConfirm(conflicts))) {
                    return null;
                }
                return send({ignore_conflicts: true});
            }
        },
        askBookingConflictConfirm(conflicts) {
            // A question still open counts as cancelled
            this.resolveBookingConflictConfirm(false);
            const text = this.getLabel('bookingConflictText') || 'Do you want to save the booking anyway?';
            return new Promise((resolve) => {
                this.bookingConflictConfirm = {
                    visible: true,
                    message: conflicts.join('\n') + '\n\n' + text,
                    resolve,
                };
            });
        },
        resolveBookingConflictConfirm(answer) {
            const {resolve} = this.bookingConflictConfirm;
            this.bookingConflictConfirm = {visible: false, message: '', resolve: null};
            if (resolve) {
                resolve(answer);
            }
        },
    },
};
