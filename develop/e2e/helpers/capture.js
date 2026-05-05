/**
 * Attach collected Mercure SSE messages to the test report.
 * Safe to call in a finally block — all errors are swallowed.
 */
async function attachSseMessages(page, testInfo) {
  try {
    const sseMessages = await page.evaluate(() => (window.__mercure_messages || []));
    await testInfo.attach('mercure-sse.json', {
      body: Buffer.from(JSON.stringify(sseMessages, null, 2), 'utf-8'),
      contentType: 'application/json',
    });
  } catch (e) {
    // ignore
  }
}

module.exports = { attachSseMessages };
