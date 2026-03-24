<template>
    <div class="customers-screen">
        <!-- Header -->
        <div class="screen-header">
            <h1 class="screen-title">{{ this.getLabel('customersAddressBookTitle') }}</h1>
            <button class="header-icon-btn" @click="isSearchVisible = !isSearchVisible" aria-label="Search">
                <font-awesome-icon :icon="isSearchVisible ? 'fa-solid fa-circle-xmark' : 'fa-solid fa-magnifying-glass'" />
            </button>
        </div>

        <!-- Search bar -->
        <div class="search-bar" v-if="isSearchVisible">
            <font-awesome-icon icon="fa-solid fa-magnifying-glass" class="search-bar-icon" />
            <input
                v-model="search"
                class="search-bar-input"
                placeholder="Search by name or phone…"
                autofocus
            />
            <font-awesome-icon icon="fa-solid fa-circle-xmark" class="search-bar-clear" @click="search = ''; isSearchVisible = false" v-if="search"/>
        </div>

        <!-- Letter filter chips -->
        <div class="filter-chips" v-if="!search">
            <button
                v-for="filter in filters"
                :key="filter.value"
                class="letter-chip"
                :class="{ 'letter-chip--active': searchFilter === filter.value }"
                @click="searchFilter = filter.value"
            >
                {{ filter.label }}
            </button>
        </div>

        <!-- Customers list -->
        <div class="customers-list">
            <div class="loading-center" v-if="isLoading">
                <b-spinner></b-spinner>
            </div>
            <template v-else-if="customersList.length > 0">
                <CustomerItem
                    v-for="customer in customersList"
                    :key="customer.id"
                    :customer="customer"
                    :chooseCustomerAvailable="chooseCustomerAvailable"
                    @choose="choose(customer)"
                    @showImages="showImages"
                    @edit="edit"
                />
            </template>
            <template v-else>
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <font-awesome-icon icon="fa-regular fa-address-book" />
                    </div>
                    <p class="empty-state-title">No customers found</p>
                    <p class="empty-state-sub">{{ this.getLabel('customersAddressBookNoResultLabel') }}</p>
                </div>
            </template>
        </div>

        <!-- Go back (when used as customer picker) -->
        <button class="go-back-btn" @click="closeChooseCustomer" v-if="chooseCustomerAvailable">
            {{ this.getLabel('goBackButtonLabel') }}
        </button>
    </div>
</template>

<script>

    import CustomerItem from './CustomerItem.vue';

    export default {
        name: 'CustomersAddressBook',
        props: {
            chooseCustomerAvailable: {
                default: function () {
                    return false;
                },
            },
            shop: {
                default: function () {
                    return {};
                },
            },
            customer: {
                default: function () {
                    return {};
                },
            },
        },
        mounted() {
            this.load();
        },
        watch: {
            searchFilter(newVal) {
                newVal && this.load();
            },
            search(newVal) {
                if (newVal) {
                    this.searchFilter = ''
                    this.loadSearch()
                } else {
                    this.searchFilter = 'a|b'
                }
            },
            shop() {
                this.load()
            },
            customer() {
                if (!this.customer) {
                    return;
                }
                this.customersList.forEach((item, i) => {
                    if (this.customer.id === item.id) {
                        this.customersList[i] = this.customer
                    }
                })
            },
        },
        data: function () {
            return {
                filters: [
                    {label: 'a - b', value: 'a|b'},
                    {label: 'c - d', value: 'c|d'},
                    {label: 'e - f', value: 'e|f'},
                    {label: 'g - h', value: 'g|h'},
                    {label: 'i - j', value: 'i|j'},
                    {label: 'k - l', value: 'k|l'},
                    {label: 'm - n', value: 'm|n'},
                    {label: 'o - p', value: 'o|p'},
                    {label: 'q - r', value: 'q|r'},
                    {label: 's - t', value: 's|t'},
                    {label: 'u - v', value: 'u|v'},
                    {label: 'w - x', value: 'w|x'},
                    {label: 'y - z', value: 'y|z'},
                ],
                searchFilter: 'a|b',
                customersList: [],
                isLoading: false,
                search: '',
                timeout: null,
                isSearchVisible: false,
            }
        },
        methods: {
            closeChooseCustomer() {
                this.$emit('closeChooseCustomer')
            },
            choose(customer) {
                this.$emit('choose', customer)
            },
            load() {
                this.isLoading = true;
                this.customersList = [];
                this.axios
                    .get('customers', {params: {search: this.searchFilter, search_type: 'start_with', search_field: 'first_name', order_by: 'first_name_last_name', shop: this.shop ? this.shop.id : null}})
                    .then((response) => {
                        this.customersList = response.data.items || [];
                    })
                    .catch(() => {
                        this.customersList = [];
                    })
                    .finally(() => {
                        this.isLoading = false
                    })
            },
            loadSearch() {
                this.timeout && clearTimeout(this.timeout)
                this.timeout = setTimeout(() => {
                    this.isLoading = true;
                    this.customersList = [];
                    this.axios
                        .get('customers', {params: {search: this.search, order_by: 'first_name_last_name', shop: this.shop ? this.shop.id : null}})
                        .then((response) => {
                            this.customersList = response.data.items || [];
                        })
                        .catch(() => {
                            this.customersList = [];
                        })
                        .finally(() => {
                            this.isLoading = false
                        })
                }, 1000)
            },
            showImages(customer) {
                this.$emit('showImages', customer)
            },
            edit(customer) {
                this.$emit('edit', customer)
            },
        },
        components: {
            CustomerItem,
        },
        emits: ['closeChooseCustomer', 'choose', 'showImages', 'edit']
    }
