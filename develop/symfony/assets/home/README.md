# assets/home

Frontend modules for the Home and Video Details Vue pages.

## Entry points

- `mountHomeSpa.js` - reads data attributes from Twig and mounts Vue router views.
- `HomeTabsView.js` - orchestration for home tabs (state/actions wiring).
- `VideoDetailsView.js` - video details page logic.

## Home tabs layout

- `tabs/upload/`
  - `state.js` - upload tab state container.
  - `actions.js` - upload widget lifecycle (`Uppy`) mount/unmount.
  - `render.js` - upload pane render function.
- `tabs/videos/`
  - `state.js` - reactive state for videos list and pagination.
  - `actions.js` - API loading, navigation, and realtime updates for videos.
  - `render.js` - videos pane render function.
- `tabs/tasks/`
  - `state.js` - reactive state for tasks list, pagination, and action key.
  - `actions.js` - API loading/cancel actions and realtime updates for tasks.
  - `render.js` - tasks pane render function.

## Realtime modules

- `realtime/bindHomeRealtime.js` - subscribes to `app:task` and `app:video` window events.
- `realtime/appTaskMessage.js` - validates/parses task messages.
- `realtime/appVideoMessage.js` - validates/parses video messages.

## Shared helpers

- `shared.js` - common helpers (headers, template replacement, error helpers, parsers).
- `HomeTabsRender.js` - shell layout (tabs header + pane composition).
- `connectMercure.js` - EventSource setup and dispatch of app-level events.
- `legacyHomeWidgets.js` - Uppy setup used by upload tab.

## How to add a new tab

1. Create `tabs/<tab-name>/state.js`, `actions.js`, `render.js`.
2. Wire state/actions in `HomeTabsView.js`.
3. Add tab button and pane renderer in `HomeTabsRender.js`.
4. If tab needs realtime updates, extend `realtime/*` and bind in `bindHomeRealtime.js`.

