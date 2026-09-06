import { createRequire } from "module";
const { chromium } = createRequire(import.meta.url)('/Users/feibisi-studio/wt/wpcy-m1-08/node_modules/playwright');
const exe='/Users/feibisi-studio/Library/Caches/ms-playwright/chromium-1234/chrome-mac-arm64/Google Chrome for Testing.app/Contents/MacOS/Google Chrome for Testing';
const b = await chromium.launch({ executablePath: exe });
for (const w of [1440, 1024]) {
  const p = await b.newPage({ viewport: { width: w, height: 1000 } });
  for (const n of ['overview-domestic','overview-crossborder','overview-degraded','overview-empty','overview-recovery','overview-upgraded','settings','settings-advanced','settings-network','services','services-pending','services-bound','services-unreachable','diagnose','onboarding','onboarding-2','onboarding-3','onboarding-4','recovery']) {
    await p.goto(`http://127.0.0.1:8898/E/${n}.html`, { waitUntil: 'networkidle' });
    await p.screenshot({ path: `/Users/feibisi-studio/wt/wpcy-proto/E/screens/${n}${w===1024?'-1024':''}.png`, fullPage: true });
  }
  await p.close();
}
await b.close();
