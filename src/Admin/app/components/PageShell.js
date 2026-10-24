/**
 * Product PageShell: store / routing 注入，展示走 @wenpai/wpcy-admin-ui PageShell 槽。
 *
 * 吃包 API：pageTitle/pageLede/pageAside、notices、footerLeft；页头→snackbar 由包默认顺序保证。
 */

import { __, sprintf } from '@wordpress/i18n';
import { useDispatch, useSelect } from '@wordpress/data';
import { Snackbar } from '@wordpress/components';
import { PageShell as UiPageShell } from '@wenpai/wpcy-admin-ui';
import Icon from '../ui/icons';
import { STORE_NAME } from '../store';
import { adminPageUrl, getPageSlug, PAGES } from '../routing';
import { daysSince } from '../ui/relTime';

const TAB_DEFS = [
	{ slug: PAGES.overview, icon: 'home', label: __( '概览', 'wp-china-yes' ) },
	{
		slug: PAGES.connect,
		icon: 'settings',
		label: __( '设置', 'wp-china-yes' ),
	},
	{ slug: PAGES.services, icon: 'grid', label: __( '服务', 'wp-china-yes' ) },
	{
		slug: PAGES.diagnose,
		icon: 'stethoscope',
		label: __( '诊断', 'wp-china-yes' ),
	},
];

/**
 * @param {Object}                    props
 * @param {import('react').ReactNode} [props.title]
 * @param {import('react').ReactNode} [props.lede]
 * @param {import('react').ReactNode} [props.actions]
 * @param {boolean}                   [props.showTabs]
 * @param {import('react').ReactNode} props.children
 */
export default function PageShell( {
	title,
	lede,
	actions,
	showTabs = true,
	children,
} ) {
	const slug = getPageSlug();
	const { links, version, stats, notice, network } = useSelect(
		( select ) => {
			const store = select( STORE_NAME );
			return {
				network: Boolean( store.getCapabilities()?.network_admin ),
				links: store.getLinks(),
				version: store.getPluginVersion(),
				stats: store.getStats(),
				notice: store.getNotice(),
			};
		},
		[]
	);
	const { clearNotice } = useDispatch( STORE_NAME );

	const days = daysSince( stats?.installed_at );
	let uptime = __( '刚安装', 'wp-china-yes' );
	if ( stats?.installed_at && days > 0 ) {
		uptime = sprintf(
			/* translators: %d: days running */
			__( '已运行 %d 天', 'wp-china-yes' ),
			days
		);
	}

	const productName = __( '文派叶子', 'wp-china-yes' );
	const homeHref = adminPageUrl(
		// Network admin only registers the settings page.
		network ? PAGES.connect : PAGES.overview
	);

	const tabs = showTabs
		? TAB_DEFS.map( ( tab ) => ( {
				id: tab.slug,
				label: tab.label,
				href: adminPageUrl( tab.slug ),
				icon: <Icon name={ tab.icon } size={ 18 } />,
		  } ) )
		: [];

	const headRight = (
		<>
			<a
				className="btn btn-ghost"
				href={ links.help || 'https://wpcy.com/docs/' }
				target="_blank"
				rel="noopener noreferrer"
			>
				<Icon name="help" size={ 16 } />
				{ __( '帮助', 'wp-china-yes' ) }
			</a>
			<a
				className="btn btn-ghost"
				href={ links.feedback || 'https://wpcy.com/feedback/' }
				target="_blank"
				rel="noopener noreferrer"
			>
				<Icon name="chat" size={ 16 } />
				{ __( '反馈', 'wp-china-yes' ) }
			</a>
		</>
	);

	const notices = notice ? (
		<Snackbar onRemove={ () => clearNotice() }>{ notice.message }</Snackbar>
	) : null;

	const footerLeft = (
		<span>
			{ productName } { version } · { uptime }
		</span>
	);

	const footerLinks = [
		{
			href: links.changelog || 'https://wpcy.com/changelog/',
			label: (
				<>
					<Icon name="sparkle" size={ 16 } />
					{ __( '更新日志', 'wp-china-yes' ) }
				</>
			),
		},
		{
			href: links.site || 'https://wpcy.com/',
			label: (
				<>
					<Icon name="external" size={ 16 } />
					wpcy.com
				</>
			),
		},
	];

	return (
		<UiPageShell
			name={ productName }
			mark={ <Icon name="leaf" size={ 16 } /> }
			homeHref={ homeHref }
			tabs={ tabs }
			current={ slug }
			headRight={ headRight }
			pageTitle={ title || undefined }
			pageLede={ lede || undefined }
			pageAside={ actions || undefined }
			notices={ notices }
			footerLeft={ footerLeft }
			footerLinks={ footerLinks }
		>
			{ children }
		</UiPageShell>
	);
}
