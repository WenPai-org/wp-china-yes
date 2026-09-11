/**
 * Recent events, single-line list. OV-动态 v2.2: ≤6, time left, warn amber.
 */

/**
 * @param {Object}                                                 props
 * @param {Array<{id:string,time:string,text:string,tone:string}>} props.items
 */
export default function EventsSimple( { items } ) {
	return (
		<ul className="evx">
			{ ( items || [] ).map( ( item ) => (
				<li
					key={ item.id || item.text }
					className={
						item.tone === 'warn' ? 'warn' : item.tone || ''
					}
				>
					<span className="et">{ item.time }</span>
					<span className="ex">{ item.text }</span>
				</li>
			) ) }
		</ul>
	);
}
