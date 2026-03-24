<template>
    <Teleport to="body">
        <div
            v-if="modelValue"
            class="sln-pro-upgrade-modal"
            role="dialog"
            aria-modal="true"
            :aria-labelledby="titleId"
        >
            <button
                type="button"
                class="sln-pro-upgrade-modal__backdrop"
                :aria-label="dismissLabel"
                @mousedown.prevent
                @click.prevent="closeDeferredNavigateUpcoming"
            />
            <div class="sln-pro-upgrade-modal__card" @click.stop>
                <button
                    type="button"
                    class="sln-pro-upgrade-modal__x"
                    :aria-label="dismissLabel"
                    @click.prevent.stop="closeDeferredNavigateUpcoming"
                >
                    <font-awesome-icon icon="fa-solid fa-xmark" />
                </button>
                <h2 :id="titleId" class="sln-pro-upgrade-modal__title">{{ title }}</h2>
                <p class="sln-pro-upgrade-modal__text">{{ message }}</p>
                <a
                    :href="pricingUrl"
                    class="btn btn-primary sln-pro-upgrade-modal__cta"
                    target="_blank"
                    rel="noopener noreferrer"
                    @click.prevent.stop="closeDeferred"
                >
                    {{ ctaLabel }}
                </a>
                <button
                    type="button"
                    class="btn btn-link sln-pro-upgrade-modal__dismiss"
                    @click.prevent.stop="closeDeferredNavigateUpcoming"
                >
                    {{ dismissLabel }}
                </button>
            </div>
        </div>
    </Teleport>
</template>

<script>
export default {
    name: 'ProUpgradeModal',
    props: {
        modelValue: {
            type: Boolean,
            default: false,
        },
        /** When empty, uses default PRO copy (calendar/customers). */
        messageOverride: {
            type: String,
            default: '',
        },
        /** When false, dismiss does not emit dismiss-to-upcoming (e.g. modal opened from Upcoming list). */
        navigateToUpcomingOnDismiss: {
            type: Boolean,
            default: true,
        },
    },
    emits: ['update:modelValue', 'dismiss-to-upcoming'],
    data() {
        return {
            titleId: 'sln-pro-upgrade-modal-title',
        }
    },
    computed: {
        title() {
            return this.getLabel('proUpgradeModalTitle')
        },
        message() {
            return this.messageOverride || this.getLabel('proUpgradeModalMessage')
        },
        ctaLabel() {
            return this.getLabel('proUpgradeModalCtaLabel')
        },
        dismissLabel() {
            return this.getLabel('proUpgradeModalCloseLabel')
        },
        pricingUrl() {
            return window.slnPWA?.pro_pricing_url || 'https://www.salonbookingsystem.com/plugin-pricing/'
        },
    },
    methods: {
        /**
         * Defer unmount so the same pointer event cannot activate the tab bar underneath
         * (Teleport modal sits above nav but closing synchronously can leave a stray click target).
         */
        /** Close only (e.g. user opened PRO pricing) — do not change tab. */
        closeDeferred() {
            setTimeout(() => {
                this.$emit('update:modelValue', false)
            }, 0)
        },
        /** Not now / backdrop / X — parent should open Upcoming reservations. */
        closeDeferredNavigateUpcoming() {
            setTimeout(() => {
                if (this.navigateToUpcomingOnDismiss) {
                    this.$emit('dismiss-to-upcoming')
                }
                this.$emit('update:modelValue', false)
            }, 0)
        },
    },
}
</script>

<style scoped>
.sln-pro-upgrade-modal {
    position: fixed;
    inset: 0;
    z-index: 100050;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    padding-bottom: calc(1rem + env(safe-area-inset-bottom, 0));
    box-sizing: border-box;
}

.sln-pro-upgrade-modal__backdrop {
    position: absolute;
    inset: 0;
    margin: 0;
    padding: 0;
    border: 0;
    background: rgba(15, 23, 42, 0.5);
    cursor: pointer;
}

.sln-pro-upgrade-modal__card {
    position: relative;
    z-index: 1;
    width: 100%;
    max-width: 22rem;
    padding: 1.5rem 1.25rem 1.25rem;
    border-radius: var(--radius-lg, 16px);
    background: var(--color-surface, #fff);
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.18);
    text-align: center;
}

.sln-pro-upgrade-modal__x {
    position: absolute;
    top: 0.65rem;
    right: 0.65rem;
    width: 2.25rem;
    height: 2.25rem;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 0;
    border-radius: 50%;
    background: transparent;
    color: var(--color-text-muted, #94a3b8);
    cursor: pointer;
    font-size: 1.1rem;
}

.sln-pro-upgrade-modal__title {
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--color-text-primary, #0f172a);
    margin: 0 1.5rem 0.75rem 0;
    line-height: 1.3;
    text-align: left;
}

.sln-pro-upgrade-modal__text {
    font-size: 0.9375rem;
    color: var(--color-text-secondary, #64748b);
    margin: 0 0 1.25rem;
    line-height: 1.45;
    text-align: left;
}

.sln-pro-upgrade-modal__cta {
    width: 100%;
    font-weight: 600;
    padding: 0.65rem 1rem;
    border-radius: var(--radius-md, 12px);
}

.sln-pro-upgrade-modal__dismiss {
    width: 100%;
    margin-top: 0.35rem;
    font-size: 0.9rem;
    color: var(--color-text-secondary, #64748b);
    text-decoration: none;
}
</style>
