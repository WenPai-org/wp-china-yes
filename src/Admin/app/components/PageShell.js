/**
 * Shared chrome: brand, tabs, help/feedback, wrap, footer.
 *
 */

import { __, sprintf } from '@wordpress/i18n';
import { useDispatch, useSelect } from '@wordpress/data';
import { Snackbar } from '@wordpress/components';
import Icon from '../ui/icons';
import { STORE_NAME } from '../store';
import { adminPageUrl, getPageSlug, PAGES } from '../routing';
import { daysSince } from '../ui/relTime';

const TABS = [
	{ slug: PAGES.overview, name: 'home', label: __( '概览', 'wp-china-yes' ) },
	{
		slug: PAGES.connect,
		name: 'settings',
		label: __( '设置', 'wp-china-yes' ),
	},
	{ slug: PAGES.services, name: 'grid', label: __( '服务', 'wp-china-yes' ) },
	{
		slug: PAGES.diagnose,
		name: 'stethoscope',
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
	const { links, version, stats, notice } = useSelect( ( select ) => {
		const store = select( STORE_NAME );
		return {
			links: store.getLinks(),
			version: store.getPluginVersion(),
			stats: store.getStats(),
			notice: store.getNotice(),
		};
	}, [] );
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

	return (
		<div className="wpcy-app">
			<div className="wpcy-top">
				<div className="wpcy-wrap">
					<div className="wpcy-head">
						<a
							className="wpcy-brand"
							href={ adminPageUrl( PAGES.overview ) }
						>
							<span className="wpcy-mark">
								<Icon name="leaf" size={ 16 } />
							</span>
							<span className="wpcy-name">
								{ __( '文派叶子', 'wp-china-yes' ) }
							</span>
						</a>
						{ showTabs ? (
							<nav
								className="wpcy-tabs"
								aria-label={ __( '文派叶子', 'wp-china-yes' ) }
							>
								{ TABS.map( ( tab ) => (
									<a
										key={ tab.slug }
										className={
											'wpcy-tab' +
											( slug === tab.slug
												? ' is-active'
												: '' )
										}
										href={ adminPageUrl( tab.slug ) }
									>
										<Icon name={ tab.name } size={ 18 } />
										{ tab.label }
									</a>
								) ) }
							</nav>
						) : null }
						<div className="wpcy-head-right">
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
								href={
									links.feedback ||
									'https://wpcy.com/feedback/'
								}
								target="_blank"
								rel="noopener noreferrer"
							>
								<Icon name="chat" size={ 16 } />
								{ __( '反馈', 'wp-china-yes' ) }
							</a>
						</div>
					</div>
				</div>
			</div>
			<main className="wpcy-main wpcy-wrap">
				{ title ? (
					<div className="wpcy-page-head">
						<div>
							<h1 className="wpcy-h1">{ title }</h1>
							{ lede ? (
								<p className="wpcy-lede">{ lede }</p>
							) : null }
						</div>
						{ actions }
					</div>
				) : null }
				{ notice ? (
					<div className="wpcy-snackbar-slot">
						<Snackbar onRemove={ () => clearNotice() }>
							{ notice.message }
						</Snackbar>
					</div>
				) : null }
				{ children }
				<footer className="foot">
					<span>
						{ __( '文派叶子', 'wp-china-yes' ) } { version } ·{ ' ' }
						{ uptime }
					</span>
					<span className="r">
						<a
							href={
								links.changelog || 'https://wpcy.com/changelog/'
							}
							target="_blank"
							rel="noopener noreferrer"
						>
							<Icon name="sparkle" size={ 16 } />
							{ __( '更新日志', 'wp-china-yes' ) }
						</a>
						<a
							href={ links.site || 'https://wpcy.com/' }
							target="_blank"
							rel="noopener noreferrer"
						>
							<Icon name="external" size={ 16 } />
							wpcy.com
						</a>
					</span>
				</footer>
			</main>
		</div>
	);
}
