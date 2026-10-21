const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');

// We can launch edge with remote debugging and CDP to inspect exact DOM bounding rects!
const edgePath = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';

if (!fs.existsSync(edgePath)) {
  console.log('Edge not found at path');
  process.exit(1);
}

const viewports = [
  { name: 'Mobile 360px', w: 360, h: 740 },
  { name: 'Mobile 375px', w: 375, h: 667 },
  { name: 'Mobile 414px', w: 414, h: 896 },
  { name: 'Tablet 768px', w: 768, h: 1024 },
  { name: 'Tablet 820px', w: 820, h: 1180 },
  { name: 'Tablet 912px', w: 912, h: 1368 },
  { name: 'Tablet 991px', w: 991, h: 800 }
];

console.log('Testing Edge availability...');
console.log('Edge exists:', fs.existsSync(edgePath));
