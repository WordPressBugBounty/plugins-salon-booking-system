<template>
  <div class="attendant-time-slots">
    <div class="time-slot-lines">
      <div v-for="(timeslot, index) in timeslots"
           :key="timeslot"
           class="time-slot-line"
           :style="getTimeSlotLineStyle(index + 1)">
      </div>
    </div>
    <template v-for="attendant in sortedAttendants" :key="attendant.id">
      <div class="attendant-column" :style="getAttendantColumnStyle(attendant)">
        <div
            v-for="(timeslot, index) in timeslots"
            :key="`${attendant.id}-${timeslot}`"
            class="time-slot"
            :data-id="`${attendant.id}-${timeslot}`"
            :style="getTimeSlotStyle(index)"
        >
          <div class="time-slot-inner"
               :class="{ 'time-slot-inner--locked': isSlotLockedForAttendant( timeslot, getNextTimeslot(index), attendant.id),
               'time-slot-inner--selected': isSelectedSlot(timeslot, attendant.id),
               'time-slot-inner--active': activeSlot === `${attendant.id}-${timeslot}`,
               'time-slot-inner--processing': isSlotProcessing( timeslot, getNextTimeslot(index), attendant.id )
                }"
               @click="handleSlotClick(timeslot, attendant, index)"
          >

            <template v-if="isSlotProcessing(timeslot, getNextTimeslot(index), attendant.id)">
              <div class="slot-processing-spinner">
                <b-spinner variant="warning" small label="Processing..."/>
              </div>
            </template>

            <template v-else>
              <template v-if="isSlotLockedForAttendant(timeslot, getNextTimeslot(index), attendant.id)">
                <div
                    class="slot-actions slot-actions--locked"
                    v-if="isSlotCenterOfLock(timeslot, attendant.id) && isSlotManuallyLockable(timeslot, attendant.id)">
                  <button @click.stop="unlockSlot(timeslot, attendant, index)" class="unlock-button">
                    <font-awesome-icon icon="fa-solid fa-lock"/>
                  </button>
                </div>
              </template>
              <div v-else class="slot-actions">
                <button @click.stop="addBooking(timeslot, attendant)"
                        class="add-button">
                  <font-awesome-icon icon="fa-solid fa-circle-plus"/>
                </button>
                <button @click.stop="lockSlot(timeslot, attendant, index)"
                        class="lock-button">
                  <font-awesome-icon icon="fa-solid fa-unlock"/>
                </button>
              </div>
            </template>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script>
