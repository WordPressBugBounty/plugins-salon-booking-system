<template>
  <div v-show="show" class="screen-wrapper">
    <div class="screen-header">
      <button class="back-btn" type="button" @click="close()">
        <font-awesome-icon icon="fa-solid fa-arrow-left" />
      </button>
      <h1 class="screen-title">{{ getLabel('addReservationTitle') }}</h1>
      <span class="back-btn-placeholder"></span>
    </div>
    <EditBooking
        :date="date"
        :time="time"
        :customerID="customer ? customer.id : ''"
        :customerFirstname="customer ? customer.first_name : ''"
        :customerLastname="customer ? customer.last_name : ''"
        :customerEmail="customer ? customer.email : ''"
        :customerPhone="customer ? getBookingPhoneNumber(customer) : ''"
        :customerPhoneCountryCode="customer ? getBookingPhoneCountryCode(customer) : ''"
        :customerAddress="customer ? customer.address : ''"
        :customerPersonalNotes="customer ? customer.note : ''"
        :customerLanguage="customer && customer.language ? customer.language : ''"
        status="sln-b-confirmed"
        :shop="shop"
        :isLoading="isLoading"
        :isSaved="isSaved"
        :isError="isError"
        :errorMessage="errorMessage"
        @close="close"
        @chooseCustomer="chooseCustomer"
        @error-state="handleErrorState"
        @save="save"
    />
    <ConfirmModal
        v-model="bookingConflictConfirm.visible"
        :title="getLabel('bookingConflictTitle') || 'Possible conflict'"
        :message="bookingConflictConfirm.message"
        :confirm-label="getLabel('bookingConflictConfirmButtonLabel') || 'Save anyway'"
        :cancel-label="getLabel('bookingConflictCancelButtonLabel') || 'Cancel'"
        @confirm="resolveBookingConflictConfirm(true)"
        @cancel="resolveBookingConflictConfirm(false)"
    />
  </div>
</template>

<script>

import EditBooking from './../upcoming-reservations/EditBooking.vue'
import ConfirmModal from '../../ConfirmModal.vue'
import mixins from '@/mixin'
import { bookingConflictMixin } from '@/utils/bookingConflicts'

export default {
  name: 'AddBookingItem',
  mixins: [mixins, bookingConflictMixin],
  props: {
    date: {
      default: function () {
        return '';
      },
    },
    time: {
      default: function () {
        return '';
      },
    },
    customer: {
      default: function () {
        return {};
      },
    },
    shop: {
      default: function () {
        return {};
      },
    },
  },
  mounted() {
    this.toggleShow();
  },
  components: {
    EditBooking,
    ConfirmModal,
  },
  data: function () {
    return {
      isLoading: false,
      isSaved: false,
      isError: false,
      errorMessage: '',
      show: true,
      bookings: [],
    };
  },
  methods: {
    handleErrorState({isError, errorMessage}) {
      this.isError = isError;
      this.errorMessage = errorMessage;
    },
    close(booking) {
      this.isError = false
      this.$emit('close', booking);
    },
    chooseCustomer() {
      this.isError = false
      this.$emit('chooseCustomer');
    },
    async save(booking) {
      this.isLoading = true
      this.isError = false
      try {
        const response = await this.saveBookingAllowingConflicts(
            (extra) => this.axios.post('bookings', {...booking, ...extra}),
            () => { this.isLoading = false }
        )
        if (!response) {
          return
        }
        this.isSaved = true
        setTimeout(() => {
          this.isSaved = false
        }, 3000)
        this.isLoading = false
        this.axios.get('bookings/' + response.data.id).then((response) => {
          this.close(response.data.items[0])
        })
      } catch (e) {
        this.isError = true
        this.errorMessage = e.response?.data?.message || e.message
        this.isLoading = false
      }
    },
    toggleShow() {
      this.show = false
      setTimeout(() => {
        this.show = true
      }, 0)
    },
  },
  emits: ['close', 'chooseCustomer']
};
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
