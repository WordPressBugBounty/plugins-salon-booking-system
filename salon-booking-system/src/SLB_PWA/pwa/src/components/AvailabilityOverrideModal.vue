<template>
    <Teleport to="body">
        <div
            v-if="modelValue"
            class="sln-avo-sheet"
            role="alertdialog"
            aria-modal="true"
            aria-labelledby="sln-avo-sheet-title"
            @keydown.esc.prevent="cancel"
        >
            <button
                type="button"
                class="sln-avo-sheet__backdrop"
                :aria-label="label('availabilityOverrideBack', 'Go back and edit')"
                @mousedown.prevent
                @click.prevent="cancel"
            />
            <div class="sln-avo-sheet__card" @click.stop>
                <div class="sln-avo-sheet__handle" aria-hidden="true"></div>
                <div class="sln-avo-sheet__head">
                    <span class="sln-avo-sheet__icon" aria-hidden="true">!</span>
                    <div>
                        <h2 id="sln-avo-sheet-title" class="sln-avo-sheet__title">{{ title }}</h2>
                        <p v-if="summary" class="sln-avo-sheet__summary">{{ summary }}</p>
                    </div>
                </div>
                <ul class="sln-avo-sheet__list">
                    <li v-for="(violation, index) in violations" :key="index" class="sln-avo-sheet__item">
                        <span class="sln-avo-sheet__rule">{{ violation.title }}</span>
                        <span class="sln-avo-sheet__msg">{{ violation.message }}</span>
                    </li>
                </ul>
                <p v-if="canForce" class="sln-avo-sheet__note">
                    {{ label('availabilityOverrideHistory', 'If you save, these exceptions are recorded in the booking history with your name.') }}
                </p>
                <p v-else class="sln-avo-sheet__note sln-avo-sheet__note--block">
                    {{ label('availabilityOverrideCannotForce', 'You can\'t save a booking that breaks these rules. Change the booking or ask an administrator.') }}
                </p>
                <button
                    v-if="canForce"
                    ref="confirmButton"
                    type="button"
                    class="btn sln-avo-sheet__confirm"
                    @click.prevent.stop="confirm"
                >
                    {{ label('bookingConflictConfirmButtonLabel', 'Save anyway') }}
                </button>
                <button
                    ref="cancelButton"
                    type="button"
                    class="btn sln-avo-sheet__cancel"
                    :class="{'sln-avo-sheet__cancel--only': !canForce}"
                    @click.prevent.stop="cancel"
                >
                    {{ label('availabilityOverrideBack', 'Go back and edit') }}
                </button>
            </div>
        </div>
    </Teleport>
</template>

<script>
/**
 * Lists the availability rules a booking breaks before it is saved anyway.
 * Administrators and salon staff confirm with "Save anyway"; other users can only
 * go back. See utils/bookingConflicts.js for the save flow.
 */
export default {
    name: 'AvailabilityOverrideModal',
    props: {
        modelValue: {
            type: Boolean,
            default: false,
        },
        violations: {
            type: Array,
            default: () => [],
        },
        summary: {
            type: String,
            default: '',
        },
        canForce: {
            type: Boolean,
            default: false,
        },
    },
    emits: ['update:modelValue', 'confirm', 'cancel'],
    computed: {
        title() {
            const count = this.violations.length;
            if (count === 1) {
                return this.label('availabilityOverrideTitleOne', 'This booking breaks 1 availability rule');
            }
            return this.label('availabilityOverrideTitleMany', 'This booking breaks %d availability rules').replace('%d', count);
        },
    },
    watch: {
        modelValue(visible) {
            if (visible) {
                this.$nextTick(() => {
                    const button = this.$refs.confirmButton || this.$refs.cancelButton;
                    if (button) {
                        button.focus();
                    }
                });
            }
        },
    },
    methods: {
        label(key, fallback) {
            const labels = (window.slnPWA && window.slnPWA.labels) || {};
            return labels[key] || fallback;
        },
        // Close on the next tick so the same pointer event can't hit what is underneath
        confirm() {
            setTimeout(() => {
                this.$emit('update:modelValue', false);
                this.$emit('confirm');
            }, 0);
        },
        cancel() {
            setTimeout(() => {
                this.$emit('update:modelValue', false);
                this.$emit('cancel');
            }, 0);
        },
    },
};
</script>

