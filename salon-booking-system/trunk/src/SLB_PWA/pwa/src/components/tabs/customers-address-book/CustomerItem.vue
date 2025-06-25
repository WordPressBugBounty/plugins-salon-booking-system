<template>
    <b-row>
        <b-col sm="12">
            <div class="customer">
                <b-row>
                    <b-col sm="8" class="customer-info">
                        <div class="customer-first-last-name-wrapper" @click="edit">
                            <span class="customer-firstname">
                                {{ customerFirstname }}
                            </span>
                            <span class="customer-lastname">
                                {{ customerLastname }}
                            </span>
                        </div>
                        <div class="customer-email">
                            {{ getDisplayEmail(customerEmail) }}
                        </div>
                        <div class="customer-phone" v-if="customerPhone">
                            {{ getDisplayPhone(customerPhone) }}
                        </div>
                    </b-col>
                    <b-col sm="4" class="total-order-wrapper">
                        <span class="total-order-sum">
                            <font-awesome-icon icon="fa-solid fa-chart-simple" />
                            <span v-html="totalSum"></span>
                        </span>
                        <span class="total-order-count">
                            <font-awesome-icon icon="fa-solid fa-medal" />
                            {{ customerScore }}
                        </span>
                    </b-col>
                </b-row>
                <b-row>
                    <b-col sm="12" class="total-info">
                        <div class="wrapper">
                            <div v-if="chooseCustomerAvailable" class="button-choose">
                                <font-awesome-icon icon="fa-solid fa-circle-plus" @click.prevent="choose" />
                            </div>
                            <div class="images" @click.prevent="showImages">
                                <img :src="photos.length ? photos[0]['url'] : ''" v-if="photos.length > 0" class="photo"/>
                                <font-awesome-icon icon="fa-solid fa-images" v-else/>
                            </div>
                        </div>
                        <div class="customer-phone-wrapper">
                            <span v-if="customerPhone && !shouldHidePhone">
                                <a target="_blank" :href="'tel:' + customerPhone" class="phone">
                                    <font-awesome-icon icon="fa-solid fa-phone" />
                                </a>
                                <a target="_blank" :href="'sms:' + customerPhone" class="sms">
                                    <font-awesome-icon icon="fa-solid fa-message" />
                                </a>
                                <a target="_blank" :href="'https://wa.me/' + customerPhone" class="whatsapp">
                                    <font-awesome-icon icon="fa-brands fa-whatsapp" />
                                </a>
                            </span>
                        </div>
                    </b-col>
                </b-row>
            </div>
        </b-col>
    </b-row>
</template>

<script>
    import mixins from "@/mixin";

    export default {
        name: 'CustomerItem',
        mixins: [mixins],
        props: {
            customer: {
                default: function () {
                    return {};
                },
            },
            chooseCustomerAvailable: {
                default: function () {
                    return false;
                },
            },
        },
        computed: {
            customerFirstname() {
                return this.customer.first_name
            },
            customerLastname() {
                return this.customer.last_name
            },
            customerEmail() {
                return this.getDisplayEmail(this.customer.email);
            },
            customerPhone() {
                const phone = this.customer.phone ?
                    this.customer.phone_country_code + this.customer.phone : '';
                return this.getDisplayPhone(phone);
            },
            customerScore() {
                return this.customer.score
            },
            totalSum() {
                return this.$root.settings.currency_symbol + this.customer.total_amount_reservations;
            },
            totalCount() {
                return this.customer.bookings.length > 0 ? this.customer.bookings.length : '-';
            },
            photos() {
                return this.customer.photos;
            },
        },
        methods: {
            choose() {
                this.$emit('choose')
            },
            showImages() {
                this.$emit('showImages', this.customer)
            },
            edit() {
                this.$emit('edit', this.customer)
            },
        },
        emits: ['choose', 'showImages', 'edit']
    }
</script>

<style scoped>
    .customer {
        padding: 10px;
        text-align: left;
        margin-bottom: 1rem;
        background-color: #ECF1FA9B;
        color: #637491;
    }
    .customer-firstname {
        margin-right: 5px;
    }
    .total-info {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .total-order-sum {
        margin-right: 15px;
    }

    .total-order-sum .fa-chart-simple {
        margin-right: 5px;
    }
    .fa-chart-simple,
    .fa-medal {
        color: #C7CED9;
        font-size: 24px;
    }
    .phone,
    .sms,
    .whatsapp {
        color: #04409F;
        font-size: 30px;
    }
    .button-choose {
        font-size: 24px;
        color: #04409F;
        margin-right: 5px;
        cursor: pointer;
    }
    .customer-first-last-name-wrapper,
    .customer-email {
        margin-bottom: 5px;
    }
    .customer-first-last-name-wrapper {
        color: #04409F;
        font-size: 22px;
    }
    .total-order-sum,
    .total-order-count {
        color: #637491;
        font-size: 20px;

    }
    .phone,
    .sms {
        margin-right: 20px;
    }
    .customer-phone-wrapper {
        text-align: right;
    }
    .total-order-wrapper {
        text-align: right;
    }
    .images {
        font-size: 30px;
        cursor: pointer;
    }
    .photo {
        max-width: 45px;
        border-radius: 30px;
        aspect-ratio: 1/1;
    }
    .wrapper {
        display: flex;
        align-items: center;
    }
    @media (max-width: 576px) {
        .customer-info {
            width: 50%;
        }
        .total-order-wrapper {
            width: 50%;
        }
    }
</style>
