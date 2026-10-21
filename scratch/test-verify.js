const fs = require('fs');
const html = fs.readFileSync('index.html', 'utf8');

// 1. Check Images
const imgMatches = [...html.matchAll(/src=["']([^"']+\.(png|jpg|jpeg|svg|webp))["']/gi)];
console.log('--- CHECKING IMAGES (' + imgMatches.length + ' found) ---');
let missingImgs = 0;
imgMatches.forEach(m => {
  const src = m[1];
  if (!src.startsWith('http')) {
    const exists = fs.existsSync(src);
    if (!exists) {
      console.error('MISSING IMAGE:', src);
      missingImgs++;
    } else {
      console.log('OK Image:', src);
    }
  }
});
console.log('Total local images verified. Missing:', missingImgs);

// 2. Check CSS and Scripts
console.log('\n--- CHECKING LOCAL SCRIPTS & STYLES ---');
const linkMatches = [...html.matchAll(/<link[^>]+href=["']([^"']+\.css)["']/gi)];
linkMatches.forEach(m => {
  const href = m[1];
  if (!href.startsWith('http')) {
    console.log('CSS:', href, fs.existsSync(href) ? 'EXISTS (OK)' : 'MISSING (ERROR)');
  }
});
const scriptMatches = [...html.matchAll(/<script[^>]+src=["']([^"']+\.js)["']/gi)];
scriptMatches.forEach(m => {
  const src = m[1];
  if (!src.startsWith('http')) {
    console.log('JS:', src, fs.existsSync(src) ? 'EXISTS (OK)' : 'MISSING (ERROR)');
  }
});

// 3. Check Anchor Targets
console.log('\n--- CHECKING ANCHOR TARGETS ---');
const anchorMatches = [...html.matchAll(/href=["']#([a-zA-Z0-9_-]+)["']/gi)];
const uniqueAnchors = [...new Set(anchorMatches.map(m => m[1]))];
uniqueAnchors.forEach(id => {
  const hasId = html.includes('id="' + id + '"');
  console.log('#' + id, hasId ? 'VALID (OK)' : 'MISSING (ERROR)');
});
