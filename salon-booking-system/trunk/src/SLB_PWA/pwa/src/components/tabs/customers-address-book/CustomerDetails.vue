<template>
    <b-col sm="12">
        <div class="customer-details-info">
            <b-row>
                <b-col sm="12">
                    <div class="customer-firstname">
                        <b-form-input :placeholder="this.getLabel('customerFirstnamePlaceholder')" v-model="elCustomerFirstname"
                                      :class="{required: requiredFields.indexOf('customer_first_name') > -1}"/>
                    </div>
                </b-col>
            </b-row>
            <b-row>
                <b-col sm="12">
                    <div class="customer-lastname">
                        <b-form-input :placeholder="this.getLabel('customerLastnamePlaceholder')" v-model="elCustomerLastname"/>
                    </div>
                </b-col>
            </b-row>
            <b-row>
                <b-col sm="12">
                    <div class="customer-email">
                        <b-form-input
                            :type="shouldHideEmail ? 'password' : 'text'"
                            :placeholder="this.getLabel('customerEmailPlaceholder')"
                            v-model="elCustomerEmail"/>
                    </div>
                </b-col>
            </b-row>
            <b-row>
                <b-col sm="12">
                    <div class="customer-address">
                        <b-form-input :placeholder="this.getLabel('customerAddressPlaceholder')" v-model="elCustomerAddress"/>
                    </div>
                </b-col>
            </b-row>
            <b-row>
                <b-col sm="12">
                    <div class="customer-phone">
                        <b-form-input
                            :type="shouldHidePhone ? 'password' : 'tel'"
                            :placeholder="this.getLabel('customerPhonePlaceholder')"
                            v-model="elCustomerPhone"
                        />
                    </div>
                </b-col>
            </b-row>
            <b-row>
                <b-col sm="12">
                    <div class="customer-details-extra-info">
                        <div class="customer-details-extra-info-header">
                            <div class="customer-details-extra-info-header-title">
                                {{ this.getLabel('extraInfoLabel') }}
                            </div>
                            <div>
                            <span
                                class="customer-details-extra-info-header-btn"
                                :class="visibleExtraInfo ? null : 'collapsed'"
                                :aria-expanded="visibleExtraInfo ? 'true' : 'false'"
                                aria-controls="collapse-2"
                                @click="visibleExtraInfo = !visibleExtraInfo"
                            >
                                <font-awesome-icon icon="fa-solid fa-circle-chevron-down" v-if="!visibleExtraInfo" />
                                <font-awesome-icon icon="fa-solid fa-circle-chevron-up" v-else />
                            </span>
                            </div>
                        </div>
                        <b-collapse id="collapse-2" class="mt-2" v-model="visibleExtraInfo">
                            <template v-for="field in customFieldsList" :key="field.key">
                                <CustomField :field="field" :value="getCustomFieldValue(field.key, field.default_value)" @update="updateCustomField"/>
                            </template>
                            <b-row class="field">
                                <b-col sm="12">
                                    <div class="customer-personal-notes">
                                        <label class="label" for="customer_personal_notes">{{ this.getLabel('customerPersonalNotesLabel') }}</label>
                                        <b-form-textarea
                                            v-model.lazy="elCustomerPersonalNotes"
                                            id="customer_personal_notes"
                                            :placeholder="this.getLabel('customerPersonalNotesPlaceholder')"
                                            rows="3"
                                            max-rows="6"
                                        ></b-form-textarea>
                                    </div>
                                </b-col>
                            </b-row>
                        </b-collapse>
                    </div>
                </b-col>
            </b-row>
            <b-row>
                <b-col sm="12" class="save-button-wrapper">
                    <b-button variant="primary" @click="save" class="save-button">
                        <b-spinner small variant="primary" v-if="isLoading"></b-spinner>
                        {{ this.getLabel('customerDetailsUpdateButtonLabel') }}
                    </b-button>
                </b-col>
            </b-row>
            <b-row>
                <b-col sm="12" class="go-back-button-wrapper">
                    <b-button variant="outline-primary" @click="close" class="go-back-button">
                        {{ this.getLabel('customerDetailsGoBackButtonLabel') }}
                    </b-button>
                </b-col>
            </b-row>
            <b-row>
                <b-col sm="12" class="save-button-result-wrapper">
                    <b-alert :show="isSaved" fade variant="success">{{ this.getLabel('savedLabel') }}</b-alert>
                    <b-alert :show="isError" fade variant="danger">{{ errorMessage }}</b-alert>
                    <b-alert :show="!isValid" fade variant="danger">{{ this.getLabel('validationMessage') }}</b-alert>
                </b-col>
            </b-row>
        </div>
    </b-col>
