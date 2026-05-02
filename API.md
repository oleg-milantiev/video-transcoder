# API Reference — Video Transcoder

All API endpoints are under `/api/` and return JSON.
Binary downloads use a redirect to a time-limited storage URL (`/task/{id}/download`).

## Authentication

The API uses **Bearer tokens** (HMAC-SHA256, Access 1 h / Refresh 24 h).
Pass the token in every request:

```
Authorization: Bearer <accessToken>
```

Obtain tokens with `POST /api/auth/token` or `POST /api/auth/refresh`.
TUS file uploads additionally require an active browser session (cookie).
Please use upload via URL pipeline.
`POST /api/video/upload` requires `ROLE_API`.

---

## Auth — `/api/auth`

### `POST /api/auth/token`

Issues a new Bearer access token and refresh token in exchange for valid credentials.
Validates the JSON body for `email` and `password`, checks against the user provider
and password hasher, then returns a token pair valid for the configured TTL.

**Body** `application/json`
```json
{ "email": "user@example.com", "password": "secret" }
```

**200 OK**
```json
{
  "tokenType": "Bearer",
  "accessToken": "<jwt>",
  "refreshToken": "<jwt>",
  "expiresIn": 3600
}
```

**400** — missing / invalid JSON  
**401** — wrong credentials

---

### `POST /api/auth/refresh`

Exchanges a valid refresh token for a new access token and refresh token pair.
Parses and validates the `refreshToken` claim from the JSON body, reloads the user,
and issues a fresh token pair without requiring credentials again.

**Body** `application/json`
```json
{ "refreshToken": "<jwt>" }
```

**200 OK** — same shape as `/token`  
**400** — missing field  
**401** — invalid or expired refresh token

---

## Profile — `/api/profile`

### `GET /api/profile` 🔒

Returns the current user's profile data including tariff limits and storage usage.

**200 OK**
```json
{
  "id": "uuid",
  "email": "user@example.com",
  "tariff": {
    "title": "Free",
    "videoSize": 512,
    "videoDuration": 300,
    "maxWidth": 1920,
    "maxHeight": 1080,
    "storageGb": 5,
    "storageHour": 72,
    "delay": 0,
    "instance": 1
  },
  "storage": { "now": 123456789, "max": 5368709120 }
}
```

---

## Presets — `/api/preset`

### `GET /api/preset/` 🔒

Returns the list of transcoding presets available to the current user
based on their active tariff plan. Results are ordered by preset title ascending.

**200 OK**
```json
[
  {
    "id": "uuid",
    "title": "Standard Quality",
    "videoCodec": "h264",
    "audioCodec": "aac",
    "format": "mp4",
    "bitrate": {
      "144": 0.06,
      "240": 0.25,
      "360": 0.6,
      "480": 1.5,
      "720": 3,
      "1080": 5,
      "1440": 10,
      "2160": 20,
      "4320": 50
    }
  }
]
```

---

## Videos — `/api/video`

### `GET /api/video/` 🔒

Returns a paginated list of videos for the current user.
Pagination parameters (`page`, `limit`) are read from the request query string.
Each item includes title, poster URL, metadata, and status flags.
Order by `updatedAt` descending. Deleted last.

**Query** `page=1&limit=10`

**200 OK**
```json
{
  "items": [
    {
      "uuid": "uuid",
      "title": "My Video",
      "poster": "https://storage/.../poster.jpg",
      "meta": { "resolution": "1920x1080", "duration": "1:23", "_duration": 83.0 },
      "createdAt": "2026-01-01T00:00:00+00:00",
      "updatedAt": "2026-01-01T00:01:00+00:00",
      "deleted": false,
      "canBeDeleted": true
    }
  ],
  "total": 42,
  "page": 1,
  "limit": 10,
  "totalPages": 5
}
```

---

### `GET /api/video/{id}` 🔒

Returns the full details DTO for a single video including metadata, presets,
task list, and the user's paginated video list (for the left sidebar).
Access is enforced via `CAN_VIEW_DETAILS` voter.

