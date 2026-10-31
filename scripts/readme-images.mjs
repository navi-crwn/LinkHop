import { createServer } from 'node:http';
import { readFile, writeFile, mkdir } from 'node:fs/promises';
import { extname, join, normalize } from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from '@playwright/test';

const root = fileURLToPath(new URL('../', import.meta.url));
const docs = join(root, 'docs');
const imgDir = process.env.SCREENSHOT_DIR || join(root, 'test-results/readme');
const assetRoot = process.env.HOP_ASSET_ROOT;
const types = {
  '.html': 'text/html',
  '.css': 'text/css',
  '.js': 'text/javascript',
  '.svg': 'image/svg+xml',
  '.webp': 'image/webp',
  '.jpg': 'image/jpeg',
  '.png': 'image/png'
};

const server = createServer(async (req, res) => {
  const p = normalize(decodeURIComponent(new URL(req.url, 'http://x').pathname)).replace(/^([/\\])+/, '');
  try {
    const filePath = join(docs, p === '' || p.endsWith('/') ? p + 'index.html' : p);
    const body = await readFile(filePath);
    res.writeHead(200, { 'Content-Type': types[extname(filePath) || '.html'] || 'application/octet-stream' }).end(body);
  } catch {
    res.writeHead(404).end();
  }
}).listen(0);

const base = `http://127.0.0.1:${server.address().port}/`;

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1200, height: 900 }, deviceScaleFactor: 2, reducedMotion: 'reduce' });
if (assetRoot) {
  await page.route('https://hel.ink/hop-assets/**', async route => {
    const relative = new URL(route.request().url()).pathname.slice('/hop-assets/'.length);
    await route.fulfill({ path: join(assetRoot, relative), headers: { 'access-control-allow-origin': '*' } });
  });
}

async function encode(png, type, quality) {
  const data = await page.evaluate(async ([b64, type, quality]) => {
    const img = new Image();
    img.src = 'data:image/png;base64,' + b64;
    await img.decode();
    const c = document.createElement('canvas');
    c.width = img.width;
    c.height = img.height;
    c.getContext('2d').drawImage(img, 0, 0);
    return c.toDataURL(type, quality).split(',')[1];
  }, [png.toString('base64'), type, quality]);
  return Buffer.from(data, 'base64');
}

await mkdir(join(docs, 'readme'), { recursive: true });
await mkdir(imgDir, { recursive: true });

for (const lang of ['en', 'id']) {
  for (const theme of ['dark', 'light']) {
    const targetUrl = `${base}${lang}/?theme=${theme}`;
    await page.goto(targetUrl, { waitUntil: 'networkidle' });
    if (theme === 'dark') {
      await page.evaluate(() => document.documentElement.setAttribute('data-theme', 'dark'));
    } else {
      await page.evaluate(() => document.documentElement.setAttribute('data-theme', 'light'));
    }
    await page.evaluate(() => document.fonts.ready);
    
    const heroEl = page.locator('.hero');
    const heroPng = await heroEl.screenshot({ animations: 'disabled' });
    const heroWebp = await encode(heroPng, 'image/webp', 0.86);
    await writeFile(join(docs, 'readme', `hero-${lang}-${theme}.webp`), heroWebp);
    await writeFile(join(imgDir, `linkhop-hero-${lang}-${theme}.png`), heroPng);
    console.log(`wrote hero-${lang}-${theme}.webp`);
  }
}

// Mobile screenshot
await page.setViewportSize({ width: 375, height: 812 });
await page.goto(base, { waitUntil: 'networkidle' });
await page.evaluate(() => document.fonts.ready);
const mobPng = await page.screenshot({ fullPage: false });
await writeFile(join(imgDir, 'linkhop-mobile-375.png'), mobPng);
console.log('wrote linkhop-mobile-375.png');

// 1200x630 OpenGraph card
await page.setViewportSize({ width: 1200, height: 630 });
await page.goto(`${base}?theme=dark`, { waitUntil: 'networkidle' });
await page.evaluate(() => {
  document.documentElement.setAttribute('data-theme', 'dark');
  document.querySelector('.site-header').style.display = 'none';
  document.querySelector('.skip').style.display = 'none';
});
await page.evaluate(() => document.fonts.ready);
const ogPng = await page.screenshot({ clip: { x: 0, y: 0, width: 1200, height: 630 } });
await writeFile(join(docs, 'og.png'), ogPng);
console.log('wrote docs/og.png');

await browser.close();
server.close();
