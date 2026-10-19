/**
 * Section heading: title + right note + action.
 *
 */

/**
 * @param {Object}                    props
 * @param {import('react').ReactNode} props.title
 * @param {import('react').ReactNode} [props.note]
 * @param {import('react').ReactNode} [props.action]
 * @param {boolean}                   [props.caps]
 * @param {import('react').ReactNode} [props.children]
 */
export default function Sec( { title, note, action, caps = false, children } ) {
	const cls = [ 'sec-h', caps ? 'caps' : '' ].filter( Boolean ).join( ' ' );
	return (
		<div className={ cls }>
			<h2>{ title }</h2>
			{ note || action ? (
				<div className="sec-r">
					{ note ? <p>{ note }</p> : null }
					{ action }
				</div>
			) : null }
			{ children }
		</div>
	);
}
