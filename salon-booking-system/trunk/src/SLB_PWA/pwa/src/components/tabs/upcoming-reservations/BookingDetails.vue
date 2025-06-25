<template>
    <div v-show="show">
        <h5>
            {{ this.getLabel('bookingDetailsTitle') }}
        </h5>
        <b-row>
            <b-col sm="12">
                <div class="booking-details-customer-info">
                    <b-row>
                        <b-col sm="10"></b-col>
                        <b-col sm="2" class="actions">
                            <font-awesome-icon icon="fa-solid fa-circle-xmark" @click="close"/>
                        </b-col>
                    </b-row>
                    <b-row>
                        <b-col sm="6">
                            <div class="date">
                                <span>{{ this.getLabel('dateTitle') }}</span><br/>
                                <font-awesome-icon icon="fa-solid fa-calendar-days" /> {{ date }}
                            </div>
                        </b-col>
                        <b-col sm="6">
                            <div class="time">
                                <span>{{ this.getLabel('timeTitle') }}</span><br/>
                                <font-awesome-icon icon="fa-regular fa-clock" /> {{ time }}
                            </div>
                        </b-col>
                    </b-row>
                    <b-row>
                        <b-col sm="12">
                            <div class="customer-firstname">
                                {{ customerFirstname }}
                                <div class="images" @click.prevent="showCustomerImages">
                                    <img :src="photos.length ? photos[0]['url'] : ''" v-if="photos.length > 0" class="photo"/>
                                    <font-awesome-icon icon="fa-solid fa-images" v-else/>
                                </div>
                            </div>
                        </b-col>
                    </b-row>
                    <b-row>
                        <b-col sm="12">
                            <div class="customer-lastname">
                                {{ customerLastname }}
                            </div>
                        </b-col>
                    </b-row>
                    <b-row>
                        <b-col sm="12">
                            <div class="customer-email">
                                {{ getDisplayEmail(customerEmail) }}
                            </div>
                        </b-col>
                    </b-row>
                    <b-row>
                        <b-col sm="12">
                            <div class="customer-phone">
                                {{ getDisplayPhone(customerPhone) }}
                                <span class="customer-phone-actions" v-if="customerPhone && !shouldHidePhone">
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
                    <b-row>
                        <b-col sm="12">
                            <div class="customer-note">
                                {{ customerNote }}
                            </div>
                        </b-col>
                    </b-row>
                </div>
            </b-col>
        </b-row>
        <b-row>
            <b-col sm="12">
                <div class="booking-details-extra-info">
                    <div class="booking-details-extra-info-header">
                        <div class="booking-details-extra-info-header-title">
                            {{ this.getLabel('extraInfoLabel') }}
                        </div>
                        <div>
                            <span
                                class="booking-details-extra-info-header-btn"
                                :class="visibleExtraInfo ? null : 'collapsed'"
                                :aria-expanded="visibleExtraInfo ? 'true' : 'false'"
                                aria-controls="collapse-2"
                                @click="visibleExtraInfo = !visibleExtraInfo"
                            >
                                <font-awesome-icon icon="fa-solid fa-circle-chevron-down" v-if="!visibleExtraInfo" />
                                <font-awesome-icon icon="fa-solid fa-circle-chevron-up" v-else />
                            </span>
                        </div>
                    </div>
                    <b-collapse id="collapse-2" class="booking-details-extra-info-fields" v-model="visibleExtraInfo">
                        <b-row v-for="field in customFieldsList" :key="field.key" class="booking-details-extra-info-field-row">
                            <b-col sm="12">
                                {{ field.label }}:<br/>
                                <strong>{{ field.value }}</strong>
                            </b-col>
                        </b-row>
                        <b-row class="booking-details-extra-info-field-row">
                            <b-col sm="12">
                                {{ this.getLabel('customerPersonalNotesLabel') }}:<br/>
                                <strong>{{ customerPersonalNote }}</strong>
                            </b-col>
                        </b-row>
                    </b-collapse>
                </div>
            </b-col>
        </b-row>
        <b-row>
            <b-col sm="12">
                <div class="booking-details-total-info">
                    <b-row v-for="(service, index) in services" :key="index">
                        <b-col sm="4">
                            <div class="service">
                                <strong>{{ service.service_name }} [<span v-html="service.service_price + booking.currency"></span>]</strong>
                            </div>
                        </b-col>
                        <b-col sm="4">
                            <div class="resource">
                                {{ service.resource_name }}
                            </div>
                        </b-col>
                        <b-col sm="4">
                            <div class="attendant">
                                {{ service.assistant_name }}
                            </div>
                        </b-col>
                    </b-row>
                    <b-row>
                        <b-col sm="4">
                            <div class="total">
                                <b-row>
                                    <b-col sm="6">
                                        <strong>{{ this.getLabel('totalTitle') }}</strong>
                                    </b-col>
                                    <b-col sm="6">
                                        <strong><span v-html="totalSum"></span></strong>
                                    </b-col>
                                </b-row>
                            </div>
                        </b-col>
                        <b-col sm="4">
                            <div class="transaction-id">
                                <b-row>
                                    <b-col sm="6">
                                        {{ this.getLabel('transactionIdTitle') }}
                                    </b-col>
                                    <b-col sm="6">
                                        {{ transactionId.join(', ') }}
                                    </b-col>
                                </b-row>
                            </div>
                        </b-col>
                    </b-row>
                    <b-row>
                        <b-col sm="4">
                            <div class="discount">
                                <b-row>
                                    <b-col sm="6">
                                        {{ this.getLabel('discountTitle') }}
                                    </b-col>
                                    <b-col sm="6" v-html="discount"></b-col>
                                </b-row>
                            </div>
                        </b-col>
                    </b-row>
                    <b-row>
                        <b-col sm="4">
                            <div class="deposit">
                                <b-row>
                                    <b-col sm="6">
                                        {{ this.getLabel('depositTitle') }}
                                    </b-col>
                                    <b-col sm="6" v-html="deposit"></b-col>
                                </b-row>
                            </div>
                        </b-col>
                    </b-row>
                    <b-row>
                        <b-col sm="4">
                            <div class="due">
                                <b-row>
                                    <b-col sm="6">
                                        {{ this.getLabel('dueTitle') }}
                                    </b-col>
                                    <b-col sm="6" v-html="due"></b-col>
                                </b-row>
                            </div>
                        </b-col>
                    </b-row>
                </div>
            </b-col>
        </b-row>
        <b-row>
            <b-col sm="12">
                <div class="booking-details-status-info">
                    <b-row>
                        <b-col sm="6" class="status">
                            {{ status }}
                        </b-col>
                        <b-col sm="6" class="booking-details-actions">
                            <b-button variant="primary" @click="edit">
                                <font-awesome-icon icon="fa-solid fa-pen-to-square" />
                                {{ this.getLabel('editButtonLabel') }}
                            </b-button>
                            <PayRemainingAmount :booking="booking"/>
                        </b-col>
                    </b-row>
                </div>
            </b-col>
        </b-row>
    </div>
