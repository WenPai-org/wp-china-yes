import { createElement, useCallback } from 'react';
import { Card, CardHead } from './Card.js';
import { Field } from './Field.js';
import { Btn } from './Btn.js';

function cx(...parts) {
	return parts.filter(Boolean).join(' ');
}

/**
 * Repeater row (Track D / E08).
 * Endpoint-like fold rows = Card composition only.
 * Ban EndpointCard / ModuleCard / StrategyCard.
 */
export function RepeaterRow({
	index,
	children,
	card = true,
	onRemove,
	onMoveUp,
	onMoveDown,
	itemLabel = '第 %d 项',
	deleteLabel = '删除第 %d 项',
	className = '',
	...rest
}) {
	const n = index + 1;
	const legend = itemLabel.replace('%d', String(n));
	const del = deleteLabel.replace('%d', String(n));
	const actions = createElement(
		'div',
		{ className: 'wpcy-repeater__row-actions' },
		onMoveUp
			? createElement(Btn, { type: 'button', variant: 'ghost', onClick: onMoveUp }, '上移')
			: null,
		onMoveDown
			? createElement(Btn, { type: 'button', variant: 'ghost', onClick: onMoveDown }, '下移')
			: null,
		onRemove
			? createElement(Btn, { type: 'button', variant: 'ghost', onClick: onRemove }, del)
			: null
	);
	const inner = createElement(
		'fieldset',
		{
			...rest,
			className: cx('wpcy-repeater__row', !card && className),
			'data-wpcy-repeater-row': '',
			'data-wpcy-repeater-index': String(index),
		},
		createElement(
			'legend',
			{ className: cx('wpcy-repeater__legend', card && 'screen-reader-text') },
			legend
		),
		card ? createElement(CardHead, { title: legend, headingLevel: 3 }) : null,
		createElement('div', { className: 'wpcy-repeater__fields' }, children),
		actions
	);
	if (!card) return inner;
	return createElement(
		Card,
		{
			tight: true,
			as: 'div',
			className: cx('wpcy-repeater__card', className),
		},
		inner
	);
}

/**
 * Controlled repeater list — Field/Card exports; kit owns sanitize/max_rows persistence.
 */
export function Repeater({
	value = [],
	onChange,
	maxRows = 20,
	renderRow,
	createRow = () => ({}),
	card = true,
	addLabel = '添加一项',
	className = '',
	...rest
}) {
	const rows = Array.isArray(value) ? value : [];
	const setRows = useCallback(
		(next) => {
			if (typeof onChange === 'function') onChange(next);
		},
		[onChange]
	);
	const add = () => {
		if (rows.length >= maxRows) return;
		setRows(rows.concat([createRow()]));
	};
	const remove = (i) => setRows(rows.filter((_, idx) => idx !== i));
	const move = (i, dir) => {
		const j = i + dir;
		if (j < 0 || j >= rows.length) return;
		const next = rows.slice();
		const tmp = next[i];
		next[i] = next[j];
		next[j] = tmp;
		setRows(next);
	};
	return createElement(
		'div',
		{
			...rest,
			className: cx('wpcy-repeater', card && 'wpcy-repeater--cards', className),
			'data-wpcy-repeater': '',
			'data-wpcy-repeater-max': String(maxRows),
		},
		createElement(
			'div',
			{ className: 'wpcy-repeater__rows' },
			rows.map((row, i) =>
				createElement(
					RepeaterRow,
					{
						key: row && row.id != null ? String(row.id) : `r-${i}`,
						index: i,
						card,
						onRemove: () => remove(i),
						onMoveUp: i > 0 ? () => move(i, -1) : undefined,
						onMoveDown: i < rows.length - 1 ? () => move(i, 1) : undefined,
					},
					typeof renderRow === 'function'
						? renderRow(row, i, {
								update: (patch) => {
									const next = rows.slice();
									next[i] = Object.assign({}, next[i], patch);
									setRows(next);
								},
						  })
						: null
				)
			)
		),
		createElement(
			'p',
			{ className: 'wpcy-repeater__toolbar' },
			createElement(
				Btn,
				{
					type: 'button',
					variant: 'secondary',
					disabled: rows.length >= maxRows,
					onClick: add,
				},
				addLabel
			)
		)
	);
}

Repeater.Row = RepeaterRow;
Repeater.Field = Field;
export default Repeater;
