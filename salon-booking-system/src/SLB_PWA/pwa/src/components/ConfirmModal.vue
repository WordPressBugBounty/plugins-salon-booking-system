<template>
    <Teleport to="body">
        <div
            v-if="modelValue"
            class="sln-confirm-modal"
            role="alertdialog"
            aria-modal="true"
            :aria-labelledby="titleId"
            :aria-describedby="messageId"
            @keydown.esc.prevent="cancel"
        >
            <button
                type="button"
                class="sln-confirm-modal__backdrop"
                :aria-label="cancelLabel"
                @mousedown.prevent
                @click.prevent="cancel"
            />
            <div class="sln-confirm-modal__card" @click.stop>
                <h2 v-if="title" :id="titleId" class="sln-confirm-modal__title">{{ title }}</h2>
                <p :id="messageId" class="sln-confirm-modal__text">{{ message }}</p>
                <button
                    ref="confirmButton"
                    type="button"
                    class="btn btn-primary sln-confirm-modal__confirm"
                    @click.prevent.stop="confirm"
                >
                    {{ confirmLabel }}
                </button>
                <button
                    type="button"
                    class="btn btn-link sln-confirm-modal__cancel"
                    @click.prevent.stop="cancel"
                >
                    {{ cancelLabel }}
                </button>
            </div>
        </div>
    </Teleport>
</template>

<script>
/**
 * In-app replacement for window.confirm(), styled like ProUpgradeModal.
 * Native dialogs are blocked in some embedded browsers / WebViews, where
 * confirm() silently returns false.
 */
export default {
    name: 'ConfirmModal',
    props: {
        modelValue: {
            type: Boolean,
            default: false,
        },
        title: {
            type: String,
            default: '',
        },
        message: {
            type: String,
            default: '',
        },
        confirmLabel: {
            type: String,
            default: 'Confirm',
        },
        cancelLabel: {
            type: String,
            default: 'Cancel',
        },
    },
    emits: ['update:modelValue', 'confirm', 'cancel'],
    data() {
        return {
            titleId: 'sln-confirm-modal-title',
            messageId: 'sln-confirm-modal-message',
        }
    },
    watch: {
        modelValue(visible) {
            if (visible) {
                this.$nextTick(() => this.$refs.confirmButton && this.$refs.confirmButton.focus())
            }
        },
    },
    methods: {
        // Close on the next tick so the same pointer event can't hit what is underneath
        confirm() {
            setTimeout(() => {
                this.$emit('update:modelValue', false)
                this.$emit('confirm')
            }, 0)
        },
        cancel() {
            setTimeout(() => {
                this.$emit('update:modelValue', false)
                this.$emit('cancel')
            }, 0)
        },
    },
}
</script>

<style scoped>
.sln-confirm-modal {
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

.sln-confirm-modal__backdrop {
    position: absolute;
    inset: 0;
    margin: 0;
    padding: 0;
    border: 0;
    background: rgba(15, 23, 42, 0.5);
    cursor: pointer;
}

.sln-confirm-modal__card {
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

.sln-confirm-modal__title {
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--color-text-primary, #0f172a);
    margin: 0 0 0.75rem;
    line-height: 1.3;
    text-align: left;
}

.sln-confirm-modal__text {
    font-size: 0.9375rem;
    color: var(--color-text-secondary, #64748b);
    margin: 0 0 1.25rem;
    line-height: 1.45;
    text-align: left;
}

.sln-confirm-modal__confirm {
    width: 100%;
    font-weight: 600;
    padding: 0.65rem 1rem;
    border-radius: var(--radius-md, 12px);
}

.sln-confirm-modal__cancel {
    width: 100%;
    margin-top: 0.35rem;
    font-size: 0.9rem;
    color: var(--color-text-secondary, #64748b);
    text-decoration: none;
}
</style>
