<template>
    <div class="custom-field">
        <template v-if="type === 'text'">
            <label class="label" :for="key">{{ displayLabel }}</label>
            <b-form-input v-model.lazy="elValue" :id="key" class="custom-field-control" />
        </template>
        <template v-else-if="type === 'textarea'">
            <label class="label" :for="key">{{ displayLabel }}</label>
            <b-form-textarea v-model.lazy="elValue" :id="key" class="custom-field-control" />
        </template>
        <template v-else-if="type === 'checkbox'">
            <b-form-checkbox v-model="elValue" :id="key" class="custom-field-checkbox">
                {{ displayLabel }}
            </b-form-checkbox>
        </template>
        <template v-else-if="type === 'select'">
            <label class="label" :for="key">{{ displayLabel }}</label>
            <b-form-select v-model="elValue" :id="key" :options="options" class="custom-field-control" />
        </template>
    </div>
</template>

<script>
    export default {
        name: 'CustomField',
        props: {
            field: {
                default: function () {
                    return {};
                },
            },
            value: {
                default: function () {
                    return '';
                },
            },
        },
        mounted() {
            this.update()
        },
        data: function () {
            let value = this.value
            if (this.field.type === 'checkbox') {
                value = !!value
            }
            return {
                elValue: value
            }
        },
        watch: {
            elValue() {
                this.update()
            }
        },
        computed: {
            key() {
                return this.field.key
            },
            type() {
                return this.field.type
            },
            displayLabel() {
                return this.sanitizeLabel(this.field.label)
            },
            options() {
                const opts = this.field.options || []
                return opts.map(i => ({
                    value: i.value,
                    text: this.sanitizeLabel(i.label),
                }))
            },
        },
        methods: {
            sanitizeLabel(str) {
                if (str == null) {
                    return ''
                }
                let s = String(str)
                /* Stored/exported labels sometimes contain literal "\u00a0" or NBSP */
                s = s.replace(/\\u00a0/gi, ' ').replace(/\\u200b/gi, '')
                s = s.replace(/\u00a0/g, ' ').replace(/\u200b/g, '')
                return s.trim()
            },
            update() {
                this.$emit('update', this.key, this.elValue);
            },
        },
        emits: ['update']
    }
</script>

<style scoped>
    .custom-field + .custom-field {
        margin-top: 1.25rem;
    }
    .label {
        display: block;
        margin: 0 0 6px;
        padding: 0;
        color: var(--color-text-secondary, #64748B);
        font-size: 14px;
        font-weight: 600;
        line-height: 1.35;
    }
    .custom-field-control {
        margin: 0 !important;
    }
    .custom-field-checkbox {
        margin: 0;
        padding-top: 2px;
    }
    .custom-field :deep(.form-control),
    .custom-field :deep(.form-select) {
        margin-bottom: 0;
        margin-top: 0;
    }
</style>
