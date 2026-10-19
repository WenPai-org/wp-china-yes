/**
 * Status as a dot plus text. Color is never used alone.
 */

/**
 * @param {Object} props
 * @param {string} props.tone  success|warning|danger|neutral
 * @param {string} props.label Visible status text.
 */
export default function StatusDot( { tone, label } ) {
	return (
		<span className="wpcy-status">
			<span
				className={ `wpcy-status-dot is-${ tone }` }
				aria-hidden="true"
			/>
			{ label }
		</span>
	);
}