</template>

<script>
    import PayRemainingAmount from './PayRemainingAmount.vue'
    import mixins from "@/mixin";

    export default {
        name: 'BookingDetails',
        mixins: [mixins],
        props: {
            booking: {
                default: function () {
                    return {};
                },
            },
        },
        computed: {
            date() {
                return this.dateFormat(this.bookingData.date)
            },
            time() {
                return this.timeFormat(this.bookingData.time)
            },
            customerFirstname() {
                return this.bookingData.customer_first_name
            },
            customerLastname() {
                return this.bookingData.customer_last_name
            },
            customerEmail() {
                return this.getDisplayEmail(this.bookingData.customer_email);
            },
            customerPhone() {
                const phone = this.bookingData.customer_phone ?
                    this.bookingData.customer_phone_country_code + this.bookingData.customer_phone : '';
                return this.getDisplayPhone(phone);
            },
            customerNote() {
                return this.bookingData.note
            },
            customerPersonalNote() {
                return this.bookingData.customer_personal_note
            },
            services() {
                return this.bookingData.services
            },
            totalSum() {
                return this.bookingData.amount + this.bookingData.currency
            },
            transactionId() {
                return this.bookingData.transaction_id
            },
            discount() {
                return this.bookingData.discounts_details.length > 0 ? this.bookingData.discounts_details.map(item => item.name + ' (' + item.amount_string + ')').join(', ') : '-'
            },
            deposit() {
                return +this.bookingData.deposit > 0 ? (this.bookingData.deposit + this.bookingData.currency) : '-'
            },
            due() {
                return (+this.bookingData.amount - +this.bookingData.deposit) + this.bookingData.currency
            },
            status() {
                return this.$root.statusesList[this.booking.status].label
            },
            customFieldsList() {
                return this.bookingData.custom_fields.filter(i => ['html', 'file'].indexOf(i.type) === -1)
            },
            photos() {
                return this.bookingData.customer_photos
            },
        },
        mounted() {
            this.toggleShow()
            setInterval(() => this.update(), 60000)
        },
        components: {
            PayRemainingAmount,
        },
        data: function () {
            return {
                show: true,
                visibleExtraInfo: false,
                bookingData: this.booking
            }
        },
        methods: {
            close() {
                this.$emit('close');
            },
            edit() {
                this.$emit('edit');
            },
            toggleShow() {
                this.show = false
                setTimeout(() => {
                    this.show = true
                }, 0)
            },
            update() {
                this.axios.get('bookings/' + this.bookingData.id).then((response) => {
                    this.bookingData = response.data.items[0]
                })
            },
            showCustomerImages() {
                this.$emit('showCustomerImages', {id: this.bookingData.customer_id, photos: this.photos})
            }
        },
        emits: ['close', 'edit', 'showCustomerImages']
    }
