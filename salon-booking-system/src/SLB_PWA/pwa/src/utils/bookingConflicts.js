/**
 * Availability rules are warnings, not blockers, for administrators and salon
 * staff: like the WordPress back-end, they can always save a booking. The API
 * refuses a booking that breaks availability rules with a 409 (code
 * salon_rest_booking_conflict) listing them; the PWA shows them in the
 * AvailabilityOverrideModal and, if the user confirms ("Save anyway"), sends the
 * same request again with availability_override set to the signature of the list
 * the user saw. Users who can't force a booking only get "Go back and edit".
 */

/**
 * The broken rules of a failed booking save, or null when the error is not about
 * availability.
 *
 * @param {Error} error Axios error.
 * @returns {{violations: Array, signature: string, summary: string, canForce: boolean}|null}
 */
export function getBookingConflicts(error) {
    const response = error && error.response;
    const data = response && response.data;
    if (!response || response.status !== 409 || !data || data.code !== 'salon_rest_booking_conflict') {
        return null;
    }
    const details = data.data || {};
    let violations = Array.isArray(details.violations) ? details.violations.filter(Boolean) : [];
    if (!violations.length) {
        // Older API: plain messages only.
        const messages = Array.isArray(details.conflicts) ? details.conflicts.filter(Boolean) : [data.message];
        violations = messages.map((message) => ({code: 'conflict', title: '', message}));
    }
    return {
        violations,
        signature: details.signature || '',
        summary: details.summary || '',
        canForce: !!details.can_force,
    };
}

/**
 * Component mixin. Pair it with
 * <AvailabilityOverrideModal v-model="bookingConflictConfirm.visible"
 *   :violations="bookingConflictConfirm.violations"
 *   :summary="bookingConflictConfirm.summary"
 *   :can-force="bookingConflictConfirm.canForce"
 *   @confirm="resolveBookingConflictConfirm(true)"
 *   @cancel="resolveBookingConflictConfirm(false)" /> in the template.
 */
export const bookingConflictMixin = {
    data() {
        return {
            bookingConflictConfirm: {visible: false, violations: [], summary: '', canForce: false, resolve: null},
        };
    },
    methods: {
        /**
         * Run a booking save; when it breaks availability rules, list them and, if
         * the user confirms, run it again with availability_override. Resolves to
         * the axios response, or null when the user went back. Other errors are
         * rethrown.
         *
         * @param {function(Object): Promise} send Called with the extra params to merge into the payload.
         * @param {function(): void} [beforeAsk] Called before the question is shown (e.g. to stop a spinner).
         */
        async saveBookingAllowingConflicts(send, beforeAsk) {
            let extra = {};
            // The list can change between the question and the new save (another
            // booking just came in): ask again, a few times at most.
            for (let attempt = 0; attempt < 3; attempt++) {
                try {
                    return await send(extra);
                } catch (error) {
                    const conflicts = getBookingConflicts(error);
                    if (!conflicts) {
                        throw error;
                    }
                    if (beforeAsk) {
                        beforeAsk();
                    }
                    if (!(await this.askBookingConflictConfirm(conflicts)) || !conflicts.canForce) {
                        return null;
                    }
                    extra = conflicts.signature
                        ? {availability_override: conflicts.signature}
                        : {ignore_conflicts: true};
                }
            }
            return null;
        },
        askBookingConflictConfirm(conflicts) {
            // A question still open counts as cancelled
            this.resolveBookingConflictConfirm(false);
            return new Promise((resolve) => {
                this.bookingConflictConfirm = {
                    visible: true,
                    violations: conflicts.violations,
                    summary: conflicts.summary,
                    canForce: conflicts.canForce,
                    resolve,
                };
            });
        },
        resolveBookingConflictConfirm(answer) {
            const {resolve} = this.bookingConflictConfirm;
            this.bookingConflictConfirm = {visible: false, violations: [], summary: '', canForce: false, resolve: null};
            if (resolve) {
                resolve(answer);
            }
        },
    },
};
