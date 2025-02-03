<template>
  <div>
    <div
        v-for="(slot, idx) in timeslots"
        :key="'line-' + idx"
        class="time-slot-line"
        :style="slotStyle(idx)"
        :class="{
        active: activeIndex === idx,
        locked: isLocked(timeslots[idx], timeslots[idx + 1]),
        processing: isProcessing(timeslots[idx], timeslots[idx + 1])
      }"
        @click="$emit('toggle', idx)"
    >
      <div class="time-slot-actions">
        <slot name="actions" v-bind="{ timeSlot: slot, slotIndex: idx }"></slot>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'TimeSlots',
  props: {
    timeslots: {
      type: Array,
      required: true
    },
    slotStyle: {
      type: Function,
      required: true
    },
    isLocked: {
      type: Function,
      required: true
    },
    isProcessing: {
      type: Function,
      required: true
    },
    activeIndex: {
      type: Number,
      default: -1
    }
  },
  emits: ['toggle']
};
</script>

<style scoped>
.time-slot-line {
  position: absolute;
  left: 0;
  width: 100%;
  z-index: 5;
  display: flex;
  align-items: center;
  box-sizing: border-box;
  margin-top: -1px;
  transition: all 0.15s ease;
}

.time-slot-line.active {
  z-index: 13;
  background-color: rgb(237 240 245 / 40%) !important;
  backdrop-filter: blur(5px);
}

.time-slot-line.locked {
  z-index: 13;
  background-color: rgba(248, 215, 218, 0.4) !important;
}

.time-slot-line.processing {
  z-index: 13;
  background-color: #fff3cd !important;
  cursor: wait;
}

.time-slot-line.processing .time-slot-actions,
.time-slot-line.locked .time-slot-actions {
  pointer-events: auto;
  opacity: 1;
}


.time-slot-actions {
  display: flex;
  align-items: center;
  gap: 95px;
  position: sticky;
  left: 35px;
  width: calc(100vw - 235px);
  justify-content: center;
  opacity: 0;
  pointer-events: none;
  transition: opacity 0.15s ease;
}

.time-slot-line.active .time-slot-actions {
  display: flex;
  opacity: 1;
  pointer-events: auto;
}

@media (hover: hover) {
  .time-slot-line:hover {
    background-color: rgb(225 233 247 / 40%) !important;
  }

  .time-slot-line.locked:hover {
    background-color: #f1b0b7 !important;
  }
}
</style>