</script>

<style scoped>
    .booking-details-customer-info,
    .booking-details-total-info,
    .booking-details-status-info,
    .booking-details-extra-info {
        border: solid 1px #ccc;
        padding: 20px;
        text-align: left;
        margin-bottom: 20px;
    }
    .actions {
        text-align: right;
    }
    .date,
    .time,
    .customer-firstname,
    .customer-lastname,
    .customer-email,
    .customer-phone,
    .customer-note,
    .service,
    .attendant,
    .resource,
    .total,
    .transaction-id,
    .discount,
    .deposit,
    .due,
    .booking-details-extra-info-field-row {
        border-bottom: solid 1px #ccc;
        margin-bottom: 20px;
        padding-bottom: 5px;
    }
    .booking-details-status-info .row {
        align-items: center;
    }
    .actions .fa-circle-xmark {
        cursor: pointer;
    }
    .phone,
    .sms,
    .whatsapp {
        color: #04409F;
        font-size: 20px;
    }
    .customer-phone {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
    }
    .phone,
    .sms {
        margin-right: 15px;
    }
    .booking-details-extra-info-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .booking-details-extra-info-header-btn {
        font-size: 22px;
        color: #0d6efd;
    }
    .booking-details-extra-info-fields {
        margin-top: 20px;
    }
    .booking-details-actions {
        display: flex;
        justify-content: space-between;
    }
    :deep(.remaining-amount-payment-link) img {
        width: 40px;
        vertical-align: middle;
        cursor: pointer;
        margin-left: 15px;
    }
    .customer-firstname {
        position: relative;
    }
    .images {
        position: absolute;
        background-color: #E1E6EF9B;
        border-radius: 50px;
        top: 0;
        width: 100px;
        height: 100px;
        right: -10px;
        cursor: pointer;
    }
    .photo {
        max-width: 100%;
        clip-path: circle(40% at 50px 50px);
    }
    .fa-images {
        font-size: 45px;
        margin-top: 30%;
        margin-left: 23px;
    }
    @media (max-width: 576px) {
        .status {
            margin-bottom: 10px;
        }
    }
</style>
