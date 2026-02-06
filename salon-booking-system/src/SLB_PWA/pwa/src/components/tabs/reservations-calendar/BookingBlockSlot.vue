<template>
  <div class="block-slot">
    <template v-if="isLoading">
      <b-spinner variant="primary" size="sm"/>
    </template>
    <template v-else>
      <font-awesome-icon
          icon="fa-solid fa-unlock"
          v-if="!isLock"
          @click="lock"
          class="icon"
          :class="{ disabled: isDisabled }"
      />
      <font-awesome-icon
          icon="fa-solid fa-lock"
          v-else-if="isManualLocked"
          @click="unlock"
          class="icon"
          :class="{ disabled: isDisabled }"
      />
      <font-awesome-icon
          v-else-if="isSystemLocked"
          icon="fa-solid fa-lock"
          class="icon system-locked"
      />
    </template>
  </div>
</template>

<script>
export default {
  name: "BookingBlockSlot",
  props: {
    isLock: {
      type: Boolean,
      default: false,
    },
    start: {
      type: String,
      default: "08:00",
    },
    end: {
      type: String,
      default: "08:30",
    },
    date: {
      type: String,
      required: true,
      validator: function (value) {
        return /^\d{4}-\d{2}-\d{2}$/.test(value);
      }
    },
    shop: {
      type: Number,
      required: true,
    },
    isSystemLocked: {
      type: Boolean,
      default: false
    },
    isManualLocked: {
      type: Boolean,
      default: false
    },
    isDisabled: {
      type: Boolean,
      default: false
    },
    assistantId: {
      type: Number,
      default: null
    }
  },
  data() {
    return {
      isLoading: false,
      END_OF_DAY: '24:00',
      MINUTES_IN_DAY: 1440
    };
  },
  computed: {
    holidayRule() {
      const toTime = (this.end === '00:00' || this.end === this.END_OF_DAY)
        ? this.END_OF_DAY
        : this.normalizeTime(this.end);

      const rule = {
        from_date: this.date,
        to_date: this.date,
        from_time: this.normalizeTime(this.start),
        to_time: toTime,
        daily: true
      };

      if (this.assistantId != null) {
        rule.assistant_id = this.assistantId;
      }

      return rule;
    },
  },
  methods: {
    lock() {
      if (this.isLoading || this.isDisabled) return;
      this.isLoading = true;
      this.$emit("lock-start", this.holidayRule);
      this.$emit("lock", this.holidayRule);

      setTimeout(() => {
        this.$emit("lock-end", this.holidayRule);
        this.isLoading = false;
      }, 300);
    },
    unlock() {
      if (this.isLoading || this.isDisabled) return;
      this.isLoading = true;
      this.$emit("unlock-start", this.holidayRule);
      this.$emit("unlock", this.holidayRule);

      setTimeout(() => {
        this.$emit("unlock-end", this.holidayRule);
        this.isLoading = false;
      }, 300);
    },
    normalizeTime(time) {
      if (time === this.END_OF_DAY) return this.END_OF_DAY;
      return this.moment(time, this.getTimeFormat()).format('HH:mm');
    }
  },
  emits: ["lock", "unlock", "lock-start", "unlock-start", "lock-end", "unlock-end"],
};
</script>

<style scoped>
.block-slot {
  display: flex;
  align-items: center;
  justify-content: center;
  min-width: 35px;
  min-height: 35px;
}

.icon {
  font-size: 35px;
  cursor: pointer;
  color: #04409F;
  transition: opacity 0.2s ease;
}

.icon:disabled,
.icon.disabled {
  cursor: not-allowed;
  opacity: 0.5;
}

.icon.system-locked {
  cursor: default;
  opacity: 0;
}

.spinner-border {
  width: 35px;
  height: 35px;
  color: #04409F;
}
</style>
