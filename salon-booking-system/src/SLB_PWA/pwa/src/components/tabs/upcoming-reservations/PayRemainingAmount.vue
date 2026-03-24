<template>
  <span class="pay-remaining-wrap" v-show="show">
    <button class="pay-btn" @click="payAmount" :disabled="isLoading" type="button">
      <b-spinner small v-if="isLoading" />
      <font-awesome-icon icon="fa-solid fa-credit-card" v-else />
      {{ getLabel('successMessagePayRemainingAmount') || 'Pay Remaining' }}
    </button>
    <b-alert :show="isSuccess" fade variant="success" class="pay-alert">{{ getLabel('successMessagePayRemainingAmount') }}</b-alert>
    <b-alert :show="isError" fade variant="danger" class="pay-alert">{{ getLabel('errorMessagePayRemainingAmount') }}</b-alert>
  </span>
</template>

<script>
    export default {
        name: 'PayRemainigAmount',
        props: {
            booking: {
                default: function () {
                    return {};
                },
            },
        },
        data() {
            return {
                isLoading: false,
                isSuccess: false,
                isError: false,
            }
        },
        computed: {
            deposit() {
                return this.booking.deposit
            },
            paid_remained() {
                return this.booking.paid_remained
            },
            show() {
                return this.deposit > 0 && !this.paid_remained
            },
            id() {
                return this.booking.id
            },
        },
        methods: {
            payAmount() {
                this.isLoading = true
                this.axios.get('bookings/' + this.id + '/pay-remaining-amount').then((response) => {
                    if (response.data.success) {
                        this.isSuccess = true
                    }
                    if (response.data.error) {
                        this.isError = true
                    }
                    setTimeout(() => {
                        this.isSuccess = false
                        this.isError = false
                    }, 3000)
                })
                .finally(() => {
                    this.isLoading = false
                })
            },
        }
    }
</script>

<style scoped>
.pay-remaining-wrap {
  display: flex;
  flex-direction: column;
  gap: 6px;
  width: 100%;
  padding-top: 4px;
}
.pay-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 10px 16px;
  border-radius: var(--radius-pill, 999px);
  border: 1.5px solid var(--color-primary, #2563EB);
  background: var(--color-primary-light, #EFF6FF);
  color: var(--color-primary, #2563EB);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  width: 100%;
}
.pay-btn:disabled { opacity: 0.6; cursor: not-allowed; }
.pay-alert {
  padding: 6px 12px;
  margin-bottom: 0;
  font-size: 13px;
}
</style>
