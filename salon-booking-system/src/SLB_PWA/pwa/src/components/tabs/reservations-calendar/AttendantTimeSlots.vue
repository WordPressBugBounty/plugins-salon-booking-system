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
      activeSlot: null
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
    }
  },
  watch: {
    lockedTimeslots: {
      immediate: true,
    },
  },
  methods: {
    handleSlotClick(timeslot, attendant) {
      const slotId = `${attendant.id}-${timeslot}`;
      if (this.activeSlot) {
        const previousSlotEl = document.querySelector(`.time-slot[data-id="${this.activeSlot}"]`);
        if (previousSlotEl) {
          previousSlotEl.classList.remove('time-slot--active');
        }
      }
      this.activeSlot = this.activeSlot === slotId ? null : slotId;
      this.$nextTick(() => {
        const timeSlotEl = document.querySelector(`.time-slot[data-id="${slotId}"]`);
        if (timeSlotEl) {
          timeSlotEl.classList.toggle('time-slot--active', this.activeSlot === slotId);
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
      const startMinutes1 = this.timeToMinutes(this.normalizeTime(start1));
      const endMinutes1 = end1 ? this.timeToMinutes(this.normalizeTime(end1)) : startMinutes1 + 30;
      const startMinutes2 = this.timeToMinutes(this.normalizeTime(start2));
      const endMinutes2 = this.timeToMinutes(this.normalizeTime(end2));

      return startMinutes1 < endMinutes2 && endMinutes1 > startMinutes2;
    },

    isSlotCenterOfLock(timeslot, attendantId) {
      const relevantLocks = this.lockedTimeslots.filter(slot => {
        const isCorrectAssistant = slot.assistant_id === attendantId || slot.assistant_id === null;
        const isCorrectDate = slot.from_date === this.moment(this.date).format("YYYY-MM-DD");
        return isCorrectAssistant && isCorrectDate;
      });

      if (relevantLocks.length === 0) return false;

      const currentSlotMinutes = this.timeToMinutes(this.normalizeTime(timeslot));
      const currentLock = relevantLocks.find(lock => {
        const lockStart = this.timeToMinutes(this.normalizeTime(lock.from_time));
        const lockEnd = this.timeToMinutes(this.normalizeTime(lock.to_time));
        return currentSlotMinutes >= lockStart && currentSlotMinutes < lockEnd;
      });

      if (!currentLock) return false;

      const lockStart = this.timeToMinutes(this.normalizeTime(currentLock.from_time));
      const lockEnd = this.timeToMinutes(this.normalizeTime(currentLock.to_time));

      const slotsInLock = this.timeslots.filter(slot => {
        const slotMinutes = this.timeToMinutes(this.normalizeTime(slot));
        return slotMinutes >= lockStart && slotMinutes < lockEnd;
      });

      const centerSlotIndex = Math.floor(slotsInLock.length / 2);
      const centerSlot = slotsInLock[centerSlotIndex];

      return this.normalizeTime(timeslot) === this.normalizeTime(centerSlot);
    },

    timeToMinutes(time) {
      if (!time) return 0;
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


    isSlotLockedForAttendant(timeslot, nextTimeslot, attendantId) {
      const lockedSlots = this.lockedTimeslots.filter(slot =>
          (slot.assistant_id === attendantId || slot.assistant_id === null) &&
          slot.from_date === this.moment(this.date).format("YYYY-MM-DD")
      );
      return lockedSlots.some(locked =>
          this.doTimeslotsOverlap(timeslot, nextTimeslot, locked.from_time, locked.to_time)
      );
    },

    normalizeTime(time) {
      return this.moment(time, 'h:mma').format('HH:mm');
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

    async lockSlot(timeslot, attendant, index) {
      const nextTimeslot = this.getNextTimeslot(index);
      const slotKey = this.getSlotKey(timeslot, nextTimeslot, attendant.id);

      if (this.processingSlots.has(slotKey)) return;
      this.processingSlots.add(slotKey);

      try {
        const payload = {
          assistants_mode: true,
          assistant_id: attendant.id || null,
          date: this.moment(this.date).format("YYYY-MM-DD"),
          from_date: this.moment(this.date).format("YYYY-MM-DD"),
          to_date: this.moment(this.date).format("YYYY-MM-DD"),
          from_time: this.moment(timeslot, 'h:mma').format('HH:mm'),
          to_time: nextTimeslot ?
              this.moment(nextTimeslot, 'h:mma').format('HH:mm') :
              this.moment(timeslot, 'h:mma').add(30, 'minutes').format('HH:mm'),
          daily: true,
        };

        await this.axios.post('holiday-rules', payload);
        const updatedRules = await this.axios.get('holiday-rules', {
          params: {
            assistants_mode: true,
            date: this.moment(this.date).format("YYYY-MM-DD"),
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

        this.$emit('lock', payload);
      } catch (error) {
        console.error('slot lock error:', error);
      } finally {
        this.processingSlots.delete(slotKey);
      }
    },

    async unlockSlot(timeslot, attendant, index) {
      const nextTimeslot = this.getNextTimeslot(index);
      const slotKey = this.getSlotKey(timeslot, nextTimeslot, attendant.id);

      if (this.processingSlots.has(slotKey)) return;
      this.processingSlots.add(slotKey);

      try {
        const formattedDate = this.moment(this.date).format('YYYY-MM-DD');
        const relevantLock = this.lockedTimeslots.find(slot => {
          const isNullAssistantLock = slot.assistant_id === null;
          const isSpecificAssistantLock = slot.assistant_id === attendant.id;
          const isCorrectDate = slot.from_date === formattedDate;
          const slotTime = this.timeToMinutes(this.normalizeTime(timeslot));
          const lockStart = this.timeToMinutes(this.normalizeTime(slot.from_time));
          const lockEnd = this.timeToMinutes(this.normalizeTime(slot.to_time));

          return (isNullAssistantLock || isSpecificAssistantLock) &&
              isCorrectDate &&
              (slotTime >= lockStart && slotTime < lockEnd);
        });

        if (!relevantLock) {
          this.processingSlots.delete(slotKey);
          return;
        }

        const payload = {
          ...relevantLock,
          assistants_mode: true,
          assistant_id: attendant.id,
          from_date: formattedDate,
          to_date: formattedDate,
          from_time: this.moment(relevantLock.from_time, 'h:mma').format('HH:mm'),
          to_time: this.moment(relevantLock.to_time, 'h:mma').format('HH:mm'),
          shop: this.shop?.id || 0,
        };

        await this.axios.delete('holiday-rules', {data: payload});

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

        this.$emit('unlock', payload);
      } catch (error) {
        console.error('slot unlock error:', error);
      } finally {
        this.processingSlots.delete(slotKey);
      }
    }
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