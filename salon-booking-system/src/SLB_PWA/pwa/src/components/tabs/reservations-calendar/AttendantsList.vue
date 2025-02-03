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
        <div class="attendant-name" :title="attendant.name">
          {{ attendant.name }}
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
  background: rgb(255 255 255 / 50%);
  border-radius: 8px;
  height: 48px;
  box-shadow: 0 1px 2px rgba(0, 0, 0, .05);
}

.attendant-avatar {
  display: flex;
  justify-content: center;
  align-items: center;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  overflow: hidden;
  border: 2px solid #fff;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
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
  max-width: 200px;
}

.attendant-column {
  flex-shrink: 0;
}
</style>
