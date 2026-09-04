/**
 * Connection settings DataForm.
 */

import { __ } from '@wordpress/i18n';
import { useDispatch, useSelect } from '@wordpress/data';
import {
	Button,
	CheckboxControl,
	Snackbar,
	ToggleControl,
} from '@wordpress/components';
import { DataForm } from '@wordpress/dataviews/wp';
import PageShell from '../components/PageShell';
import StatusDot from '../components/StatusDot';
import { STORE_NAME } from '../store';

const ASSET_OPTIONS = [
	{ value: 'google_fonts', label: 'Google Fonts' },
	{ value: 'google_ajax', label: 'Google Ajax' },
	{ value: 'cdnjs', label: 'CDNJS' },
	{ value: 'jsdelivr', label: 'jsDelivr' },
	{ value: 'emoji', label: 'Emoji' },
];

/**
 * Node status for a public-asset checkbox, from the last diagnostics run.
 *
 * @param {string} asset
 * @param {Array}  targets
 */
function assetStatus( asset, targets ) {
	const hostByAsset = {
		google_fonts: 'googlefonts.admincdn.com',
		google_ajax: 'googleajax.admincdn.com',
		cdnjs: 'cdnjs.admincdn.com',
		jsdelivr: 'jsd.admincdn.com',
	};
	const host = hostByAsset[ asset ];
	if ( ! host ) {
		return null;
	}
	const row = ( targets || [] ).find( ( item ) => item.target === host );
	if ( ! row ) {
		return null;
	}
	if ( row.result === 'ok' ) {
		return {
			tone: 'success',
			label: __( '国内镜像正常', 'wp-china-yes' ),
		};
	}
	if ( row.result === 'fallback' ) {
		return {
			tone: 'warning',
			label: __( '已回原始上游', 'wp-china-yes' ),
		};
	}
	return { tone: 'danger', label: __( '不可用', 'wp-china-yes' ) };
}

function WindfontsEdit( { data, field } ) {
	return (
		<ToggleControl
			__nextHasNoMarginBottom
			label="Windfonts"
			help={ __( '绑定后可用配额', 'wp-china-yes' ) }
			checked={ Boolean( field.getValue( { item: data } ) ) }
			disabled
			onChange={ () => undefined }
		/>
	);
}

function PublicAssetsEdit( { data, field, onChange } ) {
	const targets = useSelect(
		( select ) => select( STORE_NAME ).getDiagnostics()?.targets || [],
		[]
	);
	const selected = field.getValue( { item: data } ) || [];

	return (
		<div>
			{ ASSET_OPTIONS.map( ( option ) => {
				const status = assetStatus( option.value, targets );
				return (
					<div className="wpcy-field-row" key={ option.value }>
						<CheckboxControl
							__nextHasNoMarginBottom
							label={ option.label }
							checked={ selected.includes( option.value ) }
							onChange={ ( checked ) => {
								const next = checked
									? [ ...selected, option.value ]
									: selected.filter(
											( value ) => value !== option.value
									  );
								onChange(
									field.setValue( {
										item: data,
										value: next,
									} )
								);
							} }
						/>
						{ status ? (
							<StatusDot
								tone={ status.tone }
								label={ status.label }
							/>
						) : null }
					</div>
				);
			} ) }
		</div>
	);
}

export default function Connect() {
	const { draft, isDirty, isSaving, notice } = useSelect(
		( select ) => ( {
			draft: select( STORE_NAME ).getDraft(),
			isDirty: select( STORE_NAME ).isDirty(),
			isSaving: select( STORE_NAME ).isSaving(),
			notice: select( STORE_NAME ).getNotice(),
		} ),
		[]
	);
	const { setDraft, saveSettings, clearNotice } = useDispatch( STORE_NAME );

	const fields = [
		{
			id: 'wordpress_org',
			type: 'text',
			label: __( 'WordPress.org 源', 'wp-china-yes' ),
			Edit: 'radio',
			elements: [
				{
					value: 'auto',
					label: __( '自动（推荐）', 'wp-china-yes' ),
				},
				{ value: 'off', label: __( '关闭', 'wp-china-yes' ) },
			],
		},
		{
			id: 'public_assets',
			type: 'array',
			label: __( '公共前端库', 'wp-china-yes' ),
			Edit: PublicAssetsEdit,
		},
		{
			id: 'avatar',
			type: 'text',
			label: __( '头像', 'wp-china-yes' ),
			Edit: 'radio',
			elements: [
				{
					value: 'cravatar_cn',
					label: __( 'Cravatar 中国', 'wp-china-yes' ),
				},
				{
					value: 'cravatar_global',
					label: __( 'Cravatar 国际', 'wp-china-yes' ),
				},
				{ value: 'off', label: __( '关闭', 'wp-china-yes' ) },
			],
		},
		{
			id: 'windfonts',
			type: 'boolean',
			label: 'Windfonts',
			description: __( '绑定后可用配额', 'wp-china-yes' ),
			Edit: WindfontsEdit,
		},
	];

	const form = {
		layout: { type: 'regular', labelPosition: 'top' },
		fields: [
			{
				id: 'wordpress_org_group',
				label: __( 'WordPress.org 源', 'wp-china-yes' ),
				children: [ 'wordpress_org' ],
			},
			{
				id: 'public_assets_group',
				label: __( '公共前端库', 'wp-china-yes' ),
				children: [ 'public_assets' ],
			},
			{
				id: 'avatar_group',
				label: __( '头像', 'wp-china-yes' ),
				children: [ 'avatar' ],
			},
			{
				id: 'fonts_group',
				label: __( '字体', 'wp-china-yes' ),
				children: [ 'windfonts' ],
			},
		],
	};

	const save = (
		<Button
			variant="primary"
			disabled={ ! isDirty || isSaving }
			isBusy={ isSaving }
			onClick={ () => saveSettings( draft ) }
		>
			{ __( '保存', 'wp-china-yes' ) }
		</Button>
	);

	return (
		<PageShell title={ __( '连接优化', 'wp-china-yes' ) } actions={ save }>
			<DataForm
				data={ draft }
				fields={ fields }
				form={ form }
				onChange={ ( edits ) => {
					const next = { ...edits };
					delete next.windfonts;
					setDraft( next );
				} }
			/>
			{ notice ? (
				<div className="wpcy-snackbar-slot">
					<Snackbar onRemove={ () => clearNotice() }>
						{ notice.message }
					</Snackbar>
				</div>
			) : null }
		</PageShell>
	);
}
