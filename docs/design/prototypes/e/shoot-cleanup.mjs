import { createRequire } from "module";
import path from "path";
const { chromium } = createRequire(import.meta.url)('/Users/feibisi-studio/wt/wpcy-m1-08/node_modules/playwright');
const exe='/Users/feibisi-studio/Library/Caches/ms-playwright/chromium-1234/chrome-mac-arm64/Google Chrome for Testing.app/Contents/MacOS/Google Chrome for Testing';
const outDir = process.argv[2];
if (!outDir) {
  console.error('usage: node E/shoot-cleanup.mjs <output-dir>');
  process.exit(1);
}
const pages = ['overview-domestic','overview-crossborder','overview-degraded','overview-empty','overview-recovery','overview-upgraded','settings','settings-advanced','settings-network','services','services-pending','services-bound','services-unreachable','diagnose','onboarding','onboarding-2','onboarding-3','onboarding-4','recovery'];
const b = await chromium.launch({ executablePath: exe });
const p = await b.newPage({ viewport: { width: 1440, height: 1000 } });
for (const n of pages) {
  await p.goto(`http://127.0.0.1:8899/E/${n}.html`, { waitUntil: 'networkidle' });
  await p.screenshot({ path: path.join(outDir, `${n}.png`), fullPage: true, animations: 'disabled' });
}
await p.close();
await b.close();
