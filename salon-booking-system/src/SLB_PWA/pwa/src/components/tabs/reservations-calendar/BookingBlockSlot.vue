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
          :class="{ 'disabled': isDisabled }"
      />
      <font-awesome-icon
          icon="fa-solid fa-lock"
          v-else
          @click="unlock"
          class="icon"
          :class="{ 'disabled': isDisabled }"
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
      operationTimeout: null
    };
  },
  computed: {
    holidayRule() {
      const rule = {
        from_date: this.date,
        to_date: this.date,
        from_time: this.moment(this.start, "HH:mm").format("HH:mm"),
        to_time: this.moment(this.end, "HH:mm").format("HH:mm"),
        daily: true
      };

      if (this.assistantId !== null) {
        rule.assistant_id = this.assistantId;
      }

      return rule;
    },
  },
  methods: {
    async lock() {
      if (this.isLoading || this.isDisabled) return;

      this.isLoading = true;
      this.$emit("lock-start", this.holidayRule);

      try {
        const response = await this.axios.post('holiday-rules', this.holidayRule);

        if (response.data?.status === "OK") {
          this.$emit("lock", this.holidayRule);
        } else {
          console.error('lock operation failed');
        }
      } catch (error) {
        console.error('Lock error:', error?.response?.data || error);
      } finally {
        this.operationTimeout = setTimeout(() => {
          this.isLoading = false;
          this.$emit("lock-end", this.holidayRule);
        }, 300);
      }
    },

    async unlock() {
      if (this.isLoading || this.isDisabled) return;
      this.isLoading = true;
      this.$emit("unlock-start", this.holidayRule);

      try {
        const deletePayload = {
          from_date: this.date,
          to_date: this.date,
          from_time: this.moment(this.start, "HH:mm").format("HH:mm"),
          to_time: this.moment(this.end, "HH:mm").format("HH:mm"),
          daily: true
        };

        if (this.assistantId !== null) {
          deletePayload.assistant_id = this.assistantId;
        }

        const response = await this.axios.delete('holiday-rules', {
          data: deletePayload
        });

        if (response.data?.status === "OK") {
          this.$emit("unlock", this.holidayRule);
          this.$emit("refresh-rules");
        } else {
          console.error('unlock operation failed');
        }
      } catch (error) {
        console.error('Unlock error:', error?.response?.data || error);
      } finally {
        this.operationTimeout = setTimeout(() => {
          this.isLoading = false;
          this.$emit("unlock-end", this.holidayRule);
        }, 300);
      }
    }
  },
  unmounted() {
    if (this.operationTimeout) {
      clearTimeout(this.operationTimeout);
    }
  },
  emits: ["lock", "unlock", "lock-start", "unlock-start", "lock-end", "unlock-end", "refresh-rules"],
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

.spinner-border {
  width: 35px;
  height: 35px;
  color: #04409F;
}
</style>