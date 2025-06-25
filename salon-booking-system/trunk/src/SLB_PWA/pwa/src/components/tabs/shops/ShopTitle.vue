<template>
  <div class="shop-title-wrapper">
    <div class="shop-selector">
      <div class="selector-label">{{ this.getLabel('shopTitleLabel') }}:</div>
      <div class="selector-dropdown" v-click-outside="closeDropdown">
        <div class="selected-value" @click="toggleDropdown">
          <span v-if="selectedShopName">{{ selectedShopName }}</span>
          <span v-else>{{ this.getLabel('selectShopPlaceholder') }}</span>
          <font-awesome-icon
              icon="fa-solid fa-chevron-right"
              class="dropdown-icon"
              :class="{ 'dropdown-icon--open': isDropdownOpen }"
          />
        </div>
        <div class="dropdown-content" v-if="isDropdownOpen">
          <div v-if="isLoading" class="loading-spinner">
            <b-spinner variant="primary"></b-spinner>
          </div>
          <div v-else-if="shopsList.length === 0" class="no-shops">
            {{ this.getLabel('shopsNoResultLabel') }}
          </div>
          <div v-else class="shops-list">
            <div
                v-for="shopItem in shopsList"
                :key="shopItem.id"
                class="shop-item"
                @click="selectShop(shopItem)"
            >
              <div class="shop-info">
                <div class="shop-name">{{ shopItem.name }}</div>
                <div class="shop-address">{{ shopItem.address }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'ShopTitle',
  props: {
    shop: {
      default: function () {
        return {};
      },
    },
  },
  data() {
    return {
      isDropdownOpen: false,
      shopsList: [],
      isLoading: false,
    }
  },
  computed: {
    name() {
      return this.shop && this.shop.id ? this.shop.name : '';
    },
    selectedShopName() {
      return this.shop && this.shop.id ? this.shop.name : '';
    }
  },
  methods: {
    toggleDropdown() {
      this.isDropdownOpen = !this.isDropdownOpen;
      if (this.isDropdownOpen) {
        this.loadShops();
      }
    },
    closeDropdown() {
      this.isDropdownOpen = false;
    },
    loadShops() {
      this.isLoading = true;
      this.shopsList = [];
      this.axios
          .get('shops')
          .then((response) => {
            this.shopsList = response.data.items;
          })
          .finally(() => {
            this.isLoading = false;
          });
    },
    selectShop(shop) {
      this.$emit('applyShop', shop);
      this.closeDropdown();
    }
  },
  emits: ['applyShop'],
  directives: {
    'click-outside': {
      mounted(el, binding) {
        el.clickOutsideEvent = function (event) {
          if (!(el === event.target || el.contains(event.target))) {
            binding.value(event);
          }
        };
        document.addEventListener('click', el.clickOutsideEvent);
      },
      unmounted(el) {
        document.removeEventListener('click', el.clickOutsideEvent);
      },
    },
  }
}
</script>

<style scoped>
.shop-title {
  display: flex;
  align-items: center;
  gap: 4px;
  text-align: left;
  font-size: 1.2rem;
  margin-bottom: 15px;
}

.label {
  font-weight: normal;
}

.value {
  font-weight: bold;
  color: #04409F;
}

.shop-selector {
  display: flex;
  align-items: center;
  gap: 18px;
  text-align: left;
  margin-bottom: 15px;
}

.selector-label {
  font-size: 1.2rem;
  margin-bottom: 8px;
}

.selector-dropdown {
  position: relative;
  margin-bottom: 8px;
  max-width: 320px;
  width: 100%;
}

.dropdown-icon {
  transform: rotate(90deg);
  transition: transform 0.2s ease;
}

.dropdown-icon--open {
  transform: rotate(-90deg);
}

.selected-value {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 15px;
  background-color: #ecf1fa9b;
  border-radius: 8px;
  cursor: pointer;
  font-size: 16px;
}

.dropdown-content {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  background-color: white;
  border: 1px solid #ECF1FA;
  border-radius: 4px;
  margin-top: 5px;
  max-height: 300px;
  overflow-y: auto;
  z-index: 1000;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.loading-spinner {
  display: flex;
  justify-content: center;
  padding: 20px;
}

.no-shops {
  padding: 20px;
  text-align: center;
  color: #637491;
}

.shop-item {
  padding: 12px 15px;
  cursor: pointer;
  border-bottom: 1px solid #ECF1FA;
}

.shop-item:last-child {
  border-bottom: none;
}

.shop-item:hover {
  background-color: #ECF1FA9B;
}

.shop-name {
  color: #04409F;
  font-size: 1.1rem;
  margin-bottom: 4px;
}

.shop-address {
  color: #637491;
  font-size: 0.9rem;
}
</style>