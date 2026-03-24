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
  margin-top: 16px;
}

.search-icon {
  position: absolute;
  z-index: 1000;
  top: 12px;
  left: 15px;
  color: #64748B;
  font-size: 14px;
}

.search .search-input {
  padding-left: 40px;
  padding-right: 36px;
  border-radius: 999px;
  border: 1px solid #E2E8F0;
  background: #FFFFFF;
  color: #0F172A;
  font-size: 14px;
}

.search .search-input:focus {
  border-color: #2563EB;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.clear {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  z-index: 1000;
  right: 14px;
  cursor: pointer;
  color: #94A3B8;
  font-size: 16px;
}

.clear:hover {
  color: #64748B;
}
</style>