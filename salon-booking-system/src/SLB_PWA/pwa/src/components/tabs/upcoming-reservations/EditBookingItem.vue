<template>
  <div v-show="show">
    <h5>
      {{ this.getLabel('editReservationTitle') }}
    </h5>
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
