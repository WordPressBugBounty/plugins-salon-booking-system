<template>
    <div>
        <EditBookingItem v-if="editBooking" :booking="selectedBooking" :customer="bookingCustomer" @close="closeEditBooking"/>
        <BookingDetails v-else-if="showBooking" :show="true" :booking="selectedBooking" @close="closeBookingDetails" @edit="editBookingDetails"/>
        <ImagesList v-else-if="isShowImages" :customer="customer" @close="closeShowImages"/>
        <CustomerDetails v-else-if="editCustomer" @close="closeCustomerDetails"
                         @showBooking="showBookingDetails"
                         :customerID="customer.id"
                         :customerFirstname="customer.first_name"
                         :customerLastname="customer.last_name"
                         :customerEmail="customer.email"
                         :customerPhone="customer.phone"
                         :customerAddress="customer.address"
                         :customerPersonalNotes="customer.note"></CustomerDetails>
        <CustomersAddressBook :shop="shop" v-else @showImages="showImages" @edit="edit" :customer="customerData"
                              ref="customersAddressBook"/>
    </div>
</template>

<script>

    import CustomersAddressBook from './customers-address-book/CustomersAddressBook.vue'
    import ImagesList from './customers-address-book/ImagesList.vue'
    import CustomerDetails from "./customers-address-book/CustomerDetails.vue";
    import BookingDetails from './upcoming-reservations/BookingDetails.vue'
    import EditBookingItem from './upcoming-reservations/EditBooking.vue'

    export default {
        name: 'CustomersAddressBookTab',
        props: {
            shop: {
                default: function () {
                    return {};
                },
            }
        },
        components: {
            CustomerDetails,
            CustomersAddressBook,
            ImagesList,
            BookingDetails,
            EditBookingItem,
        },
        data: function () {
            return {
                isShowImages: false,
                customer: null,
                customerData: null,
                editCustomer: false,
                showBooking: false,
                selectedBooking: null,
                editBooking: false,
                bookingCustomer: null,
            }
        },
        methods: {
            showImages(customer) {
                this.isShowImages = true;
                this.customer = customer;
                this.$emit('hideTabsHeader', true)
            },
            closeShowImages(customer) {
                this.isShowImages = false;
                this.customerData = customer
                this.$emit('hideTabsHeader', false)
            },
            edit(customer) {
                console.log('🟣 CustomersAddressBookTab edit() called with customer:', customer)
                console.log('🟣 Customer ID:', customer.id)
                this.customer = customer;
                this.editCustomer = true;
            },
            closeCustomerDetails() {
                this.editCustomer = false;
                if (this.$refs.customersAddressBook) {
                    this.$refs.customersAddressBook.load();
                }
            },
            showBookingDetails(booking) {
                console.log('🟢 🟢 🟢 PARENT showBookingDetails called!', booking)
                console.log('🟢 Current showBooking value:', this.showBooking)
                console.log('🟢 Setting showBooking to true and selectedBooking to:', booking.id)
                
                // Show booking details within this tab
                this.selectedBooking = booking
                this.showBooking = true
                
                console.log('🟢 After setting - showBooking:', this.showBooking)
                console.log('🟢 After setting - selectedBooking:', this.selectedBooking)
            },
            closeBookingDetails() {
                this.showBooking = false
                this.selectedBooking = null
            },
            editBookingDetails() {
                console.log('🟡 editBookingDetails called for booking:', this.selectedBooking)
                
                // Extract customer data from the booking
                this.bookingCustomer = {
                    first_name: this.selectedBooking.customer_firstname,
                    last_name: this.selectedBooking.customer_lastname,
                    email: this.selectedBooking.customer_email,
                    phone: this.selectedBooking.customer_phone,
                    address: this.selectedBooking.customer_address,
                }
                
                // Show edit booking screen
                this.editBooking = true
                this.showBooking = false
            },
            closeEditBooking() {
                this.editBooking = false
                this.bookingCustomer = null
                // Optionally reload the booking details
                if (this.selectedBooking) {
                    this.showBooking = true
                }
            },
        },
        emits: ['hideTabsHeader']
    }
</script>

<style>

</style>