<template>
  <div class="slots-headline">
    <div class="selected-date">
      {{ formattedDate }}
    </div>
    <div
        class="attendant-toggle"
        v-if="settings?.attendant_enabled && attendants.length > 0"
    >
      {{ this.getLabel('attendantViewLabel') }}
      <b-form-checkbox
          v-model="isAttendantViewLocal"
          switch
          size="lg"
      />
    </div>
  </div>
</template>

<script>
export default {
  name: 'SlotsHeadline',

  props: {
    date: {
      type: Date,
      required: true
    },
    settings: {
      type: Object,
      default: () => ({})
    },
    attendants: {
      type: Array,
      default: () => []
    },
    isAttendantView: {
      type: Boolean,
      default: false
    }
  },

  emits: ['update:isAttendantView'],

  computed: {
    formattedDate() {
      const locale = this.dayjsLocale(this.getLabel('calendarLocale'));
      return this.moment(this.date).locale(locale).format('dddd DD YYYY');
    },

    isAttendantViewLocal: {
      get() {
        return this.isAttendantView;
      },
      set(value) {
        this.$emit('update:isAttendantView', value);
      }
    }
  },

  methods: {
    getLabel(key) {
      return this.$parent.getLabel?.(key) || key;
    }
  }
};
</script>

<style scoped>
.slots-headline {
  display: flex;
  align-items: center;
  margin-top: 20px;
}

.selected-date {
  font-size: 18px;
  font-weight: 600;
  color: #0F172A;
  text-align: left;
}

.attendant-toggle {
  margin-left: auto;
  color: #64748B;
  font-weight: 400;
  font-size: 13px;
  user-select: none;
  display: flex;
  align-items: center;
  gap: 8px;
}

@media screen and (max-width: 600px) {
  .selected-date {
    font-size: 16px;
  }
}
</style>