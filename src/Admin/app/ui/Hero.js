/**
 * Foldable overview hero. Collapse stored in user meta via /wp/v2/users/me.
 *
 */

import { __ } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import Icon from './icons';
import Pill from './Pill';
import SvcStack from './SvcStack';

const META_KEY = 'wpcy_overview_hero_collapsed';

/**
 * @param {Object}                                   props
 * @param {string}                                   props.eyebrow
 * @param {import('react').ReactNode}                props.title
 * @param {import('react').ReactNode}                props.lede
 * @param {import('react').ReactNode}                props.actions
 * @param {Array}                                    props.stackRows
 * @param {{pill:string,tone:string,facts:string[]}} props.summary
 */
export default function Hero( {
	eyebrow,
	title,
	lede,
	actions,
	stackRows,
	summary,
} ) {
	const [ open, setOpen ] = useState( true );

	useEffect( () => {
		let cancelled = false;
		apiFetch( { path: '/wp/v2/users/me?context=edit' } )
			.then( ( me ) => {
				if ( cancelled ) {
					return;
				}
				const collapsed = Boolean( me?.meta?.[ META_KEY ] );
				setOpen( ! collapsed );
			} )
			.catch( () => {} );
		return () => {
			cancelled = true;
		};
	}, [] );

	const toggle = () => {
		const next = ! open;
		setOpen( next );
		try {
			window.localStorage.setItem(
				'wpcy_overview_hero_collapsed',
				next ? '0' : '1'
			);
		} catch ( err ) {
			void err;
		}
		apiFetch( {
			path: '/wp/v2/users/me',
			method: 'POST',
			data: { meta: { [ META_KEY ]: ! next } },
		} ).catch( () => {} );
	};

	let pillTone = '';
	if ( summary?.tone === 'ok' ) {
		pillTone = 'ok';
	} else if ( summary?.tone === 'warn' ) {
		pillTone = 'warn';
	}

	return (
		<section
			className={ [ 'card', 'hero-w', open ? 'is-open' : '' ]
				.filter( Boolean )
				.join( ' ' ) }
			data-hero
		>
			<button
				className="hero-collapse"
				type="button"
				aria-expanded={ open ? 'true' : 'false' }
				aria-label={ __( '收起', 'wp-china-yes' ) }
				onClick={ toggle }
			>
				<Icon name="chevron" size={ 16 } />
				<span>{ __( '收起', 'wp-china-yes' ) }</span>
			</button>
			<div className="hero-body hero-body-v6">
				<div className="hero-l">
					<div className="eyebrow">{ eyebrow }</div>
					<h1>{ title }</h1>
					<p>{ lede }</p>
					<div className="cta">{ actions }</div>
				</div>
				<SvcStack rows={ stackRows } />
			</div>
			<button
				className="hero-bar"
				type="button"
				aria-expanded={ open ? 'true' : 'false' }
				onClick={ toggle }
			>
				<Pill tone={ pillTone }>{ summary?.pill }</Pill>
				{ ( summary?.facts || [] ).map( ( fact ) => (
					<span className="sf" key={ fact }>
						{ fact }
					</span>
				) ) }
				<span className="hero-bar-more">
					<Icon name="chevron" size={ 16 } />
					{ __( '展开', 'wp-china-yes' ) }
				</span>
			</button>
		</section>
	);
}
