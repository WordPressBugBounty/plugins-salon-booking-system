<template>
  <div v-show="show">
    <h5>
      {{ this.getLabel('addReservationTitle') }}
    </h5>
    <EditBooking
        :date="date"
        :time="time"
        :customerID="customer ? customer.id : ''"
        :customerFirstname="customer ? customer.first_name : ''"
        :customerLastname="customer ? customer.last_name : ''"
        :customerEmail="customer ? customer.email : ''"
        :customerPhone="customer ? customer.phone : ''"
        :customerAddress="customer ? customer.address : ''"
        :customerPersonalNotes="customer ? customer.note : ''"
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
  </div>
</template>

<script>

import EditBooking from './../upcoming-reservations/EditBooking.vue'


export default {
  name: 'AddBookingItem',
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
    save(booking) {
      this.isLoading = true
      this.axios.post('bookings', booking).then((response) => {
        this.isSaved = true
        setTimeout(() => {
          this.isSaved = false
        }, 3000)
        this.isLoading = false
        this.axios.get('bookings/' + response.data.id).then((response) => {
          this.close(response.data.items[0])
        })
      }, (e) => {
        this.isError = true
        this.errorMessage = e.response.data.message
        this.isLoading = false
      })
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
