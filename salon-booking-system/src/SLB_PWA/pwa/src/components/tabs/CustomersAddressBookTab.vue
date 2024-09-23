<template>
    <div>
        <ImagesList :customer="customer" v-if="isShowImages" @close="closeShowImages"/>
        <CustomerDetails v-else-if="editCustomer" @close="closeCustomerDetails"
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
        },
        data: function () {
            return {
                isShowImages: false,
                customer: null,
                customerData: null,
                editCustomer: false
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
                this.customer = customer;
                this.editCustomer = true;
            },
            closeCustomerDetails() {
                this.editCustomer = false;
                if (this.$refs.customersAddressBook) {
                    this.$refs.customersAddressBook.load();
                }
            },
        },
        emits: ['hideTabsHeader']
    }
</script>

<style>

</style>