export default {
  name: 'AttendantTimeSlots',
  data() {
    return {
      processingSlots: new Set(),
      activeSlot: null,
      timeCache: new Map(),
      END_OF_DAY: '24:00',
      MINUTES_IN_DAY: 1440 // 24 hours * 60 minutes
    }
  },
  props: {
    date: {
      type: Date,
      required: true,
      validator: function (value) {
        return value instanceof Date && !isNaN(value);
      }
    },
    shop: {
      default: function () {
        return {};
      },
    },
    sortedAttendants: {
      type: Array,
      required: true
    },
    timeslots: {
      type: Array,
      required: true
    },
    columnWidths: {
      type: Object,
      required: true
    },
    slotHeight: {
      type: Number,
      default: 110
    },
    selectedSlots: {
      type: Array,
      default: () => []
    },
    lockedTimeslots: {
      type: Array,
      default: () => []
    },
    processedBookings: {
      type: Array,
      default: () => []
    },
    availabilityIntervals: {
      type: Object,
      default: () => ({})
    }
  },
  watch: {
    lockedTimeslots: {
      immediate: true,
      deep: true,
      handler() {
        this.$nextTick(() => {
          this.$forceUpdate();
        });
      }
    },
  },
  computed: {
    isShopsEnabled() {
      return !!(window?.slnPWA?.is_shops);
    },
    selectedShopId() {
      return this.shop?.id || null;
    },
  },
  methods: {
    /** helpers **/
    /* method to format date consistently */
    getFormattedDate(date = this.date) {
      return this.dateFormat(date, 'YYYY-MM-DD');
    },
    /* method to convert time to minutes with caching */
    getTimeInMinutes(time) {
      if (!time) return 0;
      if (time === this.END_OF_DAY || time === '24:00') return this.MINUTES_IN_DAY;
      if (this.timeCache.has(time)) return this.timeCache.get(time);

      const normalizedTime = this.normalizeTime(time);
      const minutes = this.timeToMinutes(normalizedTime);
      this.timeCache.set(time, minutes);
      return minutes;
    },
    /* method to check if a time is in a holiday period */
    isInHolidayPeriod(holidays, date, slot, nextSlot) {
      if (!holidays || !holidays.length) return false;

      return holidays.some(holiday => {
        const holidayStart = this.moment(holiday.from_date, "YYYY-MM-DD").startOf('day');
        const holidayEnd = this.moment(holiday.to_date, "YYYY-MM-DD").startOf('day');
        const current = this.moment(date, "YYYY-MM-DD").startOf('day');

        return current.isBetween(holidayStart, holidayEnd, 'day', '[]') &&
            this.doTimeslotsOverlap(slot, nextSlot, holiday.from_time, holiday.to_time);
      });
    },
    /* method to check if time is in shifts */
    isTimeInShifts(shifts, minutes) {
      return shifts.some(shift => {
        if (!shift.from || !shift.to || shift.disabled) return false;
        const shiftFromMinutes = this.getTimeInMinutes(shift.from);
        const shiftToMinutes = this.getTimeInMinutes(shift.to);
        return minutes >= shiftFromMinutes && minutes < shiftToMinutes;
      });
    },
    /* method to check time in old format schedules */
    isTimeInFromToFormat(from, to, minutes) {
      const isValidInFirstShift =
          from[0] && to[0] &&
          this.getTimeInMinutes(from[0]) <= minutes &&
          this.getTimeInMinutes(to[0]) > minutes;

      const isValidInSecondShift =
          from[1] && to[1] &&
          this.getTimeInMinutes(from[1]) <= minutes &&
          this.getTimeInMinutes(to[1]) > minutes;

      return isValidInFirstShift || isValidInSecondShift;
    },
    /* method to check availability for a day */
    isTimeInAvailability(availability, minutes, weekday) {
      if (!availability.days || availability.days[weekday] !== 1) return false;

      // check using shifts (new format)
      if (availability.shifts && availability.shifts.length > 0) {
        return this.isTimeInShifts(availability.shifts, minutes);
      }
      // check using from/to (old format)
      else if (Array.isArray(availability.from) && Array.isArray(availability.to)) {
        return this.isTimeInFromToFormat(availability.from, availability.to, minutes);
      }
      // handle always: true case
      else if (availability.always) {
        return true;
      }

      return false;
    },
    /* method to check if holiday rules block a time */
    isBlockedByHolidayRule(slot, attendantId, currentDate, slotMinutes) {
      if (slot.assistant_id !== null && slot.assistant_id !== attendantId) {
        return false;
      }

      const fromDate = this.moment(slot.from_date, 'YYYY-MM-DD');
      const toDate = this.moment(slot.to_date, 'YYYY-MM-DD');
      const isDateInRange = currentDate.isBetween(fromDate, toDate, 'day', '[]');

      if (!isDateInRange) return false;

      const fromMinutes = this.getTimeInMinutes(slot.from_time);
      let toMinutes = this.getTimeInMinutes(slot.to_time);
      if ((slot.to_time === '00:00' || slot.to_time === '24:00') &&
          currentDate.isSame(fromDate, 'day') && currentDate.isSame(toDate, 'day')) {
        toMinutes = this.MINUTES_IN_DAY;
      }

      if (currentDate.isSame(fromDate, 'day') && currentDate.isSame(toDate, 'day')) {
        return slotMinutes >= fromMinutes && slotMinutes < toMinutes;
      } else if (currentDate.isSame(fromDate, 'day')) {
        return slotMinutes >= fromMinutes;
      } else if (currentDate.isSame(toDate, 'day')) {
        return slotMinutes < toMinutes;
      } else {
        return true;
      }
    },
    /* method to check if day is a working day */
    hasWorkingDay(availabilities, weekday) {
      return availabilities.some(av => av.days && av.days[weekday] === 1);
    },
    /* method to update locked timeslots from API */
    async updateLockedTimeslots(date = this.date) {
      const formattedDate = this.getFormattedDate(date);

      try {
        const updatedRules = await this.axios.get('holiday-rules', {
          params: this.withShop({
            assistants_mode: true,
            date: formattedDate,
          }),
        });

        if (updatedRules.data?.assistants_rules) {
          const assistantsRules = updatedRules.data.assistants_rules;
          const newLockedTimeslots = Object.entries(assistantsRules).flatMap(([assistantId, rules]) =>
              rules.map(rule => ({
                ...rule,
                assistant_id: Number(assistantId) || null,
                is_manual: rule.is_manual === true,
              }))
          );
          this.$emit('update:lockedTimeslots', newLockedTimeslots);
          this.sortedAttendants.forEach(attendant => {
            const attendantRules = newLockedTimeslots.filter(rule => rule.assistant_id === attendant.id);
            attendant.holidays = attendantRules.map(rule => ({
              from_date: rule.from_date,
              to_date: rule.to_date,
              from_time: rule.from_time,
              to_time: rule.to_time,
              is_manual: rule.is_manual === true
            }));
            if (this.shop?.id) {
              const shopData = attendant.shops?.find(shop => shop.id === this.shop.id);
              if (shopData) {
                shopData.holidays = attendantRules.map(rule => ({
                  from_date: rule.from_date,
                  to_date: rule.to_date,
                  from_time: rule.from_time,
                  to_time: rule.to_time,
                  is_manual: rule.is_manual === true,
                }));
              }
            }
          });
        }

        return updatedRules;
      } catch (error) {
        console.error('Error updating locked timeslots:', error);
        throw error;
      }
    },
    isSlotManuallyLockable(timeslot, attendantId) {
      const formattedDate = this.getFormattedDate();
      const slotMinutes = this.getTimeInMinutes(timeslot);

      const relevantLock = this.lockedTimeslots.find(slot => {
        if (slot.assistant_id !== attendantId || slot.from_date !== formattedDate) {
          return false;
        }

        const lockStart = this.getTimeInMinutes(slot.from_time);
        let lockEnd = this.getTimeInMinutes(slot.to_time);

        if (slot.to_time === '00:00' && slot.from_date === slot.to_date) {
          lockEnd = this.MINUTES_IN_DAY;
        }
        return slotMinutes >= lockStart && slotMinutes < lockEnd;
      });

      return !!(relevantLock?.is_manual);
    },
    /** main ***/
    async lockSlot(timeslot, attendant, index) {
      const nextTimeslot = this.getNextTimeslot(index);
      const slotKey = this.getSlotKey(timeslot, nextTimeslot, attendant.id);

      // prevent multiple processing of the same slot
      if (this.processingSlots.has(slotKey)) return;
      this.processingSlots.add(slotKey);
      try {
        const formattedDate = this.getFormattedDate();
        // prepare time values
        const formattedFromTime = this.normalizeTime(timeslot);
        let formattedToTime;

        if (nextTimeslot) {
          if (nextTimeslot === '00:00' || nextTimeslot === '24:00' || nextTimeslot === this.END_OF_DAY) {
            formattedToTime = this.END_OF_DAY;
          } else {
            formattedToTime = this.normalizeTime(nextTimeslot);
          }
        } else {
          const endMoment = this.moment(formattedFromTime,'HH:mm').add(30,'minutes');
          const endHours = endMoment.hours();
          const endMinutes = endMoment.minutes();
          if (endHours === 0 && endMinutes === 0) {
            formattedToTime = this.END_OF_DAY;
          } else {
            formattedToTime = endMoment.format('HH:mm');
          }
        }

        // prepare API payload
        const payload = this.withShop({
          assistants_mode: true,
          assistant_id: attendant.id || null,
          date: formattedDate, // Include date parameter for API response
          from_date: formattedDate,
          to_date: formattedDate,
          from_time: formattedFromTime,
          to_time: formattedToTime,
          daily: true,
          is_manual: true,
        });

        // send lock request
        const response = await this.axios.post('holiday-rules', payload);
        
        // Check if response indicates success
        if (!response.data || (response.data.success !== 1 && !response.data.assistants_rules)) {
          throw new Error('Lock request failed: Invalid response from server');
        }

        // update locked timeslots
        await this.updateLockedTimeslots();

        // notify parent
        this.$emit('lock', payload);
      } catch (error) {
        console.error('Slot lock error:', error);
        // Show user-friendly error message
        if (error.response?.data?.message) {
          alert('Failed to lock slot: ' + error.response.data.message);
        } else if (error.message) {
          alert('Failed to lock slot: ' + error.message);
        } else {
          alert('Failed to lock slot. Please try again.');
        }
      } finally {
        this.processingSlots.delete(slotKey);
      }
    },

    async unlockSlot(timeslot, attendant, index) {
      const nextTimeslot = this.getNextTimeslot(index);
      const slotKey = this.getSlotKey(timeslot, nextTimeslot, attendant.id);

      // prevent multiple processing of the same slot
      if (this.processingSlots.has(slotKey)) return;
      this.processingSlots.add(slotKey);
      try {
        const formattedDate = this.getFormattedDate();
        const slotMinutes = this.getTimeInMinutes(timeslot);

        // find the lock that includes this timeslot
        const relevantLock = this.lockedTimeslots.find(slot => {
          const isSpecificAssistantLock = slot.assistant_id === attendant.id;
          const isCorrectDate = slot.from_date === formattedDate;
          const lockStart = this.getTimeInMinutes(slot.from_time);
          const lockEnd = this.getTimeInMinutes(slot.to_time);

          return isSpecificAssistantLock &&
              isCorrectDate &&
              (slotMinutes >= lockStart && slotMinutes < lockEnd);
        });

        if (!relevantLock) { this.processingSlots.delete(slotKey); return; }

        // prepare API payload
        const payload = this.withShop({
          assistants_mode: true,
          assistant_id: attendant.id,
          from_date: formattedDate,
          to_date: formattedDate,
          from_time: this.normalizeTime(relevantLock.from_time),
          to_time: this.normalizeTime(relevantLock.to_time),
          daily: true,
        });

        // send unlock request
        await this.axios.delete('holiday-rules', { data: payload });

        // update locked timeslots
        await this.updateLockedTimeslots();

        // notify parent
        this.$emit('unlock', payload);
      } catch (error) {
        console.error('Slot unlock error:', error);
      } finally {
        this.processingSlots.delete(slotKey);
      }
    },
    handleSlotClick(timeslot, attendant, index) {
      // check if slot can be clicked
      const slotLocked = this.isSlotLockedForAttendant(timeslot, this.getNextTimeslot(index), attendant.id);
      const lockedByAssistant = this.lockedTimeslots.some(slot =>
          slot.assistant_id === attendant.id &&
          slot.from_date === this.getFormattedDate() &&
          this.getTimeInMinutes(timeslot) >= this.getTimeInMinutes(slot.from_time) &&
          this.getTimeInMinutes(timeslot) < this.getTimeInMinutes(slot.to_time)
      );

      // if locked by salon but not assistant, do nothing
      if (slotLocked && !lockedByAssistant) return;

      // toggle active slot
      const slotId = `${attendant.id}-${timeslot}`;

      // remove active class from previous slot
      if (this.activeSlot) {
        const previousSlotEl = document.querySelector(`.time-slot[data-id="${this.activeSlot}"]`);
        if (previousSlotEl) {
          previousSlotEl.classList.remove('time-slot--active');
        }
      }

      // set new active slot or clear if same slot clicked
      this.activeSlot = this.activeSlot === slotId ? null : slotId;

      // add active class to new slot
      this.$nextTick(() => {
        if (this.activeSlot) {
          const timeSlotEl = document.querySelector(`.time-slot[data-id="${slotId}"]`);
          if (timeSlotEl) {
            timeSlotEl.classList.add('time-slot--active');
          }
        }
      });
    },
    getAttendantColumnStyle(attendant) {
      const width = this.columnWidths[attendant.id] || 245;
      const left = this.getAssistantColumnLeft(
          this.sortedAttendants.findIndex(a => a.id === attendant.id)
      );

      return {
        position: 'absolute',
        width: `${width}px`,
        left: `${left}px`,
        height: '100%',
        background: 'rgba(171, 180, 187, .33)',
        borderRadius: '8px',
        zIndex: 10
      };
    },
    getTimeSlotStyle(index) {
      return {
        position: 'absolute',
        top: `${index * this.slotHeight}px`,
        left: 0,
        right: 0,
        height: `${this.slotHeight}px`
      };
    },
    getAssistantColumnLeft(index) {
      return this.sortedAttendants.slice(0, index).reduce((sum, attendant) => {
        const width = this.columnWidths[attendant.id] || 245;
        return sum + width + 8;
      }, 0);
    },
    doTimeslotsOverlap(start1, end1, start2, end2) {
      const startMinutes1 = this.getTimeInMinutes(start1);
      const endMinutes1 = end1 ? this.getTimeInMinutes(end1) : startMinutes1 + 30;
      const startMinutes2 = this.getTimeInMinutes(start2);
      const endMinutes2 = this.getTimeInMinutes(end2);

      return startMinutes1 < endMinutes2 && endMinutes1 > startMinutes2;
    },
    isSlotCenterOfLock(timeslot, attendantId) {
      // find locks that match this attendant and date
      const formattedDate = this.getFormattedDate();
      const relevantLocks = this.lockedTimeslots.filter(slot => {
        const isCorrectAssistant = slot.assistant_id === attendantId || slot.assistant_id === null;
        const isCorrectDate = slot.from_date === formattedDate;
        return isCorrectAssistant && isCorrectDate;
      });

      // if no locks found or only global locks
      if (relevantLocks.length === 0 || relevantLocks.every(lock => lock.assistant_id === null)) {
        return false;
      }

      // check if slot is in a lock period
      const slotMinutes = this.getTimeInMinutes(timeslot);
      const currentLock = relevantLocks.find(lock => {
        const lockStart = this.getTimeInMinutes(lock.from_time);
        let lockEnd = this.getTimeInMinutes(lock.to_time);
        if (lock.to_time === '00:00' && lock.from_date === lock.to_date) {
          lockEnd = this.MINUTES_IN_DAY;
        }

        return slotMinutes >= lockStart && slotMinutes < lockEnd;
      });

      if (!currentLock?.is_manual) return false;

      // find the center slot in the lock period
      const lockStart = this.getTimeInMinutes(currentLock.from_time);
      let lockEnd = this.getTimeInMinutes(currentLock.to_time);
      if (currentLock.to_time === '00:00' && currentLock.from_date === currentLock.to_date) {
        lockEnd = this.MINUTES_IN_DAY;
      }

      const slotsInLock = this.timeslots.filter(slot => {
        const slotMin = this.getTimeInMinutes(slot);
        return slotMin >= lockStart && slotMin < lockEnd;
      });

      // return true if this is the center slot
      const centerSlotIndex = Math.floor(slotsInLock.length / 2);
      const centerSlot = slotsInLock[centerSlotIndex];

      return this.normalizeTime(timeslot) === this.normalizeTime(centerSlot);
    },
    timeToMinutes(time) {
      if (!time) return 0;
      if (time === this.END_OF_DAY || time === '24:00') return this.MINUTES_IN_DAY;
      if (this.$root.settings.time_format.js_format === 'h:iip') {
        const momentTime = this.moment(time, 'h:mm A');
        const [hours, minutes] = [momentTime.hours(), momentTime.minutes()];
        return hours * 60 + minutes;
      }

      const [hours, minutes] = time.split(':').map(Number);
      return hours * 60 + minutes;
    },
    isSelectedSlot(timeslot, attendantId) {
      return this.selectedSlots.some(
          slot => slot.timeslot === timeslot && slot.attendantId === attendantId
      );
    },
    addBooking(timeslot, attendant) {
      this.$emit('add', {
        timeslot,
        attendantId: attendant.id
      });
    },
    isSlotProcessing(timeslot, nextTimeslot, attendantId) {
      return this.processingSlots.has(this.getSlotKey(timeslot, nextTimeslot, attendantId));
    },
    getNextTimeslot(index) {
      return index + 1 < this.timeslots.length ? this.timeslots[index + 1] : null;
    },
    getSlotKey(timeslot, nextTimeslot, attendantId) {
      return `${attendantId}-${timeslot}-${nextTimeslot}`;
    },
    isTimeSlotAllowedByRule(rule, slotMinutes, currentDate) {
      /* check for specific dates */
      if (rule.select_specific_dates && rule.specific_dates) {
        const specificDates = rule.specific_dates.split(',');
        const currentDateStr = this.getFormattedDate(currentDate);
        if (!specificDates.includes(currentDateStr)) {
          return false;
        }
        // check time intervals if available
        if (Array.isArray(rule.from) && Array.isArray(rule.to) && rule.from.length > 0 && rule.to.length > 0) {
          return Object.keys(rule.from).some(index => {
            if (index > 0 && rule.disable_second_shift) {
              return false;
            }
            const fromMin = this.getTimeInMinutes(rule.from[index]);
            const toMin = this.getTimeInMinutes(rule.to[index]);
            return slotMinutes >= fromMin && slotMinutes < toMin;
          });
        }
        // fallback for always ==> true with no time intervals
        if (rule.always) {
          return true;
        }
        return false;
      }

      /* check for date range limits */
      if (!rule.always && (rule.from_date || rule.to_date)) {
        const ruleFromDate = rule.from_date ? this.moment(rule.from_date, 'YYYY-MM-DD') : null;
        const ruleToDate = rule.to_date ? this.moment(rule.to_date, 'YYYY-MM-DD') : null;
        if (ruleFromDate && currentDate.isBefore(ruleFromDate, 'day')) {
          return false;
        }
        if (ruleToDate && currentDate.isAfter(ruleToDate, 'day')) {
          return false;
        }
      }

      /* check shifts */
      if (rule.shifts && rule.shifts.length) {
        return this.isTimeInShifts(rule.shifts, slotMinutes);
      }

      /* check time intervals (from/to pairs) */
      if (Array.isArray(rule.from) && Array.isArray(rule.to)) {
        // check if current day is allowed
        if (!rule.days || rule.days[currentDate.isoWeekday()] !== 1) {
          return false;
        }
        return Object.keys(rule.from).some(index => {
          if (index > 0 && rule.disable_second_shift) {
            return false;
          }
          const fromMin = this.getTimeInMinutes(rule.from[index]);
          const toMin = this.getTimeInMinutes(rule.to[index]);
          return slotMinutes >= fromMin && slotMinutes < toMin;
        });
      }

      /* true for non-specific dates */
      if (rule.always && rule.days && rule.days[currentDate.isoWeekday()] === 1) {
        return true;
      }

      return false;
    },
    isSlotLockedForAttendant(timeslot, nextSlot, attendantId) {
      try {
        if (!timeslot) return true;

        // get date and weekday info
        const formattedDate = this.getFormattedDate();
        const currentDate = this.moment(formattedDate, 'YYYY-MM-DD');
        const weekdaySalon = currentDate.day() + 1; // 1=Sunday, 2=Monday...
        // const weekdayAssistant = currentDate.isoWeekday(); // 1=Monday, 7=Sunday

        // get attendant data
        const attendant = this.sortedAttendants.find(a => a.id === attendantId);
        if (!attendant) return true;

        // convert time to comparable format
        const slotMinutes = this.getTimeInMinutes(timeslot);

        /* PART 1: CHECK SALON AVAILABILITY */
        /* -- step 1: salon working day -- */
        const availableDays = this.$root.settings?.available_days || {};
        if (availableDays[weekdaySalon] !== '1') return true;

        /* -- step 2: hard blocks -- */
        // 2.1: check all holiday rules (salon-wide and assistant-specific)
        const holidayRule = this.lockedTimeslots.find(slot => {
            return this.isBlockedByHolidayRule(slot, attendantId, currentDate, slotMinutes);
        });
        if (holidayRule) return true;

        // 2.2: check salon holiday periods
        const holidayPeriod = this.$root.settings?.holidays?.some(holiday => {
          if (!holiday.from_date || !holiday.to_date) return false;
          const holidayFromDate = this.moment(holiday.from_date, "YYYY-MM-DD");
          const holidayToDate = this.moment(holiday.to_date, "YYYY-MM-DD");

          if (!currentDate.isBetween(holidayFromDate, holidayToDate, 'day', '[]')) return false;

          /* same‑day holiday */
          if (currentDate.isSame(holidayFromDate, 'day') && currentDate.isSame(holidayToDate, 'day')) {
            const fromTimeMinutes = this.getTimeInMinutes(holiday.from_time);
            const toTimeMinutes = this.getTimeInMinutes(holiday.to_time);
            return slotMinutes >= fromTimeMinutes && slotMinutes < toTimeMinutes;
          }

          /* first or last day of multi‑day holiday */
          if (currentDate.isSame(holidayFromDate, 'day')) return slotMinutes >= this.getTimeInMinutes(holiday.from_time);
          if (currentDate.isSame(holidayToDate, 'day')) return slotMinutes < this.getTimeInMinutes(holiday.to_time);
          return true; // ==> fully inside range
        });
        if (holidayPeriod) return true

        /* PART 2: CHECK ATTENDANT AVAILABILITY */
        // if attendant has custom availability rules ==> use only them
        if (attendant.availabilities?.length) {
          const isSlotAllowed = attendant.availabilities.some(rule =>
              this.isTimeSlotAllowedByRule(rule, slotMinutes, currentDate)
          );
          return !isSlotAllowed; // slot is locked if no rule allows it
        }

        /*/!* PART 3: FALLBACK TO SHOP RULES *!/
        // if no custom rules ==> use shop-specific rules
        if (this.shop?.id) {
          const shopAvail = this.getAssistantShopData(attendant, this.shop.id, 'availabilities') || [];
          const dayRules = shopAvail.filter(av => av.days?.[weekdayAssistant] === 1);
          if (dayRules.length) {
            const allowed = dayRules.some(av => this.isTimeInAvailability(av, slotMinutes, weekdayAssistant));
            return !allowed;
          }
          return true; // ==> no rule = day off
        }*/

        /* PART 4: FALLBACK TO SALON SCHEDULE */
        const salonAvail = this.$root.settings?.availabilities || [];
        if (salonAvail.length) {
          // get all applicable rules for this day
          const rule = salonAvail.filter(r => r.days?.[weekdaySalon] === '1');
          if (rule.length === 0) return true; // ==> no rules for this day = day off

          // check if time is in ANY shift of ANY applicable rule
          const isInAnyShift = rule.some(rule => {
            if (rule.shifts?.length) {
              return this.isTimeInShifts(rule.shifts, slotMinutes);
            } else if (Array.isArray(rule.from) && Array.isArray(rule.to)) {
              return this.isTimeInFromToFormat(rule.from, rule.to, slotMinutes);
            } else if (rule.always) {
              return true;
            }
            return false;
          });

          if (!isInAnyShift) return true; // ==> time not in any active shift
        } else {
          return true;
        }

        /*/!* PART 5: API-PROVIDED ALLOWED TIMES *!/
        const workTimes = this.availabilityIntervals?.workTimes || {};
        const times = this.availabilityIntervals?.times || {};
        const allowedTimes = Object.keys(workTimes).length ? workTimes : times;

        if (Object.keys(allowedTimes).length) {
          const isTimeAllowed = Object.values(allowedTimes).some(time => this.getTimeInMinutes(time) === slotMinutes);
          if (!isTimeAllowed) return true; // ==> time not found in allowed times
        }*/

        return false; // ==> all checks passed ==> slot is available

      } catch (error) {
        console.error('Error in isSlotLockedForAttendant:', error);
        return true;
      }
    },
    getAssistantShopData(attendant, shopId, property) {
      if (!attendant || !attendant.shops || !shopId) return null;

      const shop = attendant.shops.find(shop => shop.id === shopId);
      return shop?.[property] || null;
    },
    normalizeTime(time) {
      if (!time) return time;
      if (time === this.END_OF_DAY || time === '24:00') return this.END_OF_DAY;

      if (this.$root.settings?.time_format?.js_format === 'h:iip') {
        const momentTime = this.moment(time, 'h:mm A');
        return momentTime.format('HH:mm');
      }

      const momentFormat = this.getTimeFormat();
      return this.moment(time, momentFormat).format('HH:mm');
    },
    getTimeSlotLineStyle(index) {
      const topPx = index * this.slotHeight;
      return {
        position: "absolute",
        left: 0,
        right: 0,
        top: `${topPx}px`,
        height: "1px",
        backgroundColor: "#ddd",
        zIndex: 1
      };
    },
    withShop(params = {}) {
      if (this.isShopsEnabled && this.selectedShopId) {
        return {...params, shop: this.selectedShopId};
      }
      return {...params};
    },
  },
  emits: ['add', 'update:lockedTimeslots', 'slot-processing', 'lock', 'unlock'],
};
</script>

