<template>
    <div>
        <ImagesList :customer="showImagesCustomer" v-if="isShowCustomerImages" @close="closeShowCustomerImages" @takePhoto="showTakePhoto" :takePhotoFile="photo"/>
        <CustomersAddressBook v-else-if="isChooseCustomer" @closeChooseCustomer="closeChooseCustomer" :chooseCustomerAvailable="true" @choose="choose" :shop="item.shop"/>
        <EditBookingItem v-else-if="editItem" :booking="item" :customer="customer" @close="closeEditItem" @chooseCustomer="chooseCustomer"/>
        <CustomerDetails v-else-if="showCustomerProfile"
                         :customerID="selectedCustomer.id"
                         :customerFirstname="selectedCustomer.first_name"
                         :customerLastname="selectedCustomer.last_name"
                         :customerEmail="selectedCustomer.email"
                         :customerPhone="selectedCustomer.phone"
                         :customerAddress="selectedCustomer.address"
                         :customerPersonalNotes="selectedCustomer.note"
                         @close="closeCustomerProfile"/>
        <BookingDetails v-else-if="showItem" :booking="item" @close="closeShowItem" @edit="setEditItem" @showCustomerImages="showCustomerImages" @viewCustomerProfile="openCustomerProfile"/>
        <UpcomingReservations @showItem="setShowItem" v-show="!showItem && !showCustomerProfile" :shop="shop"/>
    </div>
</template>

<script>

    import UpcomingReservations from './upcoming-reservations/UpcomingReservations.vue'
    import BookingDetails from './upcoming-reservations/BookingDetails.vue'
    import EditBookingItem from './upcoming-reservations/EditBookingItem.vue'
    import CustomersAddressBook from './customers-address-book/CustomersAddressBook.vue'
    import ImagesList from './customers-address-book/ImagesList.vue'
    import CustomerDetails from './customers-address-book/CustomerDetails.vue'

    export default {
        name: 'UpcomingReservationsTab',
        props: {
            shop: {
                default: function () {
                    return {};
                },
            }
        },
        components: {
            UpcomingReservations,
            BookingDetails,
            EditBookingItem,
            CustomersAddressBook,
            ImagesList,
            CustomerDetails,
        },
        data: function () {
            return {
                showItem: false,
                editItem: false,
                item: null,
                isChooseCustomer: false,
                customer: null,
                isShowCustomerImages: false,
                showImagesCustomer: null,
                showCustomerProfile: false,
                selectedCustomer: null,
            }
        },
        mounted() {
        },
        beforeUnmount() {
        },
        methods: {
            setShowItem(item) {
                this.showItem = true;
                this.item = item;
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
            chooseCustomer() {
                this.isChooseCustomer = true;
            },
            closeChooseCustomer() {
                this.isChooseCustomer = false;
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
                console.log('🟣 UpcomingReservationsTab openCustomerProfile() called with customer:', customer)
                console.log('🟣 Customer ID:', customer.id)
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
            showBookingFromCustomer(booking) {
                console.log('🟢 showBookingFromCustomer called in UpcomingReservationsTab', booking)
                // Close customer profile and show the selected booking
                this.showCustomerProfile = false;
                this.selectedCustomer = null;
                this.setShowItem(booking);
            },
        },
        emits: ['hideTabsHeader'],
    }
</script>

<style scoped>

</style>