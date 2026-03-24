<template>
    <div
        class="booking-card"
        :class="{ 'booking-card--no-show': booking.no_show }"
        @click="showDetails"
    >
        <!-- Card body -->
        <div class="booking-body">
            <!-- Row 1: time range + status badge -->
            <div class="booking-row booking-row--top">
                <span class="booking-time">{{ date }} · {{ fromTime }}–{{ toTime }}</span>
                <span class="status-badge" :style="statusBadgeStyle">{{ status }}</span>
            </div>

            <!-- Row 2: customer name + booking id -->
            <div class="booking-row">
                <span class="booking-customer">{{ customer }}</span>
                <span class="booking-id">#{{ id }}</span>
            </div>

            <!-- Row 3: services + attendants -->
            <div class="booking-row booking-row--sub" v-if="serviceNames || assistantNames">
                <span class="booking-services" v-if="serviceNames">{{ serviceNames }}</span>
                <span class="booking-attendants" v-if="assistantNames">{{ assistantNames }}</span>
            </div>

            <!-- Row 4: actions — PRO + setting: no-show + trash when no-show; otherwise delete + confirm -->
            <div class="booking-row booking-row--actions" @click.stop>
                <div class="booking-actions-start">
                    <template v-if="canUsePwaNoShowControl">
                        <button
                            type="button"
                            class="no-show-icon"
                            :class="{ active: booking.no_show }"
                            :disabled="isProcessingNoShow"
                            :title="booking.no_show ? 'Marked as No-Show (click to unmark)' : 'Mark as No-Show'"
                            aria-label="Toggle no-show"
                            @click="toggleNoShow"
                        >
                            <span class="no-show-icon-svg" aria-hidden="true" />
                        </button>
                        <button
                            v-if="booking.no_show"
                            type="button"
                            class="trash-icon"
                            title="Delete no-show booking"
                            aria-label="Delete booking"
                            @click="deleteItemDirect"
                        >
                            <span class="trash-icon-svg" aria-hidden="true" />
                        </button>
                    </template>
                    <button
                        v-else
                        type="button"
                        class="action-btn action-btn--danger"
                        @click="isDelete = true"
                        aria-label="Delete"
                    >
                        <font-awesome-icon icon="fa-solid fa-trash" />
                    </button>
                </div>
                <div class="action-right">
                    <PayRemainingAmount :booking="booking"/>
                    <span class="booking-total" v-html="totalSum"></span>
                </div>
            </div>
        </div>

        <!-- Delete confirm overlay when no-show controls are off (free, or PRO with setting disabled) -->
        <template v-if="!canUsePwaNoShowControl && isDelete">
            <div class="delete-backdrop" @click="isDelete = false"></div>
            <div class="delete-confirm">
                <p class="delete-confirm-text">{{ this.getLabel('deleteBookingConfirmText') }}</p>
                <button class="delete-confirm-btn" @click="deleteItem">
                    {{ this.getLabel('deleteBookingButtonLabel') }}
                </button>
                <button class="delete-confirm-cancel" @click.stop="isDelete = false">
                    {{ this.getLabel('deleteBookingGoBackLabel') }}
                </button>
            </div>
        </template>
    </div>
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
                isProcessingNoShow: false,
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
            statusBadgeStyle() {
                const color = this.statusColor;
                return {
                    backgroundColor: color + '1F',
                    color: color,
                };
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
                const services = this.booking.services ?? [];
                return services.length > 0 ? this.moment(services[services.length - 1].end_at, 'HH:mm').format(format) :
                    this.moment(this.booking.time, 'HH:mm').format(format)
            },
            totalSum() {
                return this.booking.amount + ' ' + this.booking.currency
            },
            id() {
                return this.booking.id
            },
            assistants() {
                return (this.booking.services ?? []).map((service) => ({id: service.assistant_id, name: service.assistant_name})).filter((i) => +i.id)
            },
            serviceNames() {
                const names = (this.booking.services ?? []).map(s => s.name).filter(Boolean);
                return names.join(', ');
            },
            assistantNames() {
                return this.assistants.map(a => a.name).join(' · ');
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
            deleteItemDirect() {
                this.$emit('deleteItem');
            },
            async toggleNoShow() {
                if (!this.canUsePwaNoShowControl || this.isProcessingNoShow) {
                    return;
                }
                this.isProcessingNoShow = true;
                try {
                    const newNoShowStatus = !this.booking.no_show;
                    const response = await this.axios.post('bookings/no-show', {
                        bookingId: this.booking.id,
                        noShow: newNoShowStatus ? 1 : 0,
                    });
                    if (response.data && response.data.success) {
                        this.booking.no_show = newNoShowStatus;
                        this.$emit('booking-no-show-changed', {
                            bookingId: this.booking.id,
                            noShow: newNoShowStatus,
                        });
                        this.$root.$emit('show-notification', {
                            type: 'success',
                            message: newNoShowStatus ? 'Marked as no-show' : 'Unmarked as no-show',
                        });
                    }
                } catch (error) {
                    console.error('Error toggling no-show:', error);
                    this.$root.$emit('show-notification', {
                        type: 'error',
                        message: 'Failed to update no-show status',
                    });
                } finally {
                    this.isProcessingNoShow = false;
                }
            },
            showDetails() {
                this.$emit('showDetails');
            },
        },
        emits: ['deleteItem', 'showDetails', 'booking-no-show-changed']
    }
