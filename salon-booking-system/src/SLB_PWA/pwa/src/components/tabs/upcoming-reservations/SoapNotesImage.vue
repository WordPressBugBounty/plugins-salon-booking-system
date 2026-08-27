<template>
  <div class="soap-image">
    <label class="field-label">{{ field.label }}</label>
    <div
      class="soap-image-stage"
      ref="stage"
      @pointermove="onPointerMove"
      @pointerup="onPointerUp"
      @pointercancel="onPointerUp"
    >
      <img
        v-if="field.image_url"
        :src="field.image_url"
        :alt="field.label"
        class="soap-image-img"
        draggable="false"
      />
      <div v-else class="soap-image-missing">No image</div>
      <div
        v-for="(pin, pinIndex) in field.pins"
        :key="pinIndex"
        class="soap-pin"
        :class="{
          'soap-pin--active': activeIndex === pinIndex,
          'soap-pin--right': pin['align-x'] === 'right',
          'soap-pin--bottom': pin['align-y'] === 'bottom',
        }"
        :style="{ top: pin.top + '%', left: pin.left + '%' }"
      >
        <button
          type="button"
          class="soap-pin-dot"
          @pointerdown.stop="onPointerDown($event, pinIndex)"
          @click.stop="togglePin(pinIndex)"
        />
        <div class="soap-pin-bubble" v-if="activeIndex === pinIndex" @pointerdown.stop>
          <textarea
            class="soap-pin-text"
            :value="pin.text"
            :placeholder="placeholder"
            rows="2"
            @input="onPinText($event, pinIndex)"
          />
          <button type="button" class="soap-pin-delete" @click.stop="removePin(pinIndex)">
            <font-awesome-icon icon="fa-solid fa-trash" />
          </button>
        </div>
      </div>
    </div>
    <div class="soap-image-actions">
      <button type="button" class="soap-img-btn soap-img-btn--ghost" @click="clearPins">
        {{ clearLabel }}
      </button>
      <button type="button" class="soap-img-btn" @click="addPin">
        {{ addLabel }}
      </button>
    </div>
  </div>
</template>

<script>
export default {
    name: 'SoapNotesImage',
    props: {
        field: { type: Object, required: true },
        addLabel: { type: String, default: 'Add note' },
        clearLabel: { type: String, default: 'Clear all notes' },
        newPinLabel: { type: String, default: 'New note' },
        placeholder: { type: String, default: 'Note' },
    },
    data() {
        return {
            activeIndex: null,
            dragIndex: null,
            dragMoved: false,
        }
    },
    methods: {
        emitUpdate() {
            this.$emit('update', this.field.pins)
        },
        addPin() {
            const pins = this.field.pins || []
            pins.push({
                top: 50,
                left: 50,
                text: this.newPinLabel,
                'align-x': 'left',
                'align-y': 'top',
            })
            this.field.pins = pins
            this.activeIndex = pins.length - 1
            this.emitUpdate()
        },
        clearPins() {
            this.field.pins = []
            this.activeIndex = null
            this.emitUpdate()
        },
        removePin(index) {
            this.field.pins.splice(index, 1)
            this.activeIndex = null
            this.emitUpdate()
        },
        togglePin(index) {
            if (this.dragMoved) {
                return
            }
            this.activeIndex = this.activeIndex === index ? null : index
        },
        onPinText(event, index) {
            this.field.pins[index].text = event.target.value
            this.emitUpdate()
        },
        onPointerDown(event, index) {
            this.dragIndex = index
            this.dragMoved = false
            event.currentTarget.setPointerCapture(event.pointerId)
        },
        onPointerMove(event) {
            if (this.dragIndex === null) {
                return
            }
            const stage = this.$refs.stage
            if (!stage) {
                return
            }
            const rect = stage.getBoundingClientRect()
            if (rect.width < 1 || rect.height < 1) {
                return
            }
            let left = ((event.clientX - rect.left) / rect.width) * 100
            let top = ((event.clientY - rect.top) / rect.height) * 100
            left = Math.max(0, Math.min(100, left))
            top = Math.max(0, Math.min(100, top))
            const pin = this.field.pins[this.dragIndex]
            if (Math.abs(pin.left - left) > 0.4 || Math.abs(pin.top - top) > 0.4) {
                this.dragMoved = true
            }
            pin.left = Math.round(left * 100) / 100
            pin.top = Math.round(top * 100) / 100
            pin['align-x'] = left > 72 ? 'right' : 'left'
            pin['align-y'] = top > 72 ? 'bottom' : 'top'
        },
        onPointerUp() {
            if (this.dragIndex !== null && this.dragMoved) {
                this.emitUpdate()
            }
            this.dragIndex = null
        },
    },
    emits: ['update'],
}
</script>

<style scoped>
.soap-image { margin-bottom: 4px; }
.field-label {
  font-size: 12px;
  font-weight: 600;
  color: var(--color-text-secondary, #64748B);
  margin-bottom: 8px;
  display: block;
}
.soap-image-stage {
  position: relative;
  border: 1.5px solid var(--color-border, #E2E8F0);
  border-radius: var(--radius-sm, 8px);
  overflow: hidden;
  background: var(--color-background, #F4F6FA);
  touch-action: none;
  line-height: 0;
}
.soap-image-img {
  width: 100%;
  height: auto;
  display: block;
  user-select: none;
  -webkit-user-drag: none;
}
.soap-image-missing {
  padding: 48px 16px;
  text-align: center;
  font-size: 13px;
  color: var(--color-text-muted, #94A3B8);
  line-height: 1.4;
}
.soap-pin {
  position: absolute;
  width: 28px;
  height: 28px;
  transform: translate(-50%, -50%);
  z-index: 1;
}
.soap-pin--active { z-index: 3; }
.soap-pin-dot {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  border: 2px solid #fff;
  background: var(--color-primary, #2563EB);
  box-shadow: 0 1px 6px rgba(15, 23, 42, 0.35);
  padding: 0;
  cursor: grab;
}
.soap-pin-bubble {
  position: absolute;
  top: 34px;
  left: 0;
  width: 180px;
  background: #fff;
  border: 1px solid var(--color-border, #E2E8F0);
  border-radius: 8px;
  padding: 8px;
  box-shadow: 0 8px 24px rgba(15, 23, 42, 0.12);
  line-height: 1.3;
}
.soap-pin--right .soap-pin-bubble { left: auto; right: 0; }
.soap-pin--bottom .soap-pin-bubble { top: auto; bottom: 34px; }
.soap-pin-text {
  width: 100%;
  border: 1px solid var(--color-border, #E2E8F0);
  border-radius: 6px;
  font-size: 13px;
  padding: 6px 8px;
  resize: vertical;
  min-height: 52px;
  color: var(--color-text-primary, #0F172A);
}
.soap-pin-delete {
  margin-top: 6px;
  border: none;
  background: #FEE2E2;
  color: #DC2626;
  border-radius: 6px;
  width: 100%;
  padding: 6px;
  font-size: 12px;
  cursor: pointer;
}
.soap-image-actions {
  display: flex;
  gap: 8px;
  margin-top: 10px;
}
.soap-img-btn {
  flex: 1;
  border: none;
  border-radius: var(--radius-pill, 999px);
  background: var(--color-primary, #2563EB);
  color: #fff;
  font-size: 13px;
  font-weight: 600;
  padding: 10px 12px;
  cursor: pointer;
}
.soap-img-btn--ghost {
  background: var(--color-background, #F4F6FA);
  color: var(--color-text-secondary, #64748B);
}
</style>
