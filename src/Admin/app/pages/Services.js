/**
 * 文派服务 placeholder. Binding and apps ship in later tasks.
 */

import { __ } from '@wordpress/i18n';
import PageShell from '../components/PageShell';

export default function Services() {
	return (
		<PageShell title={ __( '文派服务', 'wp-china-yes' ) }>
			<p>{ __( '绑定与小工具将在后续版本提供', 'wp-china-yes' ) }</p>
		</PageShell>
	);
}
