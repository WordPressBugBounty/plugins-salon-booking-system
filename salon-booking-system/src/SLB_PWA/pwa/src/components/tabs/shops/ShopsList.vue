<template>
  <div class="shops-screen">
    <div class="shops-header">
      <h1 class="shops-title">{{ getLabel('shopsTitle') }}</h1>
    </div>
    <div class="shops-body">
      <div class="loading-wrap" v-if="isLoading">
        <b-spinner variant="primary" />
      </div>
      <template v-else-if="shopsList.length > 0">
        <div class="shops-list">
          <ShopItem
              v-for="shop in shopsList"
              :key="shop.id"
              :shop="shop"
              @applyShop="applyShop"
          />
        </div>
      </template>
      <div class="empty-state" v-else>
        <font-awesome-icon icon="fa-solid fa-store" class="empty-icon" />
        <p class="empty-title">{{ getLabel('shopsNoResultLabel') }}</p>
      </div>
    </div>
  </div>
</template>

<script>

    import ShopItem from './ShopItem.vue'

    export default {
        name: 'ShopsList',
        props: {
            isShopsEnabled: {
                type: Boolean,
                required: true,
            },
        },
        data: function () {
            return {
                shopsList: [],
                isLoading: false,
            }
        },
        mounted() {
            if (this.isShopsEnabled) {
              this.load();
            }
        },
        components: {
            ShopItem,
        },
        methods: {
            load() {
                if (!this.isShopsEnabled) return;
                this.isLoading = true;
                this.shopsList = [];
                this.axios
                    .get('shops')
                    .then((response) => {
                        this.shopsList = response.data.items
                    })
                    .finally(() => {
                        this.isLoading = false
                    })
            },
            applyShop(shop) {
                this.$emit('applyShop', shop)
            },
        },
        emits: ['applyShop']
    }
</script>

<style scoped>
.shops-screen {
  min-height: 100vh;
  background: var(--color-background, #F4F6FA);
  padding-bottom: 100px;
}
.shops-header {
  padding: 20px var(--spacing-page, 16px) 12px;
  background: var(--color-surface, #fff);
  border-bottom: 1px solid var(--color-border, #E2E8F0);
}
.shops-title {
  font-size: 22px;
  font-weight: 700;
  color: var(--color-text-primary, #0F172A);
  margin: 0;
}
.shops-body {
  padding: 12px var(--spacing-page, 16px);
}
.loading-wrap {
  display: flex;
  justify-content: center;
  padding: 40px 0;
}
.shops-list { display: flex; flex-direction: column; gap: 8px; }
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 60px 20px;
  gap: 12px;
}
.empty-icon {
  font-size: 40px;
  color: var(--color-text-muted, #94A3B8);
}
.empty-title {
  font-size: 16px;
  font-weight: 600;
  color: var(--color-text-secondary, #64748B);
  margin: 0;
}
</style>