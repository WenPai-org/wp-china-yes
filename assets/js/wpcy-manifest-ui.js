/**
 * Signed mirror manifest status refresh and client-probe toggle handler (W7-W8).
 */
( function ( doc, win ) {
	'use strict';

	function sourceLabel( source, has ) {
		if ( ! has ) {
			return '静态内置映射表';
		}
		if ( source === 'primary' ) {
			return '主渠道签名清单（updates.wenpai.net）';
		}
		if ( source === 'secondary' ) {
			return '备用渠道签名清单（OSS 镜像源）';
		}
		return '本地最近一次已验证缓存（LKG）';
	}

	function formatAge( age, stale ) {
		if ( age === null || age === undefined ) {
			return '-';
		}
		var n = Number( age );
		if ( stale ) {
			return '已超过 7 天安全期（' + Math.floor( n / 3600 ) + ' 小时前），已回退静态表';
		}
		if ( n < 60 ) {
			return '刚刚更新';
		}
		if ( n < 3600 ) {
			return Math.floor( n / 60 ) + ' 分钟前';
		}
		return Math.floor( n / 3600 ) + ' 小时前';
	}

	function updateField( card, name, text ) {
		var el = card.querySelector( '[data-wpcy-manifest-field="' + name + '"] dd, [data-wpcy-manifest-field="' + name + '"] .wenpai-status-value' );
		if ( ! el ) {
			el = card.querySelector( '[data-wpcy-manifest-field="' + name + '"]' );
		}
		if ( el ) {
			el.textContent = text;
		}
	}

	function handleRefresh( btn ) {
		var cfg = win.wpcyAdmin || {};
		var root = ( cfg.restRoot || '' ).replace( /\/+$/, '' );
		if ( ! root || ! win.fetch ) {
			return;
		}

		btn.disabled = true;
		var origText = btn.textContent;
		btn.textContent = '同步中...';

		win.fetch( root + '/wpcy/v1/manifest/refresh', {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'X-WP-Nonce': cfg.nonce || '',
				'Content-Type': 'application/json'
			}
		} )
			.then( function ( resp ) { return resp.json(); } )
			.then( function ( data ) {
				var card = doc.querySelector( '[data-wpcy-manifest-card]' );
				var m = data && data.manifest;
				if ( card && m ) {
					updateField( card, 'active_source', sourceLabel( m.active_source, !!m.has_manifest ) );
					updateField( card, 'version', m.has_manifest ? ( '#' + m.version + ' · ' + ( m.issued_at || '-' ) ) : '尚未同步（使用内置静态表）' );
					updateField( card, 'age', formatAge( m.age_seconds, !!m.stale_lkg ) );
					updateField( card, 'key_id', m.key_id || '-' );
					updateField( card, 'kill_switch', m.kill_switch ? '已触发直连回源（全量暂停加速）' : '正常签名调度' );
					if ( m.last_error || m.switch_reason ) {
						updateField( card, 'switch_reason', ( m.last_error || '' ) + ( m.switch_reason ? ' · ' + m.switch_reason : '' ) );
					}
				}
				btn.textContent = ( data && data.sync && data.sync.ok ) ? '已刷新' : '校验未通过（保留缓存）';
			} )
			.catch( function () {
				btn.textContent = '刷新失败';
			} )
			.finally( function () {
				win.setTimeout( function () {
					btn.disabled = false;
					btn.textContent = origText;
				}, 1800 );
			} );
	}

	function handleProbeToggle( input ) {
		var cfg = win.wpcyAdmin || {};
		var root = ( cfg.restRoot || '' ).replace( /\/+$/, '' );
		if ( ! root || ! win.fetch ) {
			return;
		}
		var enabled = !!input.checked;
		var wrap = input.closest ? input.closest( '.wenpai-switch' ) : null;
		var labelEl = wrap ? wrap.querySelector( '.wenpai-switch__label' ) : null;
		if ( labelEl ) {
			labelEl.textContent = enabled ? '已开启' : '未开启';
		}
		var isNet = cfg.capabilities && cfg.capabilities.network_admin;
		var endpoint = root + ( isNet ? '/wpcy/v1/network-settings' : '/wpcy/v1/settings' );
		input.disabled = true;

		win.fetch( endpoint, {
			method: 'PUT',
			credentials: 'same-origin',
			headers: {
				'X-WP-Nonce': cfg.nonce || '',
				'Content-Type': 'application/json'
			},
			body: JSON.stringify( { diagnostics: { client_probe_enabled: enabled } } )
		} )
			.then( function ( resp ) {
				if ( ! resp.ok ) {
					throw new Error( 'save_failed' );
				}
				if ( typeof win.wenpaiToast === 'function' ) {
					win.wenpaiToast( '已保存', 'ok' );
				}
			} )
			.catch( function () {
				input.checked = ! enabled;
				if ( labelEl ) {
					labelEl.textContent = input.checked ? '已开启' : '未开启';
				}
				if ( typeof win.wenpaiToast === 'function' ) {
					win.wenpaiToast( '暂时无法保存设置，请稍后重试。', 'bad' );
				}
			} )
			.finally( function () {
				input.disabled = false;
			} );
	}

	doc.addEventListener( 'click', function ( ev ) {
		var target = ev.target && ev.target.closest ? ev.target.closest( '[data-wpcy-action="manifest-refresh"]' ) : null;
		if ( target ) {
			ev.preventDefault();
			handleRefresh( target );
		}
	} );

	doc.addEventListener( 'change', function ( ev ) {
		var target = ev.target && ev.target.closest ? ev.target.closest( '[data-wpcy-action="client-probe-toggle"]' ) : null;
		if ( target ) {
			handleProbeToggle( target );
		}
	} );
}( document, window ) );
