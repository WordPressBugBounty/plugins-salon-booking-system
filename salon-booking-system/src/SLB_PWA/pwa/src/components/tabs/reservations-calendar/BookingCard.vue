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
      <div class="booking-status">
        <span class="status-label">{{ statusLabel }}</span>
      </div>
    </div>

    <template v-if="isDelete">
      <div class="delete-backdrop" @click="isDelete = false"></div>
      <div class="delete-btn-wrapper">
        <p class="delete-btn-wrapper-text">{{ this.getLabel('deleteBookingConfirmText') }}</p>
        <!-- <p>
                  <b-button
                      variant="primary"
                      @click="deleteItem"
                      class="delete-btn-wrapper-button"
                  >
                    {{ this.getLabel('deleteBookingButtonLabel') }}
                  </b-button>
                </p>-->
        <p>
          <a
              href="#"
              class="delete-btn-wrapper-go-back"
              @click.prevent="isDelete = false"
          >
            {{ this.getLabel('deleteBookingGoBackLabel') }}
          </a>
        </p>
      </div>
    </template>
  </div>
</template>

<script>
// import PayRemainingAmount from '../upcoming-reservations/PayRemainingAmount.vue'

export default {
  name: 'BookingCard',
  components: {
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
    deleteItem() {
      this.$emit('deleteItem')
      this.isDelete = false
    },
    showDetails() {
      this.$emit('showDetails', this.booking);
    },
    getLabel(labelKey) {
      return this.$root.labels ? this.$root.labels[labelKey] : labelKey
    },
  },
  emits: ['deleteItem', 'showDetails'],
}
</script>

<style scoped>
.booking-wrapper {
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

.delete {
  color: #6A6F76;
  cursor: pointer;
}

.details-link {
  color: #04409F;
  margin-left: 16px;
  cursor: pointer;
}

.booking-actions-remaining-amount {
  display: flex;
}

.delete-backdrop {
  position: fixed;
  width: 100%;
  height: 100%;
  top: 0;
  background-color: #E0E0E0E6;
  left: 0;
  z-index: 1000000;
}

.delete-btn-wrapper {
  position: fixed;
  top: 45%;
  left: 0;
  width: 100%;
  z-index: 1000000;
  text-align: center;
}

.delete-btn-wrapper-text {
  font-size: 12px;
  color: #322D38;
}

.delete-btn-wrapper-button {
  font-weight: bold;
  text-transform: uppercase;
}

.delete-btn-wrapper-go-back {
  color: #6A6F76;
  font-size: 12px;
}
</style>
