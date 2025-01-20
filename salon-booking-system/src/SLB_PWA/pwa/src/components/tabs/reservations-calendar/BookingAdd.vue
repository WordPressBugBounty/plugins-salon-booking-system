<template>
    <span class="booking-add">
      <font-awesome-icon icon="fa-solid fa-circle-plus" :class="{available: isAvailable}" @click="add"/>
    </span>
</template>

<script>
export default {
  name: 'BookingAdd',
  props: {
    timeslot: {
      default: function () {
        return '09:00';
      },
    },
    isAvailable: {
      default: function () {
        return true;
      },
    },
  },
  computed: {
    timeFormat() {
      return this.$root.settings && this.$root.settings.time_format ? this.$root.settings.time_format.type : 'default'
    },
  },
  methods: {
    add() {
      this.$emit('add')
    },
  },
  emits: ['add']
}
</script>

<style scoped>
.booking-add.available,
.booking-add {
  display: flex;
  align-items: center;
  color: #04409F;
  font-size: 35px;
  cursor: pointer !important;
  line-height: 1;
}

.booking-add svg,
.booking-add svg path {
  fill: currentColor;
}
</style>
