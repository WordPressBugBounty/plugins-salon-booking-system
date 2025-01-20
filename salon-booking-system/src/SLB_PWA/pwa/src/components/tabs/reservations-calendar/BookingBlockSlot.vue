<template>
  <div class="block-slot">
    <b-spinner variant="primary" v-if="isLoading"></b-spinner>
    <font-awesome-icon
        icon="fa-solid fa-unlock"
        v-else-if="!isLock"
        @click="lock"
        :class="{ 'disabled': isLoading }"
    />
    <font-awesome-icon
        icon="fa-solid fa-lock"
        v-else
        @click="unlock"
        :class="{ 'disabled': isLoading }"
    />
  </div>
</template>

<script>
export default {
  name: 'BookingBlockSlot',
  props: {
    isLock: {
      default: function () {
        return false;
      },
    },
    start: {
      default: function () {
        return '08:00';
      },
    },
    end: {
      default: function () {
        return '08:30';
      },
    },
    date: {
      default: function () {
        return null;
      },
    },
    shop: {
      default: function () {
        return {};
      },
    },
  },
  data: function () {
    return {
      isLoading: false,
    }
  },
  computed: {
    holidayRule() {
      const fromDate = this.moment(this.date).format('YYYY-MM-DD');
      let toDate = this.moment(this.date).format('YYYY-MM-DD');

      const fromTime = this.moment(this.start, 'HH:mm').format('HH:mm');
      const toTimeFormatted = this.moment(this.end, 'HH:mm').format('HH:mm');

      if (fromTime >= toTimeFormatted) {
        toDate = this.moment(this.date).add(1, 'day').format('YYYY-MM-DD');
      }

      return {
        from_date: fromDate,
        to_date: toDate,
        from_time: fromTime,
        to_time: toTimeFormatted,
        daily: true,
        shop: this.shop && this.shop.id ? this.shop.id : 0,
      }
    },
  },
  methods: {
    lock() {
      if (this.isLoading) return;
      this.isLoading = true;
      this.$emit('lock-start', {from_time: this.start, to_time: this.end});

      this.axios.post('holiday-rules', this.holidayRule)
          .then(() => {
            this.$emit('lock', this.holidayRule);
          })
          .finally(() => {
            this.isLoading = false;
            this.$emit('lock-end', {from_time: this.start, to_time: this.end});
          });
    },
    unlock() {
      if (this.isLoading) return;
      this.isLoading = true;
      this.$emit('unlock-start', {from_time: this.start, to_time: this.end});

      this.axios.delete('holiday-rules', {data: this.holidayRule})
          .then(() => {
            this.$emit('unlock', this.holidayRule);
          })
          .finally(() => {
            this.isLoading = false;
            this.$emit('unlock-end', {from_time: this.start, to_time: this.end});
          });
    },
  },
  emits: ['lock', 'unlock', 'lock-start', 'lock-end', 'unlock-start', 'unlock-end'],
};
</script>

<style scoped>
.block-slot .fa-unlock, .block-slot .fa-lock {
  font-size: 35px;
  cursor: pointer;
  color: #04409F;
  display: flex;
}

.block-slot .fa-unlock.disabled, .block-slot .fa-lock.disabled {
  cursor: not-allowed;
  opacity: 0.5;
}
</style>
