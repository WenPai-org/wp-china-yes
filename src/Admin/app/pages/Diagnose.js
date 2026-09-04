/**
 * Diagnostics: TabPanel plus DataViews.
 */

import { __ } from '@wordpress/i18n';
import { useDispatch, useSelect } from '@wordpress/data';
import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Button, Spinner, TabPanel } from '@wordpress/components';
import { DataViews } from '@wordpress/dataviews/wp';
import PageShell from '../components/PageShell';
import StatusDot from '../components/StatusDot';
import { STORE_NAME } from '../store';
import { adminPageUrl, parseHash, PAGES, writeHash } from '../routing';

const TABS = [
	{ name: 'connect', title: __( '连接检查', 'wp-china-yes' ) },
	{ name: 'notices', title: __( '被隐藏的通知', 'wp-china-yes' ) },
	{ name: 'hosts', title: __( '出站主机记录', 'wp-china-yes' ) },
	{ name: 'data', title: __( '数据与恢复', 'wp-china-yes' ) },
];

/**
 * @param {string} result
 */
function resultStatus( result ) {
	if ( result === 'ok' ) {
		return {
			tone: 'success',
			label: __( '国内镜像正常', 'wp-china-yes' ),
		};
	}
	if ( result === 'fallback' ) {
		return {
			tone: 'warning',
			label: __( '已回原始上游', 'wp-china-yes' ),
		};
	}
	return { tone: 'danger', label: __( '不可用', 'wp-china-yes' ) };
}

const TABLE_VIEW = {
	type: 'table',
	search: '',
	filters: [],
	page: 1,
	perPage: 20,
	fields: [],
};

function EmptyTable( { fields, empty } ) {
	const [ view, setView ] = useState( {
		...TABLE_VIEW,
		fields: fields.map( ( field ) => field.id ),
	} );
	return (
		<DataViews
			data={ [] }
			fields={ fields }
			view={ view }
			onChangeView={ setView }
			defaultLayouts={ { table: {} } }
			paginationInfo={ { totalItems: 0, totalPages: 1 } }
			search={ false }
			empty={ empty }
		/>
	);
}

function ConnectTable( { targets } ) {
	const fields = [
		{
			id: 'target',
			label: __( '目标', 'wp-china-yes' ),
			enableHiding: false,
			getValue: ( { item } ) => item.target,
		},
		{
			id: 'result',
			label: __( '结果', 'wp-china-yes' ),
			render: ( { item } ) => {
				const status = resultStatus( item.result );
				return (
					<StatusDot tone={ status.tone } label={ status.label } />
				);
			},
		},
		{
			id: 'latency_ms',
			label: __( '延迟', 'wp-china-yes' ),
			render: ( { item } ) =>
				item.latency_ms === null || item.latency_ms === undefined
					? '—'
					: item.latency_ms + ' ms',
		},
		{
			id: 'checked_at',
			label: __( '最近检查', 'wp-china-yes' ),
			getValue: ( { item } ) => item.checked_at,
		},
		{
			id: 'suggestion',
			label: __( '建议', 'wp-china-yes' ),
			render: ( { item } ) =>
				item.result === 'ok' ? '' : item.suggestion || '',
		},
	];
	const [ view, setView ] = useState( {
		...TABLE_VIEW,
		fields: fields.map( ( field ) => field.id ),
	} );
	const rows = ( targets || [] ).map( ( row, index ) => ( {
		...row,
		id: row.target + '-' + index,
	} ) );

	return (
		<DataViews
			data={ rows }
			fields={ fields }
			view={ view }
			onChangeView={ setView }
			defaultLayouts={ { table: {} } }
			paginationInfo={ {
				totalItems: rows.length,
				totalPages: 1,
			} }
			search={ false }
			empty={
				<p className="wpcy-help">
					{ __( '尚未检查', 'wp-china-yes' ) }
				</p>
			}
		/>
	);
}

