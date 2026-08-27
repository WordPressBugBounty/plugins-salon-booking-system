/**
 * Scale an image file so the longest side fits maxSize. Never crop.
 * Returns a JPEG File, or the original file if compression is not possible.
 *
 * @param {File} file
 * @param {number} maxSize
 * @param {number} quality
 * @returns {Promise<File>}
 */
export function compressImage(file, maxSize = 1600, quality = 0.82) {
    if (!file || !file.type || file.type.indexOf('image/') !== 0) {
        return Promise.resolve(file)
    }

    return new Promise((resolve) => {
        const img = new Image()
        const url = URL.createObjectURL(file)
        img.onload = () => {
            let { width, height } = img
            if (width > maxSize || height > maxSize) {
                const ratio = Math.min(maxSize / width, maxSize / height)
                width = Math.round(width * ratio)
                height = Math.round(height * ratio)
            }
            const canvas = document.createElement('canvas')
            canvas.width = width
            canvas.height = height
            const ctx = canvas.getContext('2d')
            ctx.drawImage(img, 0, 0, width, height)
            canvas.toBlob((blob) => {
                URL.revokeObjectURL(url)
                if (!blob) {
                    resolve(file)
                    return
                }
                const name = String(file.name || 'photo.jpg').replace(/\.[^.]+$/, '.jpg')
                resolve(new File([blob], name, { type: 'image/jpeg' }))
            }, 'image/jpeg', quality)
        }
        img.onerror = () => {
            URL.revokeObjectURL(url)
            resolve(file)
        }
        img.src = url
    })
}
