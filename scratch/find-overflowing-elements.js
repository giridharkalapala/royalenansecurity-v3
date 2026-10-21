const { spawn } = require('child_process');
const http = require('http');

const edgePath = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';

async function checkViewport(width, height) {
  return new Promise((resolve, reject) => {
    const edge = spawn(edgePath, [
      '--headless=new',
      '--remote-debugging-port=9223',
      `--window-size=${width},${height}`,
      'http://127.0.0.1:3000/index.html'
    ]);

    setTimeout(async () => {
      try {
        const pages = await new Promise((res, rej) => {
          http.get('http://127.0.0.1:9223/json', r => {
            let d = '';
            r.on('data', c => d += c);
            r.on('end', () => res(JSON.parse(d)));
          }).on('error', rej);
        });

        const page = pages.find(p => p.type === 'page' && p.url.includes('3000'));
        if (!page) {
          edge.kill();
          return resolve({ error: 'Page not found' });
        }

        const ws = new WebSocket(page.webSocketDebuggerUrl);
        ws.onopen = () => {
          // Evaluate overflow checker in DOM
          const expr = `
            (() => {
              const docWidth = document.documentElement.clientWidth;
              const docScrollWidth = document.documentElement.scrollWidth;
              const bodyScrollWidth = document.body.scrollWidth;
              const winWidth = window.innerWidth;
              
              const overflowing = [];
              const all = document.querySelectorAll('*');
              for (const el of all) {
                const rect = el.getBoundingClientRect();
                if (rect.width > 0 && rect.height > 0) {
                  if (rect.right > winWidth + 1) {
                    overflowing.push({
                      tag: el.tagName.toLowerCase(),
                      className: el.className,
                      id: el.id,
                      rectRight: Math.round(rect.right),
                      winWidth: winWidth,
                      diff: Math.round(rect.right - winWidth)
                    });
                  }
                }
              }
              return {
                winWidth,
                docWidth,
                docScrollWidth,
                bodyScrollWidth,
                hasOverflow: docScrollWidth > winWidth || bodyScrollWidth > winWidth,
                overflowing: overflowing.slice(0, 15)
              };
            })()
          `;

          ws.send(JSON.stringify({
            id: 1,
            method: 'Runtime.evaluate',
            params: { expression: expr, returnByValue: true }
          }));
        };

        ws.onmessage = (msg) => {
          const res = JSON.parse(msg.data);
          if (res.id === 1) {
            ws.close();
            edge.kill();
            resolve(res.result.result.value);
          }
        };

        ws.onerror = (e) => {
          edge.kill();
          reject(e);
        };
      } catch (e) {
        edge.kill();
        reject(e);
      }
    }, 2500);
  });
}

async function run() {
  const tests = [
    { name: 'Mobile (375px)', w: 375, h: 812 },
    { name: 'Mobile (414px)', w: 414, h: 896 },
    { name: 'Tablet (768px)', w: 768, h: 1024 },
    { name: 'Tablet (820px)', w: 820, h: 1180 },
    { name: 'Tablet (991px)', w: 991, h: 900 }
  ];

  for (const t of tests) {
    console.log(`\n================ Testing ${t.name} ================`);
    const result = await checkViewport(t.w, t.h);
    console.log('Result:', JSON.stringify(result, null, 2));
  }
}

run();
