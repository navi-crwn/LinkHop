// Renders the README images (docs/readme/*.webp) and the social card (docs/social.jpg)
// from the GitHub Pages site in docs/, so the README and the website always match.
//
//   node scripts/readme-images.mjs
//
// Needs the Playwright devDependency and a Chromium (npx playwright install chromium).
import { createServer } from 'node:http';
import { readFile, writeFile } from 'node:fs/promises';
import { extname, join, normalize } from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from '@playwright/test';

const docs = fileURLToPath(new URL('../docs/', import.meta.url));
const types = { '.html': 'text/html', '.css': 'text/css', '.js': 'text/javascript', '.png': 'image/png', '.jpg': 'image/jpeg', '.webp': 'image/webp', '.svg': 'image/svg+xml' };

const server = createServer(async (req, res) => {
  const path = normalize(decodeURIComponent(new URL(req.url, 'http://x').pathname)).replace(/^([/\\])+/, '');
  try {
    const body = await readFile(join(docs, path.endsWith('/') || path === '' ? path + 'index.html' : path));
    res.writeHead(200, { 'Content-Type': types[extname(path) || '.html'] || 'application/octet-stream' }).end(body);
  } catch {
    res.writeHead(404).end();
  }
}).listen(0);
const base = `http://127.0.0.1:${server.address().port}/`;

// section id on the site -> image name in docs/readme
const shots = { top: 'hero', features: 'features', bio: 'bio' };

const browser = await chromium.launch(process.env.CHROMIUM_PATH ? { executablePath: process.env.CHROMIUM_PATH } : {});
const page = await browser.newPage({ viewport: { width: 1200, height: 900 }, deviceScaleFactor: 2 });

// Chromium encodes WebP through a canvas, so no image library is needed.
async function toWebp(png) {
  const data = await page.evaluate(async (b64) => {
    const img = new Image();
    img.src = 'data:image/png;base64,' + b64;
    await img.decode();
    const c = document.createElement('canvas');
    c.width = img.width; c.height = img.height;
    c.getContext('2d').drawImage(img, 0, 0);
    return c.toDataURL('image/webp', 0.86).split(',')[1];
  }, png.toString('base64'));
  return Buffer.from(data, 'base64');
}

for (const lang of ['en', 'id']) {
  for (const theme of ['dark', 'light']) {
    await page.goto(`${base}?shot&lang=${lang}&theme=${theme}`, { waitUntil: 'networkidle' });
    await page.evaluate(() => document.fonts.ready);
    for (const [id, name] of Object.entries(shots)) {
      const el = page.locator(`#${id}`);
      // the hero fills the screen; capture it at its natural height
      const png = await el.screenshot({ animations: 'disabled' });
      const out = join(docs, 'readme', `${name}-${lang}-${theme}.webp`);
      await writeFile(out, await toWebp(png));
      console.log('wrote', out);
    }
    if (lang === 'en' && theme === 'dark') {
      await page.setViewportSize({ width: 1200, height: 630 });
      await page.goto(`${base}?shot&lang=en&theme=dark`, { waitUntil: 'networkidle' });
      await page.evaluate(() => document.fonts.ready);
      await writeFile(join(docs, 'social.jpg'), await page.screenshot({ type: 'jpeg', quality: 85, animations: 'disabled', scale: 'css' }));
      console.log('wrote docs/social.jpg');
      await page.setViewportSize({ width: 1200, height: 900 });
    }
  }
}

await browser.close();
server.close();
