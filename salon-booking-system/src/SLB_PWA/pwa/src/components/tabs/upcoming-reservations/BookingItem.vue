<template>
    <b-row gutter-x="0">
        <b-col sm="12" class="booking-wrapper">
            <div class="booking">
                <b-row>
                    <b-col sm="12" class="customer-info">
                        <div class="customer-info-status-customer-wrapper">
                            <span class="status" :style="'background-color:' + statusColor"></span>
                            <span class="customer">
                                {{ customer }}
                            </span>
                        </div>
                        <span class="id">
                            {{ id }}
                        </span>
                    </b-col>
                </b-row>
                <b-row>
                    <b-col sm="12" class="booking-info">
                        <div class="booking-info-date-time-wrapper">
                            <span class="date">
                                {{ date }}
                            </span>
                            <span class="time">
                                {{ fromTime }}-{{ toTime }}
                            </span>
                        </div>
                        <span class="total">
                            <span v-html="totalSum"></span>
                        </span>
                    </b-col>
                </b-row>
                <b-row>
                    <b-col sm="12">
                        <div class="booking-assistant-info">
                            {{ assistants.map((i) => i.name).join(' | ') }}
                        </div>
                    </b-col>
                </b-row>
                <b-row>
                    <b-col sm="12" class="booking-actions-wrapper">
                        <span class="delete">
                            <font-awesome-icon icon="fa-solid fa-trash" @click="isDelete = true"/>
                        </span>
                        <span class="booking-actions-remaining-amount">
                            <PayRemainingAmount :booking="booking"/>
                            <span class="details-link">
                                <font-awesome-icon icon="fa-solid fa-chevron-right" @click="showDetails"/>
                            </span>
                        </span>
                    </b-col>
                </b-row>
            </div>
            <template v-if="isDelete">
                <div class="delete-backdrop" @click="isDelete = false"></div>
                <div class="delete-btn-wrapper">
                    <p class="delete-btn-wrapper-text">{{ this.getLabel('deleteBookingConfirmText') }}</p>
                    <p>
                        <b-button variant="primary" @click="deleteItem" class="delete-btn-wrapper-button">
                            {{ this.getLabel('deleteBookingButtonLabel') }}
                        </b-button>
                    </p>
                    <p>
                        <a href="#" class="delete-btn-wrapper-go-back" @click.prevent="isDelete = false">
                            {{ this.getLabel('deleteBookingGoBackLabel') }}
                        </a>
                    </p>
                </div>
            </template>
        </b-col>
    </b-row>
</template>

<script>

    import PayRemainingAmount from './PayRemainingAmount.vue'

    export default {
        name: 'BookingItem',
        props: {
            booking: {
                default: function () {
                    return {};
                },
            },
        },
        data: function () {
            return {
                isDelete: false,
            }
        },
        components: {
            PayRemainingAmount,
        },
        computed: {
            customer() {
                return this.booking.customer_first_name + ' ' + this.booking.customer_last_name
            },
            status() {
                return this.$root.statusesList[this.booking.status].label
            },
            statusColor() {
                return this.$root.statusesList[this.booking.status].color
            },
            date() {
                return this.dateFormat(this.booking.date)
            },
            fromTime() {
                const format = this.timeFormat === 'default' ? 'HH:mm' : 'h:mma'
                return this.moment(this.booking.time, 'HH:mm').format(format)
            },
            toTime() {
                const format = this.timeFormat === 'default' ? 'HH:mm' : 'h:mma'
                return this.booking.services.length > 0 ? this.moment(this.booking.services[this.booking.services.length - 1].end_at, 'HH:mm').format(format) :
                    this.moment(this.booking.time, 'HH:mm').format(format)
            },
            totalSum() {
                return this.booking.amount + ' ' + this.booking.currency
            },
            id() {
                return this.booking.id
            },
            assistants() {
                return this.booking.services.map((service) => ({id: service.assistant_id, name: service.assistant_name})).filter((i) => +i.id)
            },
            timeFormat() {
                if(this.$root.settings.time_format === undefined)
                  return 'default';
                return this.$root.settings.time_format.type ?? 'default'
            },
        },
        methods: {
            deleteItem() {
                this.$emit('deleteItem');
                this.isDelete = false
            },
            showDetails() {
                this.$emit('showDetails');
            },
        },
        emits: ['deleteItem', 'showDetails']
    }
</script>

<style scoped>
    .booking {
        padding: 10px;
        text-align: left;
        margin-bottom: 1rem;
        background-color: #ECF1FA9B;
    }
    .status {
        border-radius: 10px;
        width: 12px;
        height: 12px;
    }
    .booking-actions .fa-trash {
        cursor: pointer;
    }
    .customer {
        white-space: nowrap;
        overflow: hidden;
        color: #04409F;
        font-size: 22px;
        text-overflow: ellipsis;
        margin-left: 10px;
        margin-right: 10px;
    }
    .booking-actions-wrapper {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        font-size: 20px;
    }
    .delete {
        color: #6A6F76;
    }
    .details-link {
        color: #04409F;
    }
    .booking-info {
        display: flex;
        justify-content: space-between;
        margin: 10px 0 0;
        color: #637491;
        font-size: 18px;
    }
    .booking-info-date-time-wrapper {
        display: flex;
        margin-left: 25px;
    }
    .date {
        margin-right: 20px;
    }
    .customer-info-status-customer-wrapper {
        display: flex;
        max-width: 80%;
        align-items: center;
    }
    .customer-info {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .id {
        font-size: 20px;
        color: #637491;
        font-weight: bold;
    }
    .delete-backdrop {
        position: fixed;
        width: 100%;
        height: 100%;
        top: 0;
        background-color: #E0E0E0E6;
        left: 0;
        z-index: 1000000;
    }
    .delete-btn-wrapper {
        position: fixed;
        top: 45%;
        left: 0;
        width: 100%;
        z-index: 1000000;
    }
    .delete-btn-wrapper-text {
        font-size: 30px;
        color: #322D38;
    }
    .delete-btn-wrapper-button {
        font-weight: bold;
        text-transform: uppercase;
    }
    .delete-btn-wrapper-go-back {
        color: #6A6F76;
        font-size: 20px;
    }
    .booking-assistant-info {
        margin-left: 25px;
        color: #637491;
        font-size: 18px;
    }
    :deep(.remaining-amount-payment-link) img {
        width: 30px;
        vertical-align: bottom;
        cursor: pointer;
    }
    .booking-actions-remaining-amount {
        display: flex;
    }
    :deep(.remaining-amount-payment-link) {
        margin-right: 15px;
    }
</style>
