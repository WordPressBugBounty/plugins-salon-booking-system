<template>
  <div class="assistant-columns">
    <div
        v-for="(attendant, index) in attendants"
        :key="'col-' + attendant.id"
        class="assistant-column-highlight"
        :style="{
        width: (columnWidths[attendant.id] || columnWidth) + 'px',
        left: getColumnLeft(index) + 'px'
      }"
    />
  </div>
</template>

<script>
export default {
  name: 'AttendantColumns',
  props: {
    attendants: {
      type: Array,
      required: true
    },
    columnWidths: {
      type: Object,
      required: true
    },
    columnWidth: {
      type: Number,
      required: true
    },
    getColumnLeft: {
      type: Function,
      required: true
    }
  }
};
</script>

<style scoped>
.assistant-column-highlight {
  position: absolute;
  top: 0;
  bottom: 0;
  background: rgba(171, 180, 187, .33);
  z-index: 10;
  border-radius: 8px;
  user-select: none;
  pointer-events: none;
}
</style>