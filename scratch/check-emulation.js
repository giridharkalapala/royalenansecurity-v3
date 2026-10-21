const { spawn } = require('child_process');
const http = require('http');

const edge = spawn('C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe', [
  '--headless=new',
  '--remote-debugging-port=9225',
  'http://127.0.0.1:3000/index.html'
]);

setTimeout(async () => {
  try {
    const pages = await new Promise(res => {
      http.get('http://127.0.0.1:9225/json', r => {
        let d = ''; r.on('data', c => d += c); r.on('end', () => res(JSON.parse(d)));
      });
    });
    const page = pages.find(p => p.url.includes('3000'));
    const ws = new WebSocket(page.webSocketDebuggerUrl);

    const send = (method, params) => new Promise(res => {
      const id = Math.floor(Math.random() * 100000);
      const handler = (m) => {
        const msg = JSON.parse(m.data);
        if (msg.id === id) {
          ws.removeEventListener('message', handler);
          res(msg.result);
        }
      };
      ws.addEventListener('message', handler);
      ws.send(JSON.stringify({ id, method, params }));
    });

    ws.onopen = async () => {
      const widths = [360, 375, 414, 576, 768, 820, 991];
      for (const w of widths) {
        await send('Emulation.setDeviceMetricsOverride', {
          width: w,
          height: 800,
          deviceScaleFactor: 1,
          mobile: true
        });

        const expr = `
          (() => {
            const docW = document.documentElement.clientWidth;
            const docScrollW = document.documentElement.scrollWidth;
            const bodyScrollW = document.body.scrollWidth;
            const winW = window.innerWidth;
            
            const overflowing = [];
            document.querySelectorAll('*').forEach(el => {
              if (el.closest('.marquee-super-wrapper')) return;
              const r = el.getBoundingClientRect();
              if (r.right > winW + 1) {
                overflowing.push({
                  tag: el.tagName,
                  cls: (el.className && typeof el.className === 'string') ? el.className.slice(0, 50) : '',
                  id: el.id || '',
                  right: Math.round(r.right),
                  diff: Math.round(r.right - winW)
                });
              }
            });
            return {
              w: winW,
              docW,
              docScrollW,
              bodyScrollW,
              overflow: docScrollW > winW || bodyScrollW > winW,
              count: overflowing.length,
              top10: overflowing.sort((a,b) => b.diff - a.diff).slice(0, 10)
            };
          })()
        `;

        const evalRes = await send('Runtime.evaluate', {
          expression: expr,
          returnByValue: true
        });
        console.log(`\n=================== WIDTH ${w} ===================`);
        console.log(JSON.stringify(evalRes.result.value, null, 2));
      }
      ws.close();
      edge.kill();
      process.exit(0);
    };
  } catch (err) {
    console.error(err);
    edge.kill();
    process.exit(1);
  }
}, 2500);
