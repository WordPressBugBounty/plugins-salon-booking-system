<template>
    <div>
        <h5 class="title">
            {{ this.getLabel('shopsTitle') }}
        </h5>
        <div class="shops-list">
            <b-spinner variant="primary" v-if="isLoading"></b-spinner>
            <template v-else-if="shopsList.length > 0">
                <ShopItem
                    v-for="shop in shopsList"
                    :key="shop.id"
                    :shop="shop"
                    @applyShop="applyShop"
                />
            </template>
            <template v-else>
                <span class="no-result">{{ this.getLabel('shopsNoResultLabel') }}</span>
            </template>
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
    .shops-list {
        margin-top: 1.5rem;
    }
    .title {
        text-align: left;
        font-weight: bold;
        color: #322D38;
        font-size: 22px;
    }
</style>