/**
 * Per-region read failure. SH-06.
 *
 */

import { __, sprintf } from '@wordpress/i18n';
import Notice from './Notice';
import Btn from './Btn';

/**
 * @param {Object}   props
 * @param {string}   props.what    Region name from the glossary.
 * @param {Function} props.onRetry
 */
export default function LoadError( { what, onRetry } ) {
	return (
		<Notice
			tone="warn"
			act
			action={
				<Btn variant="secondary" onClick={ onRetry }>
					{ __( '重试', 'wp-china-yes' ) }
				</Btn>
			}
		>
			{ sprintf(
				/* translators: %s: region name */
				__( '暂时无法读取%s，请刷新页面重试。', 'wp-china-yes' ),
				what
			) }
		</Notice>
	);
}
