/**
 * Notice: info / warn / warn.act.
 *
 */

import Icon from './icons';

/**
 * @param {Object}                    props
 * @param {'info'|'warn'}             [props.tone]
 * @param {boolean}                   [props.act]
 * @param {string}                    [props.icon]
 * @param {import('react').ReactNode} [props.action]
 * @param {string}                    [props.className]
 * @param {import('react').ReactNode} props.children
 */
export default function Notice( {
	tone = 'info',
	act = false,
	icon = 'info',
	action,
	className = '',
	children,
} ) {
	const cls = [ 'notice', tone, act ? 'act' : '', className ]
		.filter( Boolean )
		.join( ' ' );
	return (
		<div className={ cls }>
			<Icon name={ icon } size={ 18 } />
			<span>{ children }</span>
			{ action }
		</div>
	);
}
