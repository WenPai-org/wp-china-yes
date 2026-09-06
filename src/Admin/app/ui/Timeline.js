/**
 * Event timeline. Prototype .tl.
 *
 */

/**
 * @param {Object}                                                                props
 * @param {Array<{id:string,tone:string,time:string,title:string,detail:string}>} props.items
 */
export default function Timeline( { items } ) {
	return (
		<ul className="tl">
			{ ( items || [] ).map( ( item ) => (
				<li
					key={ item.id || item.title }
					className={
						item.tone === 'ok' || item.tone === 'warn'
							? item.tone
							: ''
					}
				>
					<time>{ item.time }</time>
					<div className="e">{ item.title }</div>
					<div className="d">{ item.detail }</div>
				</li>
			) ) }
		</ul>
	);
}
