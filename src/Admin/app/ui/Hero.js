/**
 * Overview hero. v2.2: left copy + right n/5 anchor. No collapse.
 */

import { __ } from '@wordpress/i18n';

/**
 * @param {Object}                    props
 * @param {string}                    props.eyebrow
 * @param {import('react').ReactNode} props.title
 * @param {import('react').ReactNode} props.lede
 * @param {import('react').ReactNode} props.actions
 * @param {import('react').ReactNode} [props.anchor]
 */
export default function Hero( { eyebrow, title, lede, actions, anchor } ) {
	return (
		<section className="card hero-w is-open hero-v7" data-hero>
			<div className="hero-body hero-body-v7">
				<div className="hero-l">
					<div className="eyebrow">{ eyebrow }</div>
					<h1>{ title }</h1>
					<p>{ lede }</p>
					<div className="cta">{ actions }</div>
				</div>
				{ anchor }
			</div>
		</section>
	);
}

/**
 * Hero right-hand n/5 anchor (OV-10 v2.2).
 *
 * @param {Object}                             props
 * @param {string}                             props.count
 * @param {string}                             props.label
 * @param {string}                             props.sub
 * @param {string}                             props.check
 * @param {string}                             props.moreHref
 * @param {string}                             [props.tone]
 * @param {Array<{dot:string,tooltip:string}>} props.dots
 */
export function HeroAnchor( {
	count,
	label,
	sub,
	check,
	moreHref,
	tone = 'ok',
	dots,
} ) {
	return (
		<div className={ [ 'hero-a', tone ].filter( Boolean ).join( ' ' ) }>
			<div className="hero-a-n">{ count }</div>
			<div className="hero-a-label">{ label }</div>
			<div className="hero-a-dots">
				{ ( dots || [] ).map( ( dot, i ) => (
					<i
						key={ i }
						className={ dot.dot && dot.dot !== 'ok' ? dot.dot : '' }
						title={ dot.tooltip }
					/>
				) ) }
			</div>
			{ sub ? <div className="hero-a-sub">{ sub }</div> : null }
			<div className="hero-a-meta">
				{ check }
				<span className="sep" />
				<a className="hero-a-more" href={ moreHref }>
					{ __( '查看详情', 'wp-china-yes' ) }
				</a>
			</div>
		</div>
	);
}
