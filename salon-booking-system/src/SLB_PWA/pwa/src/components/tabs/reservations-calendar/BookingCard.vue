<template>
  <div class="booking-wrapper">
    <div class="booking">
      <div class="customer-info">
        <div class="customer-info-header">
          <span class="customer-name" @click="showDetails">{{ customer }}</span>
          <span class="booking-id">{{ id }}</span>
        </div>
      </div>
      <div class="services-list">
        <div
            class="service-item"
            v-for="(service, index) in booking.services"
            :key="index"
        >
          <span class="service-name">{{ service.service_name }}</span>
          <span class="assistant-name">{{ service.assistant_name }}</span>
        </div>
      </div>

      <div class="booking-actions-bottom">
        <button class="booking-actions-menu-dots" @click.stop="toggleActionsMenu">
          &bull;&bull;&bull;
        </button>
      </div>

      <div class="booking-status">
        <span class="status-label">{{ statusLabel }}</span>
      </div>
    </div>
    <CustomerActionsMenu
        :booking="booking"
        :show="showActionsMenu"
        @close="showActionsMenu = false"
        @edit="onEdit"
        @delete="onDelete"
        @view-profile="onViewProfile"
    />
  </div>
</template>

<script>
// import PayRemainingAmount from '../upcoming-reservations/PayRemainingAmount.vue'
import CustomerActionsMenu from './CustomerActionsMenu.vue';

export default {
  name: 'BookingCard',
  components: {
    CustomerActionsMenu,
    // PayRemainingAmount,
  },
  props: {
    booking: {
      default: function () {
        return {};
      },
    },
  },
  data() {
    return {
      isDelete: false,
      showActionsMenu: false
    }
  },
  computed: {
    customer() {
      return `${this.booking.customer_first_name} ${this.booking.customer_last_name}`
    },
    id() {
      return this.booking.id
    },
    assistants() {
      return (this.booking.services || [])
          .map(service => ({
            id: service.assistant_id,
            name: service.assistant_name,
          }))
          .filter(i => +i.id)
    },
    statusLabel() {
      const statusKey = this.booking.status
      if (this.$root.statusesList && this.$root.statusesList[statusKey]) {
        return this.$root.statusesList[statusKey].label
      }
      return statusKey
    },
  },
  methods: {
    toggleActionsMenu() {
      this.showActionsMenu = !this.showActionsMenu;
    },
    onEdit() {
      this.$emit('showDetails', this.booking);
      this.$emit('edit', this.booking);
    },
    onDelete() {
      this.$emit('deleteItem', this.booking.id);
    },
    onViewProfile(customer) {
      this.$emit('viewCustomerProfile', customer);
    },
    showDetails() {
      this.$emit('showDetails', this.booking);
    },
    getLabel(labelKey) {
      return this.$root.labels ? this.$root.labels[labelKey] : labelKey
    },
  },
  emits: ['deleteItem', 'showDetails', 'edit', 'viewCustomerProfile'],
}
</script>

<style scoped>
.booking-wrapper {
  position: relative;
  width: 100%;
  z-index: 20;
  padding: 10px;
}

.booking {
  margin: 0;
  background-color: rgb(225 230 239 / 70%);
  border-radius: 12px;
  width: 100%;
  height: 100%;
  padding: 8px 12px;
  display: flex;
  flex-direction: column;
  gap: 6px;
  border: 1px solid #e1e6ef;
  backdrop-filter: blur(5px);
  pointer-events: none;
  box-shadow: 0 0 10px 1px rgb(0 0 0 / 10%);
  position: relative;
}

.booking-status {
  position: absolute;
  bottom: 12px;
  right: 12px;
  text-transform: uppercase;
  font-size: 10px;
  letter-spacing: -0.1px;
  color: #637491;
}

.booking-actions {
  position: absolute;
  top: 12px;
  right: 12px;
  z-index: 5;
}

.booking-actions-button {
  background: none;
  border: none;
  color: #04409F;
  font-size: 20px;
  padding: 5px 10px;
  cursor: pointer;
  pointer-events: auto;
}

.customer-name {
  white-space: nowrap;
  overflow: hidden;
  color: #04409F;
  font-size: 16px;
  font-weight: 700;
  text-overflow: ellipsis;
  margin-right: 10px;
  cursor: pointer;
  pointer-events: auto;
}

.customer-info {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.customer-info-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;
}

.booking-id {
  font-size: 12px;
  color: #637491;
  font-weight: bold;
}

.services-list {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 4px;
}

.services-list:has(.service-item:nth-child(2)) {
  margin-top: 24px;
  gap: 8px;
}

.services-list .service-item {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
}

.services-list .service-item .service-name {
  color: #637491;
  font-size: 13px;
  text-align: left;
}

.services-list .service-item .assistant-name {
  color: #637491;
  font-size: 11px;
}

.booking-actions-bottom {
  position: absolute;
  left: 12px;
  bottom: 8px;
  z-index: 5;
}

.booking-actions-menu-dots {
  background: none;
  border: none;
  color: #000;
  font-size: 20px;
  line-height: 5px;
  letter-spacing: 1px;
  padding: 5px;
  cursor: pointer;
  pointer-events: auto;
}
</style>