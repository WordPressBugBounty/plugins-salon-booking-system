<template>
  <div class="soap-photos">
    <label class="field-label">{{ getLabel('soapNotesPhotosTitle') || 'Visit photos' }}</label>
    <p class="soap-photos-hint">{{ getLabel('soapNotesPhotosHint') || 'Saved immediately — you do not need to tap Save notes' }}</p>

    <div class="soap-photos-grid" v-if="photos.length">
      <button
        class="soap-photo-thumb"
        type="button"
        v-for="photo in photos"
        :key="photo.attachment_id"
        @click="openViewer(photo)"
      >
        <img :src="photo.thumb_url || photo.url" alt="">
      </button>
    </div>
    <p class="soap-photos-empty" v-else>{{ getLabel('soapNotesPhotoEmpty') || 'No photos yet' }}</p>

    <div class="soap-photos-actions">
      <button class="soap-photo-action" type="button" :disabled="isBusy" @click="$refs.takePhoto.click()">
        <font-awesome-icon icon="fa-solid fa-camera" />
        {{ getLabel('soapNotesTakePhoto') || getLabel('takePhotoButtonLabel') || 'Take photo' }}
      </button>
      <button class="soap-photo-action" type="button" :disabled="isBusy" @click="$refs.choosePhoto.click()">
        <font-awesome-icon icon="fa-solid fa-images" />
        {{ getLabel('soapNotesChoosePhoto') || 'Choose from library' }}
      </button>
      <input
        type="file"
        accept="image/*"
        capture="environment"
        hidden
        ref="takePhoto"
        @change="onFile($event)"
      >
      <input
        type="file"
        accept="image/*"
        hidden
        ref="choosePhoto"
        @change="onFile($event)"
      >
    </div>

    <b-spinner small v-if="isBusy" class="soap-photos-spinner" />
    <p class="soap-photos-error" v-if="errorMessage">{{ errorMessage }}</p>

    <div class="soap-photo-viewer" v-if="viewer" @click.self="closeViewer">
      <button class="soap-photo-viewer-close" type="button" @click="closeViewer">
        <font-awesome-icon icon="fa-solid fa-xmark" />
      </button>
      <img :src="viewer.url" alt="">
      <input
        class="soap-photo-caption"
        type="text"
        :value="viewer.caption"
        :placeholder="getLabel('soapNotesPhotoCaption') || 'Caption'"
        @change="saveCaption($event.target.value)"
      >
      <button class="soap-photo-delete" type="button" @click="remove">
        <font-awesome-icon icon="fa-solid fa-trash" />
        {{ getLabel('soapNotesPhotoDelete') || 'Delete photo' }}
      </button>
    </div>
  </div>
</template>

<script>
import mixins from '@/mixin'
import { compressImage } from '@/utils/compressImage'

