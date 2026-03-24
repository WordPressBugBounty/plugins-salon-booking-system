<template>
  <transition name="sheet">
    <div v-if="show" class="sheet-overlay" @click.self="close">
      <div class="sheet-container">
        <div class="sheet-handle" @click="close"></div>
        <BookingCalendar
          :model-value="modelValue"
          :availability-stats="availabilityStats"
          :is-loading="isLoading"
          @update:model-value="onDateSelect"
          @month-year-update="$emit('month-year-update', $event)"
        />
        <button class="sheet-close-btn" @click="close">
          <font-awesome-icon icon="fa-solid fa-xmark" />
        </button>
      </div>
    </div>
  </transition>
</template>

<script>
import BookingCalendar from './BookingCalendar.vue';

export default {
  name: 'CalendarBottomSheet',

  components: { BookingCalendar },

  props: {
    show: {
      type: Boolean,
      default: false,
    },
    modelValue: {
      type: Date,
      required: true,
    },
    availabilityStats: {
      type: Array,
      default: () => [],
    },
    isLoading: {
      type: Boolean,
      default: false,
    },
  },

  emits: ['update:modelValue', 'month-year-update', 'close'],

  methods: {
    close() {
      this.$emit('close');
    },
    onDateSelect(date) {
      this.$emit('update:modelValue', date);
      this.$emit('close');
    },
  },
};
</script>

<style scoped>
.sheet-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.4);
  z-index: 200000;
  display: flex;
  align-items: flex-end;
}

.sheet-container {
  background: #FFFFFF;
  width: 100%;
  border-radius: 24px 24px 0 0;
  padding: 8px 16px calc(env(safe-area-inset-bottom, 0px) + 24px);
  position: relative;
  max-height: 90vh;
  overflow-y: auto;
}

.sheet-handle {
  width: 40px;
  height: 4px;
  border-radius: 999px;
  background: #E2E8F0;
  margin: 0 auto 16px;
  cursor: pointer;
}

.sheet-close-btn {
  position: absolute;
  top: 12px;
  right: 16px;
  background: #F4F6FA;
  border: none;
  width: 32px;
  height: 32px;
  border-radius: 999px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  color: #64748B;
  font-size: 14px;
  transition: background 0.15s;
}

.sheet-close-btn:hover {
  background: #E2E8F0;
  color: #0F172A;
}

.sheet-enter-active,
.sheet-leave-active {
  transition: opacity 0.25s ease;
}

.sheet-enter-active .sheet-container,
.sheet-leave-active .sheet-container {
  transition: transform 0.25s ease;
}

.sheet-enter-from,
.sheet-leave-to {
  opacity: 0;
}

.sheet-enter-from .sheet-container,
.sheet-leave-to .sheet-container {
  transform: translateY(100%);
}
</style>
