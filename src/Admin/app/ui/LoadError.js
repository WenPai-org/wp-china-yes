/**
 * Per-region read failure. SH-06.
 *
 * The visible notice is not a live region; its text is announced once through the
 * persistent @wordpress/a11y live region via speak() (admin-ui-spec §8).
 */

import { __, sprintf } from '@wordpress/i18n';
import Notice from './Notice';
import Btn from './Btn';
// @wordpress/a11y ships with WordPress core (wp-a11y) and is externalized by wp-scripts.
// eslint-disable-next-line import/no-extraneous-dependencies
import { speak } from '@wordpress/a11y';
import { useEffect } from '@wordpress/element';

/**
 * @param {Object}   props
 * @param {string}   props.what    Region name from the glossary.
 * @param {Function} props.onRetry
 */
export default function LoadError( { what, onRetry } ) {
	const text = sprintf(
		/* translators: %s: region name */
		__( '暂时无法读取%s，请刷新页面重试。', 'wp-china-yes' ),
		what
	);
	useEffect( () => {
		// @wordpress/a11y keeps one persistent live region; speak once per error.
		speak( text, 'polite' );
	}, [ text ] );
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
			{ text }
		</Notice>
	);
}