</script>

<style scoped>
/* ── Screen ── */
.customers-screen {
    padding-top: 4px;
}

/* ── Header ── */
.screen-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 0 8px;
}

.screen-title {
    font-size: 22px;
    font-weight: 700;
    color: var(--color-text-primary, #0F172A);
    margin: 0;
}

.header-icon-btn {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    border: none;
    background: transparent;
    color: var(--color-text-secondary, #64748B);
    font-size: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: background-color 0.12s ease;
}

.header-icon-btn:active {
    background-color: var(--color-border, #E2E8F0);
}

/* ── Search bar ── */
.search-bar {
    position: relative;
    margin-bottom: 12px;
}

.search-bar-icon {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--color-text-muted, #94A3B8);
    font-size: 15px;
    pointer-events: none;
}

.search-bar-input {
    width: 100%;
    padding: 10px 40px 10px 40px;
    border-radius: var(--radius-pill, 999px);
    border: 1px solid var(--color-border, #E2E8F0);
    background-color: var(--color-surface, #FFFFFF);
    font-size: 15px;
    font-family: inherit;
    color: var(--color-text-primary, #0F172A);
    outline: none;
}

.search-bar-input::placeholder {
    color: var(--color-text-muted, #94A3B8);
}

.search-bar-input:focus {
    border-color: var(--color-primary, #2563EB);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.search-bar-clear {
    position: absolute;
    right: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--color-text-muted, #94A3B8);
    cursor: pointer;
    font-size: 15px;
}

/* ── Letter filter chips ── */
.filter-chips {
    display: flex;
    gap: 6px;
    overflow-x: auto;
    padding-bottom: 2px;
    margin-bottom: 14px;
    scrollbar-width: none;
}

.filter-chips::-webkit-scrollbar {
    display: none;
}

.letter-chip {
    flex-shrink: 0;
    min-width: 44px;
    height: 32px;
    border-radius: var(--radius-pill, 999px);
    border: none;
    background: transparent;
    color: var(--color-text-secondary, #64748B);
    font-size: 12px;
    font-weight: 500;
    font-family: inherit;
    cursor: pointer;
    transition: all 0.12s ease;
    text-transform: uppercase;
}

.letter-chip--active {
    background-color: var(--color-primary, #2563EB);
    color: #FFFFFF;
}

/* ── Customers list ── */
.customers-list {
    display: flex;
    flex-direction: column;
    gap: 0;
    background-color: var(--color-surface, #FFFFFF);
    border-radius: var(--radius-lg, 16px);
    overflow: hidden;
}

.loading-center {
    display: flex;
    justify-content: center;
    padding: 40px 0;
}

/* ── Empty state ── */
.empty-state {
    text-align: center;
    padding: 60px 24px 24px;
}

.empty-state-icon {
    font-size: 48px;
    color: var(--color-text-muted, #94A3B8);
    margin-bottom: 16px;
}

.empty-state-title {
    font-size: 17px;
    font-weight: 600;
    color: var(--color-text-primary, #0F172A);
    margin: 0 0 6px;
}

.empty-state-sub {
    font-size: 14px;
    color: var(--color-text-secondary, #64748B);
    margin: 0;
}

/* ── Go back button ── */
.go-back-btn {
    display: block;
    width: 100%;
    margin-top: 16px;
    padding: 13px;
    background-color: var(--color-primary, #2563EB);
    color: #FFFFFF;
    border: none;
    border-radius: var(--radius-lg, 16px);
    font-size: 15px;
    font-weight: 600;
    font-family: inherit;
    cursor: pointer;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
</style>