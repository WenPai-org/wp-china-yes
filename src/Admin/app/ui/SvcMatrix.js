/**
 * Core-services matrix. Replaces OV-18 stack (v2.2).
 */

import { __ } from '@wordpress/i18n';
import Prov from './Prov';
import Scope from './Scope';
import Btn from './Btn';
import { adminPageUrl, PAGES } from '../routing';

/**
 * @param {Object} props
 * @param {Array}  props.rows
 */
export default function SvcMatrix( { rows } ) {
	return (
		<ul className="svc-mx">
			{ ( rows || [] ).map( ( row ) => {
				let actionHref = '';
				if ( row.action === 'enable-services' ) {
					actionHref = adminPageUrl( PAGES.services );
				} else if ( row.action === 'enable-connect' ) {
					actionHref = adminPageUrl( PAGES.connect );
				}
				const label = row.name + ' ' + row.word;
				const showDesc =
					row.desc &&
					( row.status === 'fallback' || row.status === 'down' );
				return (
					<li
						key={ row.name }
						className={ row.dot || row.key }
						aria-label={ label }
					>
						<div className="svc-r1">
							<span
								className={ [ 'rdot', row.dot ]
									.filter( Boolean )
									.join( ' ' ) }
							/>
							<div className="svc-n">
								<span className="t">{ row.name }</span>
								<Prov>{ row.provider }</Prov>
								{ row.extra ? (
									<Scope>{ row.extra }</Scope>
								) : null }
							</div>
							{ row.word ? (
								<span
									className={ [ 'w', row.dot ]
										.filter( Boolean )
										.join( ' ' ) }
								>
									{ row.word }
								</span>
							) : null }
							<span className="meta-r">
								{ actionHref ? (
									<Btn variant="ghost" href={ actionHref }>
										{ __( '启用', 'wp-china-yes' ) }
									</Btn>
								) : (
									row.meta
								) }
							</span>
						</div>
						{ showDesc ? (
							<div className="d">{ row.desc }</div>
						) : null }
					</li>
				);
			} ) }
		</ul>
	);
}
