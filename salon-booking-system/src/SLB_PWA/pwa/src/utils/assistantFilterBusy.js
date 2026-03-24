/**
 * Assistant filter strip: "busy now" = current moment inside a service interval
 * for confirmed / paid / pay later bookings.
 */

export const ASSISTANT_FILTER_BUSY_STATUSES = new Set([
  'sln-b-confirmed',
  'sln-b-paid',
  'sln-b-paylater',
]);

/**
 * @param {string} dateStr YYYY-MM-DD
 * @param {string} timeStr HH:mm or H:mm
 * @param {Function} moment dayjs (or moment) instance factory: (input, format, strict?) => object with isValid, isBefore, isSameOrAfter, isSameOrBefore, add
 */
export function parseBookingLocalMoment(dateStr, timeStr, moment) {
  if (!dateStr || !timeStr) return null;
  const t = String(timeStr).trim();
  let m = moment(`${dateStr} ${t}`, 'YYYY-MM-DD HH:mm', true);
  if (!m.isValid()) {
    m = moment(`${dateStr} ${t}`, 'YYYY-MM-DD H:mm', true);
  }
  return m.isValid() ? m : null;
}

/**
 * IDs of assistants that appear on at least one service in the given bookings.
 */
export function assistantIdsWithBookings(bookings) {
  const set = new Set();
  (bookings || []).forEach((b) => {
    (b.services || []).forEach((s) => {
      const id = Number(s.assistant_id);
      if (Number.isFinite(id) && id > 0) {
        set.add(id);
      }
    });
  });
  return set;
}

/**
 * True if `now` falls inside [start, end] of any matching service on a busy-eligible booking.
 */
export function isAssistantBusyNow(bookings, assistantId, nowMs, moment) {
  const aid = Number(assistantId);
  if (!Number.isFinite(aid) || aid <= 0) return false;
  const now = moment(nowMs);

  for (const booking of bookings || []) {
    if (!booking || !ASSISTANT_FILTER_BUSY_STATUSES.has(booking.status)) continue;
    const dateStr = booking.date;
    if (!dateStr) continue;

    const services = booking.services || [];
    if (!services.length) continue;

    for (const service of services) {
      const sid = Number(service.assistant_id);
      if (!Number.isFinite(sid) || sid !== aid) continue;

      const startT = service.start_at || booking.time;
      const endT = service.end_at || service.start_at || booking.time;
      const start = parseBookingLocalMoment(dateStr, startT, moment);
      let end = parseBookingLocalMoment(dateStr, endT, moment);
      if (!start || !end) continue;

      if (end.isBefore(start)) {
        end = end.add(1, 'day');
      }
      if (end.isSame(start)) {
        end = start.add(1, 'hour');
      }

      if (!now.isBefore(start) && !now.isAfter(end)) {
        return true;
      }
    }
  }
  return false;
}
