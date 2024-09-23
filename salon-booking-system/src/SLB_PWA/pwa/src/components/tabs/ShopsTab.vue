<template>
    <div>
        <ShopsList @applyShop="applyShop"/>
    </div>
</template>

<script>

    import ShopsList from './shops/ShopsList.vue'

    export default {
        name: 'ShopsTab',
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

<style scoped>

</style>