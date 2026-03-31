/**
 * Formats a byte count into a human-readable MB / GB string.
 * @param {number} bytes
 * @returns {string}
 */
// todo use helper
export function formatBytes(bytes) {
    const GB = 1024 * 1024 * 1024;
    const MB = 1024 * 1024;
    if (bytes >= GB) {
        const val = bytes / GB;
        // Keep one decimal unless it is a round number
        return (Number.isInteger(val) ? val.toString() : val.toFixed(1)) + ' GB';
    }
    return Math.round(bytes / MB) + ' MB';
}

/**
 * Builds a single human-readable upload requirements string from the tariff object.
 *
 * @param {{ videoSize: number, width: number, height: number, storage: { now: number, max: number } }|null|undefined} tariff
 * @returns {string|null}
 */
export function buildUploadHint(tariff) {
    if (!tariff || !tariff.storage) {
        return null;
    }

    const storageNow = tariff.storage.now;
    const storageMax = tariff.storage.max;

    const storagePercent = storageMax > 0
        ? Math.round((storageNow / storageMax) * 100)
        : 0;

    const storageUsedFormatted = formatBytes(storageNow);
    const storageMaxFormatted = formatBytes(storageMax);

    const MB = 1024 * 1024;
    const remainingBytes = storageMax - storageNow;
    const remainingMB = remainingBytes / MB;
    const videoSizeMB = tariff.videoSize;

    let effectiveVideoSize = videoSizeMB;
    let fileSizeNote = '';

    if (remainingMB < videoSizeMB) {
        effectiveVideoSize = Math.max(0, Math.floor(remainingMB));
        fileSizeNote = ' (as storage is running low)';
    }

    return (
        `Storage: ${storagePercent}% used (${storageUsedFormatted} of ${storageMaxFormatted}). ` +
        `Max resolution: ${tariff.width}\u00d7${tariff.height}. ` +
        `Max file size: ${effectiveVideoSize} MB${fileSizeNote}.`
    );
}
