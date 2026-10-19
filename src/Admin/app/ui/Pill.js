/**
 * Status pill. Prototype .pill / .pill.ok / .warn / .bad.
 *
 */

/**
 * @param {Object} props
 * @param {string} [props.tone]   ok | warn | bad | ''
 * @param {string} props.children
 */
export default function Pill( { tone = '', children } ) {
	return (
		<span className={ [ 'pill', tone ].filter( Boolean ).join( ' ' ) }>
			{ children }
		</span>
	);
}