<style scoped>
.sln-avo-sheet {
    position: fixed;
    inset: 0;
    z-index: 100050;
    display: flex;
    align-items: flex-end;
    justify-content: center;
    box-sizing: border-box;
}

.sln-avo-sheet__backdrop {
    position: absolute;
    inset: 0;
    margin: 0;
    padding: 0;
    border: 0;
    background: rgba(15, 23, 42, 0.5);
    cursor: pointer;
}

.sln-avo-sheet__card {
    position: relative;
    z-index: 1;
    width: 100%;
    max-width: 30rem;
    max-height: 88vh;
    overflow-y: auto;
    padding: 0.6rem 1.25rem calc(1.25rem + env(safe-area-inset-bottom, 0));
    border-radius: var(--radius-lg, 16px) var(--radius-lg, 16px) 0 0;
    background: var(--color-surface, #fff);
    box-shadow: 0 -10px 40px rgba(0, 0, 0, 0.18);
    box-sizing: border-box;
}

.sln-avo-sheet__handle {
    width: 36px;
    height: 4px;
    border-radius: 2px;
    background: #cbd5e1;
    margin: 0 auto 0.9rem;
}

.sln-avo-sheet__head {
    display: flex;
    gap: 0.65rem;
    align-items: flex-start;
}

.sln-avo-sheet__icon {
    flex: 0 0 2rem;
    height: 2rem;
    border-radius: 50%;
    background: #fcf3dc;
    color: #8a5a00;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
}

.sln-avo-sheet__title {
    font-size: 1.05rem;
    font-weight: 600;
    color: var(--color-text-primary, #0f172a);
    margin: 0.2rem 0 0;
    line-height: 1.3;
}

.sln-avo-sheet__summary {
    font-size: 0.8rem;
    color: var(--color-text-secondary, #64748b);
    margin: 0.2rem 0 0;
}

.sln-avo-sheet__list {
    list-style: none;
    margin: 0.75rem 0 0;
    padding: 0;
}

.sln-avo-sheet__item {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    padding: 0.7rem 0 0.7rem 0.85rem;
    border-top: 1px solid #e2e8f0;
    position: relative;
}

.sln-avo-sheet__item::before {
    content: '';
    position: absolute;
    left: 0;
    top: 1.05rem;
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #d98e04;
}

.sln-avo-sheet__rule {
    font-size: 0.75rem;
    font-weight: 600;
    color: #8a5a00;
}

.sln-avo-sheet__msg {
    font-size: 0.875rem;
    line-height: 1.45;
    color: var(--color-text-primary, #0f172a);
}

.sln-avo-sheet__note {
    margin: 0.5rem 0 0;
    padding: 0.6rem 0.75rem;
    border-radius: var(--radius-md, 12px);
    background: #f1f5f9;
    color: var(--color-text-secondary, #64748b);
    font-size: 0.78rem;
    line-height: 1.4;
}

.sln-avo-sheet__note--block {
    background: #fdecec;
    color: #8a2424;
}

.sln-avo-sheet__confirm {
    width: 100%;
    margin-top: 1rem;
    font-weight: 600;
    padding: 0.65rem 1rem;
    border-radius: var(--radius-md, 12px);
    background: #f0b849;
    border-color: #f0b849;
    color: #1d2327;
}

.sln-avo-sheet__cancel {
    width: 100%;
    margin-top: 0.5rem;
    padding: 0.6rem 1rem;
    border-radius: var(--radius-md, 12px);
    background: transparent;
    border: 1px solid #cbd5e1;
    color: var(--color-text-primary, #0f172a);
}

.sln-avo-sheet__cancel--only {
    margin-top: 1rem;
}
</style>