export default {
    name: 'SoapVisitPhotos',
    mixins: [mixins],
    props: {
        bookingId: { type: [Number, String], required: true },
        photos: { type: Array, default: () => [] },
    },
    data() {
        return {
            isBusy: false,
            errorMessage: '',
            viewer: null,
        }
    },
    methods: {
        emitPhotos(photos) {
            this.$emit('update:photos', photos)
            this.$emit('change', photos)
        },
        onFile(event) {
            const file = event.target.files && event.target.files[0]
            event.target.value = ''
            if (!file) {
                return
            }
            this.upload(file)
        },
        upload(file) {
            this.isBusy = true
            this.errorMessage = ''
            compressImage(file).then((ready) => {
                const formData = new FormData()
                formData.append('file', ready)
                return this.axios.post('soap-notes/bookings/' + this.bookingId + '/photos', formData, {
                    headers: { 'Content-Type': 'multipart/form-data' },
                })
            }).then((response) => {
                const photos = (response.data && response.data.photos) || this.photos.concat(response.data.photo || [])
                this.emitPhotos(photos)
            }).catch((error) => {
                this.errorMessage = (error.response && error.response.data && error.response.data.message)
                    || this.getLabel('soapNotesPhotoUploadError')
                    || 'Could not upload photo'
            }).finally(() => {
                this.isBusy = false
            })
        },
        openViewer(photo) {
            this.viewer = Object.assign({}, photo)
        },
        closeViewer() {
            this.viewer = null
        },
        saveCaption(caption) {
            if (!this.viewer) {
                return
            }
            const photoId = this.viewer.attachment_id
            this.axios.put('soap-notes/bookings/' + this.bookingId + '/photos/' + photoId, { caption })
                .then((response) => {
                    const photos = (response.data && response.data.photos) || this.photos.map((photo) => {
                        if (Number(photo.attachment_id) === Number(photoId)) {
                            return Object.assign({}, photo, { caption })
                        }
                        return photo
                    })
                    this.emitPhotos(photos)
                    if (this.viewer && Number(this.viewer.attachment_id) === Number(photoId)) {
                        this.viewer = Object.assign({}, this.viewer, { caption })
                    }
                })
        },
        remove() {
            if (!this.viewer) {
                return
            }
            const photoId = this.viewer.attachment_id
            this.isBusy = true
            this.axios.delete('soap-notes/bookings/' + this.bookingId + '/photos/' + photoId)
                .then((response) => {
                    const photos = (response.data && response.data.photos) || this.photos.filter((photo) => {
                        return Number(photo.attachment_id) !== Number(photoId)
                    })
                    this.emitPhotos(photos)
                    this.closeViewer()
                })
                .catch((error) => {
                    this.errorMessage = (error.response && error.response.data && error.response.data.message)
                        || this.getLabel('soapNotesPhotoUploadError')
                        || 'Could not upload photo'
                })
                .finally(() => {
                    this.isBusy = false
                })
        },
    },
    emits: ['update:photos', 'change'],
}
</script>

<style scoped>
.soap-photos-hint,
.soap-photos-empty {
  font-size: 12px;
  color: var(--color-text-muted, #94A3B8);
  margin: 0 0 10px;
}
.soap-photos-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
  margin-bottom: 12px;
}
.soap-photo-thumb {
  padding: 0;
  border: 0;
  border-radius: 8px;
  overflow: hidden;
  background: #F1F5F9;
  aspect-ratio: 1;
  cursor: pointer;
}
.soap-photo-thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}
.soap-photos-actions {
  display: flex;
  gap: 8px;
}
.soap-photo-action {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  min-height: 40px;
  border: 1.5px solid var(--color-border, #E2E8F0);
  border-radius: var(--radius-sm, 8px);
  background: #fff;
  color: var(--color-text-primary, #0F172A);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}
.soap-photo-action:disabled { opacity: 0.6; }
.soap-photos-spinner { margin-top: 8px; }
.soap-photos-error {
  color: #DC2626;
  font-size: 13px;
  margin: 8px 0 0;
}
.soap-photo-viewer {
  position: fixed;
  inset: 0;
  z-index: 40;
  background: rgba(15, 23, 42, 0.92);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 48px 16px 24px;
}
.soap-photo-viewer img {
  max-width: 100%;
  max-height: 70vh;
  object-fit: contain;
  border-radius: 8px;
}
.soap-photo-viewer-close {
  position: absolute;
  top: 12px;
  right: 12px;
  width: 40px;
  height: 40px;
  border: 0;
  border-radius: 50%;
  background: rgba(255,255,255,0.15);
  color: #fff;
}
.soap-photo-caption {
  width: 100%;
  max-width: 420px;
  margin-top: 12px;
  padding: 10px 12px;
  border-radius: 8px;
  border: 0;
}
.soap-photo-delete {
  margin-top: 10px;
  border: 0;
  background: #DC2626;
  color: #fff;
  border-radius: 999px;
  padding: 10px 16px;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 8px;
}
.field-label {
  font-size: 12px;
  font-weight: 600;
  color: var(--color-text-secondary, #64748B);
  margin-bottom: 5px;
  display: block;
}
</style>
