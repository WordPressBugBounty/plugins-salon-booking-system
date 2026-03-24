<template>
    <div class="customer-row" @click="chooseCustomerAvailable ? choose() : edit()">
        <!-- Avatar -->
        <div class="customer-avatar">
            <img v-if="photos.length" :src="photos[0]['url']" class="customer-avatar-img" :alt="customerFirstname" />
            <span v-else class="customer-avatar-initials">{{ customerInitials }}</span>
        </div>

        <!-- Info -->
        <div class="customer-info">
            <span class="customer-name">{{ customerFirstname }} {{ customerLastname }}</span>
            <span class="customer-phone-text" v-if="customerPhone">{{ customerPhone }}</span>
        </div>

        <!-- Right side -->
        <div class="customer-right">
            <!-- Booking count badge -->
            <span class="booking-count" v-if="totalCount !== '-'">{{ totalCount }}</span>

            <!-- Choose indicator (when used as picker) -->
            <font-awesome-icon v-if="chooseCustomerAvailable" icon="fa-solid fa-chevron-right" class="customer-chevron" />

            <!-- Contact actions -->
            <div class="contact-actions" v-if="customerPhone && !shouldHidePhone" @click.stop>
                <a target="_blank" :href="'tel:' + customerRawPhone" class="contact-btn">
                    <font-awesome-icon icon="fa-solid fa-phone" />
                </a>
                <a target="_blank" :href="'sms:' + customerRawPhone" class="contact-btn">
                    <font-awesome-icon icon="fa-solid fa-message" />
                </a>
                <a target="_blank" :href="'https://wa.me/' + customerRawPhone" class="contact-btn">
                    <font-awesome-icon icon="fa-brands fa-whatsapp" />
                </a>
            </div>

            <font-awesome-icon icon="fa-solid fa-chevron-right" class="customer-chevron" v-if="!chooseCustomerAvailable" />
        </div>
    </div>
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
            customerInitials() {
                const f = this.customer.first_name?.[0] ?? '';
                const l = this.customer.last_name?.[0] ?? '';
                return (f + l).toUpperCase() || '?';
            },
            customerEmail() {
                return this.getDisplayEmail(this.customer.email);
            },
            customerRawPhone() {
                return this.customer.phone ?
                    this.customer.phone_country_code + this.customer.phone : '';
            },
            customerPhone() {
                return this.getDisplayPhone(this.customerRawPhone);
            },
            customerScore() {
                return this.customer.score
            },
            totalSum() {
                return this.$root.settings.currency_symbol + this.customer.total_amount_reservations;
            },
            totalCount() {
                const count = this.customer.bookings?.length ?? 0;
                return count > 0 ? count : '-';
            },
            photos() {
                return this.customer.photos ?? [];
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
/* ── Row ── */
.customer-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    border-bottom: 1px solid var(--color-border, #E2E8F0);
    background-color: var(--color-surface, #FFFFFF);
    cursor: pointer;
    transition: background-color 0.1s ease;
    min-height: 64px;
}

.customer-row:last-child {
    border-bottom: none;
}

.customer-row:active {
    background-color: var(--color-background, #F4F6FA);
}

/* ── Avatar ── */
.customer-avatar {
    flex-shrink: 0;
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background-color: var(--color-primary-light, #EFF6FF);
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
}

.customer-avatar-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.customer-avatar-initials {
    font-size: 15px;
    font-weight: 600;
    color: var(--color-primary, #2563EB);
    letter-spacing: 0.02em;
}

/* ── Info ── */
.customer-info {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
}

.customer-name {
    font-size: 15px;
    font-weight: 600;
    color: var(--color-text-primary, #0F172A);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.customer-phone-text {
    font-size: 12px;
    color: var(--color-text-secondary, #64748B);
}

/* ── Right side ── */
.customer-right {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.booking-count {
    background-color: var(--color-primary-light, #EFF6FF);
    color: var(--color-primary, #2563EB);
    font-size: 11px;
    font-weight: 600;
    min-width: 22px;
    height: 22px;
    border-radius: var(--radius-pill, 999px);
    padding: 0 6px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.customer-chevron {
    font-size: 12px;
    color: var(--color-text-muted, #94A3B8);
}

/* ── Contact actions ── */
.contact-actions {
    display: flex;
    gap: 10px;
    align-items: center;
}

.contact-btn {
    font-size: 18px;
    color: var(--color-primary, #2563EB);
    text-decoration: none;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    transition: background-color 0.12s ease;
}

.contact-btn:active {
    background-color: var(--color-primary-light, #EFF6FF);
}

/* ── Choose button ── */
.choose-btn {
    font-size: 22px;
    color: var(--color-primary, #2563EB);
    background: transparent;
    border: none;
    cursor: pointer;
    padding: 0;
    display: flex;
    align-items: center;
}
</style>
