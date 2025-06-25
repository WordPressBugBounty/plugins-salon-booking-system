<template>
    <span class="remaining-amount-payment-link" v-show="show">
        <img src="./../../../assets/requestpayment.png" @click="payAmount"/>
        <b-spinner variant="primary" v-if="isLoading"></b-spinner>
        <b-alert :show="isSuccess" fade variant="success">{{ this.getLabel('successMessagePayRemainingAmount') }}</b-alert>
        <b-alert :show="isError" fade variant="danger">{{ this.getLabel('errorMessagePayRemainingAmount') }}</b-alert>
    </span>
</template>

<script>
    export default {
        name: 'PayRemainigAmount',
        props: {
            booking: {
                default: function () {
                    return {};
                },
            },
        },
        data() {
            return {
                isLoading: false,
                isSuccess: false,
                isError: false,
            }
        },
        computed: {
            deposit() {
                return this.booking.deposit
            },
            paid_remained() {
                return this.booking.paid_remained
            },
            show() {
                return this.deposit > 0 && !this.paid_remained
            },
            id() {
                return this.booking.id
            },
        },
        methods: {
            payAmount() {
                this.isLoading = true
                this.axios.get('bookings/' + this.id + '/pay-remaining-amount').then((response) => {
                    if (response.data.success) {
                        this.isSuccess = true
                    }
                    if (response.data.error) {
                        this.isError = true
                    }
                    setTimeout(() => {
                        this.isSuccess = false
                        this.isError = false
                    }, 3000)
                })
                .finally(() => {
                    this.isLoading = false
                })
            },
        }
    }
</script>

<style scoped>
    .remaining-amount-payment-link :deep(.spinner-border) {
        margin-left: 20px;
    }
    .remaining-amount-payment-link :deep(.alert) {
        padding: 3px 15px;
        font-size: 14px;
        margin-left: 20px;
        margin-bottom: 0;
    }
    .remaining-amount-payment-link {
        display: flex;
        align-items: center;
    }
</style>
