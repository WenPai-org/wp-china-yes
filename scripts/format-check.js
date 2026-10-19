#!/usr/bin/env node
/**
 * 只检查、不改写的格式门禁（替代 `wp-scripts format --check`）。
 *
 * `wp-scripts format` 无条件给 prettier 传 `--write`，且用 minimist 解析参数：
 * `--check src/Admin/app` 会被解析成 `{ check: 'src/Admin/app' }`，路径被吞掉，
 * 于是退回默认的 `.`，整仓改写而不是检查。这里直接调用 wp-scripts 同一份
 * prettier（wp-prettier），沿用它的扩展名集合与 .prettierignore。
 *
 * 默认检查范围：
 *   - src/Admin/app（后台前端源码，与 `npm run format` 范围一致）；
 *   - JS 单测：Jest（wp-scripts test-unit-js）的测试文件与源码同目录放置，
 *     目前仅 src/Admin/app/ui/svcStatus.test.js，已包含在上面的目录里。
 *   - 扩展名：js/jsx/json/ts/tsx/yml/yaml（与 wp-scripts format 一致）。
 * 明确排除：vendor、node_modules、build、dist 与 *.min.js 等构建产物
 *   （下方 EXCLUDES 以否定 glob 传给 prettier，另外仍沿用 .prettierignore）。
 * 暂不纳入：tests/e2e、tests/visual（Playwright 测试，非 Jest）。它们被
 *   .prettierignore 的 `tests` 忽略；去掉忽略后有 10 个文件不合格，
 *   需另开 PR 单独格式化后再纳入默认范围。
 *
 * 用法：
 *   npm run format:check                    # 默认范围（见上）
 *   npm run format:check -- src/Admin/app/ui # 只检查给定路径（目录或文件），仍套用排除项
 */

const fs = require( 'fs' );
const path = require( 'path' );
const { spawnSync } = require( 'child_process' );

const ROOT = path.resolve( __dirname, '..' );
const DEFAULT_PATHS = [ 'src/Admin/app' ];
// 与 @wordpress/scripts/scripts/format.js 的 dir-glob 扩展名保持一致。
const EXTENSIONS = '{js,jsx,json,ts,tsx,yml,yaml}';
// 构建产物与依赖目录，无论传入什么路径都不检查。
const EXCLUDES = [
	'!**/vendor/**',
	'!**/node_modules/**',
	'!**/build/**',
	'!**/dist/**',
	'!**/*.min.js',
];

const args = process.argv.slice( 2 );
const flags = args.filter( ( a ) => a.startsWith( '-' ) );
const files = args.filter( ( a ) => ! a.startsWith( '-' ) );
const targets = ( files.length ? files : DEFAULT_PATHS ).map( ( p ) => {
	const abs = path.resolve( ROOT, p );
	let isDir = false;
	try {
		isDir = fs.statSync( abs ).isDirectory();
	} catch {
		// 不存在的路径或 glob 原样交给 prettier 报错。
	}
	const rel = path.relative( ROOT, abs ) || '.';
	return isDir
		? `${ rel.split( path.sep ).join( '/' ) }/**/*.${ EXTENSIONS }`
		: p;
} );

const prettierBin = require.resolve( 'prettier/bin/prettier.cjs', {
	paths: [ ROOT ],
} );

const result = spawnSync(
	process.execPath,
	[
		prettierBin,
		'--check',
		'--ignore-path',
		path.join( ROOT, '.prettierignore' ),
		...flags,
		...targets,
		...EXCLUDES,
	],
	{ cwd: ROOT, stdio: 'inherit' }
);

if ( result.error ) {
	process.stderr.write( `${ result.error.message }\n` );
	process.exit( 2 );
}
process.exit( result.status === null ? 2 : result.status );
