/**
 * Scope chip. Prototype .scope.
 *
 */

/**
 * @param {Object}                    props
 * @param {string}                    [props.href]
 * @param {import('react').ReactNode} props.children
 */
export default function Scope( { href, children } ) {
	if ( href ) {
		return (
			<a className="scope" href={ href }>
				{ children }
			</a>
		);
	}
	return <span className="scope">{ children }</span>;
}
