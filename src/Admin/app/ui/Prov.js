/**
 * Provider brand chip. SH-09 .prov.
 *
 */

/**
 * @param {Object} props
 * @param {string} props.children Brand name.
 */
export default function Prov( { children } ) {
	if ( ! children ) {
		return null;
	}
	return <span className="prov">{ children }</span>;
}
