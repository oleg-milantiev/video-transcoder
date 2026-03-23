const fs = require('fs');

function attachConsoleCapture(page, testInfo, opts = {}) {
  const logs = [];
  const maxBodyChars = opts.maxBodyChars || 2000;
  const mercureUrlFragment = opts.mercureUrlFragment || '/.well-known/mercure';

  function pushLine(line) {
    const ts = new Date().toISOString();
    logs.push(`${ts} ${line}`);
  }

  page.on('console', msg => {
    try {
      const args = msg.args ? msg.args().map(a => a.toString()).join(' ') : msg.text();
      pushLine(`[console][${msg.type()}] ${args || msg.text()}`);
    } catch (e) {
      pushLine(`[console][${msg.type()}] ${msg.text()}`);
    }
  });

  page.on('pageerror', err => {
    pushLine(`[pageerror] ${err.toString()}`);
  });

  page.on('requestfailed', req => {
    const failure = req.failure ? (req.failure() && req.failure().errorText) : '';
    pushLine(`[requestfailed] ${req.method()} ${req.url()} ${failure || ''}`);
  });

  page.on('response', async res => {
    try {
      const url = res.url();
      if (url.includes(mercureUrlFragment) || url.includes('/mercure')) {
        pushLine(`[response] ${res.status()} ${url}`);
        try {
          const text = await res.text();
          pushLine(`[response-body] ${text ? text.slice(0, maxBodyChars) : '<no-body>'}`);
        } catch (e) {
          pushLine(`[response-body] <failed to read body: ${String(e)}>`);
        }
      }
    } catch (e) {
      pushLine(`[response-handler-error] ${String(e)}`);
    }
  });

  async function injectEventSourceProbe() {
    await page.evaluate(() => {
      if (window.__mercure_probe_installed) return;
      const OriginalES = window.EventSource;
      try {
        window.__mercure_probe_installed = true;
        window.__mercure_messages = window.__mercure_messages || [];
        window.EventSource = function (url, opts) {
          const es = new OriginalES(url, opts);
          es.addEventListener('message', e => {
            try {
              let data = e.data;
              try { data = JSON.parse(e.data); } catch(e) {}
              window.__mercure_messages.push({ url, data, ts: (new Date()).toISOString() });
              console.log('[mercure-probe] message', url, data);
            } catch (err) {
              console.log('[mercure-probe] message parse error', err);
            }
          });
          es.addEventListener('open', () => console.log('[mercure-probe] open', url));
          es.addEventListener('error', () => console.log('[mercure-probe] error', url));
          return es;
        };
        Object.setPrototypeOf(window.EventSource, OriginalES);
        window.EventSource.prototype = OriginalES.prototype;
      } catch (e) {
        console.warn('failed to install mercure probe', e);
      }
    });
  }

  async function start() {
    await injectEventSourceProbe();
  }

  async function flushAndAttach() {
    try {
      const out = logs.join('\n') + '\n';
      await testInfo.attach('browser-console.log', {
        body: Buffer.from(out, 'utf-8'),
        contentType: 'text/plain'
      });
      try {
        const path = testInfo.outputPath('browser-console.log');
        fs.writeFileSync(path, out, 'utf-8');
      } catch (e) {
        // ignore
      }
    } catch (e) {
      // ignore attach errors
    }
  }

  return { start, flushAndAttach };
}

module.exports = { attachConsoleCapture };

