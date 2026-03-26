const { UI_TIMEOUT, NAV_TIMEOUT, UPLOAD_TIMEOUT } = require('./constants');
const { shot } = require('./screenshot');
const auth = require('./auth');
const mainApp = require('./mainApp');
const upload = require('./upload');
const video = require('./video');
const admin = require('./admin');
const dialogs = require('./dialogs');
const download = require('./download');

module.exports = {
  UI_TIMEOUT,
  NAV_TIMEOUT,
  UPLOAD_TIMEOUT,
  shot,
  ...auth,
  ...mainApp,
  ...upload,
  ...video,
  ...admin,
  ...dialogs,
  ...download,
};