<style scoped>
.attendant-time-slots {
  position: relative;
  width: 100%;
  height: 100%;
}

.time-slot-lines {
  position: absolute;
  top: -1px;
  left: 0;
  right: 0;
  bottom: 0;
  pointer-events: none;
  z-index: 11;
}

.time-slot-line {
  position: absolute;
  left: 0;
  right: 0;
  height: 1px;
  background-color: #ddd;
}

.time-slot {
  position: absolute;
  width: 100%;
  display: flex;
  justify-content: center;
  align-items: center;
}

.time-slot-inner {
  position: relative;
  height: 100%;
  width: 100%;
  padding: 8px 12px;
  background: rgba(255, 255, 255, 0.33);
  transition: all 0.2s ease;
  display: flex;
  align-items: center;
  justify-content: center;
}

.time-slot-inner--active {
  background-color: rgb(237 240 245 / 40%) !important;
  backdrop-filter: blur(5px);
}

.time-slot-inner--processing {
  background-color: #fff3cd !important;
  cursor: wait;
}

.time-slot-inner--locked {
  background-color: rgba(248, 215, 218, 0.4) !important;
}

.locked-indicator {
  font-size: 35px;
  color: #04409F;
  display: flex;
  align-items: center;
  justify-content: center;
}

.slot-actions {
  display: flex;
  gap: 16px;
  align-items: center;
  justify-content: center;
  opacity: 0;
  transition: opacity 0.2s ease;
  pointer-events: none;
}

.time-slot--active .slot-actions,
.slot-actions--locked {
  opacity: 1;
  pointer-events: auto;
}

.spinner-border {
  width: 35px;
  height: 35px;
  color: #04409F;
}

.add-button,
.lock-button,
.unlock-button {
  background: none;
  border: none;
  color: #04409F;
  padding: 4px;
  line-height: 1;
  font-size: 35px;
  transition: opacity 0.2s;
  display: flex;
  align-items: center;
  cursor: pointer !important;
}

.add-button:hover,
.lock-button:hover,
.unlock-button:hover {
  opacity: 0.8;
}

.slot-processing-spinner {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  z-index: 20;
}

.time-slot-inner--processing .slot-actions {
  opacity: 0.3;
  pointer-events: none;
}

.attendant-column {
  position: absolute;
  height: 100%;
  background: rgba(171, 180, 187, .33);
  z-index: 10;
  border-radius: 8px;
  user-select: none;
}

@media (hover: hover) {
  .time-slot-inner:hover {
    background-color: rgb(225 233 247 / 40%) !important;
  }

  .time-slot-inner--locked:hover {
    background-color: #f1b0b7 !important;
  }
}
</style>