**200 OK**
```json
{
  "video": {
    "uuid": "uuid",
    "title": "My Video",
    "poster": "https://...",
    "meta": { "resolution": "1920x1080", "duration": "1:23", "_duration": 83.0 },
    "createdAt": "...", "updatedAt": "...", "expiredAt": null,
    "deleted": false, "canBeDeleted": true
  },
  "presets": [
    { "id": "uuid", "title": "Standard Quality", "videoCodec": "libx264",
      "audioCodec": "aac", "format": "mp4", "bitrate": {} }
  ],
  "tasks": [
    { "id": "uuid", "status": "COMPLETED", "progress": 100, "height": 1080,
      "presetTitle": "Standard Quality", "createdAt": "...", "updatedAt": "..." }
  ],
  "videoList": { "items": [], "total": 42, "page": 1, "limit": 10, "totalPages": 5 }
}
```

**400** `INVALID_UUID` · **403** `ACCESS_DENIED` · **404** `VIDEO_NOT_FOUND`

---

### `POST /api/video/upload` 🔒 `ROLE_API`

Accepts a remote video URL for async download.
Creates the `Video` entity immediately (`loading=true`) so the client can start tracking it.

**Body** `application/json`
```json
{ "url": "https://example.com/source.mp4" }
```
**201 Created** — video record created, download in progress
```json
{
  "uuid": "xxxxxxxx-xxxx-4xxx-xxxx-xxxxxxxxxxxx",
  "title": "source",
  "createdAt": "2026-05-02T10:00:00+00:00",
  "updatedAt": "2026-05-02T10:00:00+00:00",
  "expiredAt": null,
  "expiredInterval": null,
  "deleted": false,
  "canBeDeleted": false,
  "meta": {},
  "poster": null,
  "loading": true
}

```
**400** `MISSING_URL`  **422** `INVALID_URL`, `INVALID_FORMAT`  **500** `INTERNAL_ERROR`
---

### `POST /api/video/{id}/transcode/{presetId}/{height}` 🔒

Creates a new (or restart exists) transcoding task for the specified video, preset, and output height.
Enforces tariff height limits and checks that the preset supports the requested height.
The task is persisted and dispatched asynchronously to the ffmpeg worker queue.

**200 OK**
```json
{ "taskId": "uuid", "status": "PENDING" }
```

**400** `INVALID_UUID` · **403** `ACCESS_DENIED`, `HEIGHT_EXCEEDS_TARIFF`  
**404** `VIDEO_NOT_FOUND`, `PRESET_NOT_FOUND`, `USER_NOT_FOUND`  
**422** `HEIGHT_NOT_IN_PRESET` · **500** `TASK_CREATION_FAILED`

---

### `PATCH /api/video/{id}` 🔒

Partially updates a video. Currently supports renaming (`title` field).
After a successful update the new title is broadcast via Mercure so all
open browser tabs update in real time.

**Body** `application/json`
```json
{ "title": "New Title" }
```

**200 OK** — updated `VideoItemDTO`  
**400** `INVALID_VIDEO_ID` · **403** `ACCESS_DENIED` · **404** `VIDEO_NOT_FOUND`

---

### `DELETE /api/video/{id}` 🔒

Soft-deletes a video and its associated files.
Blocked while any transcoding tasks are still running.
The deletion is recorded and real-time subscribers are notified via Mercure.

**204 No Content** — deleted  
**400** `INVALID_VIDEO_ID` · **403** `ACCESS_DENIED`  
**404** `VIDEO_NOT_FOUND` · **409** `VIDEO_HAS_TRANSCODING_TASKS`, `VIDEO_ALREADY_DELETED`

---

## Tasks — `/api/task`

### `GET /api/task/` 🔒

Returns a paginated list of transcoding tasks for the current user.
Pagination parameters (`page`, `limit`) are read from the request query string.

**Query** `page=1&limit=20`

**200 OK**
```json
{
  "items": [
    {
      "id": "3e5175ea-ae4b-42b7-a728-90495974cc9c",
      "videoId": "ec7c568e-b21f-4283-be3c-8b69dd088974",
      "videoTitle": "2025-08 Tortuga Swim HD",
      "presetId": "3ecc3746-530e-46c9-9755-573a552f3990",
      "presetVideoCodec": "av1",
      "presetAudioCodec": "opus",
      "presetFormat": "mp4",
      "presetTitle": "Ultra video Quality, High Efficiency Audio",
      "height": 144,
      "status": "CANCELLED",
      "progress": 2,
      "createdAt": "2026-04-29T13:44:53+00:00",
      "updatedAt": "2026-04-29T13:45:13+00:00",
      "deleted": false,
      "waitingTariffInstance": null,
      "waitingTariffDelay": null,
      "willStartAt": null
    }
  ],
  "total": 21,
  "page": 1,
  "limit": 10,
  "totalPages": 3
}
```

