<template>
    <div v-if="isShopsEnabled">
        <ShopsList :isShopsEnabled="isShopsEnabled" @applyShop="applyShop"/>
    </div>
</template>

<script>

    import ShopsList from './shops/ShopsList.vue'

    export default {
        name: 'ShopsTab',
        props: {
            isShopsEnabled: {
                type: Boolean,
                required: true,
            },
        },
        components: {
            ShopsList,
        },
        methods: {
            applyShop(shop) {
                this.$emit('applyShop', shop)
            },
        },
        emits:['applyShop']
    }
</script>