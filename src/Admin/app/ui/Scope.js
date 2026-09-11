/**
 * Scope chip. Prototype .scope.
 *
 */

/**
 * @param {Object}                    props
 * @param {string}                    [props.href]
 * @param {string}                    [props.className]
 * @param {import('react').ReactNode} props.children
 */
export default function Scope( { href, children, className = '' } ) {
	const cls = [ 'scope', className ].filter( Boolean ).join( ' ' );
	if ( href ) {
		return (
			<a className={ cls } href={ href }>
				{ children }
			</a>
		);
	}
	return <span className={ cls }>{ children }</span>;
}
