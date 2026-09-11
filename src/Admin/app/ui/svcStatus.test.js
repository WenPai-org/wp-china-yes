/**
 * Unit tests for OV-18 buildSvcRows / fromAgg.
 */

import { buildSvcRows, fromAgg } from './svcStatus';

function settings( extra = {} ) {
	return {
		profile: 'domestic',
		connectivity: {
			wordpress_org: 'auto',
			public_assets: { items: [ 'google_fonts' ] },
			avatar: 'cravatar_cn',
		},
		modules: { windfonts: false },
		...extra,
	};
}

const OK = [
	{
		target: 'api.wenpai.net',
		result: 'ok',
		latency_ms: 100,
		checked_at: '2026-09-06T04:00:00Z',
	},
	{
		target: 'downloads.wenpai.net',
		result: 'ok',
		latency_ms: 90,
		checked_at: '2026-09-06T04:00:00Z',
	},
	{
		target: 'googlefonts.admincdn.com',
		result: 'ok',
		latency_ms: 40,
		checked_at: '2026-09-06T04:00:00Z',
	},
	{
		target: 'cn.cravatar.com',
		result: 'ok',
		latency_ms: 30,
		checked_at: '2026-09-06T04:00:00Z',
	},
];

describe( 'buildSvcRows', () => {
	it( 'empty targets are 未检查, never 已接通', () => {
		const rows = buildSvcRows( {
			targets: [],
			settings: settings(),
			binding: { status: 'unbound' },
			providers: { wordpress_org: 'WenPai.org' },
			recovery: false,
		} );
		const checked = rows.filter( ( row ) => row.key !== 'off' );
		expect( checked.every( ( row ) => row.word === '未检查' ) ).toBe(
			true
		);
		expect( rows.some( ( row ) => row.word === '已接通' ) ).toBe( false );
		expect( rows.some( ( row ) => row.tone === 'ok' ) ).toBe( false );
	} );

	it( 'ok aggregates stay 已接通', () => {
		const rows = buildSvcRows( {
			targets: OK,
			settings: settings(),
			binding: { status: 'unbound' },
			providers: {},
			recovery: false,
		} );
		expect( rows[ 0 ].word ).toBe( '已接通' );
		expect( rows[ 1 ].word ).toBe( '已接通' );
		expect( rows[ 2 ].word ).toBe( '已接通' );
	} );

	it( 'unbound fonts stay 未启用 · 绑定本站后可用', () => {
		const rows = buildSvcRows( {
			targets: OK,
			settings: settings(),
			binding: { status: 'unbound' },
			providers: {},
			recovery: false,
		} );
		const font = rows[ 4 ];
		expect( font.word ).toBe( '未启用' );
		expect( font.line ).toContain( '绑定本站后可用' );
		expect( font.action ).toBe( 'enable-services' );
	} );

	it( 'motu stays 未启用 when bases are empty', () => {
		const rows = buildSvcRows( {
			targets: OK,
			settings: settings(),
			binding: { status: 'unbound' },
			providers: {},
			recovery: false,
		} );
		expect( rows[ 3 ].name ).toBe( '图标与图片' );
		expect( rows[ 3 ].word ).toBe( '未启用' );
		expect( rows[ 3 ].status ).toBe( 'off' );
	} );

	it( 'motu follows probe when enabled with a base', () => {
		const rows = buildSvcRows( {
			targets: [
				...OK,
				{
					target: 'motucloud',
					result: 'ok',
					latency_ms: 20,
					checked_at: '2026-09-06T04:00:00Z',
				},
			],
			settings: settings( {
				connectivity: {
					wordpress_org: 'auto',
					public_assets: { items: [ 'google_fonts' ] },
					avatar: 'cravatar_cn',
					icon_photos: {
						enabled: 'on',
						mirrored_base: 'https://motu.example/m',
						native_api_base: '',
					},
				},
			} ),
			binding: { status: 'unbound' },
			providers: {},
			recovery: false,
		} );
		expect( rows[ 3 ].word ).toBe( '已接通' );
		expect( rows[ 3 ].line ).toBe( '经 MotuCloud 接通' );
		expect( rows[ 3 ].tone ).toBe( 'ok' );
	} );

	it( 'bound fonts follow modules.windfonts', () => {
		const off = buildSvcRows( {
			targets: OK,
			settings: settings( { modules: { windfonts: false } } ),
			binding: { status: 'bound' },
			providers: {},
			recovery: false,
		} )[ 4 ];
		expect( off.word ).toBe( '未启用' );
		expect( off.line ).toBe( '未启用' );
		expect( off.action ).toBe( 'enable-connect' );

		const on = buildSvcRows( {
			targets: OK,
			settings: settings( { modules: { windfonts: true } } ),
			binding: { status: 'bound' },
			providers: {},
			recovery: false,
		} )[ 4 ];
		expect( on.word ).toBe( '已接通' );
		expect( on.line ).toBe( '经 Windfonts 接通' );
		expect( on.tone ).toBe( 'ok' );
	} );

	it( 'fallback line uses 不可达 N 分钟', () => {
		const ago = new Date( Date.now() - 5 * 60 * 1000 ).toISOString();
		const rows = buildSvcRows( {
			targets: [
				{
					target: 'api.wenpai.net',
					result: 'fallback',
					latency_ms: 2000,
					checked_at: ago,
				},
				{
					target: 'downloads.wenpai.net',
					result: 'fallback',
					latency_ms: 2000,
					checked_at: ago,
				},
			],
			settings: settings(),
			binding: { status: 'unbound' },
			providers: { wordpress_org: 'WenPai.org' },
			recovery: false,
		} );
		expect( rows[ 0 ].word ).toBe( '已回退' );
		expect( rows[ 0 ].line ).toMatch( /不可达 \d+ 分钟/ );
		expect( rows[ 0 ].line ).toContain( '已回原始上游' );
		expect( rows[ 0 ].line ).not.toMatch( /分钟前/ );
	} );
} );

describe( 'fromAgg', () => {
	it( 'null result is 未检查, not 已接通', () => {
		const row = fromAgg( {
			icon: 'download',
			name: 'WordPress 更新与安装包',
			agg: { result: null },
			provider: 'WenPai.org',
			okLine: '经 WenPai.org 镜像接通',
		} );
		expect( row.word ).toBe( '未检查' );
		expect( row.status ).toBe( 'unchecked' );
		expect( row.tone ).toBe( '' );
	} );
} );
