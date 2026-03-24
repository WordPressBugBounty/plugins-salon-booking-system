<template>
  <div class="attendant-chips" role="toolbar" :aria-label="ariaLabel">
    <button
      v-for="attendant in assistants"
      :key="chipKey(attendant)"
      type="button"
      class="attendant-chip"
      :class="chipClasses(attendant)"
      :disabled="chipDisabled(attendant)"
      :aria-busy="attendant.busyNow ? 'true' : undefined"
      :title="chipTitle(attendant)"
      @click="onChipClick(attendant)"
    >
      <span class="attendant-avatar">
        <img
          v-if="attendant.imageUrl"
          class="attendant-avatar-img"
          :src="attendant.imageUrl"
          :alt="attendant.name || ''"
          loading="lazy"
          decoding="async"
        />
        <template v-else>{{ initials(attendant.name) }}</template>
      </span>
      <span class="attendant-name">{{ firstName(attendant.name) }}</span>
    </button>
  </div>
</template>

<script>
export default {
  name: 'AssistantFilterChips',
  props: {
    /**
     * Rows: { id, name, imageUrl?, filterable?: boolean (default true), busyNow?: boolean }
     */
    assistants: {
      type: Array,
      default: () => [],
    },
    modelValue: {
      type: [String, Number],
      default: '',
    },
    ariaLabel: {
      type: String,
      default: 'Filter by assistant',
    },
  },
  emits: ['update:modelValue'],
  methods: {
    chipDisabled(attendant) {
      if (attendant.id === '' || attendant.id === null || typeof attendant.id === 'undefined') {
        return false;
      }
      return attendant.filterable === false;
    },
    chipClasses(attendant) {
      return {
        'attendant-chip--active': this.isActive(attendant.id),
        'attendant-chip--busy': !!attendant.busyNow,
      };
    },
    chipTitle(attendant) {
      if (this.chipDisabled(attendant)) {
        return 'No bookings to filter for this assistant';
      }
      if (attendant.busyNow) {
        return 'In a reservation now';
      }
      return '';
    },
    chipKey(attendant) {
      const id = attendant.id;
      return id === '' || id === null || typeof id === 'undefined' ? 'all' : id;
    },
    initials(name) {
      if (!name) return '?';
      return name.split(' ').slice(0, 2).map((w) => w[0]?.toUpperCase() ?? '').join('');
    },
    firstName(name) {
      return name ? name.split(' ')[0] : '';
    },
    isActive(attendantId) {
      if (attendantId === '' || attendantId === null || typeof attendantId === 'undefined') {
        return this.modelValue === '' || this.modelValue === null;
      }
      return String(this.modelValue) === String(attendantId);
    },
    onChipClick(attendant) {
      if (this.chipDisabled(attendant)) return;
      const attendantId = attendant.id;
      if (this.isActive(attendantId)) {
        this.$emit('update:modelValue', '');
        return;
      }
      if (attendantId === '' || attendantId === null || typeof attendantId === 'undefined') {
        this.$emit('update:modelValue', '');
        return;
      }
      const n = Number(attendantId);
      this.$emit('update:modelValue', Number.isFinite(n) && n > 0 ? n : attendantId);
    },
  },
};
</script>

<style scoped>
.attendant-chips {
  display: flex;
  gap: 12px;
  overflow-x: auto;
  padding-bottom: 2px;
  margin-top: 12px;
  margin-bottom: 16px;
  scrollbar-width: none;
}

.attendant-chips::-webkit-scrollbar {
  display: none;
}

.attendant-chip {
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  background: transparent;
  border: none;
  cursor: pointer;
  padding: 0;
}

.attendant-chip:disabled {
  opacity: 0.42;
  cursor: not-allowed;
}

.attendant-chip--busy:not(:disabled) .attendant-avatar {
  animation: assistant-busy-bounce 0.95s ease-in-out infinite;
}

@keyframes assistant-busy-bounce {
  0%,
  100% {
    transform: translateY(0);
  }
  50% {
    transform: translateY(-4px);
  }
}

@media (prefers-reduced-motion: reduce) {
  .attendant-chip--busy .attendant-avatar {
    animation: none;
  }
}

.attendant-avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background-color: var(--color-background, #f4f6fa);
  border: 2px solid transparent;
  color: var(--color-text-secondary, #64748b);
  font-size: 12px;
  font-weight: 600;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: border-color 0.12s ease;
  overflow: hidden;
}

.attendant-avatar-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.attendant-chip--active .attendant-avatar {
  border-color: var(--color-primary, #2563eb);
  background-color: var(--color-primary-light, #eff6ff);
  color: var(--color-primary, #2563eb);
}

.attendant-name {
  font-size: 10px;
  font-weight: 500;
  color: var(--color-text-muted, #94a3b8);
}

.attendant-chip--active .attendant-name {
  color: var(--color-primary, #2563eb);
}
</style>
