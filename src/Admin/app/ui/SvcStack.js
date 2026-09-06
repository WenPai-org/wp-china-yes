/**
 * OV-18 core-service connectivity stack.
 *
 */

import { __ } from '@wordpress/i18n';
import Icon from './icons';
import Pill from './Pill';
import Scope from './Scope';
import Btn from './Btn';
import { adminPageUrl, PAGES } from '../routing';

const PILL_TONE = {
	on: 'ok',
	fallback: 'warn',
	down: 'bad',
	direct: '',
	off: '',
	paused: '',
	unchecked: '',
};

/**
 * @param {Object} props
 * @param {Array}  props.rows
 * @param {string} [props.title]
 */
export default function SvcStack( {
	rows,
	title = __( '核心服务', 'wp-china-yes' ),
} ) {
	return (
		<div className="hero-svc">
			<div className="hero-svc-h">{ title }</div>
			<ul className="svc-stack">
				{ ( rows || [] ).map( ( row ) => {
					let actionHref = '';
					if ( row.action === 'enable-services' ) {
						actionHref = adminPageUrl( PAGES.services );
					} else if ( row.action === 'enable-connect' ) {
						actionHref = adminPageUrl( PAGES.connect );
					}
					const label = row.name + ' ' + row.word;
					return (
						<li
							key={ row.name }
							className={ row.key }
							aria-label={ label }
						>
							<div
								className={ [ 'tile', row.tone ]
									.filter( Boolean )
									.join( ' ' ) }
							>
								<Icon name={ row.icon } size={ 18 } />
							</div>
							<div className="svc-t">
								<div className="n">
									{ row.name }
									{ row.extra ? (
										<Scope>{ row.extra }</Scope>
									) : null }
								</div>
								<div className="p">{ row.line }</div>
							</div>
							<div className="svc-r">
								<Pill
									tone={ PILL_TONE[ row.status ] || row.tone }
								>
									{ row.word }
								</Pill>
								{ actionHref ? (
									<Btn variant="ghost" href={ actionHref }>
										{ __( '启用', 'wp-china-yes' ) }
									</Btn>
								) : null }
							</div>
						</li>
					);
				} ) }
			</ul>
		</div>
	);
}
