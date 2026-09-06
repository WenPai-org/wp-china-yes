/**
 * Toggle with visible label text and aria-checked.
 *
 */

/**
 * @param {Object}   props
 * @param {boolean}  props.checked
 * @param {string}   props.label
 * @param {Function} [props.onChange]
 * @param {boolean}  [props.disabled]
 */
export default function Toggle( { checked, label, onChange, disabled } ) {
	const cls = [ 'toggle', checked ? 'on' : '', disabled ? 'off-dis' : '' ]
		.filter( Boolean )
		.join( ' ' );
	return (
		<button
			type="button"
			className={ cls }
			role="switch"
			aria-checked={ checked ? 'true' : 'false' }
			disabled={ disabled }
			onClick={ () => {
				if ( ! disabled && onChange ) {
					onChange( ! checked );
				}
			} }
		>
			<i />
			{ label }
		</button>
	);
}
