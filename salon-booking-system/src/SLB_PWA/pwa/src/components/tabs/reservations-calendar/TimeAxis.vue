<template>
  <div class="time-axis">
    <div
        v-for="(timeslot, index) in formattedTimeslots"
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
    },
    timeFormatNew: {
      type: String,
      required: false,
    }
  },
  computed: {
    formattedTimeslots() {
      return this.timeslots.map(time => this.formatTime(time, this.timeFormatNew));
    }
  },
  methods: {
    formatTime(timeString) {
      return this.timeFormat(timeString);
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
  color: #94A3B8;
  padding: 7px 10px 0 0;
  font-size: 12px;
  font-weight: 400;
  border-bottom: 1px solid #E2E8F0;
  box-sizing: border-box;
  text-align: right;
}

.time-axis-item:last-child {
  border-bottom: none;
}
</style>
