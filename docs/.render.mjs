#!/usr/bin/env node
import { mkdirSync, readFileSync, writeFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";

const root = dirname(dirname(fileURLToPath(import.meta.url)));
const docs = join(root, "docs");

const BASE = "https://navi-crwn.github.io/LinkHop/";
const ORDER = ["en", "id", "zh", "es", "ar", "pt", "fr", "ja"];

const LANG_NAMES = {
  en: "English",
  id: "Bahasa Indonesia",
  zh: "简体中文",
  es: "Español",
  ar: "العربية",
  pt: "Português",
  fr: "Français",
  ja: "日本語",
};
const LANG_SHORT = {
  en: "EN",
  id: "ID",
  zh: "ZH",
  es: "ES",
  ar: "AR",
  pt: "PT",
  fr: "FR",
  ja: "JA",
};

function parseIni(text) {
  const blocks = {};
  let current = null;
  for (const raw of text.split(/\r?\n/)) {
    const line = raw.trim();
    if (!line || line.startsWith("#")) continue;
    if (line.startsWith("[") && line.endsWith("]")) {
      current = line.slice(1, -1);
      blocks[current] = {};
      continue;
    }
    const eq = line.indexOf("=");
    if (current && eq > 0) {
      const key = line.slice(0, eq).trim();
      let value = line.slice(eq + 1).trim();
      value = value.replace(/\\n/g, "\n");
      if (!(key in blocks[current])) blocks[current][key] = value;
    }
  }
  return blocks;
}

function upperKeys(block) {
  const out = {};
  for (const [k, v] of Object.entries(block)) out[k.toUpperCase()] = v;
  return out;
}

const locales = parseIni(readFileSync(join(docs, "locales.ini"), "utf8"));
const template = readFileSync(join(docs, ".template.html"), "utf8");

const hreflangLines = ORDER.map(
  (lang) => `  <link rel="alternate" hreflang="${lang}" href="${BASE}${lang}/">`
).join("\n");

function fill(str, map) {
  return str.replace(/\{\{([A-Z0-9_]+)\}\}/g, (_, key) => {
    if (!(key in map)) throw new Error(`Missing placeholder value: ${key}`);
    return map[key];
  });
}

function makeLangMenu(currentLang, rel) {
  const items = ORDER.map((l) => {
    const href = (l === "en") ? (rel || "./") : `${rel}${l}/`;
    const isCurrent = l === currentLang ? ' aria-current="page"' : '';
    return `<li><a href="${href}" hreflang="${l}" lang="${l}"${isCurrent}>${LANG_NAMES[l]}<small>${LANG_SHORT[l]}</small></a></li>`;
  }).join("\n        ");
  return `<details class="langmenu"><summary aria-label="Language"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.7 3.8 5.7 3.8 9s-1.3 6.3-3.8 9c-2.5-2.7-3.8-5.7-3.8-9S9.5 5.7 12 3z"/></svg>${LANG_SHORT[currentLang]}</summary><ul>\n        ${items}\n      </ul></details>`;
}

function renderPage(lang, values, isRoot = false) {
  const rel = isRoot ? "" : "../";
  const canonPath = isRoot ? "" : `${lang}/`;
  const map = {
    ...upperKeys(values),
    LANG: lang,
    DIR: values.dir,
    REL: rel,
    CANON_PATH: canonPath,
    SELF_URL: isRoot ? BASE : `${BASE}${lang}/`,
    THEME_BTN: values.theme_btn_dark,
    LANG_MENU: makeLangMenu(lang, rel),
  };
  let html = template.replace(/\{\{HREFLANGS\}\}/g, hreflangLines);
  html = fill(html, map);
  if (/\{\{[A-Z0-9_]+\}\}/.test(html)) {
    throw new Error(`Unfilled placeholder remains for lang=${lang}`);
  }
  return html;
}

const written = [];
for (const lang of ORDER) {
  const values = locales[lang];
  if (!values) throw new Error(`Missing locale block: ${lang}`);
  if (lang === "en") {
    const rootHtml = renderPage("en", values, true);
    mkdirSync(docs, { recursive: true });
    writeFileSync(join(docs, "index.html"), rootHtml);
    written.push(join(docs, "index.html"));

    const enHtml = renderPage("en", values, false);
    mkdirSync(join(docs, "en"), { recursive: true });
    writeFileSync(join(docs, "en", "index.html"), enHtml);
    written.push(join(docs, "en", "index.html"));
  } else {
    const html = renderPage(lang, values, false);
    mkdirSync(join(docs, lang), { recursive: true });
    writeFileSync(join(docs, lang, "index.html"), html);
    written.push(join(docs, lang, "index.html"));
  }
}

console.log(`Wrote ${written.length} pages for LinkHop`);
