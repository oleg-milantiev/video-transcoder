export const ROUTE_VIDEO_DETAILS    = '/video/__UUID__';
export const ROUTE_REFRESH_TOKEN    = '/api/auth/refresh';
export const ROUTE_UPLOAD           = '/api/upload';
export const ROUTE_VIDEO_LIST       = '/api/video/';
export const ROUTE_VIDEO_DETAILS_API = '/api/video/__UUID__';
export const ROUTE_VIDEO_TRANSCODE  = '/api/video/__UUID__/transcode/__PRESET_ID__/__HEIGHT__';
export const ROUTE_VIDEO_DELETE     = '/api/video/__UUID__';
export const ROUTE_VIDEO_PATCH      = '/api/video/__UUID__';
export const ROUTE_TASK_LIST        = '/api/task/';
export const ROUTE_TASK_CANCEL      = '/api/task/__TASK_ID__/cancel';
export const ROUTE_TASK_DOWNLOAD    = '/task/__TASK_ID__/download';
export const ROUTE_PROFILE          = '/api/profile';
export const ROUTE_CONTACT          = '/api/contact';
export const ROUTE_PAYMENT_PAYPAL_CHECKOUT = '/payment/paypal/checkout';

/**
 * Build a video-details page URL for a given UUID.
 * @param {string} uuid
 * @returns {string}
 */
export function videoDetailsPath(uuid) {
    return ROUTE_VIDEO_DETAILS.replace('__UUID__', uuid);
}

/**
 * Build an API URL for video details.
 * @param {string} uuid
 * @returns {string}
 */
export function apiVideoDetailsUrl(uuid) {
    return ROUTE_VIDEO_DETAILS_API.replace('__UUID__', uuid);
}

/**
 * Build an API URL for video patch.
 * @param {string} uuid
 * @returns {string}
 */
export function apiVideoPatchUrl(uuid) {
    return ROUTE_VIDEO_PATCH.replace('__UUID__', uuid);
}

/**
 * Build an API URL for video delete.
 * @param {string} uuid
 * @returns {string}
 */
export function apiVideoDeleteUrl(uuid) {
    return ROUTE_VIDEO_DELETE.replace('__UUID__', uuid);
}

/**
 * Build an API URL for starting a transcode.
 * @param {string} videoId
 * @param {string} presetId
 * @param {number|string} height
 * @returns {string}
 */
export function apiVideoTranscodeUrl(videoId, presetId, height) {
    return ROUTE_VIDEO_TRANSCODE
        .replace('__UUID__', videoId)
        .replace('__PRESET_ID__', presetId)
        .replace('__HEIGHT__', String(height));
}

/**
 * Build an API URL for cancelling a task.
 * @param {string|number} taskId
 * @returns {string}
 */
export function apiTaskCancelUrl(taskId) {
    return ROUTE_TASK_CANCEL.replace('__TASK_ID__', String(taskId));
}

/**
 * Build a download URL for a task.
 * @param {string|number} taskId
 * @returns {string}
 */
export function taskDownloadUrl(taskId) {
    return ROUTE_TASK_DOWNLOAD.replace('__TASK_ID__', String(taskId));
}
