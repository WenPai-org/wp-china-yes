/**
 * OV-15 「来自文派」 signature bar (admin-ui-spec v2.1 / DESIGN-E §5.1).
 *
 * Left: eyebrow + title + lede + ghost「了解文派开源 →」.
 * Right: brand columns (WenPai.org / adminCDN / Cravatar / Windfonts;
 * profile≠domestic adds 薇晓朵). Domain links ≤ 6. No button wall.
 * MotuCloud is not an OV-15 brand column (see D-01 / D-08).
 */

import { __ } from '@wordpress/i18n';
import Icon from './icons';

const BRANDS = [
	{
		id: 'wenpai_org',
		icon: 'download',
		name: 'WenPai.org',
		role: __(
			'WordPress.org 更新与安装包的国内镜像，文派开源维护。',
			'wp-china-yes'
		),
		host: 'wenpai.org',
	},
	{
		id: 'admincdn',
		icon: 'bolt',
		name: 'adminCDN',
		role: __(
			'Google Fonts、Ajax、jsDelivr、CDNJS、Emoji 的国内节点。',
			'wp-china-yes'
		),
		host: 'admincdn.com',
	},
	{
		id: 'cravatar',
		icon: 'user',
		name: 'Cravatar',
		role: __(
			'Gravatar 的中国替代，中国线路与国际线路各一套节点。',
			'wp-china-yes'
		),
		host: 'cravatar.com',
	},
	{
		id: 'windfonts',
		icon: 'font',
		name: 'Windfonts',
		role: __( '面向中文网页的字体服务，绑定本站后启用。', 'wp-china-yes' ),
		host: 'windfonts.com',
	},
	{
		id: 'weixiaoduo',
		icon: 'store',
		name: __( '薇晓朵', 'wp-china-yes' ),
		role: __(
			'跨境店工具：微信支付 for WooCommerce、订单微信通知。',
			'wp-china-yes'
		),
		roleInbound: __(
			'中国买家工具：微信支付 for WooCommerce、订单微信通知。',
			'wp-china-yes'
		),
		host: 'weixiaoduo.com',
		scene: 'overseas',
	},
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
	return <Icon name={ icon } size={ 18 } />;
}

/**
 * Visible domain label for a brand href.
 *
 * @param {string} href
 * @param {string} fallback
 * @return {string} Hostname label.
 */
function domainLabel( href, fallback ) {
	if ( ! href || href === '#' ) {
		return fallback;
	}
	try {
		const host = new URL( href ).hostname.replace( /^www\./, '' );
		return host || fallback;
	} catch {
		return fallback;
	}
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
	const openHref = brands.wenpai_open || links.site || 'https://wenpai.org/';

	return (
		<section className={ overseas ? 'eco cols-5' : 'eco' }>
			<div className="eco-l">
				<div className="eyebrow">
					{ __( '来自文派', 'wp-china-yes' ) }
				</div>
				<h2>
					{ __(
						'文派叶子是文派 WordPress 生态的入口',
						'wp-china-yes'
					) }
				</h2>
				<p>
					{ __(
						'上面这些核心服务由文派与合作方免费提供，叶子把它们接到你的站点里。',
						'wp-china-yes'
					) }
				</p>
				<a
					className="btn btn-ghost"
					href={ openHref }
					target="_blank"
					rel="noopener noreferrer"
				>
					{ __( '了解文派开源', 'wp-china-yes' ) }{ ' ' }
					<Icon name="arrow" size={ 16 } />
				</a>
			</div>
			<div className="eco-r">
				{ cols.map( ( col ) => {
					const href = brands[ col.id ] || '#';
					const role =
						inbound && col.roleInbound ? col.roleInbound : col.role;
					return (
						<div className="eco-b" key={ col.id }>
							<div className="eco-bn">
								<BrandMark
									icon={ col.icon }
									logo={ logos[ col.id ] }
								/>
								{ col.name }
							</div>
							<p>{ role }</p>
							<a
								href={ href }
								target="_blank"
								rel="noopener noreferrer"
							>
								{ domainLabel( href, col.host ) }
								<Icon name="external" size={ 14 } />
							</a>
						</div>
					);
				} ) }
			</div>
		</section>
	);
}
