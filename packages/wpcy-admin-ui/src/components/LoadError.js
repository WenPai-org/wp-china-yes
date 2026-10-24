/**
 * Per-region read failure (CTRL-17 / SH-06).
 *
 * The visible notice is not a live region; text is announced through the
 * persistent @wordpress/a11y live region via speak() (admin-ui-spec §8).
 *
 * Failures within ANNOUNCE_WINDOW_MS of the first one form one load round
 * and are announced together.
 */

import { createElement, useEffect } from 'react';
import { __, _n, sprintf } from '@wordpress/i18n';
import { speak } from '@wordpress/a11y';
import { Notice } from './Notice.js';
import { Btn } from './Btn.js';

/** Collection window for one load round, from the first failure. */
export const ANNOUNCE_WINDOW_MS = 300;

/** Default text domain (leaf); override via `textDomain` prop if needed. */
const DEFAULT_DOMAIN = 'wp-china-yes';

let pending = [];
let timer = null;

function flush() {
	timer = null;
	const round = pending;
	pending = [];
	if (round.length === 0) {
		return;
	}
	if (round.length === 1) {
		speak(round[0].text, 'polite');
		return;
	}
	const domain = round[0].domain || DEFAULT_DOMAIN;
	speak(
		sprintf(
			/* translators: %d: number of regions that failed to load */
			_n(
				'有 %d 项内容暂时无法读取，请刷新页面重试。',
				'有 %d 项内容暂时无法读取，请刷新页面重试。',
				round.length,
				domain
			),
			round.length
		),
		'polite'
	);
}

function enqueue(entry) {
	pending.push(entry);
	if (timer === null) {
		timer = setTimeout(flush, ANNOUNCE_WINDOW_MS);
	}
}

function dequeue(entry) {
	pending = pending.filter((item) => item !== entry);
}

/**
 * @param {object} props
 * @param {string} props.what Region name from the glossary.
 * @param {Function} props.onRetry
 * @param {string} [props.textDomain='wp-china-yes']
 * @param {string} [props.retryLabel] Override for the retry button label.
 * @param {string} [props.className]
 */
export function LoadError({
	what,
	onRetry,
	textDomain = DEFAULT_DOMAIN,
	retryLabel,
	className = '',
}) {
	const text = sprintf(
		/* translators: %s: region name */
		__('暂时无法读取%s，请刷新页面重试。', textDomain),
		what
	);
	const retry = retryLabel ?? __('重试', textDomain);

	useEffect(() => {
		const entry = { text, domain: textDomain };
		enqueue(entry);
		return () => dequeue(entry);
	}, [text, textDomain]);

	return createElement(
		Notice,
		{
			level: 'warn',
			act: true,
			className,
			action: createElement(
				Btn,
				{ variant: 'secondary', onClick: onRetry },
				retry
			),
		},
		text
	);
}

export default LoadError;
