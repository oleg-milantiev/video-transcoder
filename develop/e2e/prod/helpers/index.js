const shared = require('../../helpers');
const { buildRunContext, formatDateSuffix, randomAlphaNumeric } = require('./runContext');
const prodAdmin = require('./admin');

async function loginAsCredentials(page, email, password) {
  await shared.openHome(page);
  await shared.openSignIn(page);
  await shared.fillSignInCredentials(page, email, password);
  await shared.submitSignIn(page);
}

module.exports = {
  ...shared,
  ...prodAdmin,
  buildRunContext,
  formatDateSuffix,
  randomAlphaNumeric,
  loginAsCredentials,
};

