import { createRequire } from "module";
const { chromium } = createRequire(import.meta.url)('/Users/feibisi-studio/wt/wpcy-m1-08/node_modules/playwright');
const exe='/Users/feibisi-studio/Library/Caches/ms-playwright/chromium-1234/chrome-mac-arm64/Google Chrome for Testing.app/Contents/MacOS/Google Chrome for Testing';
const b = await chromium.launch({ executablePath: exe });
const p = await b.newPage({ viewport: { width: 1440, height: 900 } });
for (const u of ['E/onboarding.html','E/overview-domestic.html']) {
  await p.goto('http://127.0.0.1:8898/'+u, { waitUntil: 'load' });
  const r = await p.evaluate(() => {
    const q = s => document.querySelector(s);
    const cs = (s,prop) => q(s) ? getComputedStyle(q(s))[prop] : 'MISSING';
    return { sheets: [...document.styleSheets].map(x=>x.href&&x.href.split('/').slice(-2).join('/')),
      brand: cs('.wpcy-brand','display'), head: cs('.wpcy-head','display'), wrapMax: cs('.wpcy-wrap','maxWidth'), wrapX: q('.wpcy-wrap')?.getBoundingClientRect().x,
      brandX: q('.wpcy-brand')?.getBoundingClientRect().x, markW: cs('.wpcy-mark','width') };
  });
  console.log(u, JSON.stringify(r));
}
await b.close();
