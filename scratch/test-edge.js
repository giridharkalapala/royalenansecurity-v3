const { spawn } = require('child_process');
const http = require('http');

const edgePath = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';

const edge = spawn(edgePath, [
  '--headless=new',
  '--remote-debugging-port=9222',
  '--window-size=375,800',
  'http://127.0.0.1:3000/index.html'
]);

setTimeout(async () => {
  try {
    const listRes = await new Promise((resolve, reject) => {
      http.get('http://127.0.0.1:9222/json', res => {
        let data = '';
        res.on('data', chunk => data += chunk);
        res.on('end', () => resolve(JSON.parse(data)));
      }).on('error', reject);
    });

    console.log('Edge pages:', listRes.length);
    const page = listRes.find(p => p.type === 'page');
    console.log('Page URL:', page ? page.url : 'none');
    edge.kill();
  } catch (err) {
    console.error('CDP test error:', err.message);
    edge.kill();
  }
}, 2500);
