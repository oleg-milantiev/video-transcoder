const { UI_TIMEOUT } = require('./constants');

async function shot(page, testInfo, name) {
  await page.screenshot({ path: testInfo.outputPath(name), fullPage: true, timeout: UI_TIMEOUT });
  console.log(name);
}

module.exports = { shot };

