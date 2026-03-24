<template>
  <div class="attendants-list" :class="{ 'attendants-list--hidden': isHidden }">
    <div
        v-for="(attendant, index) in attendants"
        :key="attendant.id"
        class="attendant-column"
        :style="{
        width: columnWidths[attendant.id] + 'px',
        marginRight: (index === attendants.length - 1 ? 0 : columnGap) + 'px'
      }"
    >
      <div class="attendant-header">
        <div class="attendant-avatar">
          <img
              v-if="attendant.image_url"
              :src="attendant.image_url"
              :alt="attendant.name"
          />
          <font-awesome-icon v-else icon="fa-solid fa-user-alt" class="default-avatar-icon"/>
        </div>
        <div class="attendant-name" :title="nameWithCount(attendant)">
          <span class="attendant-name-text">{{ attendant.name }}</span>
          <span class="attendant-booking-count">({{ rawBookingCount(attendant.id) }})</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'AttendantsList',
  props: {
    attendants: {
      type: Array,
      required: true
    },
    columnWidths: {
      type: Object,
      required: true
    },
    columnGap: {
      type: Number,
      required: true
    },
    isHidden: {
      type: Boolean,
      default: false
    },
    /** Map assistant id -> count of distinct bookings on the selected day */
    bookingCountsByAttendantId: {
      type: Object,
      default: () => ({})
    }
  },
  methods: {
    rawBookingCount(attendantId) {
      const map = this.bookingCountsByAttendantId || {};
      const n = map[attendantId] ?? map[String(attendantId)];
      return typeof n === 'number' ? n : 0;
    },
    nameWithCount(attendant) {
      return `${attendant.name} (${this.rawBookingCount(attendant.id)})`;
    }
  }
};
</script>

<style scoped>
.attendants-list {
  display: flex;
  position: absolute;
  top: 0;
  z-index: 10;
  padding: 8px 0;
  opacity: 1;
  transform: translateY(-10px);
  transition: opacity 0.3s ease, transform 0.3s ease;
  width: 100%;
}

.attendants-list--hidden {
  opacity: 0;
  transform: translateY(-10px);
}

.attendant-header {
  position: relative;
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  background: transparent;
  border-radius: 8px;
  height: 48px;
  box-shadow: none;
}

.attendant-avatar {
  display: flex;
  justify-content: center;
  align-items: center;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  overflow: hidden;
  border: 2px solid rgba(226, 232, 240, 0.95);
  box-shadow: none;
  color: #04409F;
}

.attendant-avatar img {
  display: block;
  object-fit: cover;
  width: 100%;
  height: 100%;
}

.attendant-name {
  font-weight: 500;
  color: #333;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 240px;
  display: flex;
  align-items: baseline;
  gap: 2px;
  min-width: 0;
}

.attendant-name-text {
  overflow: hidden;
  text-overflow: ellipsis;
}

.attendant-booking-count {
  flex-shrink: 0;
  font-weight: 600;
  font-size: 0.92em;
  color: #64748b;
}

.attendant-column {
  flex-shrink: 0;
}
</style>
