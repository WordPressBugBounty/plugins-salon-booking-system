<template>
  <div class="slot-actions">
    <BookingAdd
        v-if="!isLocked(timeSlot, getNextSlot())"
        :timeslot="timeSlot"
        :is-available="isAvailable(timeSlot)"
        @add="$emit('add', timeSlot)"
    />
    <BookingBlockSlot
        v-if="!hasOverlapping(index) || isLocked(timeSlot, getNextSlot())"
        :is-lock="isLocked(timeSlot, getNextSlot())"
        :is-system-locked="isSystemLocked(timeSlot)"
        :is-manual-locked="isManualLocked(timeSlot, getNextSlot())"
        :is-disabled="isDisabled"
        :start="timeSlot"
        :shop="shop"
        :end="getNextSlot()"
        :date="getFormattedDate()"
        :assistant-id="assistantId"
        @lock-start="handleLockStart"
        @lock="$emit('lock', $event)"
        @lock-end="handleLockEnd"
        @unlock-start="handleUnlockStart"
        @unlock="$emit('unlock', $event)"
        @unlock-end="handleUnlockEnd"
    />
  </div>
</template>

<script>
import BookingAdd from './BookingAdd.vue';
import BookingBlockSlot from './BookingBlockSlot.vue';

export default {
  name: 'SlotActions',
  components: {
    BookingAdd,
    BookingBlockSlot
  },
  props: {
    shop: {
      default: () => ({})
    },
    index: {
      type: Number,
      required: true
    },
    timeSlot: {
      type: String,
      required: true
    },
    timeslots: {
      type: Array,
      required: true
    },
    isLocked: {
      type: Function,
      required: true
    },
    isAvailable: {
      type: Function,
      required: true
    },
    hasOverlapping: {
      type: Function,
      required: true
    },
    date: {
      type: Date,
      required: true
    },
    assistantId: {
      type: Number,
      default: null
    },
    isSystemLocked: {
      type: Function,
      required: true
    },
    isManualLocked: {
      type: Function,
      required: true
    },
    isDisabled: {
      type: Boolean,
      default: false
    },
  },
  methods: {
    getNextSlot() {
      return this.timeslots[this.index + 1] || null;
    },
    getFormattedDate() {
      return this.dateFormat(this.date, 'YYYY-MM-DD');
    },
    handleLockStart() {
      this.$emit('update-processing', {slot: `${this.timeSlot}-${this.getNextSlot()}`, status: true});
    },
    handleLockEnd() {
      this.$emit('update-processing', {slot: `${this.timeSlot}-${this.getNextSlot()}`, status: false});
    },
    handleUnlockStart() {
      this.$emit('update-processing', {slot: `${this.timeSlot}-${this.getNextSlot()}`, status: true});
      this.$emit('unlock-start');
    },
    handleUnlockEnd() {
      this.$emit('update-processing', {slot: `${this.timeSlot}-${this.getNextSlot()}`, status: false});
      this.$emit('unlock-end');
    }
  },
  emits: ['add', 'lock', 'unlock', 'lock-start', 'lock-end', 'unlock-start', 'unlock-end', 'update-processing']
}
</script>

<style scoped>
.slot-actions {
  display: flex;
  gap: 95px;
  align-items: center;
}
</style>