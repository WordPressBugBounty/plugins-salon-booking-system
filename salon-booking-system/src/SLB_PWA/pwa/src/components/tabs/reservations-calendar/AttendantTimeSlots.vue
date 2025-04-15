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
                <div class="slot-actions slot-actions--locked"
                     v-if="isSlotCenterOfLock(timeslot, attendant.id)">
                  <button @click.stop="unlockSlot(timeslot, attendant, index)"
                          class="unlock-button">
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
      timeCache: new Map()
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
    attendantsLoaded(newVal) {
      if (newVal) {
        this.loadLockedTimeslots();
      }
    },
    lockedTimeslots: {
      immediate: true,
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
      else if (availability.from && availability.to) {
        return this.isTimeInFromToFormat(availability.from, availability.to, minutes);
      }

      return false;
    },
    /* method to check if holiday rules block a time */
    isBlockedByHolidayRule(slot, attendantId, currentDate, slotMinutes) {
      if ((attendantId !== null && slot.assistant_id !== attendantId) ||
          (attendantId === null && slot.assistant_id !== null)) {
        return false;
      }

      const fromDate = this.moment(slot.from_date, 'YYYY-MM-DD');
      const toDate = this.moment(slot.to_date, 'YYYY-MM-DD');
      const isDateInRange = currentDate.isBetween(fromDate, toDate, 'day', '[]');

      if (!isDateInRange) return false;

      const fromMinutes = this.getTimeInMinutes(slot.from_time);
      const toMinutes = this.getTimeInMinutes(slot.to_time);

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
          params: {
            assistants_mode: true,
            date: formattedDate,
          }
        });

        if (updatedRules.data?.assistants_rules) {
          const assistantsRules = updatedRules.data.assistants_rules;
          this.$emit('update:lockedTimeslots', Object.entries(assistantsRules).flatMap(([assistantId, rules]) =>
              rules.map(rule => ({
                ...rule,
                assistant_id: Number(assistantId) || null,
              }))
          ));
        }

        return updatedRules;
      } catch (error) {
        console.error('Error updating locked timeslots:', error);
        throw error;
      }
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
          formattedToTime = this.normalizeTime(nextTimeslot);
        } else {
          formattedToTime = this.moment(formattedFromTime, 'HH:mm').add(30, 'minutes').format('HH:mm');
        }

        // prepare API payload
        const payload = {
          assistants_mode: true,
          assistant_id: attendant.id || null,
          date: formattedDate,
          from_date: formattedDate,
          to_date: formattedDate,
          from_time: formattedFromTime,
          to_time: formattedToTime,
          daily: true,
        };

        // send lock request
        await this.axios.post('holiday-rules', payload);

        // update locked timeslots
        await this.updateLockedTimeslots();

        // notify parent
        this.$emit('lock', payload);
      } catch (error) {
        console.error('Slot lock error:', error);
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

        if (!relevantLock) {
          this.processingSlots.delete(slotKey);
          return;
        }

        // prepare API payload
        const payload = {
          assistants_mode: true,
          assistant_id: attendant.id,
          from_date: formattedDate,
          to_date: formattedDate,
          from_time: this.normalizeTime(relevantLock.from_time),
          to_time: this.normalizeTime(relevantLock.to_time),
          daily: true,
          shop: this.shop?.id || 0,
        };

        // send unlock request
        await this.axios.delete('holiday-rules', {data: payload});

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
        const lockEnd = this.getTimeInMinutes(lock.to_time);
        return slotMinutes >= lockStart && slotMinutes < lockEnd;
      });

      if (!currentLock) return false;

      // find the center slot in the lock period
      const lockStart = this.getTimeInMinutes(currentLock.from_time);
      const lockEnd = this.getTimeInMinutes(currentLock.to_time);

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
    isSlotLockedForAttendant(timeslot, nextSlot, attendantId) {
      try {
        if (!timeslot) return true;

        // get date and weekday info
        const formattedDate = this.getFormattedDate();
        const currentDate = this.moment(formattedDate, 'YYYY-MM-DD');
        const globalWeekday = currentDate.day() + 1; // 1=Sunday, 2=Monday...
        const assistantWeekday = currentDate.isoWeekday(); // 1=Monday, 7=Sunday

        // get attendant data
        const attendant = this.sortedAttendants.find(a => a.id === attendantId);
        if (!attendant) return true;

        // convert time to comparable format
        const slotMinutes = this.getTimeInMinutes(timeslot);

        /* PART 1: CHECK SALON AVAILABILITY */
        // 1: first check: is salon open on this day? If not, all slots are locked
        const availableDays = this.$root.settings?.available_days || {};
        const isSalonWorkingDay = availableDays[globalWeekday] === "1";
        if (!isSalonWorkingDay) return true;

        /* PART 2: CHECK REQUIRED BLOCKS */
        // 2.1: check if attendant has specific locks for this slot
        const assistantHoliday = this.lockedTimeslots.find(slot =>
            slot.assistant_id === attendantId &&
            this.isBlockedByHolidayRule(slot, attendantId, currentDate, slotMinutes)
        );
        if (assistantHoliday) return true;

        // 2.2: check salon-wide blocks
        const globalHoliday = this.lockedTimeslots.find(slot =>
            slot.assistant_id === null &&
            this.isBlockedByHolidayRule(slot, null, currentDate, slotMinutes)
        );
        if (globalHoliday) return true;

        // 2.3: check salon holiday periods
        const holidayPeriod = this.$root.settings?.holidays?.find(holiday => {
          if (!holiday || !holiday.from_date || !holiday.to_date) return false;

          const holidayFromDate = this.moment(holiday.from_date, "YYYY-MM-DD");
          const holidayToDate = this.moment(holiday.to_date, "YYYY-MM-DD");

          if (currentDate.isSameOrAfter(holidayFromDate, 'day') &&
              currentDate.isSameOrBefore(holidayToDate, 'day')) {
            if (currentDate.isSame(holidayFromDate, 'day') && currentDate.isSame(holidayToDate, 'day')) {
              const fromTimeMinutes = this.getTimeInMinutes(holiday.from_time);
              const toTimeMinutes = this.getTimeInMinutes(holiday.to_time);
              return slotMinutes >= fromTimeMinutes && slotMinutes < toTimeMinutes;
            } else if (currentDate.isSame(holidayFromDate, 'day')) {
              const fromTimeMinutes = this.getTimeInMinutes(holiday.from_time);
              return slotMinutes >= fromTimeMinutes;
            } else if (currentDate.isSame(holidayToDate, 'day')) {
              const toTimeMinutes = this.getTimeInMinutes(holiday.to_time);
              return slotMinutes < toTimeMinutes;
            } else {
              return true;
            }
          }

          return false;
        });
        if (holidayPeriod) return true;

        // 2.4: check daily holiday rules
        const dailyHoliday = this.$root.settings?.holidays_daily?.find(rule => {
          if (rule.assistant_id !== null) return false;

          if (formattedDate === rule.from_date && formattedDate === rule.to_date) {
            const ruleFromMinutes = this.getTimeInMinutes(rule.from_time);
            const ruleToMinutes = this.getTimeInMinutes(rule.to_time);

            return slotMinutes >= ruleFromMinutes && slotMinutes < ruleToMinutes;
          }
          return false;
        });
        if (dailyHoliday) return true;

        // 2.5: check attendants holiday periods
        // 2.5.1: check shop-specific holidays for this attendant
        const shopSpecificHolidays = this.getAssistantShopData(attendant, this.shop?.id, 'holidays');
        if (this.isInHolidayPeriod(shopSpecificHolidays, formattedDate, timeslot, nextSlot)) return true;

        // 2.5.2: then check general attendant holidays if no shop-specific ones found
        if (!shopSpecificHolidays || shopSpecificHolidays.length === 0) {
          if (this.isInHolidayPeriod(attendant.holidays, formattedDate, timeslot, nextSlot)) return true;
        }

        /* PART 3: CHECK WORKING HOURS (PRIORITY HIERARCHY) */
        // 3: order of priority: shop-specific attendant schedule -> general attendant schedule -> salon schedule

        // 3.1: check shop-specific schedule for this attendant
        if (this.shop?.id) {
          const shopSpecificAvailabilities = this.getAssistantShopData(attendant, this.shop.id, 'availabilities');
          if (shopSpecificAvailabilities && shopSpecificAvailabilities.length > 0) {
            // check if ANY availability rule explicitly marks this day as non-working (0)
            const dayExplicitlyDisabled = shopSpecificAvailabilities.some(av =>
                av.days && av.days[assistantWeekday] === 0
            );

            if (dayExplicitlyDisabled) return true; // ==> slot is locked if day is explicitly marked as non-working

            // original logic for checking working hours if day is marked as working
            if (this.hasWorkingDay(shopSpecificAvailabilities, assistantWeekday)) {
              // if shop-specific schedule exists, use it and stop checking others
              const isInWorkingHours = shopSpecificAvailabilities.some(av =>
                  this.isTimeInAvailability(av, slotMinutes, assistantWeekday)
              );
              return !isInWorkingHours;
            }
          }
        }

        // 3.2: check general attendant schedule if no shop-specific schedule
        if (attendant.availabilities && attendant.availabilities.length > 0) {
          // check if ANY availability rule explicitly marks this day as non-working
          const dayExplicitlyDisabled = false;//attendant.availabilities.some(av => av.days && av.days[assistantWeekday] === 0);

          if (dayExplicitlyDisabled) return true; // ==> slot is locked if day is explicitly marked as non-working

          // original logic for checking working hours if day is marked as working
          if (this.hasWorkingDay(attendant.availabilities, assistantWeekday)) {
            // console.log(attendant.name)
            // if general schedule exists, use it and stop checking salon schedule
            const isInWorkingHours = attendant.availabilities.some(av =>
                this.isTimeInAvailability(av, slotMinutes, assistantWeekday)
            );
            return !isInWorkingHours;
          } else {
            return true;
          }
        }

        // 3.3: fall back to salon schedule if no attendant schedules exist
        const availabilities = this.$root.settings?.availabilities || [];

        if (availabilities.length > 0) {
          // find rule for current day
          const availabilityRule = availabilities.find(rule =>
              rule.days && rule.days[globalWeekday]
          );

          if (!availabilityRule) {
            return true; // ==> no salon rule for this day
          }

          // check salon shifts
          if (availabilityRule.shifts && availabilityRule.shifts.length > 0) {
            if (!this.isTimeInShifts(availabilityRule.shifts, slotMinutes)) {
              return true; // ==> time not in salon shifts
            }
          }
          // check old format salon schedule
          else if (availabilityRule.from && availabilityRule.to) {
            if (!this.isTimeInFromToFormat(availabilityRule.from, availabilityRule.to, slotMinutes)) {
              return true; // ==> time not in salon working hours
            }
          } else {
            return true; // ==> no working hours defined
          }
        } else {
          return true; // ==> no salon availability rules
        }

        /* PART 4: FINAL API CHECK */
        // check time is allowed by API
        const workTimes = this.availabilityIntervals?.workTimes || {};
        const times = this.availabilityIntervals?.times || {};
        const allowedTimes = Object.keys(workTimes).length ? workTimes : times;

        if (Object.keys(allowedTimes).length > 0) {
          const isTimeAllowed = Object.values(allowedTimes).some(time => {
            return this.getTimeInMinutes(time) === slotMinutes;
          });

          if (!isTimeAllowed) return true; // ==> time not found in allowed times
        }

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