function HiddenNoticesTable() {
	const [ rows, setRows ] = useState( [] );
	const fields = [
		{
			id: 'plugin',
			label: __( '来源插件', 'wp-china-yes' ),
			enableHiding: false,
			getValue: ( { item } ) => item.plugin,
		},
		{
			id: 'rule',
			label: __( '匹配规则', 'wp-china-yes' ),
			getValue: ( { item } ) => item.rule,
		},
		{
			id: 'first',
			label: __( '首次隐藏', 'wp-china-yes' ),
			getValue: ( { item } ) => item.first_hidden,
		},
		{
			id: 'count',
			label: __( '次数', 'wp-china-yes' ),
			getValue: ( { item } ) => item.count,
		},
	];
	const [ view, setView ] = useState( {
		...TABLE_VIEW,
		fields: fields.map( ( field ) => field.id ),
	} );

	useEffect( () => {
		let cancelled = false;
		apiFetch( { path: '/wpcy/v1/notice-control/hidden' } )
			.then( ( payload ) => {
				if ( cancelled ) {
					return;
				}
				const items = Array.isArray( payload?.items )
					? payload.items
					: [];
				setRows(
					items.map( ( row, index ) => ( {
						...row,
						id: ( row.rule || 'row' ) + '-' + index,
					} ) )
				);
			} )
			.catch( () => {
				if ( ! cancelled ) {
					setRows( [] );
				}
			} );
		return () => {
			cancelled = true;
		};
	}, [] );

	return (
		<div>
			<p className="wpcy-help">
				{ __( '核心更新、安全与站点健康通知永不隐藏', 'wp-china-yes' ) }
			</p>
			<DataViews
				data={ rows }
				fields={ fields }
				view={ view }
				onChangeView={ setView }
				defaultLayouts={ { table: {} } }
				paginationInfo={ {
					totalItems: rows.length,
					totalPages: 1,
				} }
				search={ false }
				empty={
					<p className="wpcy-help">
						{ __( '暂无被隐藏的通知。', 'wp-china-yes' ) }
					</p>
				}
			/>
		</div>
	);
}

export default function Diagnose() {
	const { targets, running } = useSelect(
		( select ) => ( {
			targets: select( STORE_NAME ).getDiagnostics()?.targets || [],
			running: select( STORE_NAME ).isRunning(),
		} ),
		[]
	);
	const { runDiagnostics } = useDispatch( STORE_NAME );
	const hash = parseHash();
	const initial = TABS.some( ( tab ) => tab.name === hash.tab )
		? hash.tab
		: 'connect';

	useEffect( () => {
		if ( ! hash.tab ) {
			return;
		}
		if ( ! TABS.some( ( tab ) => tab.name === hash.tab ) ) {
			writeHash( {} );
		}
	}, [ hash.tab ] );

	const actions = (
		<Button
			variant="primary"
			isBusy={ running }
			disabled={ running }
			onClick={ () => runDiagnostics() }
		>
			{ running ? <Spinner /> : null }
			{ __( '立即检查', 'wp-china-yes' ) }
		</Button>
	);

	return (
		<PageShell title={ __( '诊断', 'wp-china-yes' ) } actions={ actions }>
			<TabPanel
				tabs={ TABS }
				initialTabName={ initial }
				onSelect={ ( name ) => {
					writeHash( name === 'connect' ? {} : { tab: name } );
				} }
			>
				{ ( tab ) => {
					if ( tab.name === 'connect' ) {
						return <ConnectTable targets={ targets } />;
					}
					if ( tab.name === 'notices' ) {
						return <HiddenNoticesTable />;
					}
					if ( tab.name === 'hosts' ) {
						return (
							<div>
								<p className="wpcy-help">
									{ __(
										'主机表由文派发布，用户不可编辑',
										'wp-china-yes'
									) }
								</p>
								<EmptyTable
									fields={ [
										{
											id: 'host',
											label: __( '主机', 'wp-china-yes' ),
										},
										{
											id: 'data_class',
											label: __(
												'数据类别',
												'wp-china-yes'
											),
										},
										{
											id: 'count',
											label: __( '次数', 'wp-china-yes' ),
										},
										{
											id: 'last_seen',
											label: __(
												'最近时间',
												'wp-china-yes'
											),
										},
										{
											id: 'disposition',
											label: __( '处置', 'wp-china-yes' ),
										},
									] }
									empty={
										<p className="wpcy-help">
											{ __(
												'暂无出站主机记录。',
												'wp-china-yes'
											) }
										</p>
									}
								/>
							</div>
						);
					}
					return (
						<div>
							<p>
								<Button
									variant="secondary"
									href={ adminPageUrl( PAGES.recovery ) }
									className="wpcy-destructive"
								>
									{ __( '进入恢复模式', 'wp-china-yes' ) }
								</Button>
							</p>
						</div>
					);
				} }
			</TabPanel>
		</PageShell>
	);
}
