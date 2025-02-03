<template>
  <div class="time-axis">
    <div
        v-for="(timeslot, index) in timeslots"
        :key="'axis-' + index"
        class="time-axis-item"
        :style="{ height: slotHeight + 'px' }"
    >
      {{ timeslot }}
    </div>
  </div>
</template>

<script>
export default {
  name: 'TimeAxis',
  props: {
    timeslots: {
      type: Array,
      required: true
    },
    slotHeight: {
      type: Number,
      required: true
    }
  }
};
</script>

<style scoped>
.time-axis {
  flex-shrink: 0;
  transition: .15s ease-in-out;
}

.time-axis-item {
  color: #5f5f5f;
  padding: 7px 14px 0 0;
  font-size: 14px;
  border-bottom: 1px solid #ddd;
  box-sizing: border-box;
}

.time-axis-item:last-child {
  border-bottom: none;
}
</style>