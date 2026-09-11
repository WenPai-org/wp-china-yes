/**
 * OV-15 「来自文派」 signature bar (v2.2 three segments).
 *
 * Brand mark is a placeholder SVG injected via props; built-in monochrome
 * RemixIcon until feibisi supplies official logos.
 */

import { __ } from '@wordpress/i18n';
import Icon from './icons';

const BRANDS = [
	{
		id: 'wenpai_org',
		icon: 'globe',
		name: 'WenPai.org',
		role: __( 'WordPress 更新镜像', 'wp-china-yes' ),
	},
	{
		id: 'admincdn',
		icon: 'bolt',
		name: 'adminCDN',
		role: __( '公共库加速节点', 'wp-china-yes' ),
	},
	{
		id: 'cravatar',
		icon: 'user',
		name: 'Cravatar',
		role: __( '头像服务', 'wp-china-yes' ),
	},
	{
		id: 'windfonts',
		icon: 'font',
		name: 'Windfonts',
		role: __( '中文字体服务', 'wp-china-yes' ),
	},
	{
		id: 'motucloud',
		icon: 'cloud',
		name: 'MotuCloud',
		role: __( '图标资源服务', 'wp-china-yes' ),
	},
	{
		id: 'weixiaoduo',
		icon: 'store',
		name: __( '薇晓朵', 'wp-china-yes' ),
		role: __( '跨境店工具与服务', 'wp-china-yes' ),
		roleInbound: __( '中国买家工具与服务', 'wp-china-yes' ),
		scene: 'overseas',
	},
];

const LINKS = [
	{ id: 'guide', label: __( '入门指南', 'wp-china-yes' ), hrefKey: 'help' },
	{
		id: 'forum',
		label: __( '支持论坛', 'wp-china-yes' ),
		hrefKey: 'help',
	},
	{ id: 'faq', label: __( '常见问题', 'wp-china-yes' ), hrefKey: 'help' },
	{
		id: 'log',
		label: __( '更新日志', 'wp-china-yes' ),
		hrefKey: 'changelog',
	},
	{ id: 'wechat', label: __( '公众号', 'wp-china-yes' ), hrefKey: 'site' },
	{ id: 'bili', label: 'Bilibili', hrefKey: 'site' },
];

/**
 * Placeholder brand mark. Official SVG may be passed as `logo`.
 *
 * @param {Object}                    props
 * @param {string}                    props.icon
 * @param {import('react').ReactNode} [props.logo]
 */
function BrandMark( { icon, logo } ) {
	if ( logo ) {
		return logo;
	}
	return <Icon name={ icon } size={ 16 } />;
}

/**
 * @param {Object}  props
 * @param {boolean} [props.crossborder]
 * @param {boolean} [props.inbound]
 * @param {Object}  [props.brands]
 * @param {Object}  [props.links]
 * @param {Object}  [props.logos]       Optional official SVGs keyed by brand id.
 */
export default function Eco( {
	crossborder = false,
	inbound = false,
	brands = {},
	links = {},
	logos = {},
} ) {
	const overseas = crossborder || inbound;
	const cols = BRANDS.filter( ( col ) => ! col.scene || overseas );
	let extraLink = null;
	if ( inbound ) {
		extraLink = {
			id: 'cn-tools',
			label: __( '中国买家工具', 'wp-china-yes' ),
			href: 'admin.php?page=wpcy-services',
		};
	} else if ( crossborder ) {
		extraLink = {
			id: 'xb-tools',
			label: __( '跨境店工具', 'wp-china-yes' ),
			href: 'admin.php?page=wpcy-services',
		};
	}

	return (
		<section className="eco7">
			<div className="eco7-head">
				<span className="eco7-mark">
					<Icon name="leaf" size={ 16 } />
				</span>
				<div>
					<div className="eyebrow">
						{ __( '来自文派', 'wp-china-yes' ) }
					</div>
					<h2>
						{ __( '叶子背后，是整个文派生态', 'wp-china-yes' ) }
					</h2>
				</div>
			</div>
			<p className="eco7-lede">
				{ __(
					'更新镜像、公共库加速、头像、中文字体这些核心服务，由文派与合作伙伴免费提供。',
					'wp-china-yes'
				) }
			</p>
			<div className="eco7-brands">
				{ cols.map( ( col ) => {
					const href = brands[ col.id ] || '#';
					const role =
						inbound && col.roleInbound ? col.roleInbound : col.role;
					return (
						<a
							className="eco7-b"
							key={ col.id }
							href={ href }
							target="_blank"
							rel="noopener noreferrer"
						>
							<span className="eco7-lg">
								<BrandMark
									icon={ col.icon }
									logo={ logos[ col.id ] }
								/>
								<b>{ col.name }</b>
							</span>
							<span className="eco7-r">{ role }</span>
						</a>
					);
				} ) }
			</div>
			<div className="eco7-links">
				{ LINKS.map( ( link ) => (
					<a
						key={ link.id }
						href={ links[ link.hrefKey ] || '#' }
						target="_blank"
						rel="noopener noreferrer"
					>
						{ link.label }
					</a>
				) ) }
				{ extraLink ? (
					<a href={ extraLink.href }>{ extraLink.label }</a>
				) : null }
			</div>
		</section>
	);
}
