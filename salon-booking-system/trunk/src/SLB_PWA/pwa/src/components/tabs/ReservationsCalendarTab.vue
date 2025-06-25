<template>
  <div>
    <b-spinner variant="primary" v-if="isLoading"></b-spinner>
    <ImagesList :customer="showImagesCustomer" v-else-if="isShowCustomerImages" @close="closeShowCustomerImages"/>
    <CustomersAddressBook v-else-if="isChooseCustomer" @closeChooseCustomer="closeChooseCustomer"
                          :chooseCustomerAvailable="true" @choose="choose" :shop="addItem ? shop : item.shop"/>
    <AddBookingItem v-else-if="addItem" @close="close" :date="date" :time="time" :customer="customer"
                    @chooseCustomer="chooseCustomer" :shop="shop"/>
    <EditBookingItem v-else-if="editItem" :booking="item" :customer="customer" @close="closeEditItem"
                     @chooseCustomer="chooseCustomer"/>
    <CustomerDetails v-else-if="showCustomerProfile"
                     :customerID="selectedCustomer.id"
                     :customerFirstname="selectedCustomer.first_name"
                     :customerLastname="selectedCustomer.last_name"
                     :customerEmail="selectedCustomer.email"
                     :customerPhone="selectedCustomer.phone"
                     :customerAddress="selectedCustomer.address"
                     :customerPersonalNotes="selectedCustomer.note"
                     @close="closeCustomerProfile"/>
    <BookingDetails v-else-if="showItem" :booking="item" @close="closeShowItem" @edit="setEditItem"
                    @showCustomerImages="showCustomerImages"/>
    <ReservationsCalendar
        v-else
        v-model="selectedDate"
        @showItem="setShowItem"
        @add="add"
        @viewCustomerProfile="openCustomerProfile"
        :shop="shop"
    />
  </div>
</template>

<script>
import ReservationsCalendar from './reservations-calendar/ReservationsCalendar.vue'
import AddBookingItem from './reservations-calendar/AddBookingItem.vue'
import CustomersAddressBook from './customers-address-book/CustomersAddressBook.vue'
import BookingDetails from './upcoming-reservations/BookingDetails.vue'
import EditBookingItem from './upcoming-reservations/EditBookingItem.vue'
import ImagesList from './customers-address-book/ImagesList.vue'
import CustomerDetails from './customers-address-book/CustomerDetails.vue'

export default {
  name: 'ReservationsCalendarTab',
  props: {
    shop: {
      default: function () {
        return {};
      },
    }
  },
  components: {
    ReservationsCalendar,
    AddBookingItem,
    CustomersAddressBook,
    BookingDetails,
    EditBookingItem,
    ImagesList,
    CustomerDetails,
  },
  mounted() {
    let params = this.getQueryParams()
    if (typeof params['booking_id'] !== 'undefined') {
      this.isLoading = true;
      this.axios.get('bookings/' + params['booking_id']).then((response) => {
        this.isLoading = false;
        this.setShowItem(response.data.items[0])
      })
    }
  },
  data: function () {
    return {
      addItem: false,
      showItem: false,
      isChooseCustomer: false,
      item: null,
      editItem: false,
      customer: null,
      date: '',
      time: '',
      isLoading: false,
      isShowCustomerImages: false,
      showImagesCustomer: null,
      selectedDate: new Date(),
      showCustomerProfile: false,
      selectedCustomer: null,
    };
  },
  methods: {
    add(date, time) {
      this.addItem = true;
      this.date = date;
      this.time = time;
    },
    setShowItem(item) {
      this.showItem = true;
      this.item = item;
    },
    close(booking) {
      this.addItem = false;
      this.customer = null;
      if (booking) {
        this.setShowItem(booking)
      }
    },
    chooseCustomer() {
      this.isChooseCustomer = true;
    },
    closeChooseCustomer() {
      this.isChooseCustomer = false;
    },
    closeShowItem() {
      this.showItem = false;
    },
    setEditItem() {
      this.editItem = true;
    },
    closeEditItem(booking) {
      this.editItem = false;
      this.customer = null;
      if (booking) {
        this.setShowItem(booking)
      }
    },
    choose(customer) {
      this.customer = customer;
      this.closeChooseCustomer()
    },
    showCustomerImages(customer) {
      this.isShowCustomerImages = true;
      this.showImagesCustomer = customer;
      this.$emit('hideTabsHeader', true)
    },
    closeShowCustomerImages(customer) {
      this.item.customer_photos = customer.photos
      this.isShowCustomerImages = false;
      this.$emit('hideTabsHeader', false)
    },
    openCustomerProfile(customer) {
      this.selectedCustomer = customer;
      this.showItem = false;
      this.showCustomerProfile = true;
    },
    closeCustomerProfile() {
      this.showCustomerProfile = false;
      this.selectedCustomer = null;
      if (this.item) {
        this.showItem = true;
      }
    },
  },
  emits: ['hideTabsHeader'],
}
</script>