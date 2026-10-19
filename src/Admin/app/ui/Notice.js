/**
 * Notice: info / warn / warn.act / bad (red, CO-01 save failure).
 *
 */

import Icon from './icons';

/**
 * @param {Object}                    props
 * @param {'info'|'warn'|'bad'}       [props.tone]
 * @param {string}                    [props.role]      alert / status for live regions.
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
	role,
	children,
} ) {
	const cls = [ 'notice', tone, act ? 'act' : '', className ]
		.filter( Boolean )
		.join( ' ' );
	return (
		<div className={ cls } role={ role }>
			<Icon name={ icon } size={ 18 } />
			<span>{ children }</span>
			{ action }
		</div>
	);
}
