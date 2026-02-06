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
      return this.moment(this.date).locale(this.getLabel('calendarLocale')).format("dddd DD YYYY");
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
}

.selected-date {
  margin-top: 55px;
  font-size: 18px;
  font-weight: 700;
  color: #322d38;
  text-align: left;
}

.attendant-toggle {
  margin-top: 55px;
  margin-left: auto;
  color: #4A454F;
  font-weight: 400;
  font-size: 14px;
  user-select: none;
  display: flex;
  align-items: center;
  gap: 12px;
}

@media screen and (max-width: 600px) {
  .attendant-toggle {
    margin-top: 32px;
  }

  .selected-date {
    font-size: 16px;
    margin-top: 32px;
  }
}
</style>