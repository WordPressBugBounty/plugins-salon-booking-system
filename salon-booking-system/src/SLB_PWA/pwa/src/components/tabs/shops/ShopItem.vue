<template>
  <div class="shop-card" @click="applyShop">
    <div class="shop-icon-wrap">
      <font-awesome-icon icon="fa-solid fa-store" class="shop-icon" />
    </div>
    <div class="shop-info">
      <div class="shop-name">{{ name }}</div>
      <div class="shop-meta" v-if="address || phone">
        <span v-if="address">{{ address }}</span>
        <span v-if="address && phone"> · </span>
        <span v-if="phone">{{ phone }}</span>
      </div>
      <div class="shop-email-text" v-if="email">{{ email }}</div>
    </div>
    <font-awesome-icon icon="fa-solid fa-chevron-right" class="shop-chevron" />
  </div>
</template>

<script>
    export default {
        name: 'ShopItem',
        props: {
            shop: {
                default: function () {
                    return {};
                },
            },
        },
        computed: {
            name() {
                return this.shop.name
            },
            address() {
                return this.shop.address
            },
            email() {
                return this.shop.email
            },
            phone() {
                return this.shop.phone
            },
        },
        methods: {
            applyShop() {
                this.$emit('applyShop', this.shop)
            },
        },
        emits: ['applyShop']
    }
</script>

<style scoped>
.shop-card {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px;
  background: var(--color-surface, #fff);
  border-radius: var(--radius-md, 12px);
  cursor: pointer;
  transition: background 0.15s;
}
.shop-card:active { background: var(--color-background, #F4F6FA); }
.shop-icon-wrap {
  width: 44px;
  height: 44px;
  border-radius: var(--radius-sm, 8px);
  background: var(--color-primary-light, #EFF6FF);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.shop-icon {
  font-size: 18px;
  color: var(--color-primary, #2563EB);
}
.shop-info { flex: 1; }
.shop-name {
  font-size: 15px;
  font-weight: 600;
  color: var(--color-text-primary, #0F172A);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.shop-meta {
  font-size: 13px;
  color: var(--color-text-secondary, #64748B);
  margin-top: 2px;
}
.shop-email-text {
  font-size: 12px;
  color: var(--color-text-muted, #94A3B8);
  margin-top: 2px;
}
.shop-chevron {
  font-size: 14px;
  color: var(--color-text-muted, #94A3B8);
}
</style>
