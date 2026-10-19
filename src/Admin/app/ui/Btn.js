/**
 * Button: WP Button keyboard, .btn* appearance.
 *
 */

import { Button } from '@wordpress/components';

const VARIANT_CLASS = {
	primary: 'btn-primary',
	secondary: 'btn-secondary',
	ghost: 'btn-ghost',
	danger: 'btn-danger',
};

/**
 * @param {Object}                                 props
 * @param {'primary'|'secondary'|'ghost'|'danger'} [props.variant]
 * @param {string}                                 [props.href]
 * @param {string}                                 [props.className]
 * @param {boolean}                                [props.disabled]
 * @param {Function}                               [props.onClick]
 * @param {string}                                 [props.type]
 * @param {import('react').ReactNode}              props.children
 */
export default function Btn( {
	variant = 'secondary',
	href,
	className = '',
	disabled,
	onClick,
	type = 'button',
	children,
	...rest
} ) {
	const cls = [
		'btn',
		VARIANT_CLASS[ variant ] || 'btn-secondary',
		className,
	]
		.filter( Boolean )
		.join( ' ' );
	return (
		<Button
			className={ cls }
			href={ href }
			disabled={ disabled }
			onClick={ onClick }
			type={ href ? undefined : type }
			{ ...rest }
		>
			{ children }
		</Button>
	);
}
