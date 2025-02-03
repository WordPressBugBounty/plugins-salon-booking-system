<template>
  <div class="search">
    <font-awesome-icon
        icon="fa-solid fa-magnifying-glass"
        class="search-icon"
    />
    <b-form-input
        v-model="searchValue"
        class="search-input"
        @input="handleInput"
    />
    <font-awesome-icon
        v-if="searchValue"
        icon="fa-solid fa-circle-xmark"
        class="clear"
        @click="clearSearch"
    />
  </div>
</template>

<script>
function debounce(fn, delay = 300) {
  let timer = null;
  return (...args) => {
    if (timer) clearTimeout(timer);
    timer = setTimeout(() => {
      fn(...args);
    }, delay);
  };
}

export default {
  name: 'SearchInput',

  props: {
    modelValue: {
      type: String,
      default: ''
    },
    debounceTime: {
      type: Number,
      default: 600
    }
  },

  data() {
    return {
      searchValue: this.modelValue
    };
  },

  watch: {
    modelValue(newValue) {
      this.searchValue = newValue;
    }
  },

  created() {
    this.debouncedEmit = debounce((value) => {
      this.$emit('update:modelValue', value);
      this.$emit('search', value);
    }, this.debounceTime);
  },

  methods: {
    handleInput(value) {
      this.debouncedEmit(value);
    },

    clearSearch() {
      this.searchValue = '';
      this.$emit('update:modelValue', '');
      this.$emit('search', '');
    }
  }
};
</script>

<style scoped>
.search {
  position: relative;
  margin-top: 1.5rem;
}

.search-icon {
  position: absolute;
  z-index: 1000;
  top: 12px;
  left: 15px;
  color: #7f8ca2;
}

.search .search-input {
  padding-left: 40px;
  padding-right: 20px;
  border-radius: 30px;
  border-color: #7f8ca2;
}

.clear {
  position: absolute;
  top: 10px;
  z-index: 1000;
  right: 15px;
  cursor: pointer;
}
</style>