<template>
    <b-row>
        <b-col sm="12">
            <div class="images">
                <b-row>
                    <b-col sm="12" class="list">
                        <b-spinner variant="primary" v-if="isLoading"></b-spinner>
                         <carousel ref="carousel" :modelValue="photoIndex" :wrapAround="true" :transition="0">
                            <slide v-for="(photo, index) in photos" :key="index">
                              <span class="photo-wrapper">
                                <img :src="photo.url" class="photo"/>
                                <span class="photo-icon-wrapper fa-trash-wrapper" @click.prevent.stop="remove(photo)" v-if="photo.attachment_id">
                                    <font-awesome-icon icon="fa-solid fa-trash" />
                                </span>
                                <span class="photo-icon-wrapper fa-circle-check-wrapper" @click.prevent.stop="setAsDefault(photo)" v-if="photo.attachment_id">
                                    <font-awesome-icon icon="fa-regular fa-circle-check" :class="{default: photo.default}" />
                                </span>
                                <span class="date" v-if="photo.attachment_id">{{ dateFormat(photo.created * 1000, 'DD.MM.YYYY') }}</span>
                              </span>
                            </slide>
                            <template #addons="{slidesCount}">
                              <navigation v-if="slidesCount > 1"/>
                              <pagination v-if="slidesCount > 1"/>
                            </template>
                        </carousel>
                    </b-col>
                    <b-col sm="12" class="buttons">
                        <b-row>
                            <b-col sm="6" class="take-photo" @click="this.$refs.takePhoto.click()">
                                <b-button variant="primary" size="lg">
                                    <font-awesome-icon icon="fa-solid fa-camera" />
                                </b-button>
                                <div class="take-photo-label">
                                    {{ this.getLabel('takePhotoButtonLabel') }}
                                </div>
                                <input type="file" accept="image/*" capture="camera" v-show="false" ref="takePhoto" @change="uploadTakePhoto"/>
                            </b-col>
                            <b-col sm="6" class="select-photo">
                                <b-button variant="primary" size="lg" @click="this.$refs.downloadImages.click()">
                                    <font-awesome-icon icon="fa-solid fa-cloud-arrow-up" />
                                </b-button>
                                <div class="select-photo-label">
                                    {{ this.getLabel('selectPhotoButtonLabel') }}
                                </div>
                                <input type="file" accept="image/*" v-show="false" ref="downloadImages" @change="uploadFromPhone"/>
                            </b-col>
                        </b-row>
                    </b-col>
                </b-row>
                <b-row>
                    <b-col sm="12" class="back">
                        <b-button variant="outline-primary" @click="close" size="lg">
                            {{ this.getLabel('backImagesButtonLabel') }}
                        </b-button>
                    </b-col>
                </b-row>
            </div>
        </b-col>
    </b-row>
</template>

<script>
    export default {
        name: 'ImagesList',
        props: {
            customer: {
                default: function () {
                    return {};
                },
            },
        },
        data () {

            let index = 0;
            this.customer.photos.forEach((photo, i) => {
                if (+photo.default) {
                    index = i
                }
            })

            return {
                baseUrl: process.env.BASE_URL,
                customerData: this.customer,
                photoIndex: index,
                isLoading: false,
            }
        },
        computed: {
            photos() {
                return this.customerData.photos.length ? this.customerData.photos : [{url: this.baseUrl + 'img/placeholder-image.png'}]
            },
            id() {
                return this.customerData.id
            },
        },
        methods: {
            close() {
                this.$emit('close', this.customerData)
            },
            uploadTakePhoto() {
                let file = this.$refs.takePhoto.files[0]
                this.upload(file)
                this.$refs.takePhoto.value = ''
            },
            uploadFromPhone() {
                let file = this.$refs.downloadImages.files[0]
                this.upload(file)
                this.$refs.downloadImages.value = ''
            },
            upload(file, name) {
                let formData = new FormData();
                formData.append('file', file, name);
                this.isLoading = true
                this.axios.post('customers/' + this.id + '/photos', formData, {
                    headers: {'Content-Type': 'multipart/form-data'}
                }).then((response) => {
                    this.customerData = response.data.items[0]
                }).finally(() => {
                    this.isLoading = false
                })
            },
            remove(file) {
                this.isLoading = true
                this.axios
                    .delete('customers/' + this.id + '/photos/' + file.attachment_id)
                    .then((response) => {
                        this.customerData = response.data.items[0]
                    }).finally(() => {
                        this.isLoading = false
                    })
            },
            setAsDefault(file) {
                this.isLoading = true
                this.axios
                    .put('customers/' + this.id + '/photos/' + file.attachment_id, {photo: Object.assign({}, file, {default: 1})})
                    .then((response) => {
                        this.customerData = response.data.items[0]
                        this.$refs.carousel.slideTo(0)
                    }).finally(() => {
                        this.isLoading = false
                    })
            },
        },
        emits: ['close']
    }
</script>

<style scoped>
    .take-photo,
    .select-photo {
        text-align: center;
        text-transform: uppercase;
    }

    .btn {
        width: 100%;
    }

    .back {
        margin-top: 50px;
    }

    .list {
        margin-bottom: 20px;
        position: relative;
    }

    .photo {
        max-width: 500px;
        width: 100%;
    }

    .take-photo .btn,
    .select-photo .btn {
        background-color: #04409F;
        border-color: #04409F;
    }

    .back .btn {
        border-color: #04409F;
        color: #04409F;
    }

    .back .btn:hover {
        background-color: #04409F;
        border-color: #04409F;
    }

    .photo-wrapper {
        position: relative;
        border: solid 1px #eee;
    }

    .fa-trash-wrapper {
        bottom: 5px;
        right: 5px;
    }

    .fa-trash {
        color: #888;
    }

    .fa-circle-check-wrapper {
        left: 5px;
        bottom: 5px;
        color: #7f8ca2;
    }

    .fa-circle-check.default {
        color: #04409F;
    }

    .photo-icon-wrapper {
        position: absolute;
        background-color: #fff;
        cursor: pointer;
        display: flex;
        padding: 8px 10px;
        border-radius: 20px;
    }

    .take-photo-label,
    .select-photo-label {
        margin-top: 10px;
    }

    .date {
        position: absolute;
        display: block;
        bottom: 10px;
        left: 50px;
        color: #fff;
        padding: 0 10px;
        font-size: 14px;
        border-radius: 20px;
    }

    .spinner-border {
        position: absolute;
        z-index: 1;
        top: 50%;
    }

    @media (min-width: 250px) {
        .col-sm-6 {
            flex: 0 0 auto;
            width: 50%;
        }
    }
</style>