---

### `POST /api/task/{id}/cancel` 🔒

Cancels an active transcoding task owned by the current user.
Sets a Redis cancellation flag; the running ffmpeg worker will detect it
and abort processing on its next polling cycle.

**204 No Content** — cancellation accepted  
**400** `INVALID_TASK_ID` · **403** `ACCESS_DENIED`  
**404** `TASK_NOT_FOUND`, `VIDEO_NOT_FOUND` · **500** `INTERNAL_ERROR`

---

## Downloads — `/task`

### `GET /task/{id}/download` 🔒

Generates a signed redirect URL for downloading a completed transcode task output.
Resolves the task and video, verifies download access via the voter,
and redirects the browser to the time-limited S3/storage URL.

**302 Redirect** — to signed download URL  
**400** `INVALID_TASK_ID` · **403** `ACCESS_DENIED`  
**404** `TASK_NOT_FOUND`, `VIDEO_NOT_FOUND` · **500** `INTERNAL_ERROR`

---

## TUS Uploads — `/api/upload`

### `POST /api/upload` · `PATCH /api/upload/{token}` · etc. 🔒

Handles all TUS resumable upload protocol requests (HEAD, POST, PATCH, DELETE).
The route captures the optional TUS upload token in the URL so that continuation
requests reach the same server instance.

Used by the Uppy JS client on the frontend — not intended for direct API use.
On upload completion, a `VideoUploaded` command is dispatched automatically.

---

## Contact — `/api/contact`

### `POST /api/contact` 🔒

Submits a contact-us message from the authenticated user.
Validates that the message is non-empty and does not exceed 1000 characters,
then records it via the log service.

**Body** `application/json`
```json
{ "message": "Hello, I have a question..." }
```

**204 No Content** — message received  
**400** `VALIDATION_ERROR`

---

## Success Flow: Upload Source → Download Result

The following describes the complete happy path for uploading a video file
and downloading a transcoded output.

