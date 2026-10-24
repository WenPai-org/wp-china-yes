/**
 * @wenpai/wpcy-admin-ui — React admin design-system package (dual-stack B).
 *
 * Consumers: WPCY leaf admin; future plugins choosing a React shell.
 * Tokens: import '@wenpai/wpcy-admin-ui/tokens.css'
 * Surfaces: import '@wenpai/wpcy-admin-ui/p2a-surfaces.css'
 * Not the PHP kit: that lives in wenpai-admin-ui.
 *
 * EXTRACT CTRL P2c (2026-10-24): CTRL-12 Spin, CTRL-16 StatusDot, CTRL-17 LoadError.
 * Builds on SHELL/CARD P2 + CTRL P2b. PageShell API gaps (2026-10-24): pageHead→notices
 * by default, pageAside no double `.mode`, footerLeft, footerLinks.label ReactNode.
 * P2-charts: surface API is StatArea + Area (SVG sparkline; no Chart.js).
 * Track A / D-24: Subnav composes Seg (nav.wpcy-subnav + aria-current); no third family.
 * Track B / E09: SecretInput (Field.Secret) — password reveal UX; nested name is caller-owned.
 * Helper also exported: areaPath (path math only; not a surface component).
 * CSS: consumers must import '@wenpai/wpcy-admin-ui/p2-charts.css' (or an entry that includes it).
 * Out of scope: SHELL-14/15/16; CARD-15..20; StatStrip; bar/line/donut; CARD-06 verify.
 */

export const PACKAGE_NAME = '@wenpai/wpcy-admin-ui';
export const PACKAGE_VERSION = '0.0.0';

/** @deprecated skeleton probe — components now land; kept for early consumers */
export function isWpcyAdminUiSkeleton() {
	return false;
}

export { Btn } from './components/Btn.js';
export { Card, CardHead, CardFoot, Tile } from './components/Card.js';
export { Pill } from './components/Pill.js';
export { Notice } from './components/Notice.js';
export { PageShell } from './components/PageShell.js';
export { Empty } from './components/Empty.js';
export { Next } from './components/Next.js';
export { Scope } from './components/Scope.js';
export { Sec } from './components/Sec.js';
export { Saved } from './components/Saved.js';
export { SimpleRow } from './components/SimpleRow.js';
export { SectionTitle } from './components/SectionTitle.js';
export { Toggle } from './components/Toggle.js';
export { Seg, SegItem } from './components/Seg.js';
export { Subnav } from './components/Subnav.js';
export { Field, Hint, Opts, Opt, SecretInput } from './components/Field.js';
export { Choice, ChoiceCards } from './components/Choice.js';
export { Spin } from './components/Spin.js';
export { StatusDot } from './components/StatusDot.js';
export { LoadError, ANNOUNCE_WINDOW_MS } from './components/LoadError.js';
export { StatArea } from './components/StatArea.js';
export { Area, areaPath } from './components/Area.js'; /* areaPath: helper; Q4 surfaces remain StatArea + Area */
