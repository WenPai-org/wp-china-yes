/**
 * OV-15 「来自文派」 block. Brand columns from wpcyAdmin.links.brands.
 *
 */

import { __ } from '@wordpress/i18n';
import Icon from './icons';
import Btn from './Btn';

const COLS = [
	{
		id: 'wenpai_org',
		icon: 'download',
		name: 'WenPai.org',
		desc: __(
			'WordPress.org 更新与安装包的国内镜像，文派开源维护。',
			'wp-china-yes'
		),
		host: 'wenpai.org',
	},
	{
		id: 'admincdn',
		icon: 'bolt',
		name: 'adminCDN',
		desc: __(
			'Google Fonts、Ajax、jsDelivr、CDNJS、Emoji 的国内节点。',
			'wp-china-yes'
		),
		host: 'admincdn.com',
	},
	{
		id: 'cravatar',
		icon: 'user',
		name: 'Cravatar',
		desc: __(
			'Gravatar 的中国替代，中国线路与国际线路各一套节点。',
			'wp-china-yes'
		),
		host: 'cravatar.com',
	},
	{
		id: 'windfonts',
		icon: 'font',
		name: 'Windfonts',
		desc: __( '面向中文网页的字体服务，绑定本站后启用。', 'wp-china-yes' ),
		host: 'windfonts.com',
	},
	{
		id: 'weixiaoduo',
		icon: 'store',
		name: __( '薇晓朵', 'wp-china-yes' ),
		desc: __(
			'跨境店工具：微信支付 for WooCommerce、订单微信通知。',
			'wp-china-yes'
		),
		host: 'weixiaoduo.com',
		crossborder: true,
	},
];

/**
 * @param {Object}  props
 * @param {boolean} [props.crossborder]
 * @param {Object}  [props.brands]
 * @param {string}  [props.openHref]
 */
export default function Eco( { crossborder = false, brands = {}, openHref } ) {
	const cols = COLS.filter( ( col ) => ! col.crossborder || crossborder );
	const open = openHref || brands.wenpai_open || brands.wenpai_org || '#';
	return (
		<section
			className={ [ 'eco', crossborder ? 'cols-5' : '' ]
				.filter( Boolean )
				.join( ' ' ) }
		>
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
				<Btn
					variant="ghost"
					href={ open }
					target="_blank"
					rel="noopener noreferrer"
				>
					{ __( '了解文派开源', 'wp-china-yes' ) } →
					<Icon name="arrow" size={ 16 } />
				</Btn>
			</div>
			<div className="eco-r">
				{ cols.map( ( col ) => {
					const href = brands[ col.id ] || '#';
					return (
						<div className="eco-b" key={ col.id }>
							<div className="eco-bn">
								<Icon name={ col.icon } size={ 18 } />
								{ col.name }
							</div>
							<p>{ col.desc }</p>
							<a
								href={ href }
								target="_blank"
								rel="noopener noreferrer"
							>
								{ col.host }
								<Icon name="external" size={ 14 } />
							</a>
						</div>
					);
				} ) }
			</div>
		</section>
	);
}
