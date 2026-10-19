#!/usr/bin/env node
/**
 * 只检查、不改写的格式门禁（替代 `wp-scripts format --check`）。
 *
 * `wp-scripts format` 无条件给 prettier 传 `--write`，且用 minimist 解析参数：
 * `--check src/Admin/app` 会被解析成 `{ check: 'src/Admin/app' }`，路径被吞掉，
 * 于是退回默认的 `.`，整仓改写而不是检查。这里直接调用 wp-scripts 同一份
 * prettier（wp-prettier），沿用它的扩展名集合与 .prettierignore。
 *
 * 用法：
 *   npm run format:check                    # 默认 src/Admin/app，与 `npm run format` 范围一致
 *   npm run format:check -- src/Admin/app/ui # 只检查给定路径（目录或文件）
 */

const fs = require( 'fs' );
const path = require( 'path' );
const { spawnSync } = require( 'child_process' );

const ROOT = path.resolve( __dirname, '..' );
const DEFAULT_PATHS = [ 'src/Admin/app' ];
// 与 @wordpress/scripts/scripts/format.js 的 dir-glob 扩展名保持一致。
const EXTENSIONS = '{js,jsx,json,ts,tsx,yml,yaml}';

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
	],
	{ cwd: ROOT, stdio: 'inherit' }
);

if ( result.error ) {
	process.stderr.write( `${ result.error.message }\n` );
	process.exit( 2 );
}
process.exit( result.status === null ? 2 : result.status );
