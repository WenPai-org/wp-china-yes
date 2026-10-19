/**
 * Route list. Prototype .routes, each row with Prov.
 *
 */

import Prov from './Prov';

/**
 * @param {Object}                                                                                      props
 * @param {Array<{id:string,tone:string,name:string,provider:string,desc:string,ms:string,ago:string}>} props.rows
 */
export default function Routes( { rows } ) {
	return (
		<ul className="routes">
			{ ( rows || [] ).map( ( row ) => (
				<li key={ row.id || row.name }>
					<span
						className={ [ 'rdot', row.tone ]
							.filter( Boolean )
							.join( ' ' ) }
					/>
					<div>
						<div className="t">
							{ row.name }
							<Prov>{ row.provider }</Prov>
						</div>
						<div className="d">{ row.desc }</div>
					</div>
					<span className="ms">{ row.ms }</span>
					<span className="ago">{ row.ago }</span>
				</li>
			) ) }
		</ul>
	);
}
