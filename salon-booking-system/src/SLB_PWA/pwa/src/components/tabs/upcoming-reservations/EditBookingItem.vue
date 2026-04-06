<template>
  <div v-show="show" class="screen-wrapper">
    <div class="screen-header">
      <button class="back-btn" type="button" @click="close()">
        <font-awesome-icon icon="fa-solid fa-arrow-left" />
      </button>
      <h1 class="screen-title">{{ getLabel('editReservationTitle') }}</h1>
      <span class="back-btn-placeholder"></span>
    </div>
    <EditBooking
        :bookingID="booking.id"
        :date="booking.date"
        :time="booking.time"
        :customerID="customer ? customer.id : booking.customer_id"
        :customerFirstname="customer ? customer.first_name : booking.customer_first_name"
        :customerLastname="customer ? customer.last_name : booking.customer_last_name"
        :customerEmail="customer ? customer.email : booking.customer_email"
        :customerPhone="customer ? customer.phone : booking.customer_phone"
        :customerAddress="customer ? customer.address : booking.customer_address"
        :customerNotes="booking.note"
        :customerPersonalNotes="customer ? customer.note : booking.customer_personal_note"
        :adminNote="booking.admin_note"
        :services="booking.services"
        :discounts="booking.discounts"
        :status="booking.status"
        :isLoading="isLoading"
        :isSaved="isSaved"
        :isError="isError"
        :errorMessage="errorMessage"
        :customFields="booking.custom_fields"
        :shop="booking.shop"
        @close="close"
        @chooseCustomer="chooseCustomer"
        @error-state="handleErrorState"
        @save="save"
    />
  </div>
</template>

<script>
import EditBooking from './EditBooking.vue'

export default {
  name: 'EditBookingItem',
  props: {
    booking: {
      default: function () {
        return {};
      },
    },
    customer: {
      default: function () {
        return {};
      },
    },
  },
  components: {
    EditBooking,
  },
  mounted() {
    this.toggleShow()
  },
  data: function () {
    return {
      isLoading: false,
      isSaved: false,
      isError: false,
      errorMessage: '',
      show: true,
      bookings: [],
    }
  },
  methods: {
    handleErrorState({isError, errorMessage}) {
      this.isError = isError;
      this.errorMessage = errorMessage;
    },
    close(booking) {
      this.isError = false;
      this.$emit('close', booking);
    },
    chooseCustomer() {
      this.isError = false;
      this.$emit('chooseCustomer');
    },
    save(booking) {
      this.isLoading = true;
      this.axios.put('bookings/' + this.booking.id, booking).then((response) => {
        this.isSaved = true;
        setTimeout(() => {
          this.isSaved = false;
        }, 3000);
        this.isLoading = false;
        this.axios.get('bookings/' + response.data.id).then((response) => {
          this.close(response.data.items[0]);
        });
      }, (e) => {
        this.isError = true;
        this.errorMessage = e.response.data.message;
        this.isLoading = false;
      });
    },
    toggleShow() {
      this.show = false;
      setTimeout(() => {
        this.show = true;
      }, 0);
    },
  },
  emits: ['close', 'chooseCustomer']
}
</script>

<style scoped>
.screen-wrapper {
  min-height: 100vh;
  background: var(--color-background, #F4F6FA);
}
.screen-header {
  display: flex;
  align-items: center;
  padding: 12px var(--spacing-page, 16px);
  position: sticky;
  top: 0;
  z-index: 10;
}
.back-btn {
  background: none;
  border: none;
  padding: 6px 8px;
  color: var(--color-text-primary, #0F172A);
  font-size: 18px;
  cursor: pointer;
  min-width: 40px;
  min-height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  transition: background 0.15s;
}
.back-btn:hover { background: rgba(0,0,0,0.06); }
.back-btn-placeholder { min-width: 40px; }
.screen-title {
  flex: 1;
  text-align: center;
  font-size: 17px;
  font-weight: 600;
  color: var(--color-text-primary, #0F172A);
  margin: 0;
}
</style>