</script>

<style scoped>
/* ── Card shell ── */
.booking-card {
    position: relative;
    display: flex;
    background-color: var(--color-surface, #FFFFFF);
    border: 1px solid var(--color-border, #E2E8F0);
    border-radius: var(--radius-lg, 16px);
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
    overflow: hidden;
    cursor: pointer;
    transition: box-shadow 0.12s ease;
}

.booking-card:active {
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
}

/* ── Card body ── */
.booking-body {
    flex: 1;
    padding: 12px 14px;
    display: flex;
    flex-direction: column;
    gap: 6px;
    min-width: 0;
}

/* ── Row layouts ── */
.booking-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}

.booking-row--sub {
    flex-direction: column;
    align-items: flex-start;
    gap: 2px;
}

.booking-row--actions {
    margin-top: 4px;
    padding-top: 8px;
    border-top: 1px solid var(--color-border, #E2E8F0);
    align-items: center;
}

.booking-actions-start {
    display: flex;
    align-items: center;
    gap: 4px;
    flex-shrink: 0;
}

/* Same diagonal stripe treatment as calendar BookingCard when no-show */
.booking-card--no-show .booking-body {
    background-image: repeating-linear-gradient(
        45deg,
        #ffffff,
        #ffffff 10px,
        #f1f5f9 10px,
        #f1f5f9 20px
    );
    background-color: #ffffff;
}

/* No-show + trash icons (aligned with reservations-calendar/BookingCard.vue) */
.no-show-icon {
    background: transparent;
    border: none;
    padding: 0;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.no-show-icon:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.no-show-icon-svg {
    display: inline-block;
    width: 28px;
    height: 28px;
    background-color: #2171b1;
    -webkit-mask-size: 22px;
    mask-size: 22px;
    -webkit-mask-repeat: no-repeat;
    mask-repeat: no-repeat;
    -webkit-mask-position: center;
    mask-position: center;
    -webkit-mask-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 32 32' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M23.7778 17.25C23.7778 16.388 24.1056 15.5614 24.689 14.9519C25.2724 14.3424 26.0638 14 26.8889 14C27.714 14 28.5053 14.3424 29.0888 14.9519C29.6722 15.5614 30 16.388 30 17.25V23.75M26.8889 27H5.11111C4.28599 27 3.49467 26.6576 2.91122 26.0481C2.32778 25.4386 2 24.612 2 23.75V17.25C2 16.388 2.32778 15.5614 2.91122 14.9519C3.49467 14.3424 4.28599 14 5.11111 14C5.93623 14 6.72755 14.3424 7.311 14.9519C7.89445 15.5614 8.22222 16.388 8.22222 17.25V20.5H20.7227' stroke='white' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'/%3E%3Cpath d='M5 14V6.50001C4.99947 6.04743 5.07045 5.59737 5.21057 5.16501M8.333 2.19501C8.78074 2.06492 9.24627 1.9992 9.71429 2.00001H22.2857C23.536 2.00001 24.7351 2.47411 25.6192 3.31803C26.5033 4.16194 27 5.30653 27 6.50001V14' stroke='white' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'/%3E%3Cpath d='M7 27V30' stroke='white' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'/%3E%3Cpath d='M25 27V30' stroke='white' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'/%3E%3Cpath d='M2 2L30 30' stroke='white' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
    mask-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 32 32' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M23.7778 17.25C23.7778 16.388 24.1056 15.5614 24.689 14.9519C25.2724 14.3424 26.0638 14 26.8889 14C27.714 14 28.5053 14.3424 29.0888 14.9519C29.6722 15.5614 30 16.388 30 17.25V23.75M26.8889 27H5.11111C4.28599 27 3.49467 26.6576 2.91122 26.0481C2.32778 25.4386 2 24.612 2 23.75V17.25C2 16.388 2.32778 15.5614 2.91122 14.9519C3.49467 14.3424 4.28599 14 5.11111 14C5.93623 14 6.72755 14.3424 7.311 14.9519C7.89445 15.5614 8.22222 16.388 8.22222 17.25V20.5H20.7227' stroke='white' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'/%3E%3Cpath d='M5 14V6.50001C4.99947 6.04743 5.07045 5.59737 5.21057 5.16501M8.333 2.19501C8.78074 2.06492 9.24627 1.9992 9.71429 2.00001H22.2857C23.536 2.00001 24.7351 2.47411 25.6192 3.31803C26.5033 4.16194 27 5.30653 27 6.50001V14' stroke='white' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'/%3E%3Cpath d='M7 27V30' stroke='white' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'/%3E%3Cpath d='M25 27V30' stroke='white' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'/%3E%3Cpath d='M2 2L30 30' stroke='white' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
    transition: all 0.2s ease;
}

.no-show-icon.active .no-show-icon-svg {
    background-color: #ec1e1e;
}

.no-show-icon:hover:not(:disabled) .no-show-icon-svg {
    transform: scale(1.1);
}

.no-show-icon:not(.active):hover:not(:disabled) .no-show-icon-svg {
    background-color: #1a5a8e;
}

.no-show-icon.active:hover:not(:disabled) .no-show-icon-svg {
    background-color: #d11a1a;
}

.trash-icon {
    background: transparent;
    border: none;
    padding: 0;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.trash-icon:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.trash-icon-svg {
    display: inline-block;
    width: 28px;
    height: 28px;
    background-color: #dc2626;
    -webkit-mask-size: 22px;
    mask-size: 22px;
    -webkit-mask-repeat: no-repeat;
    mask-repeat: no-repeat;
    -webkit-mask-position: center;
    mask-position: center;
    -webkit-mask-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 29 33' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M1.375 7.75033H4.29167M4.29167 7.75033H27.625M4.29167 7.75033V28.167C4.29167 28.9405 4.59896 29.6824 5.14594 30.2294C5.69292 30.7764 6.43479 31.0837 7.20833 31.0837H21.7917C22.5652 31.0837 23.3071 30.7764 23.8541 30.2294C24.401 29.6824 24.7083 28.9405 24.7083 28.167V7.75033M8.66667 7.75033V4.83366C8.66667 4.06011 8.97396 3.31824 9.52094 2.77126C10.0679 2.22428 10.8098 1.91699 11.5833 1.91699H17.4167C18.1902 1.91699 18.9321 2.22428 19.4791 2.77126C20.026 3.31824 20.3333 4.06011 20.3333 4.83366V7.75033M11.5833 15.042V23.792M17.4167 15.042V23.792' stroke='white' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
    mask-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 29 33' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M1.375 7.75033H4.29167M4.29167 7.75033H27.625M4.29167 7.75033V28.167C4.29167 28.9405 4.59896 29.6824 5.14594 30.2294C5.69292 30.7764 6.43479 31.0837 7.20833 31.0837H21.7917C22.5652 31.0837 23.3071 30.7764 23.8541 30.2294C24.401 29.6824 24.7083 28.9405 24.7083 28.167V7.75033M8.66667 7.75033V4.83366C8.66667 4.06011 8.97396 3.31824 9.52094 2.77126C10.0679 2.22428 10.8098 1.91699 11.5833 1.91699H17.4167C18.1902 1.91699 18.9321 2.22428 19.4791 2.77126C20.026 3.31824 20.3333 4.06011 20.3333 4.83366V7.75033M11.5833 15.042V23.792M17.4167 15.042V23.792' stroke='white' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
    transition: all 0.2s ease;
}

.trash-icon:hover:not(:disabled) .trash-icon-svg {
    background-color: #b91c1c;
    transform: scale(1.1);
}

/* ── Typography ── */
.booking-time {
    font-size: 13px;
    font-weight: 600;
    color: var(--color-text-primary, #0F172A);
}

.booking-customer {
    font-size: 16px;
    font-weight: 600;
    color: var(--color-text-primary, #0F172A);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.booking-id {
    font-size: 12px;
    color: var(--color-text-muted, #94A3B8);
    flex-shrink: 0;
}

.booking-services {
    font-size: 13px;
    color: var(--color-text-secondary, #64748B);
}

.booking-attendants {
    font-size: 12px;
    color: var(--color-text-muted, #94A3B8);
}

.booking-total {
    font-size: 13px;
    font-weight: 600;
    color: var(--color-text-secondary, #64748B);
}

/* ── Status badge ── */
.status-badge {
    flex-shrink: 0;
    font-size: 11px;
    font-weight: 600;
    padding: 3px 10px;
    border-radius: var(--radius-pill, 999px);
    white-space: nowrap;
}

/* ── Action buttons ── */
.action-btn {
    width: 32px;
    height: 32px;
    border: none;
    border-radius: 50%;
    background: transparent;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background-color 0.12s ease;
}

.action-btn--danger {
    color: var(--color-text-muted, #94A3B8);
}

.action-btn--danger:hover,
.action-btn--danger:active {
    background-color: rgba(220, 38, 38, 0.08);
    color: var(--color-error, #DC2626);
}

.action-right {
    display: flex;
    align-items: center;
    gap: 8px;
}

/* ── PayRemainingAmount ── */
:deep(.remaining-amount-payment-link) img {
    width: 24px;
    vertical-align: bottom;
    cursor: pointer;
}

:deep(.remaining-amount-payment-link) {
    margin-right: 4px;
}

/* ── Delete confirm overlay ── */
.delete-backdrop {
    position: fixed;
    inset: 0;
    background-color: rgba(15, 23, 42, 0.4);
    z-index: 1000000;
}

.delete-confirm {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: calc(100% - 48px);
    max-width: 360px;
    background-color: var(--color-surface, #FFFFFF);
    border-radius: var(--radius-xl, 24px);
    padding: 28px 24px 20px;
    z-index: 1000001;
    text-align: center;
}

.delete-confirm-text {
    font-size: 17px;
    font-weight: 600;
    color: var(--color-text-primary, #0F172A);
    margin: 0 0 20px;
}

.delete-confirm-btn {
    display: block;
    width: 100%;
    padding: 13px;
    background-color: var(--color-error, #DC2626);
    color: #FFFFFF;
    border: none;
    border-radius: var(--radius-lg, 16px);
    font-size: 15px;
    font-weight: 600;
    font-family: inherit;
    cursor: pointer;
    margin-bottom: 12px;
}

.delete-confirm-cancel {
    display: block;
    width: 100%;
    padding: 10px;
    background: transparent;
    border: none;
    font-size: 15px;
    color: var(--color-text-secondary, #64748B);
    font-family: inherit;
    cursor: pointer;
}
</style>