</template>

<script>
import CustomField from "@/components/tabs/upcoming-reservations/CustomField.vue";
import mixins from "@/mixin";

export default {
    name: 'CustomerDetails',
    components: {CustomField},
    mixins: [mixins],
    props: {
        customerID: {
            default: function () {
                return '';
            },
        },
        customerFirstname: {
            default: function () {
                return '';
            },
        },
        customerLastname: {
            default: function () {
                return '';
            },
        },
        customerEmail: {
            default: function () {
                return '';
            },
        },
        customerAddress: {
            default: function () {
                return '';
            },
        },
        customerPhone: {
            default: function () {
                return '';
            },
        },
        customerPersonalNotes: {
            default: function () {
                return '';
            },
        },
    },
    mounted() {
        this.loadCustomFields()
    },
    data: function () {
        return {
            elCustomerFirstname: this.customerFirstname,
            elCustomerLastname: this.customerLastname,
            elCustomerAddress: this.customerAddress,
            originalEmail: this.customerEmail,
            originalPhone: this.customerPhone,
            elCustomerEmail: this.shouldHideEmail ? '***@***' : this.customerEmail,
            elCustomerPhone: this.shouldHidePhone ? '*******' : this.customerPhone,
            elCustomerPersonalNotes: this.customerPersonalNotes,
            isValid: true,
            requiredFields: [],
            visibleExtraInfo: false,
            customFieldsList: [],
            elCustomFields: [],
            vueTelInputOptions: {
                'placeholder': this.getLabel('customerPhonePlaceholder')
            },
            isLoading: false,
            isSaved: false,
            isError: false,
            errorMessage: '',
        };
    },
    methods: {
        close() {
            this.$emit('close');
        },
        save() {
            this.isValid = this.validate()
            if (!this.isValid) {
                return;
            }
            var customer = {
                id: this.customerID ? this.customerID : 0,
                first_name: this.elCustomerFirstname,
                last_name: this.elCustomerLastname,
                email: this.originalEmail,
                phone: this.originalPhone,
                address: this.elCustomerAddress,
                note: this.elCustomerPersonalNotes,
                custom_fields: this.customFieldsList,
            }

            this.isLoading = true

            this.axios.put('customers/' + customer.id, customer).then(() => {
                this.isSaved = true
                setTimeout(() => {
                    this.isSaved = false
                }, 3000)
            }, (e) => {
                this.isError = true
                this.errorMessage = e.response.data.message
                setTimeout(() => {
                    this.isError = false
                    this.errorMessage = ''
                }, 3000)
            }).finally(() => {
                this.isLoading = false
            })
        },
        validate() {
            this.requiredFields = []
            if (!this.elCustomerFirstname.trim()) {
                this.requiredFields.push('customer_first_name')
            }
            return this.requiredFields.length === 0
        },
        updateCustomField(key, value) {
            let field = this.customFieldsList.find(i => i.key === key)
            if (field) {
                field.value = value
            } else {
                this.customFieldsList.push({key: key, value: value})
            }
        },
        getCustomFieldValue(key, default_value) {
            let field = this.customFieldsList.find(i => i.key === key)
            if (field) {
                return field.value
            }
            return default_value
        },
        loadCustomFields() {
            // const data = {user_profile: true, customer_id: this.customerID}
            this.axios.get('custom-fields/booking', {params: {user_profile: 1, customer_id: this.customerID}}).then((response) => {
                this.customFieldsList = response.data.items.filter(i => ['html', 'file'].indexOf(i.type) === -1)
            })
        },
    },
    emits: ['close', 'save'],
}
</script>

<style scoped>
.customer-details-info,
.customer-details-extra-info {
    border: solid 1px #ccc;
    padding: 20px;
    text-align: left;
    margin-bottom: 20px;
}
.customer-firstname,
.customer-lastname,
.customer-email,
.customer-address,
.customer-phone {
    border-bottom: solid 1px #ccc;
    margin-bottom: 20px;
    padding-bottom: 5px;
}
.spinner-border {
    vertical-align: middle;
}
.required {
    border: solid 1px #9F0404;
}
.customer-details-extra-info-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.customer-details-extra-info-header-btn {
    font-size: 22px;
    color: #0d6efd;
}
.save-button-wrapper,
.go-back-button-wrapper,
.save-button-result-wrapper {
    padding-top: 20px;
}
.save-button,
.go-back-button,
.save-button-result-wrapper .alert {
    width: 100%;
    max-width: 300px;
}
</style>