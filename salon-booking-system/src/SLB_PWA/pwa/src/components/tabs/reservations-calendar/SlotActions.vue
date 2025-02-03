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
    }
  },
  methods: {
    getNextSlot() {
      return this.timeslots[this.index + 1] || null;
    },
    getFormattedDate() {
      return this.moment(this.date).format('YYYY-MM-DD');
    },
    handleLockStart() {
      const slotKey = `${this.timeSlot}-${this.getNextSlot()}`;
      this.$emit('update-processing', {slot: slotKey, status: true});
    },
    handleLockEnd() {
      const slotKey = `${this.timeSlot}-${this.getNextSlot()}`;
      this.$emit('update-processing', {slot: slotKey, status: false});
    },
    handleUnlockStart() {
      const slotKey = `${this.timeSlot}-${this.getNextSlot()}`;
      this.$emit('update-processing', {slot: slotKey, status: true});
    },
    handleUnlockEnd() {
      const slotKey = `${this.timeSlot}-${this.getNextSlot()}`;
      this.$emit('update-processing', {slot: slotKey, status: false});
    },
  },
  emits: ['add', 'lock', 'unlock', 'lock-start', 'unlock-start', 'update-processing', 'refresh-rules'],
};
</script>

<style scoped>
.slot-actions {
  display: flex;
  gap: 95px;
  align-items: center;
}
</style>