```
┌─────────────┐         ┌─────────────┐      ┌──────────────────┐     ┌────────┐
│   Browser   │         │  API Server │      │  ffmpeg Worker   │     │  S3    │
└──────┬──────┘         └──────┬──────┘      └────────┬─────────┘     └───┬────┘
       │                       │                       │                   │
  1.   │ POST /api/auth/token  │                       │                   │
       │──────────────────────►│                       │                   │
       │ {accessToken, refresh}│                       │                   │
       │◄──────────────────────│                       │                   │
       │                       │                       │                   │
  ── Option A: Browser TUS upload ─────────────────────────────────────────
       │                       │                       │                   │
  2a.  │ POST /api/upload      │ (TUS create)          │                   │
       │──────────────────────►│                       │                   │
       │ TUS token             │                       │                   │
       │◄──────────────────────│                       │                   │
       │                       │                       │                   │
  3a.  │ PATCH /api/upload/{t} │ (TUS chunks)          │                   │
       │──────────────────────►│                       │                   │
       │ 204 No Content        │                       │                   │
       │◄──────────────────────│                       │                   │
       │                       │ UploadComplete event  │                   │
       │                       │ → CreateVideo command │                   │
       │                       │   queued (Redis)      │                   │
       │                       │                       │                   │
  ── Option B: Remote URL download ────────────────────────────────────────
       │                       │                       │                   │
  2b.  │ POST /api/video/upload│                       │                   │
       │ { "url": "https://…" }│                       │                   │
       │──────────────────────►│                       │                   │
       │ 201 { uuid, loading } │                       │                   │
       │◄──────────────────────│                       │                   │
       │                       │ CreateVideo command   │                   │
       │                       │   queued (Redis)      │                   │
       │                       │                       │                   │
  3b.  │ (video appears via Mercure app:video event)   │                   │
       │──────────────────────►│                       │                   │
       │ 204 (not ready yet)   │                       │                   │
       │◄──────────────────────│                       │                   │
       │                       │                       │                   │
  ── Common path ──────────────────────────────────────────────────────────
       │                       │                       │                   │
  4.   │                       │ Worker picks up       │                   │
       │                       │ CreateVideo command   │                   │
       │                       │──────────────────────►│                   │
       │                       │                       │ Moves file to S3  │
       │                       │                       │──────────────────►│
       │                       │                       │ Saves Video entity│
       │                       │                       │ Mercure: "uploaded"
       │                       │                       │ Dispatches        │
       │                       │                       │ ExtractMetadata   │
       │                       │                       │                   │
  5.   │ Mercure SSE: app:video (action=uploaded)      │                   │
       │◄──────────────────────│                       │                   │
       │ Video appears in list │                       │                   │
       │ (or poll session →    │                       │                   │
       │  GET /api/video/{id}) │                       │                   │
       │                       │                       │                   │
  6.   │                       │ ExtractMetadata done  │                   │
       │                       │ Mercure: "meta" + poster                  │
       │ Mercure SSE: app:video (action=meta)          │                   │
       │◄──────────────────────│                       │                   │
       │ Poster/meta updated   │                       │                   │
       │                       │                       │                   │
  7.   │ GET /api/preset/      │                       │                   │
       │──────────────────────►│                       │                   │
       │ [{id, title, format…}]│                       │                   │
       │◄──────────────────────│                       │                   │
       │                       │                       │                   │
  8.   │ POST /api/video/{id}/transcode/{presetId}/{h} │                   │
       │──────────────────────►│                       │                   │
       │ { taskId, status }    │                       │                   │
       │◄──────────────────────│                       │                   │
       │                       │ TranscodeVideo command│                   │
       │                       │   queued (Redis)      │                   │
       │                       │                       │                   │
  9.   │                       │ Worker picks up       │                   │
       │                       │ TranscodeVideo command│                   │
       │                       │──────────────────────►│                   │
       │                       │                       │ ffmpeg runs       │
       │                       │                       │ Progress updates  │
       │                       │                       │ via Mercure       │
       │                       │                       │                   │
  10.  │ Mercure SSE: app:task │ (action=progress)     │                   │
       │◄──────────────────────│                       │                   │
       │ Progress bar updates  │                       │                   │
       │                       │                       │                   │
  11.  │                       │                       │ ffmpeg done       │
       │                       │                       │ Output uploaded   │
       │                       │                       │──────────────────►│
       │                       │                       │ Task → COMPLETED  │
       │                       │                       │ Mercure: "task"   │
       │                       │                       │                   │
  12.  │ Mercure SSE: app:task │ (action=completed)    │                   │
       │◄──────────────────────│                       │                   │
       │ Download button shown │                       │                   │
       │                       │                       │                   │
  13.  │ GET /task/{taskId}/download                   │                   │
       │──────────────────────►│                       │                   │
       │ 302 → signed S3 URL   │                       │                   │
       │◄──────────────────────│                       │                   │
       │                       │                       │                   │
  14.  │ GET <signed S3 URL>   │                       │                   ►│
       │ File downloaded ✓     │                       │                   │
```

### Step summary

| # | Action | Endpoint |
|---|--------|----------|
| 1 | Obtain Bearer token | `POST /api/auth/token` |
| 2a | Start TUS resumable upload | `POST /api/upload` |
| 3a | Upload file in chunks | `PATCH /api/upload/{token}` |
| 2b | Submit remote URL for download | `POST /api/video/upload` |
| 3b | Receive video via Mercure realtime event | `app:video` SSE |
| 4 | Worker processes file asynchronously | *(internal)* |
| 5 | Receive video-ready notification | Mercure SSE `app:video` action=`uploaded` |
| 6 | Receive poster & metadata | Mercure SSE `app:video` action=`meta` |
| 7 | List available presets | `GET /api/preset/` |
| 8 | Request transcoding | `POST /api/video/{id}/transcode/{presetId}/{height}` |
| 9–10 | Worker transcodes; progress updates | Mercure SSE `app:task` action=`progress` |
| 11–12 | Transcoding complete | Mercure SSE `app:task` action=`completed` |
| 13 | Obtain download redirect | `GET /task/{taskId}/download` |
| 14 | Download the transcoded file | *(signed storage URL, direct)* |

