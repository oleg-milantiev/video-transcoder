# EVENTS.md

## Purpose

This file documents application events published to `messenger.bus.event`.

Convention:
- base name matches the generation place/action (`CreateVideo`, `ExtractVideoMetadata`, etc.)
- each action has `Start`, `Success`, `Fail`

## Event Catalog

### CreateVideo
- `App\Application\Event\CreateVideoStart`
  - fields: `userId`, `filename`
- `App\Application\Event\CreateVideoSuccess`
  - fields: `videoId`, `userId`
- `App\Application\Event\CreateVideoFail`
  - fields: `error`, `userId`, `filename`

### ExtractVideoMetadata
- `App\Application\Event\ExtractVideoMetadataStart`
  - fields: `videoId`
- `App\Application\Event\ExtractVideoMetadataSuccess`
  - fields: `videoId`
- `App\Application\Event\ExtractVideoMetadataFail`
  - fields: `error`, `videoId`

### CreateVideoPreview
- `App\Application\Event\CreateVideoPreviewStart`
  - fields: `videoId`
- `App\Application\Event\CreateVideoPreviewSuccess`
  - fields: `videoId`
- `App\Application\Event\CreateVideoPreviewFail`
  - fields: `error`, `videoId`

### StartTaskScheduler
- `App\Application\Event\StartTaskSchedulerStart`
  - fields: none
- `App\Application\Event\StartTaskSchedulerSuccess`
  - fields: `scheduledCount`
- `App\Application\Event\StartTaskSchedulerFail`
  - fields: `error`

### TranscodeVideo
- `App\Application\Event\TranscodeVideoStart`
  - fields: `taskId`, `userId`, `videoId`
- `App\Application\Event\TranscodeVideoSuccess`
  - fields: `taskId`, `videoId`
- `App\Application\Event\TranscodeVideoFail`
  - fields: `error`, `taskId?`, `videoId?`

### StartTranscode
- `App\Application\Event\StartTranscodeStart`
  - fields: `videoId`, `presetId`, `userId`
- `App\Application\Event\StartTranscodeSuccess`
  - fields: `taskId`, `videoId`, `presetId`, `userId`
- `App\Application\Event\StartTranscodeFail`
  - fields: `error`, `videoId`, `presetId`, `userId`

### DeleteVideo
- `App\Application\Event\DeleteVideoStart`
  - fields: `videoId`, `requestedByUserId`
- `App\Application\Event\DeleteVideoSuccess`
  - fields: `videoId`, `requestedByUserId`, `deletedTaskCount`
- `App\Application\Event\DeleteVideoFail`
  - fields: `error`, `videoId?`, `requestedByUserId?`
