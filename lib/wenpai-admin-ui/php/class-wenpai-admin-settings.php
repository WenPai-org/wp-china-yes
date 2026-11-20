<?php
/**
 * Declarative settings framework (roadmap layer 3).
 *
 * @package WenpaiAdminUI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Wenpai_Admin_Settings' ) ) {
	/**
	 * One instance per option.
	 *
	 * Field schema keys: type, default, label, desc, choices, choices_from, rows,
	 * row_label, col_label, unset_value, min, max, step, unit, secret, sanitize,
	 * validate, depends_on, tab, layout, actions, status, render.
	 * Types: toggle, select, radio, choice, number, text, textarea, multicheck,
	 * multiselect, matrix, secret, map, status, entity, file, tags, schedule,
	 * icon, time, datetime-local, week, range. See docs/framework/settings.md.
	 */
	class Wenpai_Admin_Settings {

		/**
		 * Submitted list of secret field ids to clear: {option}[__wenpai_clear][].
		 */
		const CLEAR_KEY = '__wenpai_clear';

		/**
		 * Submitted tab id: {option}[__wenpai_tab]. Only that tab's fields are written.
		 */
		const TAB_KEY = '__wenpai_tab';

		/**
		 * Posted key carrying the nonce of the tab marker.
		 */
		const TAB_NONCE_KEY = '__wenpai_tab_nonce';

		/**
		 * Posted list of secret field ids where kit JS caught a multi-line paste.
		 */
		const MULTILINE_KEY = '__wenpai_multiline';

		/**
		 * Settings error code that tags an options.php save of this option, so the
		 * redirect can be recognised and core's duplicate 「Settings saved.」 dropped.
		 */
		const SAVED_CODE = 'wenpai_saved';

		/**
		 * Stored schema version key. Written only when register() was given 'version'.
		 */
		const SCHEMA_KEY = '_schema';

		/**
		 * Instances keyed by option name.
		 *
		 * @var array<string, Wenpai_Admin_Settings>
		 */
		private static $registry = array();

		/**
		 * Whether the kit 0.8 wp_ajax endpoints were hooked this request.
		 *
		 * @var bool
		 */
		private static $ajax_endpoints = false;

		/**
		 * File-field mime whitelists for wp_ajax_wenpai_file_upload, keyed
		 * {option}__{field id}. Populated when a file control is rendered and
		 * backfilled from registered schemas in file_field_mimes().
		 *
		 * @var array<string, mixed>
		 */
		private static $file_fields = array();

		/**
		 * Whether any registered schema has a media field this request; gates
		 * the scoped wp_enqueue_media() in maybe_enqueue_media() (kit 0.9.2).
		 *
		 * @var bool
		 */
		private static $needs_media = false;

		/**
		 * Option name in wp_options.
		 *
		 * @var string
		 */
		private $option;

		/**
		 * Settings API option group.
		 *
		 * @var string
		 */
		private $group;

		/**
		 * Field schema keyed by field id, with defaults filled in.
		 *
		 * @var array<string, array>
		 */
		private $fields;

		/**
		 * UI strings. The kit has no text domain; plugins pass translated strings.
		 *
		 * @var array<string, string>
		 */
		private $strings;

		/**
		 * Field errors recorded during sanitize() in this request: id => message.
		 *
		 * @var array<string, string>
		 */
		private $errors = array();

		/**
		 * Matrix rows in error: field id => row keys.
		 *
		 * @var array<string, string[]>
		 */
		private $error_rows = array();

		/**
		 * Output of the last sanitize() call, so a second pass over the same
		 * values (a plugin sanitize_callback wrapping ours) changes nothing.
		 *
		 * @var array|null
		 */
		private $last_sanitized = null;

		/**
		 * Whether the "tabbed schema, no tab marker" notice was given this request.
		 *
		 * @var bool
		 */
		private $warned_marker = false;

		/**
		 * Form-level error of this request (e.g. an expired tab marker).
		 *
		 * @var string
		 */
		private $form_error = '';

		/**
		 * Settings errors of this option taken out of core's list on the page
		 * that shows them (see claim_core_notices()); null until claimed.
		 *
		 * @var array|null
		 */
		private $claimed = null;

		/**
		 * True while import() runs sanitize(): no tab-marker notice there.
		 *
		 * @var bool
		 */
		private $importing = false;

		/**
		 * Schema version from register(). 1 when the caller omitted 'version'.
		 *
		 * @var int
		 */
		private $version = 1;

		/**
		 * Whether register() was given an explicit 'version' (then sanitize writes _schema).
		 *
		 * @var bool
		 */
		private $version_explicit = false;

		/**
		 * Upgrade callbacks keyed by target version.
		 *
		 * @var array<int, callable>
		 */
		private $upgrades = array();

		/**
		 * Tools instance when register() was given 'tools'; otherwise null.
		 *
		 * @var Wenpai_Admin_Tools|null
		 */
		private $tools = null;

		/**
		 * Group id => label map for `?section=` resolution (kit 0.10).
		 * Lets schema fields with `group` hide server-side like hand-written
		 * group_attrs(); empty means "JS resolves, no-JS shows all".
		 *
		 * @var array
		 */
		private $sections = array();

		/**
		 * Create and register a settings instance for one option.
		 *
		 * @param string $option Option name.
		 * @param array  $fields Field schema keyed by field id.
		 * @param array  $args   Optional. Supports 'group', 'strings', 'layout' (row|stack, default row),
		 *                       'version', 'upgrades', 'tools', 'sections' (id=>label map for
		 *                       server-side `?section=` resolution of field `group`, kit 0.10).
		 * @return Wenpai_Admin_Settings
		 */
		public static function register( $option, array $fields, array $args = array() ) {
			$inst                      = new self( $option, $fields, $args );
			self::$registry[ $option ] = $inst;
			$needs_media               = false;
			foreach ( $inst->fields as $f ) {
				if ( in_array( $f['type'], array( 'entity', 'file' ), true ) ) {
					// Kit 0.8 pickers need their wp_ajax endpoints on every request
					// (admin-ajax.php never renders the settings page).
					self::ensure_ajax_endpoints();
				}
				if ( 'media' === $f['type'] ) {
					$needs_media = true;
				}
			}
			if ( $needs_media && ! self::$needs_media ) {
				// The media picker needs wp.media; enqueue it only on this
				// plugin's admin pages (kit 0.9.2). Products rendering the form
				// on a page outside Wenpai_Admin_Menu call wp_enqueue_media()
				// themselves.
				self::$needs_media = true;
				if ( function_exists( 'add_action' ) ) {
					add_action( 'admin_enqueue_scripts', array( __CLASS__, 'maybe_enqueue_media' ) );
				}
			}
			if ( function_exists( 'add_action' ) ) {
				add_action( 'admin_init', array( $inst, 'register_setting' ) );
				add_action( 'admin_init', array( $inst, 'maybe_upgrade' ) );
				// in_admin_header runs before options-head.php's settings_errors() and before every
				// admin_notices callback (admin-header.php), so neither core nor a plugin calling
				// settings_errors() there prints this option's entries; the page shows one kit notice.
				add_action( 'in_admin_header', array( $inst, 'claim_core_notices' ), 0 );
				// Fallback for screens that print notices without in_admin_header; no-op once claimed.
				add_action( 'all_admin_notices', array( $inst, 'claim_core_notices' ), PHP_INT_MAX );
			}
			return $inst;
		}

		/**
		 * Look up a registered instance.
		 *
		 * @param string $option Option name.
		 * @return Wenpai_Admin_Settings|null
		 */
		public static function get_instance( $option ) {
			return isset( self::$registry[ $option ] ) ? self::$registry[ $option ] : null;
		}

		/**
		 * Default UI strings (zh_CN, same wording as the WPSlug and wpsss packs).
		 *
		 * @return array<string, string>
		 */
		public static function default_strings() {
			return array(
				'on'                     => '已开启',
				'off'                    => '未开启',
				'secret_saved'           => '已保存一个值，出于安全不会显示。留空表示不修改。',
				'secret_empty'           => '还没有填写。',
				'secret_clear'           => '清除已保存的值',
				'secret_show'            => '显示',
				'media_select'           => '选择',
				'media_clear'            => '清除',
				'media_empty'            => '未选择',
				'secret_hide'            => '隐藏',
				'saved'                  => '设置已保存。',
				'saving'                 => '正在保存…',
				'partial_saved'          => '其余设置已保存。',
				/* translators: %d: number of fields that were not saved. */
				'invalid'                => '暂时无法保存 %d 项设置，请按字段下的提示修改。',
				/* translators: %d: total number of fields with errors. */
				'invalid_more'           => '等 %d 项',
				'invalid_value'          => '这一项的值无效，已保留原来的设置。',
				'follow_global'          => '跟随全局设置',
				/* translators: %s: what would be listed, e.g. 文章类型. */
				'empty_choices'          => '还没有可选的%s。',
				/* translators: 1: hidden tab label, 2: first tab label. */
				'tab_hidden'             => '当前模式不需要%1$s的设置，已切回%2$s。',
				// In-progress copy without the trailing 「…」: kit JS appends it.
				'action_busy'            => '正在处理',
				'action_ok'              => '已完成。',
				// Global fallback only. A handler names the service in its WP_Error message.
				'action_failed'          => '暂时无法完成操作，请稍后再试。',
				'tab_expired'            => '暂时无法保存，页面已过期，请刷新后再试。',
				'action_denied'          => '暂时无法完成操作，请刷新页面后再试。',
				'confirm_needed'         => '暂时无法完成操作，请先勾选确认。',
				'invalid_email'          => '请填写有效的邮箱地址。',
				'invalid_url'            => '请填写以 http:// 或 https:// 开头的地址。',
				// Screen-reader hint after a status link with target=_blank.
				'new_tab'                => '（在新标签页打开）',
				// P-7 「不再提示」 on a referral (status field). Capability labels['dismiss'] wins when set.
				'dismiss'                => '不再提示',
				/* translators: %s: the recommended product (status dismiss context, else the field label). Screen-reader only. */
				'dismiss_context'        => '：%s的推荐',
				// Announced (aria-live) after kit JS removed the referral.
				'dismissed'              => '已关闭这条推荐。',
				// A secret can only be one line (password inputs drop line breaks).
				'secret_multiline'       => '暂时无法保存密钥，请填写单行的值。',
				'imported'               => '已导入设置。',
				/* translators: %d: number of fields that were not imported. */
				'imported-partial'       => '已导入设置，其中 %d 项无效，已保留原来的值。',
				'import-invalid'         => '暂时无法导入这份文件，请确认它是本插件导出的设置。',
				'import-newer'           => '暂时无法导入较新版本的设置，请先升级插件。',
				'import-size'            => '暂时无法导入这份文件，请确认文件小于 256 KB。',
				'reset'                  => '已恢复默认设置。',
				'reset-confirm'          => '暂时无法重置，请先勾选确认。',
				'import-confirm'         => '暂时无法导入，请先勾选确认。',
				'reset_confirm'          => '我已确认要把本插件的设置恢复为默认值',
				'tools_export_title'     => '导出设置',
				'tools_export_sub'       => '下载一份设置文件（JSON），不含密钥。',
				'tools_export_button'    => '导出设置',
				'tools_import_title'     => '导入设置',
				'tools_import_sub'       => '用之前导出的文件恢复设置。密钥保持不变。',
				'tools_import_button'    => '导入设置',
				'tools_import_file'      => '设置文件',
				'tools_import_confirm'   => '我已确认用这份文件覆盖当前设置',
				'tools_import_need_file' => '先选择文件',
				'tools_reset_title'      => '重置设置',
				/* translators: %s: in-page link labelled 导出一份. */
				'tools_reset_sub'        => '把本插件的设置恢复为默认值，不可撤销。建议先%s。',
				'tools_reset_export'     => '导出一份',
				'tools_reset_button'     => '重置设置',
				'tools_reset_clear'      => '同时清除已保存的密钥',
				'tools_reset_busy'       => '正在重置…',
				'repeater_item'          => '第 %d 项',
				'repeater_delete'        => '删除第 %d 项',
				'repeater_add'           => '添加一项',
				'repeater_move_up'       => '上移',
				'repeater_move_down'     => '下移',
				/* translators: 1: selected count, 2: total count (checkgroup head). */
				'checkgroup_count'       => '已选 %1$d / %2$d',
				'checkgroup_all'         => '全选',
				'checkgroup_none'        => '清空',
				'catalog_filter'         => '过滤…',
				'entity_search'          => '搜索…',
				'entity_empty'           => '没有匹配的结果',
				'entity_clear'           => '清除',
				'file_choose'            => '选择文件',
				'file_drop'              => '拖入文件，或点击选择',
				'file_remove'            => '移除',
				'file_uploading'         => '上传中…',
				'file_failed'            => '上传失败',
				'tags_placeholder'       => '输入后回车添加',
				/* translators: %s: human-readable next run time. */
				'schedule_next'          => '下次运行：%s',
				'schedule_none'          => '未安排',
				'generate'               => '生成',
				/* translators: %s: item title (sortable move-up button aria-label). */
				'sortable_move_up'       => '把 %s 上移',
				/* translators: %s: item title (sortable move-down button aria-label). */
				'sortable_move_down'     => '把 %s 下移',
				/* translators: 1: item title, 2: new position, 3: item count (sortable live announcement). */
				'sortable_moved'         => '%1$s 已移到第 %2$d 位，共 %3$d 项',
				'chart_data'             => '查看数据',
				'statbar_empty'          => '还没有数据',
				'copy_hint'              => '（点击复制）',
			);
		}

		/**
		 * Use register() instead.
		 *
		 * @param string $option Option name.
		 * @param array  $fields Field schema keyed by field id.
		 * @param array  $args   Optional args.
		 */
		private function __construct( $option, array $fields, array $args ) {
			$this->option  = $option;
			$this->group   = isset( $args['group'] ) ? $args['group'] : $option . '_group';
			$this->strings = array_merge(
				self::default_strings(),
				isset( $args['strings'] ) && is_array( $args['strings'] ) ? $args['strings'] : array()
			);
			// 0.3.1: row (label left, control right; stacks at <=960px) is the default again, as in 0.2.x. 'stack' opts out.
			$layout         = isset( $args['layout'] ) && 'stack' === $args['layout'] ? 'stack' : 'row';
			$this->sections = isset( $args['sections'] ) && is_array( $args['sections'] ) ? $args['sections'] : array();
			$this->fields   = array();
			foreach ( $fields as $id => $f ) {
				$f = wp_parse_args(
					$f,
					array(
						'type'         => 'text',
						'default'      => '',
						'label'        => $id,
						'desc'         => '',
						'choices'      => array(),
						'choices_from' => '',
						'rows'         => array(),
						'row_label'    => '',
						'col_label'    => '',
						'unset_value'  => null,
						'secret'       => false,
						'path'         => null,
						'validate'     => null,
						'depends_on'   => array(),
						'tab'          => '',
						'layout'       => $layout,
						'actions'      => array(),
						'generate'     => null,
						'instant'      => '',
						'status'       => null,
						'render'       => null,
						'fields'       => array(),
						'max_rows'     => 20,
						'presentation' => '',
						'prefix'       => '',
						'suffix'       => '',
						'mode'         => '',
						'group'        => '',
					)
				);
				if ( 'secret' === $f['type'] ) {
					$f['secret'] = true;
				}
				if ( ! empty( $f['secret'] ) && 'secret' !== $f['type'] ) {
					// A secret is always a password box, so its value never reaches the page source.
					if ( 'text' !== $f['type'] ) {
						self::doing_it_wrong( sprintf( 'Field 「%s」: a secret is always rendered as a password input; type 「%s」 is ignored.', $id, $f['type'] ) );
					}
					$f['type'] = 'secret';
				}
				$f['path'] = self::normalize_path( isset( $f['path'] ) ? $f['path'] : null, (string) $id );
				if ( 'repeater' === $f['type'] ) {
					$f['fields']   = self::normalize_repeater_fields( isset( $f['fields'] ) ? $f['fields'] : array() );
					$f['max_rows'] = max( 1, (int) $f['max_rows'] );
					if ( ! is_array( $f['default'] ) ) {
						$f['default'] = array();
					}
				}
				$pres = sanitize_key( (string) $f['presentation'] );
				if ( 'repeater' === $f['type'] ) {
					$f['presentation'] = 'card' === $pres ? 'card' : '';
				} elseif ( in_array( $f['type'], array( 'multicheck', 'multiselect' ), true ) ) {
					// 0.7: scrollable checklist (checkgroup) and checklist + filter (catalog).
					$f['presentation'] = in_array( $pres, array( 'checkgroup', 'catalog' ), true ) ? $pres : '';
				} elseif ( 'radio' === $f['type'] ) {
					// D-33: two to four exclusive options drawn as .seg, still native radios.
					$f['presentation'] = 'seg' === $pres ? 'seg' : '';
				} else {
					$f['presentation'] = '';
				}
				if ( null !== $f['path'] && ! self::stores( $f ) ) {
					self::doing_it_wrong( sprintf( 'Field 「%s」: path is only for stored fields; ignored on type 「%s」.', $id, $f['type'] ) );
					$f['path'] = null;
				}
				$tree = self::dep_tree( $f['depends_on'] );
				if ( null !== $tree && 'disable' === $tree['mode'] && ! self::tree_has_reason( $tree ) ) {
					self::doing_it_wrong( sprintf( 'Field 「%s」: depends_on mode 「disable」 needs a 「reason」 telling the user why and how to enable it.', $id ) );
				}
				$vmode = sanitize_key( (string) $f['mode'] );
				if ( '' !== $vmode && ! in_array( $vmode, array( 'simple', 'advanced' ), true ) ) {
					self::doing_it_wrong( sprintf( 'Field 「%s」: mode must be 「simple」 or 「advanced」; ignored.', $id ) );
					$vmode = '';
				}
				$f['mode']           = $vmode;
				$f['group']          = sanitize_key( (string) $f['group'] );
				$this->fields[ $id ] = $f;
			}
			$this->version_explicit = array_key_exists( 'version', $args );
			$this->version          = $this->version_explicit ? max( 1, (int) $args['version'] ) : 1;
			$this->upgrades         = isset( $args['upgrades'] ) && is_array( $args['upgrades'] ) ? $args['upgrades'] : array();
			if ( isset( $args['tools'] ) && is_array( $args['tools'] ) ) {
				$plugin = isset( $args['tools']['plugin'] ) ? sanitize_key( (string) $args['tools']['plugin'] ) : '';
				if ( '' === $plugin ) {
					self::doing_it_wrong( 'register() tools.plugin is required (used in the export filename and envelope).' );
				} elseif ( ! class_exists( 'Wenpai_Admin_Tools', false ) ) {
					self::doing_it_wrong( 'Wenpai_Admin_Tools is not loaded; require php/load.php so boot() / complete() load class-wenpai-admin-tools.php.' );
				} else {
					$this->tools = new Wenpai_Admin_Tools( $this, $args['tools'] );
				}
			}
		}

		/**
		 * Filter caller HTML: choice icon_html (SVG only) and status render
		 * (post HTML plus SVG). Without wp_kses (outside WP) it is escaped.
		 *
		 * @param string $html    Caller HTML.
		 * @param string $context 'svg' or 'post'.
		 * @return string
		 */
		private static function kses( $html, $context ) {
			self::ensure_ui();
			if ( class_exists( 'Wenpai_Admin_UI', false ) ) {
				return Wenpai_Admin_UI::kses( $html, $context );
			}
			return function_exists( 'esc_html' ) ? esc_html( $html ) : (string) $html;
		}

		/**
		 * Load Wenpai_Admin_UI when Settings was required first.
		 */
		private static function ensure_ui() {
			if ( class_exists( 'Wenpai_Admin_UI', false ) ) {
				return;
			}
			$file = __DIR__ . '/class-wenpai-admin-ui.php';
			if ( is_readable( $file ) ) {
				require_once $file;
			}
		}

		/**
		 * Developer notice, only with WP_DEBUG on.
		 *
		 * @param string $message  Message (English, developer-facing, plain text; core escapes it when displaying).
		 * @param string $where   Function to name.
		 */
		private static function doing_it_wrong( $message, $where = 'Wenpai_Admin_Settings::register' ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && function_exists( '_doing_it_wrong' ) ) {
				_doing_it_wrong( $where, $message, '0.3.0' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plain developer text from field ids; core escapes it when displaying.
			}
		}

		/**
		 * Whether a depends_on tree carries reason copy anywhere.
		 *
		 * @param array $node Normalized node.
		 * @return bool
		 */
		private static function tree_has_reason( array $node ) {
			if ( isset( $node['reason'] ) && '' !== $node['reason'] ) {
				return true;
			}
			foreach ( isset( $node['rules'] ) ? $node['rules'] : array() as $r ) {
				if ( self::tree_has_reason( $r ) ) {
					return true;
				}
			}
			return false;
		}

		/**
		 * Register the option with the Settings API. Hooked on admin_init.
		 */
		public function register_setting() {
			register_setting(
				$this->group,
				$this->option,
				array(
					'type'              => 'array',
					'default'           => array(),
					'show_in_rest'      => false,
					'sanitize_callback' => array( $this, 'sanitize' ),
				)
			);
		}

		/**
		 * Settings API option group.
		 *
		 * @return string
		 */
		public function group() {
			return $this->group;
		}

		/**
		 * Option name.
		 *
		 * @return string
		 */
		public function option() {
			return $this->option;
		}

		/**
		 * Field schema with defaults filled in.
		 *
		 * @return array
		 */
		public function fields() {
			return $this->fields;
		}

		/**
		 * Nonce action of a tab marker.
		 *
		 * @param string $tab Tab id.
		 * @return string
		 */
		private function tab_nonce_action( $tab ) {
			return 'wenpai_tab_' . $this->option . '_' . $tab;
		}

		/**
		 * Whether a posted tab marker was issued by tab_input_html().
		 *
		 * @param string $tab   Tab id.
		 * @param mixed  $nonce Posted nonce.
		 * @return bool
		 */
		private function tab_nonce_ok( $tab, $nonce ) {
			if ( ! function_exists( 'wp_verify_nonce' ) ) {
				return true;
			}
			return is_scalar( $nonce ) && false !== wp_verify_nonce( (string) $nonce, $this->tab_nonce_action( $tab ) );
		}

		/**
		 * Whether a field is posted as checkboxes: when nothing is checked the
		 * browser sends nothing, so "absent" means "empty", not "not on this
		 * page". The kit adds a hidden 0 / empty sentinel for these, but
		 * hand-written forms may not.
		 *
		 * @param array $f Field schema.
		 * @return bool
		 */
		private static function is_checkbox_like( array $f ) {
			return in_array( $f['type'], array( 'toggle', 'multicheck', 'multiselect' ), true ) && ! self::is_secret( $f );
		}

		/**
		 * Whether any field declares a tab.
		 *
		 * @return bool
		 */
		private function has_tabs() {
			foreach ( $this->fields as $f ) {
				if ( array() !== array_filter( array_map( 'strval', (array) $f['tab'] ), 'strlen' ) ) {
					return true;
				}
			}
			return false;
		}

		/**
		 * Whether a field stores a value (status fields only display).
		 *
		 * @param array $f Field schema.
		 * @return bool
		 */
		private static function stores( array $f ) {
			return 'status' !== $f['type'];
		}

		/**
		 * Default value of every stored field.
		 *
		 * @return array
		 */
		public function defaults() {
			$out = array();
			foreach ( $this->fields as $id => $f ) {
				if ( self::stores( $f ) ) {
					$out[ $id ] = $f['default'];
				}
			}
			return $out;
		}

		/**
		 * Stored option as saved, without defaults.
		 *
		 * @return array
		 */
		private function stored() {
			$stored = get_option( $this->option, array() );
			return is_array( $stored ) ? $stored : array();
		}

		/**
		 * Stored values merged over defaults. Never includes _schema.
		 * When register() was given version, old stored values are upgraded in memory.
		 *
		 * @return array
		 */
		public function all() {
			$stored = $this->stored();
			$from   = $this->stored_schema( $stored );
			unset( $stored[ self::SCHEMA_KEY ] );
			// Upgrades see the stored values as saved, so they can tell a missing key from a default.
			if ( $this->version_explicit && $from < $this->version ) {
				$up = $this->run_upgrades( $stored, $from, $this->version );
				if ( $up['ok'] ) {
					$stored = $up['values'];
				}
			}
			// Field-id view: path fields are read from nested option keys; flat fields stay top-level.
			$values = $this->flatten_stored( $stored );
			$values = wp_parse_args( $values, $this->defaults() );
			unset( $values[ self::SCHEMA_KEY ] );
			return $values;
		}

		/**
		 * Value of one field.
		 *
		 * @param string $id Field id.
		 * @return mixed|null Null for an unknown field.
		 */
		public function get( $id ) {
			$all = $this->all();
			return isset( $all[ $id ] ) ? $all[ $id ] : null;
		}

		/**
		 * Whether a field never echoes its value.
		 *
		 * @param array $f Field schema.
		 * @return bool
		 */
		private static function is_secret( array $f ) {
			return ! empty( $f['secret'] );
		}

		/**
		 * Whether a field belongs to a tab. A field may list several tabs.
		 *
		 * @param array  $f   Field schema.
		 * @param string $tab Tab id.
		 * @return bool
		 */
		private static function in_tab( array $f, $tab ) {
			return in_array( (string) $tab, array_map( 'strval', (array) $f['tab'] ), true );
		}

		/**
		 * Ids of the fields in a tab, in schema order.
		 *
		 * @param string $tab Tab id.
		 * @return string[]
		 */
		public function fields_in_tab( $tab ) {
			$out = array();
			foreach ( $this->fields as $id => $f ) {
				if ( isset( $f['group'] ) && '' !== $f['group'] ) {
					continue;
				}
				if ( self::in_tab( $f, $tab ) ) {
					$out[] = (string) $id;
				}
			}
			return $out;
		}

		/**
		 * Resolve a list that may be given lazily as a Closure (for example post types).
		 *
		 * @param mixed $items Array, or a Closure returning one.
		 * @param array $f     Field schema, passed to the Closure.
		 * @return array
		 */
		private static function resolve( $items, array $f ) {
			if ( $items instanceof Closure ) {
				$items = call_user_func( $items, $f );
			}
			return is_array( $items ) ? $items : array();
		}

		/**
		 * Choices of a field, with the 'choices_from' sugar applied. 'choices_from'
		 * (roles | post_types | taxonomies) wins over 'choices' when both are set
		 * (doing_it_wrong); 'choices' may still be a Closure.
		 *
		 * @param array $f Field schema.
		 * @return array
		 */
		private static function resolve_choices( array $f ) {
			$from = isset( $f['choices_from'] ) ? (string) $f['choices_from'] : '';
			if ( '' === $from ) {
				return self::resolve( isset( $f['choices'] ) ? $f['choices'] : array(), $f );
			}
			if ( ! empty( $f['choices'] ) ) {
				self::doing_it_wrong( 'choices_from and choices are both set on a field; choices_from wins.' );
			}
			switch ( $from ) {
				case 'roles':
					if ( function_exists( 'wp_roles' ) ) {
						$roles = wp_roles();
						if ( is_object( $roles ) && method_exists( $roles, 'get_names' ) ) {
							return (array) $roles->get_names();
						}
					}
					return array();
				case 'post_types':
					return self::choices_from_objects( 'post' );
				case 'taxonomies':
					return self::choices_from_objects( 'taxonomy' );
				default:
					self::doing_it_wrong( sprintf( 'Unknown choices_from 「%s」; expected roles, post_types or taxonomies.', $from ) );
					return self::resolve( isset( $f['choices'] ) ? $f['choices'] : array(), $f );
			}
		}

		/**
		 * The choices_from 'post_types' / 'taxonomies' sugar: public objects as slug => label,
		 * attachment and post_format excluded.
		 *
		 * @param string $what 'post' or 'taxonomy'.
		 * @return array<string, string>
		 */
		private static function choices_from_objects( $what ) {
			$out   = array();
			$names = 'post' === $what ? 'get_post_types' : 'get_taxonomies';
			if ( ! function_exists( $names ) ) {
				return $out;
			}
			$items = 'post' === $what
				? get_post_types( array( 'public' => true ), 'objects' )
				: get_taxonomies( array( 'public' => true ), 'objects' );
			$skip  = 'post' === $what ? 'attachment' : 'post_format';
			foreach ( (array) $items as $name => $obj ) {
				if ( $skip === $name ) {
					continue;
				}
				$out[ $name ] = is_object( $obj ) && isset( $obj->label ) ? (string) $obj->label : (string) $name;
			}
			return $out;
		}

		/**
		 * Sanitize callback for the whole option.
		 *
		 * Secret fields: an empty value keeps the saved one; ids listed under
		 * {option}[__wenpai_clear][] are cleared. When {option}[__wenpai_tab] is
		 * posted only that tab's fields are sanitized and written; every other
		 * field keeps its saved value. A 'validate' callback that does not return
		 * true keeps the saved value and records a field error. A field whose
		 * depends_on fails for the values being saved (hide or disable mode)
		 * keeps its saved value; its sanitize and validate never run.
		 *
		 * @param mixed $input Raw submitted value.
		 * @return array
		 */
		public function sanitize( $input ) {
			$input = is_array( $input ) ? $input : array();
			if ( null !== $this->last_sanitized && $input === $this->last_sanitized ) {
				return $input;
			}
			$stored = $this->stored();
			$clear  = isset( $input[ self::CLEAR_KEY ] ) && is_array( $input[ self::CLEAR_KEY ] )
				? array_map( 'strval', $input[ self::CLEAR_KEY ] )
				: array();
			$tab    = isset( $input[ self::TAB_KEY ] ) && is_scalar( $input[ self::TAB_KEY ] )
				? sanitize_key( (string) $input[ self::TAB_KEY ] )
				: null;
			if ( null !== $tab && ! $this->tab_nonce_ok( $tab, isset( $input[ self::TAB_NONCE_KEY ] ) ? $input[ self::TAB_NONCE_KEY ] : '' ) ) {
				// A tab marker we did not issue: write nothing rather than guess which fields were on the page.
				$this->form_error = $this->strings['tab_expired'];
				if ( function_exists( 'add_settings_error' ) ) {
					add_settings_error( $this->option, 'wenpai_tab', $this->form_error, 'error' );
				}
				$kept = array();
				foreach ( $this->fields as $id => $f ) {
					if ( self::stores( $f ) ) {
						$kept[ $id ] = $this->stored_field_value( $stored, $id, $f['default'] );
					}
				}
				$kept                 = $this->with_schema( $this->pack_values( $kept ) );
				$this->last_sanitized = $kept;
				return $kept;
			}
			$multi = isset( $input[ self::MULTILINE_KEY ] ) && is_array( $input[ self::MULTILINE_KEY ] )
				? array_map( 'strval', $input[ self::MULTILINE_KEY ] )
				: array();
			if ( null === $tab && ! $this->importing && ! $this->warned_marker && $this->has_tabs() ) {
				$this->warned_marker = true;
				self::doing_it_wrong( sprintf( 'Option 「%s」 declares tabs but the saved form has no kit tab marker, so every unsubmitted toggle, multicheck and multiselect is saved as unchecked. Output render_tab() or tab_input_html() inside each tab form.', $this->option ), 'Wenpai_Admin_Settings::sanitize' );
			}
			$out      = array();
			$prevs    = array();
			$pending  = array();
			$deferred = array();
			foreach ( $this->fields as $id => $f ) {
				if ( ! self::stores( $f ) ) {
					continue;
				}
				$raw  = $this->input_value( $input, $id );
				$prev = $this->stored_field_value( $stored, $id, $f['default'] );
				if ( null !== $tab && ! self::in_tab( $f, $tab ) ) {
					$out[ $id ] = $prev;
					continue;
				}
				$prevs[ $id ] = $prev;
				$tree         = self::dep_tree( $f['depends_on'] );
				if ( null !== $tree ) {
					// Decided below, before any sanitize runs: a disabled or hidden field gets its stored value,
					// never sanitize( null ) or sanitize( what was posted ).
					$deferred[ $id ] = $raw;
					$out[ $id ]      = $prev;
					continue;
				}
				if ( null === $raw && self::is_secret( $f ) && in_array( (string) $id, $clear, true ) ) {
					// Clear without the (empty) password input in the request: still a clear.
					$out[ $id ] = '';
					continue;
				}
				if ( null === $raw && ! self::is_checkbox_like( $f ) ) {
					// Not on the submitted page (text, select, number, matrix …): keep it.
					// An unchecked checkbox is different: it sends nothing, see is_checkbox_like().
					$out[ $id ] = $prev;
					continue;
				}
				if ( self::is_secret( $f ) && ( in_array( (string) $id, $multi, true ) || ( is_string( $raw ) && preg_match( '/[\r\n]/', $raw ) ) ) ) {
					// Multi-line value (kit JS flags a pasted one, as the browser would fold it into spaces): refuse, keep the stored one.
					$out[ $id ] = $prev;
					$this->add_error( $id, $this->strings['secret_multiline'] );
					continue;
				}
				if ( self::is_secret( $f ) ) {
					$typed = is_scalar( $raw ) && '' !== trim( (string) $raw );
					if ( ! $typed ) {
						// A typed new value wins over "clear"; clear only applies to an empty box.
						$out[ $id ] = in_array( (string) $id, $clear, true ) ? '' : $prev;
						continue;
					}
				}
				$out[ $id ] = $this->after_sanitize_typed( $id, $f, $raw, $this->sanitize_field( $f, $raw ), $prev );
				if ( is_callable( $f['validate'] ) ) {
					$pending[ $id ] = true;
				}
			}

			// Fields with depends_on: settle which are active for the values being
			// saved (stored values stand in for the undecided ones), then sanitize
			// only those. A disabled or hidden field keeps its stored value without
			// its sanitize callback ever running (no side effects, e.g. remote checks).
			// Decide in dependency order: a field waits until every depends_on
			// field it depends on is decided, so sanitize runs at most once and
			// only for fields that end up enabled. Cycles keep the stored value.
			$decided = array();
			$total   = count( $deferred );
			for ( $pass = 0; $pass <= $total; $pass++ ) {
				if ( count( $decided ) >= $total ) {
					break;
				}
				$off = $this->inactive_fields( $out );
				foreach ( $deferred as $id => $raw ) {
					if ( isset( $decided[ $id ] ) ) {
						continue;
					}
					$wait = false;
					foreach ( $this->upstream( $id ) as $trigger ) {
						if ( isset( $deferred[ $trigger ] ) && ! isset( $decided[ $trigger ] ) ) {
							$wait = true;
						}
					}
					if ( $wait ) {
						continue;
					}
					$decided[ $id ] = true;
					if ( ! isset( $off[ $id ] ) ) {
						$out[ $id ] = $this->sanitize_deferred( $id, $raw, $prevs[ $id ], $clear, $multi, $pending );
						$off        = $this->inactive_fields( $out );
					}
				}
			}

			// depends_on, checked against the values being saved: a disabled or
			// hidden field always keeps the saved value, silently (not validated,
			// not in the error summary). Backstop for the loop above.
			foreach ( array_keys( $this->inactive_fields( $out ) ) as $id ) {
				if ( ! array_key_exists( $id, $prevs ) ) {
					continue;
				}
				unset( $pending[ $id ] );
				$out[ $id ] = $prevs[ $id ];
			}

			foreach ( array_keys( $pending ) as $id ) {
				$f  = $this->fields[ $id ];
				$ok = call_user_func( $f['validate'], $out[ $id ], $f );
				if ( true === $ok ) {
					continue;
				}
				$msg  = $this->strings['invalid_value'];
				$rows = array();
				if ( is_string( $ok ) && '' !== $ok ) {
					$msg = $ok;
				} elseif ( is_array( $ok ) ) {
					$msg  = isset( $ok['message'] ) && '' !== (string) $ok['message'] ? (string) $ok['message'] : $msg;
					$rows = isset( $ok['rows'] ) ? array_map( 'strval', (array) $ok['rows'] ) : array();
				}
				$this->add_error( $id, $msg, $rows );
				$out[ $id ] = $prevs[ $id ];
			}
			if ( empty( $this->errors ) && $this->saving_from_options_php() && function_exists( 'add_settings_error' ) ) {
				// Tags the options.php redirect as this option's, so claim_core_notices() knows the page;
				// it also keeps core from adding its own general/settings_updated.
				add_settings_error( $this->option, self::SAVED_CODE, $this->strings['saved'], 'success' );
			}
			$out                  = $this->with_schema( $this->pack_values( $out ) );
			$this->last_sanitized = $out;
			return $out;
		}

		/**
		 * Whether this request is options.php saving this option's group.
		 *
		 * @return bool
		 */
		private function saving_from_options_php() {
			return isset( $GLOBALS['pagenow'], $GLOBALS['option_page'] )
				&& 'options.php' === $GLOBALS['pagenow']
				&& $this->group === $GLOBALS['option_page'];
		}

		/**
		 * Field ids a depends_on tree looks at.
		 *
		 * @param array|null $node Normalized node.
		 * @return string[]
		 */
		private static function dep_fields( $node ) {
			if ( null === $node ) {
				return array();
			}
			if ( isset( $node['field'] ) ) {
				return array( $node['field'] );
			}
			$out = array();
			foreach ( $node['rules'] as $r ) {
				$out = array_merge( $out, self::dep_fields( $r ) );
			}
			return $out;
		}

		/**
		 * Every field a field's depends_on reaches, directly or through other
		 * fields' depends_on (a hide-mode field in between counts too).
		 *
		 * @param string $id Field id.
		 * @return string[]
		 */
		private function upstream( $id ) {
			$seen  = array();
			$queue = array( $id );
			while ( $queue ) {
				$cur = array_shift( $queue );
				if ( ! isset( $this->fields[ $cur ] ) ) {
					continue;
				}
				foreach ( self::dep_fields( self::dep_tree( $this->fields[ $cur ]['depends_on'] ) ) as $up ) {
					if ( ! isset( $seen[ $up ] ) && $up !== $id ) {
						$seen[ $up ] = true;
						$queue[]     = $up;
					}
				}
			}
			return array_keys( $seen );
		}

		/**
		 * Sanitize an enabled disable-mode field the same way as any other field.
		 *
		 * @param string $id      Field id.
		 * @param mixed  $raw     Posted value or null.
		 * @param mixed  $prev    Stored value.
		 * @param array  $clear   Secret ids to clear.
		 * @param array  $multi   Secret ids flagged multi-line by kit JS.
		 * @param array  $pending Field ids waiting for validate (by reference).
		 * @return mixed
		 */
		private function sanitize_deferred( $id, $raw, $prev, array $clear, array $multi, array &$pending ) {
			$f = $this->fields[ $id ];
			if ( null === $raw && ! self::is_checkbox_like( $f ) ) {
				return self::is_secret( $f ) && in_array( (string) $id, $clear, true ) ? '' : $prev;
			}
			if ( self::is_secret( $f ) ) {
				if ( in_array( (string) $id, $multi, true ) || ( is_string( $raw ) && preg_match( '/[\r\n]/', $raw ) ) ) {
					$this->add_error( $id, $this->strings['secret_multiline'] );
					return $prev;
				}
				if ( ! is_scalar( $raw ) || '' === trim( (string) $raw ) ) {
					return in_array( (string) $id, $clear, true ) ? '' : $prev;
				}
			}
			if ( is_callable( $f['validate'] ) ) {
				$pending[ $id ] = true;
			}
			return $this->after_sanitize_typed( $id, $f, $raw, $this->sanitize_field( $f, $raw ), $prev );
		}

		/**
		 * Fields whose depends_on fails for the given values, with their mode.
		 * A field that depends on an inactive field is inactive too.
		 *
		 * @param array $values Field values.
		 * @return array<string, string> Field id => 'hide' | 'disable' | 'exclude'.
		 */
		public function inactive_fields( array $values ) {
			$off = array();
			for ( $pass = 0; $pass < 5; $pass++ ) {
				$changed = false;
				foreach ( $this->fields as $id => $f ) {
					$tree = self::dep_tree( $f['depends_on'] );
					if ( null === $tree ) {
						continue;
					}
					$met = $this->node_met( $tree, $values, $off );
					if ( ! $met && ! isset( $off[ $id ] ) ) {
						$off[ $id ] = $tree['mode'];
						$changed    = true;
					} elseif ( $met && isset( $off[ $id ] ) ) {
						unset( $off[ $id ] );
						$changed = true;
					}
				}
				if ( ! $changed ) {
					break;
				}
			}
			return $off;
		}

		/**
		 * Record a field error for this request and for the Settings API redirect.
		 * Matrix rows in error travel in the error code as "id:row1,row2".
		 *
		 * @param string   $id      Field id.
		 * @param string   $message Translated message.
		 * @param string[] $rows    Optional matrix rows in error.
		 */
		private function add_error( $id, $message, array $rows = array() ) {
			$this->errors[ $id ] = $message;
			if ( ! empty( $rows ) ) {
				$this->error_rows[ $id ] = $rows;
			}
			if ( function_exists( 'add_settings_error' ) ) {
				add_settings_error( $this->option, (string) $id . ( empty( $rows ) ? '' : ':' . implode( ',', $rows ) ), $message, 'error' );
			}
		}

		/**
		 * Matrix rows in error, from this request or the last redirect.
		 *
		 * @param string $id Field id.
		 * @return string[]
		 */
		public function error_rows( $id ) {
			$this->errors();
			return isset( $this->error_rows[ $id ] ) ? $this->error_rows[ $id ] : array();
		}

		/**
		 * Field errors from this request or from the last options.php redirect.
		 *
		 * @return array<string, string> Field id => message.
		 */
		public function errors() {
			$errs = $this->errors;
			foreach ( $this->own_settings_errors() as $e ) {
				if ( ! isset( $e['code'], $e['message'] ) || self::SAVED_CODE === $e['code'] ) {
					continue;
				}
				$parts = explode( ':', (string) $e['code'], 2 );
				$code  = $parts[0];
				if ( isset( $this->fields[ $code ] ) && ! isset( $errs[ $code ] ) ) {
					$errs[ $code ] = $e['message'];
					if ( isset( $parts[1] ) && '' !== $parts[1] ) {
						$this->error_rows[ $code ] = explode( ',', $parts[1] );
					}
				}
			}
			return $errs;
		}

		/**
		 * This option's settings errors: the ones claimed from core on this page
		 * plus any still in core's list (this request, or before the claim).
		 *
		 * @return array[]
		 */
		private function own_settings_errors() {
			$list = null !== $this->claimed ? $this->claimed : array();
			if ( function_exists( 'get_settings_errors' ) ) {
				$list = array_merge( $list, (array) get_settings_errors( $this->option ) );
			}
			return $list;
		}

		/**
		 * One kit notice per page (in_admin_header, priority 0; all_admin_notices fallback).
		 *
		 * After options.php saves this option it redirects back with its
		 * settings errors in a transient; core then prints them again through
		 * settings_errors() on Settings submenu pages (options-head.php), and a
		 * plugin page may call settings_errors() itself. This takes this
		 * option's entries (field errors, the tab marker error, the saved tag)
		 * into the instance, where errors() and notices_html() read them, and
		 * removes them plus core's success general/settings_updated from the
		 * global list, so core prints nothing for this save. A general
		 * settings_updated of another type (core's error) is kept. Pages that
		 * did not just save this option are left alone.
		 */
		public function claim_core_notices() {
			if ( null !== $this->claimed || ! function_exists( 'get_settings_errors' ) ) {
				return;
			}
			$mine = array();
			// get_settings_errors() also merges (and deletes) the options.php transient.
			foreach ( (array) get_settings_errors() as $e ) {
				if ( isset( $e['setting'] ) && $this->option === $e['setting'] ) {
					$mine[] = $e;
				}
			}
			if ( empty( $mine ) ) {
				return;
			}
			$this->claimed = $mine;
			$keep          = array();
			foreach ( (array) ( isset( $GLOBALS['wp_settings_errors'] ) ? $GLOBALS['wp_settings_errors'] : array() ) as $e ) {
				$setting = isset( $e['setting'] ) ? $e['setting'] : '';
				$code    = isset( $e['code'] ) ? $e['code'] : '';
				$type    = isset( $e['type'] ) ? $e['type'] : '';
				if ( $this->option === $setting || ( 'general' === $setting && 'settings_updated' === $code && in_array( $type, array( 'success', 'updated' ), true ) ) ) {
					continue;
				}
				$keep[] = $e;
			}
			$GLOBALS['wp_settings_errors'] = $keep; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- core's own settings errors list; only this option's entries and its duplicate 「Settings saved.」 are removed.
		}


		/**
		 * Email/url post-sanitize check: invalid non-empty → field error + keep previous.
		 *
		 * @param string $id   Field id.
		 * @param array  $f    Field schema.
		 * @param mixed  $raw  Posted raw value.
		 * @param mixed  $clean Sanitized value.
		 * @param mixed  $prev Stored value.
		 * @return mixed Value to store.
		 */
		private function after_sanitize_typed( $id, array $f, $raw, $clean, $prev ) {
			if ( 'email' === $f['type'] ) {
				$posted = is_scalar( $raw ) ? trim( (string) $raw ) : '';
				if ( '' === $posted ) {
					return '';
				}
				if ( ! function_exists( 'is_email' ) || ! is_email( (string) $clean ) ) {
					$this->add_error( $id, $this->strings['invalid_email'] );
					return $prev;
				}
				return (string) $clean;
			}
			if ( 'url' === $f['type'] ) {
				$posted = is_scalar( $raw ) ? trim( (string) $raw ) : '';
				if ( '' === $posted ) {
					return '';
				}
				if ( '' === (string) $clean ) {
					$this->add_error( $id, $this->strings['invalid_url'] );
					return $prev;
				}
				return (string) $clean;
			}
			return $clean;
		}

		/**
		 * Sanitize one field value by its type.
		 *
		 * @param array $f Field schema.
		 * @param mixed $v Raw value.
		 * @return mixed
		 */
		private function sanitize_field( array $f, $v ) {
			if ( isset( $f['sanitize'] ) && is_callable( $f['sanitize'] ) ) {
				return call_user_func( $f['sanitize'], $v, $f );
			}
			switch ( $f['type'] ) {
				case 'toggle':
					$pair = self::toggle_values( $f );
					if ( null !== $pair ) {
						return is_scalar( $v ) && (string) $v === $pair[0] ? $pair[0] : $pair[1];
					}
					return ! empty( $v );
				case 'number':
					$n = null === $v || '' === $v ? (int) $f['default'] : (int) $v;
					if ( isset( $f['min'] ) ) {
						$n = max( (int) $f['min'], $n );
					}
					if ( isset( $f['max'] ) ) {
						$n = min( (int) $f['max'], $n );
					}
					return $n;
				case 'select':
				case 'radio':
				case 'choice':
					$v = is_scalar( $v ) ? (string) $v : '';
					return array_key_exists( $v, self::resolve_choices( $f ) ) ? $v : $f['default'];
				case 'multicheck':
					$v       = is_array( $v ) ? array_values( array_filter( array_map( 'sanitize_key', $v ), 'strlen' ) ) : array();
					$choices = self::resolve_choices( $f );
					return empty( $choices ) ? $v : array_values( array_intersect( $v, array_keys( $choices ) ) );
				case 'multiselect':
					return self::sanitize_multiselect( $f, $v );
				case 'matrix':
					return self::sanitize_matrix( $f, $v );
				case 'map':
					$res = array();
					foreach ( ( is_array( $v ) ? $v : array() ) as $k => $val ) {
						$res[ sanitize_key( $k ) ] = sanitize_key( $val );
					}
					return $res;
				case 'textarea':
					return null === $v ? $f['default'] : sanitize_textarea_field( (string) $v );
				case 'email':
					if ( null === $v || '' === $v ) {
						return '';
					}
					if ( ! is_scalar( $v ) ) {
						return $f['default'];
					}
					$email = function_exists( 'sanitize_email' ) ? sanitize_email( (string) $v ) : sanitize_text_field( (string) $v );
					return $email;
				case 'url':
					if ( null === $v || '' === $v ) {
						return '';
					}
					if ( ! is_scalar( $v ) ) {
						return $f['default'];
					}
					return function_exists( 'esc_url_raw' )
						? esc_url_raw( (string) $v, array( 'http', 'https' ) )
						: sanitize_text_field( (string) $v );
				case 'date':
					$v = is_scalar( $v ) ? trim( (string) $v ) : '';
					if ( '' === $v ) {
						return '';
					}
					if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m ) ) {
						return $f['default'];
					}
					return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ? $v : $f['default'];
				case 'color':
					return self::sanitize_hex_color( $v, $f['default'] );
				case 'media':
					return is_scalar( $v ) ? max( 0, (int) $v ) : 0;
				case 'group':
					return $f['default'];
				case 'repeater':
					return $this->sanitize_repeater( $f, $v );
				case 'entity':
					return self::sanitize_entity( $f, $v );
				case 'file':
					return self::sanitize_file_items( $f, $v );
				case 'tags':
					return self::sanitize_tags( $v );
				case 'schedule':
					return self::sanitize_schedule( $f, $v );
				case 'icon':
					return self::sanitize_icon( $f, $v );
				case 'time':
				case 'datetime-local':
				case 'week':
					return self::sanitize_datetime_part( $f, $v );
				case 'range':
					return self::sanitize_range( $f, $v );
				default:
					return null === $v || ! is_scalar( $v ) ? $f['default'] : sanitize_text_field( (string) $v );
			}
		}

		/**
		 * Multiselect: list of choice keys, in choice order, no duplicates.
		 *
		 * @param array $f Field schema.
		 * @param mixed $v Raw value. The empty sentinel from the form is dropped.
		 * @return string[]
		 */
		/**
		 * Hex color: #rrggbb or #rgb, normalized to lowercase #rrggbb.
		 *
		 * @param mixed  $v       Raw value.
		 * @param string $fallback Schema default.
		 * @return string
		 */
		private static function sanitize_hex_color( $v, $fallback ) {
			$v = is_scalar( $v ) ? ltrim( trim( (string) $v ), '#' ) : '';
			if ( preg_match( '/^[0-9a-fA-F]{3}$/', $v ) ) {
				$v = $v[0] . $v[0] . $v[1] . $v[1] . $v[2] . $v[2];
			}
			if ( ! preg_match( '/^[0-9a-fA-F]{6}$/', $v ) ) {
				return $fallback;
			}
			return '#' . strtolower( $v );
		}

		/**
		 * Multiselect: list of choice keys, in choice order, no duplicates.
		 *
		 * @param array $f Field schema.
		 * @param mixed $v Raw value. The empty sentinel from the form is dropped.
		 * @return string[]
		 */
		private static function sanitize_multiselect( array $f, $v ) {
			$picked = array();
			foreach ( ( is_array( $v ) ? $v : array() ) as $item ) {
				if ( is_scalar( $item ) && '' !== (string) $item ) {
					$picked[] = sanitize_text_field( (string) $item );
				}
			}
			$choices = self::resolve_choices( $f );
			if ( empty( $choices ) ) {
				return array_values( array_unique( $picked ) );
			}
			$out = array();
			foreach ( array_keys( $choices ) as $k ) {
				if ( in_array( (string) $k, $picked, true ) ) {
					$out[] = (string) $k;
				}
			}
			return $out;
		}

		/**
		 * Matrix: row key => choice key. Unknown rows and choices are dropped;
		 * rows set to unset_value are not stored (they follow the global setting).
		 *
		 * @param array $f Field schema.
		 * @param mixed $v Raw value.
		 * @return array<string, string>
		 */
		private static function sanitize_matrix( array $f, $v ) {
			$rows    = self::resolve( $f['rows'], $f );
			$choices = self::resolve_choices( $f );
			$out     = array();
			foreach ( ( is_array( $v ) ? $v : array() ) as $row => $val ) {
				$row = sanitize_key( (string) $row );
				$val = is_scalar( $val ) ? (string) $val : '';
				if ( '' === $row || ( ! empty( $rows ) && ! array_key_exists( $row, $rows ) ) ) {
					continue;
				}
				if ( ! array_key_exists( $val, $choices ) ) {
					continue;
				}
				if ( null !== $f['unset_value'] && (string) $f['unset_value'] === $val ) {
					continue;
				}
				$out[ $row ] = $val;
			}
			return $out;
		}

		/**
		 * Normalize nested repeater field schemas (Track D / E08).
		 *
		 * @param mixed $fields Raw fields map.
		 * @return array<string, array>
		 */
		private static function normalize_repeater_fields( $fields ) {
			if ( ! is_array( $fields ) ) {
				return array();
			}
			$out = array();
			foreach ( $fields as $fid => $sf ) {
				$fid = sanitize_key( (string) $fid );
				if ( '' === $fid || ! is_array( $sf ) ) {
					continue;
				}
				$type = isset( $sf['type'] ) ? (string) $sf['type'] : 'text';
				// Nested repeater / matrix / status / choice not in this lean slice.
				if ( in_array( $type, array( 'repeater', 'matrix', 'status', 'choice', 'multicheck', 'multiselect', 'map' ), true ) ) {
					continue;
				}
				$sf          = wp_parse_args(
					$sf,
					array(
						'type'    => 'text',
						'default' => ( 'toggle' === $type ? false : ( 'number' === $type ? 0 : '' ) ),
						'label'   => $fid,
						'desc'    => '',
						'choices' => array(),
						'min'     => null,
						'max'     => null,
					)
				);
				$out[ $fid ] = $sf;
			}
			return $out;
		}

		/**
		 * Repeater: recursive sanitize of nested fields; drop empty rows; honor max_rows.
		 *
		 * @param array $f Field schema.
		 * @param mixed $v Raw value.
		 * @return array<int, array<string, mixed>>
		 */
		private function sanitize_repeater( array $f, $v ) {
			$fields = isset( $f['fields'] ) && is_array( $f['fields'] ) ? $f['fields'] : array();
			$max    = isset( $f['max_rows'] ) ? max( 1, (int) $f['max_rows'] ) : 20;
			$out    = array();
			foreach ( ( is_array( $v ) ? $v : array() ) as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$clean = array();
				foreach ( $fields as $fid => $sf ) {
					$raw = array_key_exists( $fid, $row ) ? $row[ $fid ] : null;
					// Absent nested toggle / unchecked → false (checkbox-like inside row).
					if ( null === $raw && in_array( $sf['type'], array( 'toggle' ), true ) ) {
						$raw = false;
					}
					if ( null === $raw ) {
						$clean[ $fid ] = $sf['default'];
						continue;
					}
					$clean[ $fid ] = $this->sanitize_field( $sf, $raw );
				}
				if ( self::repeater_row_empty( $clean, $fields ) ) {
					continue;
				}
				$out[] = $clean;
				if ( count( $out ) >= $max ) {
					break;
				}
			}
			return $out;
		}

		/**
		 * Whether a sanitized repeater row is empty (all defaults / empty scalars).
		 *
		 * @param array $row    Clean row.
		 * @param array $fields Nested schemas.
		 * @return bool
		 */
		private static function repeater_row_empty( array $row, array $fields ) {
			if ( array() === $fields ) {
				return true;
			}
			foreach ( $fields as $fid => $sf ) {
				$val = array_key_exists( $fid, $row ) ? $row[ $fid ] : $sf['default'];
				$def = $sf['default'];
				if ( 'toggle' === $sf['type'] ) {
					if ( ! empty( $val ) ) {
						return false;
					}
					continue;
				}
				if ( 'number' === $sf['type'] ) {
					if ( (int) $val !== (int) $def ) {
						return false;
					}
					continue;
				}
				if ( is_scalar( $val ) && '' !== trim( (string) $val ) && (string) $val !== (string) $def ) {
					return false;
				}
				if ( is_scalar( $val ) && '' !== trim( (string) $val ) && '' === (string) $def ) {
					return false;
				}
			}
			return true;
		}

		/**
		 * Entity picker kind: post | term | user (default post).
		 *
		 * @param array $f Field schema.
		 * @return string
		 */
		private static function entity_kind( array $f ) {
			$kind = isset( $f['kind'] ) ? sanitize_key( (string) $f['kind'] ) : 'post';
			return in_array( $kind, array( 'post', 'term', 'user' ), true ) ? $kind : 'post';
		}

		/**
		 * Allowed post types of an entity field (kind=post). String or array;
		 * comma strings and empty values normalize to array( 'post' ).
		 *
		 * @param array $f Field schema.
		 * @return string[]
		 */
		private static function entity_post_types( array $f ) {
			$pt = isset( $f['post_type'] ) ? $f['post_type'] : 'post';
			if ( is_string( $pt ) ) {
				$pt = '' === trim( $pt ) ? array() : explode( ',', $pt );
			}
			$out = array();
			foreach ( (array) $pt as $t ) {
				$t = sanitize_key( (string) $t );
				if ( '' !== $t ) {
					$out[] = $t;
				}
			}
			return array() === $out ? array( 'post' ) : array_values( array_unique( $out ) );
		}

		/**
		 * Taxonomy of an entity field (kind=term). Default category.
		 *
		 * @param array $f Field schema.
		 * @return string
		 */
		private static function entity_taxonomy( array $f ) {
			$tax = isset( $f['taxonomy'] ) ? sanitize_key( (string) $f['taxonomy'] ) : '';
			return '' === $tax ? 'category' : $tax;
		}

		/**
		 * Whether an entity id exists for the field's kind and filters.
		 * Posts: any post_status, post_type must be in the allowed list.
		 *
		 * @param array $f  Field schema.
		 * @param mixed $id Candidate id.
		 * @return bool
		 */
		private static function entity_exists( array $f, $id ) {
			$id = absint( $id );
			if ( $id <= 0 ) {
				return false;
			}
			switch ( self::entity_kind( $f ) ) {
				case 'term':
					return function_exists( 'term_exists' ) && (bool) term_exists( $id, self::entity_taxonomy( $f ) );
				case 'user':
					return function_exists( 'get_user_by' ) && (bool) get_user_by( 'id', $id );
				default:
					if ( ! function_exists( 'get_post' ) ) {
						return false;
					}
					$p = get_post( $id );
					if ( null === $p || false === $p ) {
						return false;
					}
					$ptype = is_object( $p ) && isset( $p->post_type ) ? (string) $p->post_type : '';
					if ( ! in_array( $ptype, self::entity_post_types( $f ), true ) ) {
						return false;
					}
					return ! function_exists( 'get_post_type_object' ) || (bool) get_post_type_object( $ptype );
			}
		}

		/**
		 * Entity: a single positive id that exists, '' for none, else default.
		 *
		 * @param array $f Field schema.
		 * @param mixed $v Raw value.
		 * @return int|string
		 */
		private static function sanitize_entity( array $f, $v ) {
			if ( null === $v || ! is_scalar( $v ) ) {
				return $f['default'];
			}
			$v = trim( (string) $v );
			if ( '' === $v ) {
				return '';
			}
			$id = absint( $v );
			return $id > 0 && self::entity_exists( $f, $id ) ? $id : $f['default'];
		}

		/**
		 * Label and meta line of a picked entity, rendered server-side so the
		 * saved value shows without JS.
		 *
		 * @param array $f  Field schema.
		 * @param mixed $id Entity id.
		 * @return array{0: string, 1: string}
		 */
		private static function entity_label_meta( array $f, $id ) {
			$id    = absint( $id );
			$label = '';
			$meta  = '';
			if ( $id <= 0 ) {
				return array( '', '' );
			}
			switch ( self::entity_kind( $f ) ) {
				case 'term':
					if ( function_exists( 'get_term' ) ) {
						$t = get_term( $id, self::entity_taxonomy( $f ) );
						if ( $t && ( ! function_exists( 'is_wp_error' ) || ! is_wp_error( $t ) ) ) {
							$label = isset( $t->name ) ? (string) $t->name : '';
							if ( function_exists( 'get_taxonomy' ) ) {
								$tx = get_taxonomy( self::entity_taxonomy( $f ) );
								if ( $tx && isset( $tx->label ) ) {
									$meta = (string) $tx->label;
								}
							}
						}
					}
					break;
				case 'user':
					if ( function_exists( 'get_userdata' ) ) {
						$u = get_userdata( $id );
						if ( $u ) {
							$label = isset( $u->display_name ) && '' !== (string) $u->display_name
								? (string) $u->display_name
								: ( isset( $u->user_login ) ? (string) $u->user_login : '' );
							$meta  = isset( $u->user_login ) ? (string) $u->user_login : '';
						}
					}
					break;
				default:
					if ( function_exists( 'get_post' ) ) {
						$p = get_post( $id );
						if ( $p ) {
							$label = function_exists( 'get_the_title' )
								? (string) get_the_title( $p )
								: ( isset( $p->post_title ) ? (string) $p->post_title : '' );
							$ptype = isset( $p->post_type ) ? (string) $p->post_type : '';
							if ( '' !== $ptype && function_exists( 'get_post_type_object' ) ) {
								$pto = get_post_type_object( $ptype );
								if ( $pto && isset( $pto->labels->name ) ) {
									$meta = (string) $pto->labels->name;
								}
							}
							if ( function_exists( 'get_post_time' ) ) {
								$d = get_post_time( 'Y-m-d', false, $p );
								if ( $d ) {
									$meta = trim( $meta . ( '' !== $meta ? ' · ' : '' ) . (string) $d );
								}
							}
						}
					}
					break;
			}
			if ( '' === $label ) {
				$label = '#' . $id;
			}
			return array( $label, $meta );
		}

		/**
		 * File field: list of array( id, url, name, size, type ). A single assoc
		 * item normalizes to a one-item list; multiple=false keeps the first.
		 *
		 * @param array $f Field schema.
		 * @param mixed $v Raw value.
		 * @return array<int, array>
		 */
		private static function sanitize_file_items( array $f, $v ) {
			if ( ! is_array( $v ) ) {
				return array();
			}
			if ( isset( $v['url'] ) || isset( $v['id'] ) || isset( $v['name'] ) ) {
				$v = array( $v );
			}
			$items = array();
			foreach ( $v as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$url = isset( $item['url'] ) && is_scalar( $item['url'] ) ? trim( (string) $item['url'] ) : '';
				if ( '' === $url ) {
					continue;
				}
				$url = function_exists( 'esc_url_raw' ) ? esc_url_raw( $url ) : $url;
				if ( '' === $url ) {
					continue;
				}
				$items[] = array(
					'id'   => isset( $item['id'] ) ? absint( $item['id'] ) : 0,
					'url'  => $url,
					'name' => isset( $item['name'] ) && is_scalar( $item['name'] ) ? sanitize_text_field( (string) $item['name'] ) : '',
					'size' => isset( $item['size'] ) ? absint( $item['size'] ) : 0,
					'type' => isset( $item['type'] ) && is_scalar( $item['type'] ) ? sanitize_text_field( (string) $item['type'] ) : '',
				);
			}
			if ( empty( $f['multiple'] ) ) {
				$items = array_slice( $items, 0, 1 );
			}
			return $items;
		}

		/**
		 * Tags: list of unique non-empty strings.
		 *
		 * @param mixed $v Raw value.
		 * @return string[]
		 */
		private static function sanitize_tags( $v ) {
			if ( ! is_array( $v ) ) {
				return array();
			}
			$out = array();
			foreach ( $v as $t ) {
				if ( ! is_scalar( $t ) ) {
					continue;
				}
				$t = sanitize_text_field( (string) $t );
				if ( '' !== $t && ! in_array( $t, $out, true ) ) {
					$out[] = $t;
				}
			}
			return $out;
		}

		/**
		 * Schedule: a wp_get_schedules() key, else default.
		 *
		 * @param array $f Field schema.
		 * @param mixed $v Raw value.
		 * @return string
		 */
		private static function sanitize_schedule( array $f, $v ) {
			$v = is_scalar( $v ) ? (string) $v : '';
			if ( function_exists( 'wp_get_schedules' ) && array_key_exists( $v, (array) wp_get_schedules() ) ) {
				return $v;
			}
			return $f['default'];
		}

		/**
		 * Icon: an icon name known to Wenpai_Admin_Icons, '' for none, else
		 * default. Without the Icons class (or has()) it degrades to a
		 * sanitize_key non-empty check.
		 *
		 * @param array $f Field schema.
		 * @param mixed $v Raw value.
		 * @return string
		 */
		private static function sanitize_icon( array $f, $v ) {
			$v = is_scalar( $v ) ? trim( (string) $v ) : '';
			if ( '' === $v ) {
				return '';
			}
			if ( class_exists( 'Wenpai_Admin_Icons', false ) && method_exists( 'Wenpai_Admin_Icons', 'has' ) ) {
				return Wenpai_Admin_Icons::has( $v ) ? $v : $f['default'];
			}
			$k = sanitize_key( $v );
			return '' !== $k ? $k : $f['default'];
		}

		/**
		 * The time / datetime-local / week types: strict format check, '' when invalid.
		 *
		 * @param array $f Field schema.
		 * @param mixed $v Raw value.
		 * @return string
		 */
		private static function sanitize_datetime_part( array $f, $v ) {
			$v = is_scalar( $v ) ? trim( (string) $v ) : '';
			if ( '' === $v ) {
				return '';
			}
			$patterns = array(
				'time'           => '/^([01]\d|2[0-3]):[0-5]\d$/',
				'datetime-local' => '/^\d{4}-\d{2}-\d{2}T([01]\d|2[0-3]):[0-5]\d$/',
				'week'           => '/^\d{4}-W(0[1-9]|[1-4]\d|5[0-3])$/',
			);
			return isset( $patterns[ $f['type'] ] ) && preg_match( $patterns[ $f['type'] ], $v ) ? $v : '';
		}

		/**
		 * Range: float clamped to min/max (defaults 0/100).
		 *
		 * @param array $f Field schema.
		 * @param mixed $v Raw value.
		 * @return float
		 */
		private static function sanitize_range( array $f, $v ) {
			$min = isset( $f['min'] ) && is_numeric( $f['min'] ) ? (float) $f['min'] : 0.0;
			$max = isset( $f['max'] ) && is_numeric( $f['max'] ) ? (float) $f['max'] : 100.0;
			$n   = ( null === $v || '' === $v || ! is_scalar( $v ) )
				? ( is_numeric( $f['default'] ) ? (float) $f['default'] : $min )
				: (float) $v;
			return max( $min, min( $max, $n ) );
		}

		/**
		 * Values for export, with secret fields removed.
		 *
		 * @return array
		 */
		public function export() {
			$data = $this->all();
			foreach ( $this->fields as $id => $f ) {
				if ( self::is_secret( $f ) ) {
					unset( $data[ $id ] );
				}
			}
			return $data;
		}

		/**
		 * Import values; secret fields keep their current value.
		 *
		 * @param array $data Values to import.
		 * @return bool Result of update_option().
		 */
		public function import( array $data ) {
			$current = $this->all();
			foreach ( $this->fields as $id => $f ) {
				if ( self::is_secret( $f ) ) {
					unset( $data[ $id ] );
				}
			}
			unset( $data[ self::TAB_KEY ], $data[ self::CLEAR_KEY ] );
			$this->importing = true;
			try {
				$clean = $this->sanitize( wp_parse_args( $data, $current ) );
			} finally {
				$this->importing = false;
			}
			foreach ( $this->fields as $id => $f ) {
				if ( self::is_secret( $f ) ) {
					$clean = $this->write_field_value( $clean, $id, $current[ $id ] );
				}
			}
			return update_option( $this->option, $clean );
		}

		/**
		 * Sanitize $input with the importing flag (no tab-marker notice) and write it.
		 *
		 * @param array $input Values as if posted.
		 * @return bool Result of update_option().
		 */
		public function commit( array $input ) {
			$this->importing = true;
			try {
				$clean = $this->sanitize( $input );
			} finally {
				$this->importing = false;
			}
			return update_option( $this->option, $clean );
		}

		/**
		 * Registered schema version (1 when register() omitted version).
		 *
		 * @return int
		 */
		public function schema_version() {
			return $this->version;
		}

		/**
		 * UI strings after defaults and overrides are merged.
		 *
		 * @return array<string, string>
		 */
		public function strings() {
			return $this->strings;
		}

		/**
		 * Tools instance, or null when register() was not given tools.
		 *
		 * @return Wenpai_Admin_Tools|null
		 */
		public function tools() {
			return $this->tools;
		}

		/**
		 * Echo the three tools cards.
		 *
		 * @param array $args reset_confirm, reset_note.
		 */
		public function render_tools( $args = array() ) {
			echo $this->tools_html( $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts.
		}

		/**
		 * Markup for the three tools cards. Empty (and _doing_it_wrong) without tools.
		 *
		 * @param array $args reset_confirm, reset_note.
		 * @return string
		 */
		public function tools_html( $args = array() ) {
			if ( ! $this->tools ) {
				self::doing_it_wrong( 'register() with a tools.plugin argument before render_tools().', 'Wenpai_Admin_Settings::render_tools' );
				return '';
			}
			return $this->tools->cards_html( is_array( $args ) ? $args : array() );
		}

		/**
		 * Write upgraded values when stored schema is older than register() version.
		 * No-op when version was omitted, the option is missing, or an upgrade callback fails.
		 */
		public function maybe_upgrade() {
			if ( ! $this->version_explicit ) {
				return;
			}
			$raw = get_option( $this->option, null );
			if ( ! is_array( $raw ) ) {
				return;
			}
			$from = $this->stored_schema( $raw );
			if ( $from >= $this->version ) {
				return;
			}
			unset( $raw[ self::SCHEMA_KEY ] );
			$up = $this->run_upgrades( $raw, $from, $this->version );
			if ( ! $up['ok'] ) {
				return;
			}
			$this->import( $up['values'] );
		}

		/**
		 * Run upgrades from $from+1 through $to. On throw or non-array, returns the pre-upgrade values.
		 *
		 * @param array $values Field values (no _schema).
		 * @param int   $from   Stored (or file) schema version.
		 * @param int   $to     Target schema version.
		 * @return array{ok: bool, values: array}
		 */
		public function run_upgrades( array $values, $from, $to ) {
			unset( $values[ self::SCHEMA_KEY ] );
			$original = $values;
			$from     = (int) $from;
			$to       = (int) $to;
			for ( $v = $from + 1; $v <= $to; $v++ ) {
				if ( ! isset( $this->upgrades[ $v ] ) || ! is_callable( $this->upgrades[ $v ] ) ) {
					continue;
				}
				try {
					$next = call_user_func( $this->upgrades[ $v ], $values );
				} catch ( Exception $e ) {
					self::doing_it_wrong( sprintf( 'Upgrade callback for version %1$d of option 「%2$s」 threw; stored values were not written.', $v, $this->option ), 'Wenpai_Admin_Settings::run_upgrades' );
					return array(
						'ok'     => false,
						'values' => $original,
					);
				} catch ( Error $e ) {
					self::doing_it_wrong( sprintf( 'Upgrade callback for version %1$d of option 「%2$s」 threw; stored values were not written.', $v, $this->option ), 'Wenpai_Admin_Settings::run_upgrades' );
					return array(
						'ok'     => false,
						'values' => $original,
					);
				}
				if ( ! is_array( $next ) ) {
					self::doing_it_wrong( sprintf( 'Upgrade callback for version %1$d of option 「%2$s」 did not return an array; stored values were not written.', $v, $this->option ), 'Wenpai_Admin_Settings::run_upgrades' );
					return array(
						'ok'     => false,
						'values' => $original,
					);
				}
				$values = $next;
				unset( $values[ self::SCHEMA_KEY ] );
			}
			return array(
				'ok'     => true,
				'values' => $values,
			);
		}

		/**
		 * Schema version recorded in a stored option array; missing means 1.
		 *
		 * @param array $stored Stored option.
		 * @return int
		 */
		private function stored_schema( array $stored ) {
			if ( ! isset( $stored[ self::SCHEMA_KEY ] ) || ! is_numeric( $stored[ self::SCHEMA_KEY ] ) ) {
				return 1;
			}
			return max( 1, (int) $stored[ self::SCHEMA_KEY ] );
		}

		/**
		 * Attach _schema when register() was given version.
		 *
		 * @param array $out Sanitized values.
		 * @return array
		 */

		/**
		 * Normalize a field path from schema or a bracket-shaped field id.
		 *
		 * @param mixed  $path Schema 'path' (array|string|null).
		 * @param string $id   Field id (fallback when it looks like a[b][c]).
		 * @return string[]|null
		 */
		private static function normalize_path( $path, $id ) {
			if ( is_array( $path ) ) {
				$segs = array();
				foreach ( $path as $seg ) {
					if ( ! is_scalar( $seg ) ) {
						continue;
					}
					$seg = (string) $seg;
					if ( '' !== $seg ) {
						$segs[] = $seg;
					}
				}
				return array() === $segs ? null : $segs;
			}
			if ( is_string( $path ) && '' !== $path ) {
				$parsed = self::parse_bracket_path( $path );
				if ( null !== $parsed ) {
					return $parsed;
				}
			}
			return self::parse_bracket_path( $id );
		}

		/**
		 * Parse 'a[b][c]' / 'a.b.c' into segments. A plain id with no brackets
		 * or dots returns null (flat field).
		 *
		 * @param string $spec Path string or field id.
		 * @return string[]|null
		 */
		private static function parse_bracket_path( $spec ) {
			$spec = (string) $spec;
			if ( '' === $spec ) {
				return null;
			}
			if ( false !== strpos( $spec, '[' ) ) {
				if ( ! preg_match( '/^([^\[\]]+)((?:\[[^\[\]]+\])+)$/', $spec, $m ) ) {
					return null;
				}
				$segs = array( $m[1] );
				if ( preg_match_all( '/\[([^\[\]]+)\]/', $m[2], $mm ) ) {
					foreach ( $mm[1] as $seg ) {
						$segs[] = $seg;
					}
				}
				return $segs;
			}
			if ( false !== strpos( $spec, '.' ) ) {
				$segs = array_values( array_filter( explode( '.', $spec ), 'strlen' ) );
				return count( $segs ) > 1 ? $segs : null;
			}
			return null;
		}

		/**
		 * Read a nested value by path segments.
		 *
		 * @param mixed    $data Root array.
		 * @param string[] $path Segments.
		 * @return mixed|null Null when missing.
		 */
		private static function path_get( $data, array $path ) {
			$cur = $data;
			foreach ( $path as $seg ) {
				if ( ! is_array( $cur ) || ! array_key_exists( $seg, $cur ) ) {
					return null;
				}
				$cur = $cur[ $seg ];
			}
			return $cur;
		}

		/**
		 * Write a nested value by path segments (creates arrays as needed).
		 *
		 * @param array    $data Root array (by ref).
		 * @param string[] $path Segments.
		 * @param mixed    $value Value to set.
		 */
		private static function path_set( array &$data, array $path, $value ) {
			$cur  =& $data;
			$last = count( $path ) - 1;
			foreach ( $path as $i => $seg ) {
				if ( $i === $last ) {
					$cur[ $seg ] = $value;
					return;
				}
				if ( ! isset( $cur[ $seg ] ) || ! is_array( $cur[ $seg ] ) ) {
					$cur[ $seg ] = array();
				}
				$cur =& $cur[ $seg ];
			}
		}

		/**
		 * Posted or programmatic value for a field: nested path first, then flat id.
		 *
		 * @param array  $input Submitted / commit values.
		 * @param string $id    Field id.
		 * @return mixed|null Null when absent (not on the page).
		 */
		private function input_value( array $input, $id ) {
			$path = $this->field_path( $id );
			if ( null !== $path ) {
				$nested = self::path_get( $input, $path );
				if ( null !== $nested ) {
					return $nested;
				}
			}
			return array_key_exists( $id, $input ) ? $input[ $id ] : null;
		}

		/**
		 * Stored value for a field from the raw option array (nested or flat).
		 *
		 * @param array  $stored  Raw option.
		 * @param string $id      Field id.
		 * @param mixed  $fallback Default when missing.
		 * @return mixed
		 */
		private function stored_field_value( array $stored, $id, $fallback ) {
			$path = $this->field_path( $id );
			if ( null !== $path ) {
				$v = self::path_get( $stored, $path );
				return null !== $v ? $v : $fallback;
			}
			return array_key_exists( $id, $stored ) ? $stored[ $id ] : $fallback;
		}

		/**
		 * Flatten a stored option into field-id => value (for all()/get()/depends_on).
		 *
		 * @param array $stored Raw option without requiring _schema.
		 * @return array<string, mixed>
		 */
		private function flatten_stored( array $stored ) {
			$out = array();
			foreach ( $this->fields as $id => $f ) {
				if ( ! self::stores( $f ) ) {
					continue;
				}
				$path = $this->field_path( $id );
				if ( null !== $path ) {
					$v = self::path_get( $stored, $path );
					if ( null !== $v ) {
						$out[ $id ] = $v;
					}
				} elseif ( array_key_exists( $id, $stored ) ) {
					$out[ $id ] = $stored[ $id ];
				}
			}
			return $out;
		}

		/**
		 * Pack a field-id => value map into the option storage shape (nested paths).
		 *
		 * @param array $flat Field-id keyed values.
		 * @return array
		 */
		private function pack_values( array $flat ) {
			$out = array();
			foreach ( $this->fields as $id => $f ) {
				if ( ! self::stores( $f ) || ! array_key_exists( $id, $flat ) ) {
					continue;
				}
				$out = $this->write_field_value( $out, $id, $flat[ $id ] );
			}
			return $out;
		}

		/**
		 * Write one field's value into a storage-shaped array.
		 *
		 * @param array  $storage Storage array.
		 * @param string $id      Field id.
		 * @param mixed  $value   Value.
		 * @return array
		 */
		private function write_field_value( array $storage, $id, $value ) {
			$path = $this->field_path( $id );
			if ( null !== $path ) {
				self::path_set( $storage, $path, $value );
			} else {
				$storage[ $id ] = $value;
			}
			return $storage;
		}

		/**
		 * Add the schema version to an export payload when explicitly configured.
		 *
		 * @param array $out Export payload.
		 * @return array
		 */
		private function with_schema( array $out ) {
			if ( $this->version_explicit ) {
				$out[ self::SCHEMA_KEY ] = $this->version;
			} else {
				unset( $out[ self::SCHEMA_KEY ] );
			}
			return $out;
		}

		// --- Output ---

		/**
		 * Echo the markup for one field.
		 *
		 * @param string $id Field id.
		 */
		public function render_field( $id ) {
			echo $this->field_html( $id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped parts; caller HTML (choice icon_html, status render) goes through wp_kses in field_html().
		}

		/**
		 * Echo every field of a tab (schema order) plus the hidden tab marker, so
		 * saving this form writes only these fields.
		 *
		 * @param string $tab Tab id.
		 */
		public function render_tab( $tab ) {
			$out = $this->tab_input_html( $tab );
			foreach ( $this->fields_in_tab( $tab ) as $id ) {
				$out .= $this->field_html( $id );
			}
			echo $out; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
		}

		/**
		 * Hidden marker for per-tab save, with its nonce. Put it once inside the
		 * form. On save only fields whose 'tab' is this tab are validated and
		 * written; the kit merges every other field from the saved option. A
		 * marker whose nonce does not verify writes nothing.
		 *
		 * @param string $tab Tab id.
		 * @return string
		 */
		public function tab_input_html( $tab ) {
			$tab   = sanitize_key( (string) $tab );
			$nonce = function_exists( 'wp_create_nonce' ) ? wp_create_nonce( $this->tab_nonce_action( $tab ) ) : '';
			return '<input type="hidden" name="' . esc_attr( $this->option . '[' . self::TAB_KEY . ']' ) . '" value="' . esc_attr( $tab ) . '">'
				. '<input type="hidden" name="' . esc_attr( $this->option . '[' . self::TAB_NONCE_KEY . ']' ) . '" value="' . esc_attr( $nonce ) . '">';
		}

		/**
		 * Evaluate a depends_on tree and name the reason of the first failing
		 * condition. Only the field's own conditions are looked at: a trigger
		 * that is itself hidden or disabled simply counts as unmet, and the
		 * reason stays this field's own (「开启 B 后可用」), not the upstream one.
		 *
		 * @param array $node     Normalized node.
		 * @param array $values   Values.
		 * @param array $inactive Inactive field ids (keys).
		 * @return array{0: bool, 1: string} Result and reason.
		 */
		private function eval_reason( array $node, array $values, array $inactive ) {
			if ( isset( $node['field'] ) ) {
				$ok = $this->node_met( $node, $values, $inactive );
				return array( $ok, $ok ? '' : $node['reason'] );
			}
			$reason = '';
			foreach ( $node['rules'] as $r ) {
				list( $ok, $why ) = $this->eval_reason( $r, $values, $inactive );
				if ( 'OR' === $node['relation'] && $ok ) {
					return array( true, '' );
				}
				if ( 'AND' === $node['relation'] && ! $ok ) {
					return array( false, '' !== $why ? $why : $node['reason'] );
				}
				if ( '' === $reason ) {
					$reason = $why;
				}
			}
			return 'AND' === $node['relation'] ? array( true, '' ) : array( false, '' !== $reason ? $reason : $node['reason'] );
		}

		/**
		 * Initial depends_on state from saved values, the same decision the
		 * server makes on save. Printed so the page is right without JS and
		 * before JS runs.
		 *
		 * @param mixed $dep Schema depends_on.
		 * @return array{mode: string, reason: string}|null Set only when the condition fails.
		 */
		private function server_off( $dep ) {
			$tree = self::dep_tree( $dep );
			if ( null === $tree ) {
				return null;
			}
			$values           = $this->all();
			list( $ok, $why ) = $this->eval_reason( $tree, $values, $this->inactive_fields( $values ) );
			if ( $ok ) {
				return null;
			}
			return array(
				'mode'   => $tree['mode'],
				'reason' => '' !== $why ? $why : $tree['reason'],
			);
		}

		/**
		 * Disable every form control in a block of field HTML, hidden companions
		 * included, so a field disabled by depends_on submits nothing.
		 *
		 * @param string $html Field HTML.
		 * @return string
		 */
		private static function disable_controls( $html ) {
			return (string) preg_replace( '/<(input|select|textarea|button)\b(?![^>]*\sdisabled(?=[\s>=\/]))/', '<$1 disabled data-wenpai-dep-dis', $html );
		}

		/**
		 * Echo only the control of one field, for layouts that draw their own
		 * label (simple-row). Pass 'labelledby' with the id of that label.
		 *
		 * @param string $id   Field id.
		 * @param array  $args Optional. 'labelledby', 'describedby'.
		 */
		public function render_control( $id, array $args = array() ) {
			echo $this->render_control_html( $id, $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
		}

		/**
		 * Return what render_control() echoes, e.g. for
		 * Wenpai_Admin_UI::row( array( 'control_html' => … ) ) (0.3.4+).
		 *
		 * @param string $id   Field id.
		 * @param array  $args Optional. 'labelledby', 'describedby'.
		 * @return string Escaped HTML, or '' for an unknown field.
		 */
		public function render_control_html( $id, array $args = array() ) {
			if ( ! isset( $this->fields[ $id ] ) ) {
				return '';
			}
			$f     = $this->fields[ $id ];
			$fid   = $this->field_id( $id );
			$ctl   = array(
				'labelledby'  => isset( $args['labelledby'] ) ? (string) $args['labelledby'] : '',
				'describedby' => isset( $args['describedby'] ) ? (string) $args['describedby'] : '',
				'invalid'     => isset( $this->errors()[ $id ] ),
			);
			$off   = $this->server_off( $f['depends_on'] );
			$inner = $this->control_html( $id, $f, $fid, $ctl );
			$attrs = $this->depends_attrs( $f['depends_on'] );
			if ( null !== $off && 'exclude' === $off['mode'] ) {
				$inner  = self::disable_controls( $inner );
				$attrs .= ' hidden inert data-wenpai-dep-hidden data-wenpai-dep-disabled';
			} elseif ( null !== $off && 'disable' === $off['mode'] ) {
				$inner  = self::disable_controls( $inner );
				$attrs .= ' data-wenpai-dep-disabled';
			} elseif ( null !== $off ) {
				$attrs .= ' hidden inert data-wenpai-dep-hidden';
			}
			return '<span class="wenpai-control" data-wenpai-field="' . esc_attr( $id ) . '"' . $attrs . '>' . $inner . '</span>';
		}

		/**
		 * HTML id of a field's control.
		 *
		 * @param string $id Field id.
		 * @return string
		 */
		public function field_id( $id ) {
			// Bracket / dotted path ids must stay valid in HTML id and for= targets.
			$safe = trim( preg_replace( '/[^A-Za-z0-9_-]+/', '-', (string) $id ), '-' );
			return 'wenpai-f-' . $this->option . '-' . $safe;
		}

		/**
		 * Form input name of a field.
		 *
		 * @param string $id Field id.
		 * @return string
		 */
		public function field_name( $id ) {
			$path = $this->field_path( $id );
			if ( null === $path ) {
				return $this->option . '[' . $id . ']';
			}
			$name = $this->option;
			foreach ( $path as $seg ) {
				$name .= '[' . $seg . ']';
			}
			return $name;
		}

		/**
		 * Nested storage path for a field, or null for a flat top-level key.
		 *
		 * Schema: 'path' => array( 'endpoints', 'deepseek', 'api_key' ) or the
		 * bracket string 'endpoints[deepseek][api_key]'. A field id that itself
		 * looks like a[b][c] is treated as path when 'path' is omitted (Track B /
		 * E09 nested secret). Clear / depends_on / actions still use the field id.
		 *
		 * @param string $id Field id.
		 * @return string[]|null
		 */
		public function field_path( $id ) {
			if ( ! isset( $this->fields[ $id ] ) ) {
				return null;
			}
			$path = $this->fields[ $id ]['path'];
			return is_array( $path ) && array() !== $path ? $path : null;
		}

		/**
		 * Markup for one field: wrapper, label (or legend), control, actions,
		 * description, state, error. Layout 'row' outputs the two-column
		 * .field > .field-label + .field-ctl structure.
		 *
		 * @param string $id Field id.
		 * @return string Escaped HTML, or '' for an unknown field.
		 */
		public function field_html( $id ) {
			if ( ! isset( $this->fields[ $id ] ) ) {
				return '';
			}
			$f      = $this->fields[ $id ];
			$fid    = $this->field_id( $id );
			$errors = $this->errors();
			$err    = isset( $errors[ $id ] ) ? $errors[ $id ] : '';
			$row    = 'row' === $f['layout'];
			$group  = in_array( $f['type'], array( 'radio', 'choice', 'multicheck', 'multiselect', 'matrix', 'status', 'repeater', 'group' ), true );
			$secret = self::is_secret( $f );
			$tree   = self::dep_tree( $f['depends_on'] );
			$reason = null !== $tree && 'disable' === $tree['mode']; // exclude hides the field; no reason element.
			$rows   = 'matrix' === $f['type'] ? $this->error_rows( $id ) : array();
			$off    = $this->server_off( $f['depends_on'] );

			$described = array();
			if ( '' !== $err ) {
				$described[] = $fid . '-error';
			}
			if ( $reason ) {
				$described[] = $fid . '-reason';
			}
			$desc_html = isset( $f['desc_html'] ) && '' !== (string) $f['desc_html'] ? self::kses( (string) $f['desc_html'], 'post' ) : '';
			if ( '' !== $f['desc'] || '' !== $desc_html ) {
				$described[] = $fid . '-desc';
			}
			if ( $secret ) {
				$described[] = $fid . '-state';
			}
			foreach ( $this->actions( $id, $f ) as $a ) {
				$described[] = $a['result_id'];
			}
			$ctl = array(
				'labelledby'   => '',
				'describedby'  => $group ? '' : implode( ' ', $described ),
				'invalid'      => '' !== $err && 'matrix' !== $f['type'],
				'invalid_rows' => $rows,
				'error_id'     => '' !== $err ? $fid . '-error' : '',
			);

			$control = $this->control_html( $id, $f, $fid, $ctl );
			$acts    = $this->actions_html( $id, $f );
			$multi   = ! empty( $f['actions_row'] ) && '' !== $acts;
			$extra   = ( $multi ? '' : $acts ) . $this->generate_html( $f, $fid );
			if ( '' !== $extra ) {
				$control = '<div class="field-ctl-row wenpai-field__row">' . $control . $extra . '</div>';
			}
			$after = '';
			if ( '' !== $err ) {
				$icon   = class_exists( 'Wenpai_Admin_Icons', false ) ? Wenpai_Admin_Icons::svg( 'info' ) : '';
				$after .= '<p class="wenpai-field__error" id="' . esc_attr( $fid . '-error' ) . '">' . $icon . '<span>' . esc_html( $err ) . '</span></p>';
			}
			if ( $reason ) {
				$why    = null !== $off ? $off['reason'] : '';
				$after .= '<p class="wenpai-field__reason" id="' . esc_attr( $fid . '-reason' ) . '"' . ( '' === $why ? ' hidden' : '' ) . '>' . esc_html( $why ) . '</p>';
			}
			if ( '' !== $desc_html ) {
				$after .= '<p class="wenpai-field__desc" id="' . esc_attr( $fid . '-desc' ) . '">' . $desc_html . '</p>';
			} elseif ( '' !== $f['desc'] ) {
				$after .= '<p class="wenpai-field__desc" id="' . esc_attr( $fid . '-desc' ) . '">' . esc_html( $f['desc'] ) . '</p>';
			}
			if ( $secret ) {
				$after .= $this->secret_extra_html( $id, $fid );
			}
			if ( $multi ) {
				$after .= $acts;
			}

			$state = '';
			if ( null !== $off && 'exclude' === $off['mode'] ) {
				// Hidden and not submitted (0.7): controls disabled like mode=disable, plus hidden like mode=hide.
				$control = self::disable_controls( $control );
				$after   = self::disable_controls( $after );
				$state   = ' hidden inert data-wenpai-dep-hidden data-wenpai-dep-disabled';
			} elseif ( null !== $off && 'disable' === $off['mode'] ) {
				// Disabled from saved values, controls included (hidden companions too), so nothing is submitted.
				$control = self::disable_controls( $control );
				$after   = self::disable_controls( $after );
				$state   = ' data-wenpai-dep-disabled';
			} elseif ( null !== $off ) {
				$state = ' hidden inert data-wenpai-dep-hidden';
			}
			$cls = 'wenpai-field wenpai-field--' . $f['type'] . ( $row ? ' field wenpai-field--row' : '' ) . ( '' !== $err ? ' is-invalid' : '' ) . ( '' !== $state && null !== $off && in_array( $off['mode'], array( 'disable', 'exclude' ), true ) ? ' is-disabled' : '' );

			// View rules (0.10): schema fields join the same mode/group contract
			// as hand-written mode_attrs()/group_attrs(). Hidden stays submitted;
			// depends_on and view-hidden combine by OR in kit JS.
			$view = '';
			if ( '' !== $f['mode'] && class_exists( 'Wenpai_Admin_UI', false ) ) {
				$view .= ' data-wenpai-mode="' . esc_attr( $f['mode'] ) . '"';
				if ( Wenpai_Admin_UI::mode() !== $f['mode'] ) {
					$view .= ' data-wenpai-view-hidden';
				}
			}
			if ( '' !== $f['group'] ) {
				$view .= ' data-wenpai-group="' . esc_attr( $f['group'] ) . '"';
				if ( ! empty( $this->sections ) && class_exists( 'Wenpai_Admin_UI', false )
					&& Wenpai_Admin_UI::section( $this->sections ) !== $f['group'] ) {
					$view .= ' data-wenpai-view-hidden';
				}
			}
			if ( false !== strpos( $view, 'data-wenpai-view-hidden' ) && false === strpos( $state, ' hidden' ) ) {
				$view .= ' hidden';
			}

			$open = '<div class="' . esc_attr( $cls ) . '" data-wenpai-field="' . esc_attr( $id ) . '"' . $this->depends_attrs( $f['depends_on'] ) . $state . $view;
			$aria = empty( $described ) ? '' : ' aria-describedby="' . esc_attr( implode( ' ', $described ) ) . '"';

			if ( $row ) {
				if ( $group ) {
					$labelled = $fid . '-label' . ( false !== strpos( $control, 'id="' . $fid . '-pill"' ) ? ' ' . $fid . '-pill' : '' );
					$html     = $open . ' id="' . esc_attr( $fid ) . '" role="group" aria-labelledby="' . esc_attr( $labelled ) . '"' . $aria . '>';
					$html    .= '<div class="field-label" id="' . esc_attr( $fid . '-label' ) . '">' . esc_html( $f['label'] ) . '</div>';
				} else {
					$html  = $open . '>';
					$html .= '<div class="field-label"><label class="wenpai-field__label" for="' . esc_attr( $fid ) . '">' . esc_html( $f['label'] ) . '</label></div>';
				}
				return $html . '<div class="field-ctl">' . $control . $after . '</div></div>';
			}
			if ( 'status' === $f['type'] ) {
				$labelled = $fid . '-label' . ( false !== strpos( $control, 'id="' . $fid . '-pill"' ) ? ' ' . $fid . '-pill' : '' );
				$html     = $open . ' role="group" aria-labelledby="' . esc_attr( $labelled ) . '"' . $aria . '>';
				$html    .= '<span class="wenpai-field__label" id="' . esc_attr( $fid . '-label' ) . '">' . esc_html( $f['label'] ) . '</span>';
				return $html . $control . $after . '</div>';
			}
			if ( $group ) {
				$html  = $open . '>';
				$html .= '<fieldset class="wenpai-field__group" id="' . esc_attr( $fid ) . '"' . $aria . '>';
				$html .= '<legend class="wenpai-field__label">' . esc_html( $f['label'] ) . '</legend>';
				return $html . $control . '</fieldset>' . $after . '</div>';
			}
			$html  = $open . '>';
			$html .= '<label class="wenpai-field__label" for="' . esc_attr( $fid ) . '">' . esc_html( $f['label'] ) . '</label>';
			return $html . $control . $after . '</div>';
		}

		/**
		 * Secret field state line and the clear checkbox.
		 *
		 * @param string $id  Field id.
		 * @param string $fid Control id.
		 * @return string
		 */
		private function secret_extra_html( $id, $fid ) {
			$val   = $this->get( $id );
			$saved = is_scalar( $val ) && '' !== (string) $val;
			$text  = $saved ? $this->strings['secret_saved'] : $this->strings['secret_empty'];
			$own   = isset( $this->fields[ $id ]['state_text'] ) ? (string) $this->fields[ $id ]['state_text'] : '';
			if ( $saved && '' !== $own ) {
				$text = $own;
			}
			$html = '<p class="wenpai-field__state ' . ( $saved ? 'is-saved' : 'is-empty' ) . '" id="' . esc_attr( $fid . '-state' ) . '">' . esc_html( $text ) . '</p>';
			if ( $saved ) {
				$html .= '<label class="wenpai-field__clear"><input type="checkbox" name="' . esc_attr( $this->option . '[' . self::CLEAR_KEY . '][]' ) . '" value="' . esc_attr( $id ) . '"> ' . esc_html( $this->strings['secret_clear'] ) . '</label>';
			}
			return $html;
		}

		/**
		 * Normalized action buttons of a field: the first one, always .btn-secondary; with
		 * field key actions_row => true all of them, and 'variant' => 'ghost' is honoured.
		 *
		 * Schema: 'actions' => array( array( 'id', 'label', 'ajax' (wp_ajax) or 'post' (admin-post Action),
		 * 'nonce' (defaults to the ajax action), 'include' (field ids sent along,
		 * defaults to this field), 'busy' (in-progress copy WITHOUT the trailing
		 * 「…」, e.g. 正在测试; kit JS appends 「…」; default action_busy 正在处理),
		 * 'data' (extra static params), 'requires' (field ids that must have a
		 * value), 'requires_note' (why the button is disabled, e.g. 先填写 API
		 * 密钥。), 'result' (the last known receipt, printed on page load: array(
		 * 'ok' => bool, 'message' => plain text ); ajax actions only) ) ).
		 *
		 * Receipt copy comes from the handler (see ajax_action()): a failure
		 * returns WP_Error whose message may name the service, e.g.
		 * 暂时无法连接百度翻译，请检查密钥。 action_ok / action_failed are only the
		 * global fallback (bare true/false, network error, timeout).
		 *
		 * @param string $id Field id.
		 * @param array  $f  Field schema.
		 * @return array<int, array>
		 */
		private function actions( $id, array $f ) {
			$out = array();
			$row = ! empty( $f['actions_row'] );
			foreach ( (array) $f['actions'] as $i => $a ) {
				if ( ! is_array( $a ) || empty( $a['label'] ) ) {
					continue;
				}
				$post = ! empty( $a['post'] ) ? sanitize_key( (string) $a['post'] ) : '';
				$ajax = ! empty( $a['ajax'] ) ? (string) $a['ajax'] : '';
				if ( '' === $post && '' === $ajax ) {
					continue;
				}
				$aid   = isset( $a['id'] ) ? sanitize_key( (string) $a['id'] ) : (string) $i;
				$out[] = array(
					'id'            => $aid,
					'variant'       => $row && isset( $a['variant'] ) && 'ghost' === $a['variant'] ? 'ghost' : 'secondary',
					'label'         => (string) $a['label'],
					'ajax'          => $ajax,
					'post'          => $post,
					'nonce'         => isset( $a['nonce'] ) ? (string) $a['nonce'] : ( '' !== $ajax ? $ajax : $post ),
					'include'       => isset( $a['include'] ) ? (array) $a['include'] : array( $id ),
					'busy'          => isset( $a['busy'] ) ? (string) $a['busy'] : $this->strings['action_busy'],
					'data'          => isset( $a['data'] ) && is_array( $a['data'] ) ? $a['data'] : array(),
					'requires'      => isset( $a['requires'] ) ? (array) $a['requires'] : array(),
					'requires_note' => isset( $a['requires_note'] ) ? (string) $a['requires_note'] : '',
					'result_id'     => $this->field_id( $id ) . '-act-' . $aid,
					'result'        => self::initial_result( isset( $a['result'] ) ? $a['result'] : null ),
				);
				if ( ! $row ) {
					break;
				}
			}
			return $out;
		}

		/**
		 * The action button followed by its receipt (role=status, filled by kit
		 * JS). The button carries `hidden` until kit JS runs: without JS there
		 * is nothing to press.
		 *
		 * Default: the first action only, next to the control. Field key
		 * actions_row => true: every action in a .wenpai-actions row under the
		 * field (after the secret state line), receipts after the row.
		 *
		 * @param string $id Field id.
		 * @param array  $f  Field schema.
		 * @return string
		 */
		private function actions_html( $id, array $f ) {
			$acts = $this->actions( $id, $f );
			if ( ! empty( $f['actions_row'] ) && array() !== $acts ) {
				$btns = '';
				$recs = '';
				foreach ( $acts as $a ) {
					$one   = $this->action_html( $a );
					$cut   = strpos( $one, '<p class="wenpai-field__result' );
					$btns .= false === $cut ? $one : substr( $one, 0, $cut );
					$recs .= false === $cut ? '' : substr( $one, $cut );
				}
				return '<div class="wenpai-actions wenpai-field__actions">' . $btns . '</div>' . $recs;
			}
			$out = '';
			foreach ( $acts as $a ) {
				$out .= $this->action_html( $a );
			}
			return $out;
		}

		/**
		 * One normalized action: admin-post form, or ajax button + receipt.
		 *
		 * @param array $a Entry from actions().
		 * @return string
		 */
		private function action_html( array $a ) {
			$btn = 'btn btn-' . $a['variant'];
			if ( '' !== $a['post'] && class_exists( 'Wenpai_Admin_Action', false ) ) {
				// Track C / E10: admin-post Action path (works without JS; single page notice).
				$action_url = function_exists( 'admin_url' ) ? admin_url( 'admin-post.php' ) : 'admin-post.php';
				$out        = '<form method="post" action="' . esc_url( $action_url ) . '" class="wenpai-field__action-form">';
				$out       .= Wenpai_Admin_Action::fields_html( $a['post'] );
				foreach ( $a['data'] as $dk => $dv ) {
					if ( is_scalar( $dv ) ) {
						$out .= '<input type="hidden" name="' . esc_attr( (string) $dk ) . '" value="' . esc_attr( (string) $dv ) . '" />';
					}
				}
				$out .= '<button type="submit" class="' . esc_attr( $btn ) . '" data-wenpai-busy="' . esc_attr( $a['busy'] ) . '">'
					. esc_html( $a['label'] ) . '</button>';
				return $out . '</form>';
			}
			$names = array();
			foreach ( $a['include'] as $inc ) {
				$names[] = $this->field_name( (string) $inc );
			}
			$needs = array();
			foreach ( $a['requires'] as $req ) {
				$needs[] = $this->field_name( (string) $req );
			}
			$out = '<button type="button" class="' . esc_attr( $btn ) . '" hidden'
				. ' data-wenpai-action="' . esc_attr( $a['ajax'] ) . '"'
				. ' data-wenpai-nonce="' . esc_attr( function_exists( 'wp_create_nonce' ) ? wp_create_nonce( $a['nonce'] ) : '' ) . '"'
				. ' data-wenpai-url="' . esc_attr( function_exists( 'admin_url' ) ? admin_url( 'admin-ajax.php' ) : 'admin-ajax.php' ) . '"'
				. ' data-wenpai-include="' . esc_attr( wp_json_encode( $names ) ) . '"'
				. ( empty( $a['data'] ) ? '' : ' data-wenpai-data="' . esc_attr( wp_json_encode( $a['data'] ) ) . '"' )
				. ( empty( $needs ) ? '' : ' data-wenpai-requires="' . esc_attr( wp_json_encode( $needs ) ) . '" data-wenpai-requires-note="' . esc_attr( $a['requires_note'] ) . '"' )
				. ' data-wenpai-result="' . esc_attr( $a['result_id'] ) . '"'
				. ' data-wenpai-busy="' . esc_attr( $a['busy'] ) . '"'
				. ' data-wenpai-ok="' . esc_attr( $this->strings['action_ok'] ) . '"'
				. ' data-wenpai-fail="' . esc_attr( $this->strings['action_failed'] ) . '"'
				. ' aria-describedby="' . esc_attr( $a['result_id'] ) . '">'
				. esc_html( $a['label'] ) . '</button>';
			if ( null === $a['result'] ) {
				return $out . '<p class="wenpai-field__result" id="' . esc_attr( $a['result_id'] ) . '" role="status" aria-live="polite"></p>';
			}
			// Same markup kit JS leaves after a run (setResult()), so the next run or an edit to an included field replaces it.
			$ok = $a['result']['ok'];
			return $out . '<p class="wenpai-field__result ' . ( $ok ? 'is-ok' : 'is-bad' ) . '" id="' . esc_attr( $a['result_id'] ) . '" role="' . ( $ok ? 'status' : 'alert' ) . '" aria-live="polite" data-wenpai-filled="1">'
				. esc_html( $a['result']['message'] ) . '</p>';
		}

		/**
		 * Normalize an action's initial receipt.
		 *
		 * @param mixed $r array( 'ok' => bool, 'message' => string ), or anything else for none.
		 * @return array{ok: bool, message: string}|null Null when there is no message to show.
		 */
		private static function initial_result( $r ) {
			if ( ! is_array( $r ) || ! isset( $r['message'] ) || ! is_scalar( $r['message'] ) || '' === (string) $r['message'] ) {
				return null;
			}
			return array(
				'ok'      => ! empty( $r['ok'] ),
				'message' => (string) $r['message'],
			);
		}

		/**
		 * Client-side random generator button (kit 0.9.2). Field arg `generate`:
		 * true or array( 'length' => 8..128 (default 32), 'charset' =>
		 * 'hex'|'base64url'|'alnum' (default 'hex'), 'label' ). Only on
		 * single-line text-like types; kit JS fills the control by id and
		 * fires input/change so depends_on and dirty guards see it.
		 *
		 * @param array  $f   Field schema.
		 * @param string $fid Control id.
		 * @return string
		 */
		private function generate_html( array $f, $fid ) {
			$g = $f['generate'];
			if ( empty( $g ) || ! in_array( $f['type'], array( 'text', 'secret' ), true ) ) {
				return '';
			}
			$g       = true === $g ? array() : (array) $g;
			$length  = isset( $g['length'] ) ? (int) $g['length'] : 32;
			$length  = max( 8, min( 128, $length ) );
			$charset = isset( $g['charset'] ) && in_array( $g['charset'], array( 'hex', 'base64url', 'alnum' ), true ) ? $g['charset'] : 'hex';
			$label   = isset( $g['label'] ) && '' !== (string) $g['label'] ? (string) $g['label'] : $this->strings['generate'];
			return '<button type="button" class="btn btn-ghost wenpai-generate"'
				. ' data-wenpai-generate-for="' . esc_attr( $fid ) . '"'
				. ' data-wenpai-generate-length="' . esc_attr( (string) $length ) . '"'
				. ' data-wenpai-generate-charset="' . esc_attr( $charset ) . '">'
				. esc_html( $label ) . '</button>';
		}

		/**
		 * Control markup by type.
		 *
		 * @param string $id  Field id.
		 * @param array  $f   Field schema.
		 * @param string $fid Control id.
		 * @param array  $ctl 'labelledby', 'describedby', 'invalid'.
		 * @return string
		 */
		/**
		 * Media picker row: thumb + readonly url + hidden id + select/clear.
		 *
		 * @param array  $f    Field schema.
		 * @param string $fid  Control id.
		 * @param string $name Input name.
		 * @param int    $v    Attachment id.
		 * @param string $aria Aria attributes.
		 * @param string $inv  aria-invalid attribute.
		 * @return string
		 */
		private function media_html( array $f, $fid, $name, $v, $aria, $inv ) {
			$url = '';
			if ( $v > 0 && function_exists( 'wp_get_attachment_image_url' ) ) {
				$url = (string) wp_get_attachment_image_url( $v, 'thumbnail' );
			}
			$thumb = '' !== $url
				? '<img src="' . esc_url( $url ) . '" alt="">'
				: esc_html( $this->strings['media_empty'] );
			$out   = '<span class="wenpai-media-row">';
			$out  .= '<span class="wenpai-media-thumb" data-wenpai-media-thumb data-wenpai-media-empty="' . esc_attr( $this->strings['media_empty'] ) . '">' . $thumb . '</span>';
			$out  .= '<input type="text" readonly value="' . esc_attr( $url ) . '" data-wenpai-media-url tabindex="-1">';

			// The visible number input is the submitted control (kit 0.9.2):
			// without JS or wp.media the user can still type an attachment ID;
			// kit JS keeps it in sync with the media frame selection.
			$out .= '<input type="number" class="wenpai-media-id" min="0" step="1" inputmode="numeric" data-wenpai-media-id id="' . esc_attr( $fid ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $v > 0 ? $v : '' ) . '"' . $aria . $inv . '>';
			$out .= '<button type="button" class="btn btn-ghost" data-wenpai-media-select aria-controls="' . esc_attr( $fid ) . '">' . esc_html( $this->strings['media_select'] ) . '</button>';
			$out .= '<button type="button" class="btn btn-ghost" data-wenpai-media-clear aria-controls="' . esc_attr( $fid ) . '">' . esc_html( $this->strings['media_clear'] ) . '</button>';
			return $out . '</span>';
		}

		/**
		 * Visual group: fieldset + legend + left rule, rows of registered fields.
		 *
		 * @param array $f Field schema with 'fields' => list of registered ids.
		 * @return string
		 */
		private function group_html( array $f ) {
			$out = '<fieldset class="wenpai-group"><legend class="screen-reader-text">' . esc_html( $f['label'] ) . '</legend><div class="wenpai-group-body">';
			foreach ( (array) $f['fields'] as $sid ) {
				$out .= $this->field_html( (string) $sid );
			}
			return $out . '</div></fieldset>';
		}

		/**
		 * Scrollable checklist for multicheck/multiselect (0.7).
		 *
		 * Presentation checkgroup: bordered list, head with selected count and
		 * select-all / clear buttons (kit JS). Presentation catalog adds a
		 * filter input that hides non-matching items (kit JS). Choice arrays may
		 * carry 'meta' for the right-aligned note.
		 *
		 * @param array  $f    Field schema.
		 * @param string $name Input name (without []).
		 * @param mixed  $v    Current value.
		 * @param string $inv  aria-invalid attribute or ''.
		 * @return string
		 */
		private function checkgroup_html( array $f, $name, $v, $inv ) {
			$multi   = 'multicheck' === $f['type'];
			$pres    = (string) $f['presentation'];
			$choices = self::resolve_choices( $f );
			$picked  = array_map( 'strval', (array) $v );
			$total   = count( $choices );
			$sel     = 0;
			foreach ( $choices as $k => $lab ) {
				if ( in_array( (string) $k, $picked, true ) ) {
					++$sel;
				}
			}
			$count_tpl = $this->strings['checkgroup_count'];
			$out       = '';
			if ( 'catalog' === $pres ) {
				$out .= '<div class="wenpai-catalog" data-wenpai-catalog>';
				$out .= '<div class="wenpai-catalog__tools">'
					. '<input type="search" data-wenpai-catalog-filter placeholder="' . esc_attr( $this->strings['catalog_filter'] ) . '" aria-label="' . esc_attr( $this->strings['catalog_filter'] ) . '">'
					. ( isset( $f['tools_html'] ) && '' !== (string) $f['tools_html'] ? self::kses( (string) $f['tools_html'], 'control' ) : '' )
					. '</div>';
			}
			$out .= '<div class="wenpai-checkgroup" data-wenpai-checkgroup data-wenpai-count-total="' . esc_attr( $total ) . '">'
				. '<div class="wenpai-checkgroup__head">'
				. '<span class="wenpai-checkgroup__count" data-wenpai-count-template="' . esc_attr( $count_tpl ) . '">'
				. esc_html( sprintf( $count_tpl, $sel, $total ) )
				. '</span>'
				. '<span class="wenpai-checkgroup__acts">'
				. '<button type="button" data-wenpai-cg-all>' . esc_html( $this->strings['checkgroup_all'] ) . '</button>'
				. '<button type="button" data-wenpai-cg-none>' . esc_html( $this->strings['checkgroup_none'] ) . '</button>'
				. '</span></div>';
			$out .= '<input type="hidden" name="' . esc_attr( $name . '[]' ) . '" value="">';
			$out .= '<ul class="wenpai-checkgroup__list">';
			foreach ( $choices as $k => $lab ) {
				$meta  = '';
				$media = '';
				$desc  = '';
				if ( is_array( $lab ) ) {
					$meta  = isset( $lab['meta'] ) ? (string) $lab['meta'] : '';
					$media = isset( $lab['media_html'] ) && '' !== (string) $lab['media_html'] ? self::kses( (string) $lab['media_html'], 'logo' ) : '';
					$desc  = isset( $lab['desc_html'] ) && '' !== (string) $lab['desc_html'] ? self::kses( (string) $lab['desc_html'], 'control' ) : '';
				}
				$out .= '' !== $desc ? '<li class="has-desc">' : '<li>';
				$out .= '<label class="chk"><input type="checkbox" name="' . esc_attr( $name . '[]' ) . '" value="' . esc_attr( $k ) . '"' . checked( in_array( (string) $k, $picked, true ), true, false ) . $inv . '> '
					. ( '' !== $media ? '<span class="wenpai-checkgroup__media">' . $media . '</span>' : '' )
					. '<span class="wenpai-checkgroup__text">' . esc_html( self::choice_label( $lab ) ) . '</span>'
					. ( '' !== $meta ? '<span class="wenpai-checkgroup__meta">' . esc_html( $meta ) . '</span>' : '' )
					. '</label>';
				// Links and copy buttons stay outside the label: no interactive content inside it.
				$out .= ( '' !== $desc ? '<div class="wenpai-checkgroup__desc">' . $desc . '</div>' : '' ) . '</li>';
			}
			$out .= '</ul></div>';
			if ( 'catalog' === $pres ) {
				$out .= '</div>';
			}
			return $out;
		}

		/**
		 * Control markup by type.
		 *
		 * @param string $id  Field id.
		 * @param array  $f   Field schema.
		 * @param string $fid Control id.
		 * @param array  $ctl 'labelledby', 'describedby', 'invalid'.
		 * @return string
		 */
		private function control_html( $id, array $f, $fid, array $ctl ) {
			$v    = $this->get( $id );
			$name = $this->field_name( $id );
			$aria = '';
			if ( '' !== $ctl['labelledby'] ) {
				$aria .= ' aria-labelledby="' . esc_attr( $ctl['labelledby'] ) . '"';
			}
			if ( '' !== $ctl['describedby'] ) {
				$aria .= ' aria-describedby="' . esc_attr( $ctl['describedby'] ) . '"';
			}
			$inv = $ctl['invalid'] ? ' aria-invalid="true"' : '';
			$out = '';
			if ( in_array( $f['type'], array( 'radio', 'choice', 'multicheck', 'multiselect', 'matrix' ), true ) ) {
				$list = 'matrix' === $f['type'] ? self::resolve( $f['rows'], $f ) : self::resolve_choices( $f );
				if ( empty( $list ) ) {
					$noun = isset( $f['empty_noun'] ) ? (string) $f['empty_noun'] : $f['label'];
					return '<p class="wenpai-field__empty">' . esc_html( sprintf( $this->strings['empty_choices'], $noun ) ) . '</p>';
				}
			}
			switch ( $f['type'] ) {
				case 'status':
					return $this->status_html( $f, $fid );
				case 'toggle':
					$pair = self::toggle_values( $f );
					$sw   = array(
						'id'          => $fid,
						'name'        => $name,
						'checked'     => null !== $pair ? is_scalar( $v ) && (string) $v === $pair[0] : (bool) $v,
						'on'          => isset( $f['on'] ) && '' !== (string) $f['on'] ? (string) $f['on'] : $this->strings['on'],
						'off'         => isset( $f['off'] ) && '' !== (string) $f['off'] ? (string) $f['off'] : $this->strings['off'],
						'labelledby'  => $ctl['labelledby'],
						'describedby' => $ctl['describedby'],
						'invalid'     => $ctl['invalid'],
					);
					if ( null !== $pair ) {
						$sw['value']     = $pair[0];
						$sw['off_value'] = $pair[1];
					}
					$switch = self::switch_html( $sw );
					// Track C / E06: instant module toggle via Wenpai_Admin_Action (admin-post).
					$instant = isset( $f['instant'] ) ? sanitize_key( (string) $f['instant'] ) : '';
					if ( '' !== $instant && class_exists( 'Wenpai_Admin_Action', false ) ) {
						$action_url = function_exists( 'admin_url' ) ? admin_url( 'admin-post.php' ) : 'admin-post.php';
						$html       = '<form method="post" action="' . esc_url( $action_url ) . '" class="wenpai-instant" data-wenpai-instant>';
						$html      .= Wenpai_Admin_Action::fields_html( $instant );
						$html      .= $switch;
						$html      .= '<noscript><button type="submit" class="btn btn-secondary">' . esc_html( $this->strings['saved'] ) . '</button></noscript>';
						return $html . '</form>';
					}
					return $switch;
				case 'select':
					$out    .= '<select id="' . esc_attr( $fid ) . '" name="' . esc_attr( $name ) . '"' . $aria . $inv . '>';
					$groups  = isset( $f['groups'] ) && is_array( $f['groups'] ) ? $f['groups'] : array();
					$choices = self::resolve_choices( $f );
					$render  = function ( $k, $lab ) use ( $v ) {
						return '<option value="' . esc_attr( $k ) . '"' . selected( (string) $v, (string) $k, false ) . '>' . esc_html( self::choice_label( $lab ) ) . '</option>';
					};
					if ( empty( $groups ) ) {
						foreach ( $choices as $k => $lab ) {
							$out .= $render( $k, $lab );
						}
						return $out . '</select>';
					}
					$by_group = array();
					foreach ( $choices as $k => $lab ) {
						$g = is_array( $lab ) && isset( $lab['group'] ) ? (string) $lab['group'] : '';
						if ( '' !== $g && isset( $groups[ $g ] ) ) {
							$by_group[ $g ][ $k ] = $lab;
						} else {
							$out .= $render( $k, $lab );
						}
					}
					foreach ( $groups as $gid => $glabel ) {
						if ( empty( $by_group[ $gid ] ) ) {
							continue;
						}
						$out .= '<optgroup label="' . esc_attr( (string) $glabel ) . '">';
						foreach ( $by_group[ $gid ] as $k => $lab ) {
							$out .= $render( $k, $lab );
						}
						$out .= '</optgroup>';
					}
					return $out . '</select>';
				case 'radio':
					if ( 'seg' === $f['presentation'] ) {
						self::ensure_ui();
						$items = array();
						foreach ( self::resolve_choices( $f ) as $k => $lab ) {
							$items[] = array( (string) $k, self::choice_label( $lab ) );
						}
						$seg = Wenpai_Admin_UI::seg_radio_html(
							array(
								'name'  => $name,
								'value' => is_scalar( $v ) ? (string) $v : '',
								'items' => $items,
							)
						);
						return '' === $inv ? $seg : str_replace( '<input type="radio"', '<input type="radio"' . $inv, $seg );
					}
					$out .= '<div class="opts">';
					foreach ( self::resolve_choices( $f ) as $k => $lab ) {
						$on   = (string) $v === (string) $k;
						$out .= '<label><input type="radio" name="' . esc_attr( $name ) . '" value="' . esc_attr( $k ) . '"' . checked( $on, true, false ) . $inv . '> ' . esc_html( self::choice_label( $lab ) ) . '</label>';
					}
					return $out . '</div>';
				case 'multicheck':
					if ( '' !== (string) $f['presentation'] ) {
						return $this->checkgroup_html( $f, $name, $v, $inv );
					}
					$out .= '<div class="chk">';
					foreach ( self::resolve_choices( $f ) as $k => $lab ) {
						$on   = in_array( (string) $k, array_map( 'strval', (array) $v ), true );
						$out .= '<label><input type="checkbox" name="' . esc_attr( $name . '[]' ) . '" value="' . esc_attr( $k ) . '"' . checked( $on, true, false ) . $inv . '> ' . esc_html( self::choice_label( $lab ) ) . '</label>';
					}
					return $out . '</div>';
				case 'choice':
					return $this->choice_html( $f, $name, (string) $v, $inv );
				case 'multiselect':
					if ( '' !== (string) $f['presentation'] ) {
						return $this->checkgroup_html( $f, $name, $v, $inv );
					}
					$picked = array_map( 'strval', (array) $v );
					$out   .= '<div class="chk-grid">';
					$out   .= '<input type="hidden" name="' . esc_attr( $name . '[]' ) . '" value="">';
					foreach ( self::resolve_choices( $f ) as $k => $lab ) {
						$out .= '<label><input type="checkbox" name="' . esc_attr( $name . '[]' ) . '" value="' . esc_attr( $k ) . '"' . checked( in_array( (string) $k, $picked, true ), true, false ) . $inv . '> ' . esc_html( self::choice_label( $lab ) ) . '</label>';
					}
					return $out . '</div>';
				case 'matrix':
					return $this->matrix_html( $f, $fid, $name, is_array( $v ) ? $v : array(), $ctl );
				case 'number':
					return $this->addon_html(
						'<input type="number" id="' . esc_attr( $fid ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $v ) . '"' . self::placeholder_attr( $f ) . ( isset( $f['min'] ) ? ' min="' . esc_attr( $f['min'] ) . '"' : '' ) . ( isset( $f['max'] ) ? ' max="' . esc_attr( $f['max'] ) . '"' : '' ) . $aria . $inv . '>',
						$f
					);
				case 'textarea':
					return '<textarea id="' . esc_attr( $fid ) . '" name="' . esc_attr( $name ) . '" rows="4"' . self::placeholder_attr( $f ) . $aria . $inv . '>' . esc_textarea( $v ) . '</textarea>';
				case 'secret':
					$saved  = is_scalar( $v ) && '' !== (string) $v;
					$input  = '<input type="password" id="' . esc_attr( $fid ) . '" name="' . esc_attr( $name ) . '" value=""' . self::placeholder_attr( $f ) . ' autocomplete="new-password" spellcheck="false" data-wenpai-secret="' . esc_attr( $id ) . '" data-wenpai-multiline="' . esc_attr( $this->strings['secret_multiline'] ) . '"' . ( $saved ? ' data-wenpai-saved' : '' ) . $aria . $inv . '>';
					$reveal = '<button type="button" class="btn btn-ghost wenpai-field__reveal" data-wenpai-reveal aria-pressed="false" aria-controls="' . esc_attr( $fid ) . '" aria-label="' . esc_attr( $this->strings['secret_show'] ) . '" data-wenpai-reveal-show="' . esc_attr( $this->strings['secret_show'] ) . '" data-wenpai-reveal-hide="' . esc_attr( $this->strings['secret_hide'] ) . '">' . esc_html( $this->strings['secret_show'] ) . '</button>';
					return '<span class="wenpai-field__secret">' . $input . $reveal . '</span>';
				case 'email':
					return '<input type="email" id="' . esc_attr( $fid ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( is_scalar( $v ) ? $v : '' ) . '"' . self::placeholder_attr( $f ) . ' autocomplete="email"' . $aria . $inv . '>';
				case 'url':
					return '<input type="url" id="' . esc_attr( $fid ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( is_scalar( $v ) ? $v : '' ) . '"' . self::placeholder_attr( $f ) . ' autocomplete="url"' . $aria . $inv . '>';
				case 'date':
					return '<input type="date" id="' . esc_attr( $fid ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( is_scalar( $v ) ? $v : '' ) . '"' . $aria . $inv . '>';
				case 'color':
					$hex = is_scalar( $v ) && '' !== (string) $v ? self::sanitize_hex_color( $v, '#000000' ) : '#000000';
					return '<span class="wenpai-color-row"><input type="color" id="' . esc_attr( $fid ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $hex ) . '"' . $aria . $inv . '><code data-wenpai-color-hex aria-hidden="true">' . esc_html( strtoupper( $hex ) ) . '</code></span>';
				case 'media':
					return $this->media_html( $f, $fid, $name, (int) $v, $aria, $inv );
				case 'group':
					return $this->group_html( $f );
				case 'repeater':
					return $this->repeater_html( $id, $f, $fid, $name, is_array( $v ) ? $v : array() );
				case 'entity':
					return $this->entity_html( $f, $fid, $name, $v, $aria, $inv );
				case 'file':
					return $this->file_html( $id, $f, $fid, $name, $v, $aria, $inv );
				case 'tags':
					return $this->tags_html( $f, $fid, $name, $v, $aria, $inv );
				case 'schedule':
					return $this->schedule_html( $f, $fid, $name, $v, $aria, $inv );
				case 'icon':
					return $this->icon_html( $f, $fid, $name, $v, $aria, $inv );
				case 'time':
				case 'datetime-local':
				case 'week':
					return '<input type="' . esc_attr( $f['type'] ) . '" id="' . esc_attr( $fid ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( is_scalar( $v ) ? $v : '' ) . '"' . $aria . $inv . '>';
				case 'range':
					return $this->range_html( $f, $fid, $name, $v, $aria, $inv );
				default:
					return $this->addon_html(
						'<input type="text" id="' . esc_attr( $fid ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( is_scalar( $v ) ? $v : '' ) . '"' . self::placeholder_attr( $f ) . ' autocomplete="off"' . $aria . $inv . '>',
						$f
					);
			}
		}

		/**
		 * Entity picker (kit 0.8): picked label + meta + clear, a search input,
		 * the hidden submitted id, and the dropdown list shell (kit JS fills it
		 * from wp_ajax_wenpai_entity_lookup).
		 *
		 * @param array  $f    Field schema.
		 * @param string $fid  Control id.
		 * @param string $name Input name.
		 * @param mixed  $v    Current value (entity id or '').
		 * @param string $aria Aria attributes.
		 * @param string $inv  aria-invalid attribute.
		 * @return string
		 */
		private function entity_html( array $f, $fid, $name, $v, $aria, $inv ) {
			self::ensure_ajax_endpoints();
			$kind                 = self::entity_kind( $f );
			$object               = 'post' === $kind
				? implode( ',', self::entity_post_types( $f ) )
				: ( 'term' === $kind ? self::entity_taxonomy( $f ) : '' );
			$id                   = absint( $v );
			list( $label, $meta ) = self::entity_label_meta( $f, $id );
			$ajax                 = function_exists( 'admin_url' ) ? admin_url( 'admin-ajax.php' ) : 'admin-ajax.php';
			$nonce                = function_exists( 'wp_create_nonce' ) ? wp_create_nonce( 'wenpai_entity_lookup' ) : '';
			$out                  = '<span class="wenpai-entity" data-wenpai-entity data-kind="' . esc_attr( $kind ) . '" data-object="' . esc_attr( $object ) . '"'
				. ' data-ajax="' . esc_attr( $ajax ) . '" data-nonce="' . esc_attr( $nonce ) . '" data-wenpai-entity-nonce="' . esc_attr( $nonce ) . '"'
				. ' data-empty="' . esc_attr( $this->strings['entity_empty'] ) . '">';
			$out                 .= '<span class="wenpai-entity__picked"' . ( $id > 0 ? '' : ' hidden' ) . '>'
				. '<span class="wenpai-entity__picked-label">' . esc_html( $label ) . '</span>'
				. ( '' !== $meta ? '<span class="wenpai-entity__meta">' . esc_html( $meta ) . '</span>' : '' )
				. '<button type="button" class="wenpai-entity__clear">' . esc_html( $this->strings['entity_clear'] ) . '</button></span>';
			$out                 .= '<input class="wenpai-entity__input" type="search" id="' . esc_attr( $fid ) . '" placeholder="' . esc_attr( $this->strings['entity_search'] ) . '" autocomplete="off"' . $aria . $inv . '>';
			$out                 .= '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $id > 0 ? $id : '' ) . '">';
			$out                 .= '<span class="wenpai-entity__out" hidden><ul class="wenpai-entity__list"></ul></span>';
			return $out . '</span>';
		}

		/**
		 * File field (kit 0.8): drop zone + file input, saved items with hidden
		 * inputs, and the ajax upload endpoint wiring.
		 *
		 * @param string $id   Field id (registry key suffix).
		 * @param array  $f    Field schema.
		 * @param string $fid  Control id.
		 * @param string $name Input name.
		 * @param mixed  $v    Current value (list of items).
		 * @param string $aria Aria attributes.
		 * @param string $inv  aria-invalid attribute.
		 * @return string
		 */
		private function file_html( $id, array $f, $fid, $name, $v, $aria, $inv ) {
			self::ensure_ajax_endpoints();
			// Mime whitelist for wp_ajax_wenpai_file_upload, keyed option__field.
			self::$file_fields[ $this->option . '__' . $id ] = isset( $f['mime'] ) ? $f['mime'] : array();
			$items = is_array( $v ) ? array_values( $v ) : array();
			$multi = ! empty( $f['multiple'] );
			$ajax  = function_exists( 'admin_url' ) ? admin_url( 'admin-ajax.php' ) : 'admin-ajax.php';
			$nonce = function_exists( 'wp_create_nonce' ) ? wp_create_nonce( 'wenpai_file_upload' ) : '';
			$label = isset( $f['button_label'] ) && '' !== (string) $f['button_label'] ? (string) $f['button_label'] : $this->strings['file_choose'];
			$out   = '<span class="wenpai-file" data-wenpai-file data-name="' . esc_attr( $name ) . '" data-multiple="' . ( $multi ? '1' : '0' ) . '"'
				. ' data-field-key="' . esc_attr( $this->option . '__' . $id ) . '"'
				. ' data-ajax="' . esc_attr( $ajax ) . '" data-nonce="' . esc_attr( $nonce ) . '"'
				. ' data-remove="' . esc_attr( $this->strings['file_remove'] ) . '"'
				. ' data-uploading="' . esc_attr( $this->strings['file_uploading'] ) . '" data-failed="' . esc_attr( $this->strings['file_failed'] ) . '">';
			$out  .= '<span class="wenpai-file__drop" role="button" tabindex="0" aria-label="' . esc_attr( $label ) . '">';
			if ( class_exists( 'Wenpai_Admin_Icons', false ) ) {
				$out .= Wenpai_Admin_Icons::svg( 'upload' );
			}
			$out .= '<span>' . esc_html( $this->strings['file_drop'] ) . '</span>';
			$out .= '<input type="file" class="wenpai-file__input" id="' . esc_attr( $fid ) . '" hidden' . ( $multi ? ' multiple' : '' ) . $aria . $inv . '>';
			$out .= '</span>';
			$out .= '<span class="wenpai-file__list">';
			foreach ( $items as $i => $item ) {
				$out .= $this->file_item_html( $name, $i, is_array( $item ) ? $item : array() );
			}
			return $out . '</span></span>';
		}

		/**
		 * One saved file item: name, size, remove button, hidden inputs.
		 *
		 * @param string $name  Field name (without item keys).
		 * @param int    $i     Item index.
		 * @param array  $item  Sanitized item.
		 * @return string
		 */
		private function file_item_html( $name, $i, array $item ) {
			$out  = '<span class="wenpai-file__item">';
			$out .= '<span class="wenpai-file__name">' . esc_html( isset( $item['name'] ) ? (string) $item['name'] : '' ) . '</span>';
			$out .= '<span class="wenpai-file__size">' . esc_html( self::file_size( isset( $item['size'] ) ? $item['size'] : 0 ) ) . '</span>';
			$out .= '<button type="button" class="wenpai-file__remove">' . esc_html( $this->strings['file_remove'] ) . '</button>';
			foreach ( array( 'id', 'url', 'name', 'size', 'type' ) as $k ) {
				$val  = isset( $item[ $k ] ) ? $item[ $k ] : '';
				$out .= '<input type="hidden" name="' . esc_attr( $name . '[' . $i . '][' . $k . ']' ) . '" value="' . esc_attr( $val ) . '">';
			}
			return $out . '</span>';
		}

		/**
		 * Human file size for the item meta line.
		 *
		 * @param mixed $bytes Byte count.
		 * @return string
		 */
		private static function file_size( $bytes ) {
			$bytes = max( 0, (float) $bytes );
			if ( $bytes >= 1048576 ) {
				return round( $bytes / 1048576, 1 ) . ' MB';
			}
			if ( $bytes >= 1024 ) {
				return round( $bytes / 1024, 1 ) . ' KB';
			}
			return (string) (int) $bytes . ' B';
		}

		/**
		 * Tags input (kit 0.8): chips with remove buttons, one hidden input per
		 * tag (plus the empty sentinel when there are none), and the text input.
		 *
		 * @param array  $f    Field schema.
		 * @param string $fid  Control id.
		 * @param string $name Input name (without []).
		 * @param mixed  $v    Current value (list of strings).
		 * @param string $aria Aria attributes.
		 * @param string $inv  aria-invalid attribute.
		 * @return string
		 */
		private function tags_html( array $f, $fid, $name, $v, $aria, $inv ) {
			$tags = array();
			foreach ( (array) $v as $t ) {
				if ( is_scalar( $t ) && '' !== trim( (string) $t ) ) {
					$tags[] = sanitize_text_field( (string) $t );
				}
			}
			$out = '<span class="wenpai-tags" data-wenpai-tags><span class="wenpai-tags__chips">';
			foreach ( $tags as $t ) {
				$out .= '<span class="wenpai-tags__chip">' . esc_html( $t )
					. '<button type="button" class="wenpai-tags__chip-x" aria-label="' . esc_attr( $this->strings['file_remove'] ) . '">×</button></span>';
			}
			$out .= '</span>';
			if ( array() === $tags ) {
				$out .= '<input type="hidden" name="' . esc_attr( $name . '[]' ) . '" value="">';
			}
			foreach ( $tags as $t ) {
				$out .= '<input type="hidden" name="' . esc_attr( $name . '[]' ) . '" value="' . esc_attr( $t ) . '">';
			}
			$out .= '<input class="wenpai-tags__input" type="text" id="' . esc_attr( $fid ) . '" placeholder="' . esc_attr( $this->strings['tags_placeholder'] ) . '" autocomplete="off"' . $aria . $inv . '>';
			return $out . '</span>';
		}

		/**
		 * Schedule picker (kit 0.8): a select of wp_get_schedules() plus an
		 * optional next-run line when 'hook' and 'show_next' are set.
		 *
		 * @param array  $f    Field schema.
		 * @param string $fid  Control id.
		 * @param string $name Input name.
		 * @param mixed  $v    Current value (schedule slug).
		 * @param string $aria Aria attributes.
		 * @param string $inv  aria-invalid attribute.
		 * @return string
		 */
		private function schedule_html( array $f, $fid, $name, $v, $aria, $inv ) {
			$schedules = function_exists( 'wp_get_schedules' ) ? (array) wp_get_schedules() : array();
			$out       = '<select id="' . esc_attr( $fid ) . '" name="' . esc_attr( $name ) . '"' . $aria . $inv . '>';
			foreach ( $schedules as $key => $s ) {
				$label = is_array( $s ) && isset( $s['display'] ) ? (string) $s['display'] : ( is_scalar( $s ) ? (string) $s : (string) $key );
				$out  .= '<option value="' . esc_attr( $key ) . '"' . selected( (string) $v, (string) $key, false ) . '>' . esc_html( $label ) . '</option>';
			}
			$out .= '</select>';
			if ( ! empty( $f['show_next'] ) && isset( $f['hook'] ) && '' !== (string) $f['hook'] ) {
				$next = function_exists( 'wp_next_scheduled' ) ? wp_next_scheduled( (string) $f['hook'] ) : false;
				if ( $next ) {
					$when = function_exists( 'human_time_diff' ) ? human_time_diff( (int) $next, time() ) : '';
					if ( '' === (string) $when && function_exists( 'date_i18n' ) ) {
						$df   = function_exists( 'get_option' ) ? get_option( 'date_format' ) : false;
						$tf   = function_exists( 'get_option' ) ? get_option( 'time_format' ) : false;
						$when = date_i18n( ( $df ? (string) $df : 'Y-m-d' ) . ' ' . ( $tf ? (string) $tf : 'H:i' ), (int) $next );
					}
					$text = sprintf( $this->strings['schedule_next'], (string) $when );
				} else {
					$text = $this->strings['schedule_none'];
				}
				$out .= '<p class="wenpai-schedule__next">' . esc_html( $text ) . '</p>';
			}
			return $out;
		}

		/**
		 * Icon picker (kit 0.8): text input + preview + picker grid of every
		 * icon from Wenpai_Admin_Icons::names() (empty grid until names() lands).
		 *
		 * @param array  $f    Field schema.
		 * @param string $fid  Control id.
		 * @param string $name Input name.
		 * @param mixed  $v    Current value (icon name or '').
		 * @param string $aria Aria attributes.
		 * @param string $inv  aria-invalid attribute.
		 * @return string
		 */
		private function icon_html( array $f, $fid, $name, $v, $aria, $inv ) {
			$has_icons = class_exists( 'Wenpai_Admin_Icons', false );
			$names     = array();
			if ( $has_icons && method_exists( 'Wenpai_Admin_Icons', 'names' ) ) {
				$names = array_map( 'strval', (array) Wenpai_Admin_Icons::names() );
				sort( $names );
			}
			$cur = is_scalar( $v ) ? trim( (string) $v ) : '';
			$svg = '';
			if ( $has_icons && '' !== $cur && ( ! method_exists( 'Wenpai_Admin_Icons', 'has' ) || Wenpai_Admin_Icons::has( $cur ) ) ) {
				$svg = Wenpai_Admin_Icons::svg( $cur );
			}
			$out  = '<span class="wenpai-icon" data-wenpai-icon>';
			$out .= '<input type="text" id="' . esc_attr( $fid ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $cur ) . '" autocomplete="off" spellcheck="false"' . $aria . $inv . '>';
			$out .= '<span class="wenpai-icon__preview' . ( '' === $svg ? ' is-empty' : '' ) . '" aria-hidden="true">' . $svg . '</span>';
			$out .= '<button type="button" class="btn btn-ghost wenpai-icon__pick">选择图标</button>';
			$out .= '<span class="wenpai-icon__picker" hidden>'
				. '<input type="search" class="wenpai-icon__filter" placeholder="' . esc_attr( $this->strings['entity_search'] ) . '" aria-label="' . esc_attr( $this->strings['entity_search'] ) . '">'
				. '<span class="wenpai-icon__grid">';
			foreach ( $names as $n ) {
				$opt_svg = $has_icons ? Wenpai_Admin_Icons::svg( $n ) : '';
				$out    .= '<button type="button" class="wenpai-icon__opt" data-name="' . esc_attr( $n ) . '" title="' . esc_attr( $n ) . '">' . $opt_svg . '<span>' . esc_html( $n ) . '</span></button>';
			}
			return $out . '</span></span></span>';
		}

		/**
		 * Range slider (kit 0.8): input + live output with optional unit.
		 *
		 * @param array  $f    Field schema.
		 * @param string $fid  Control id.
		 * @param string $name Input name.
		 * @param mixed  $v    Current value.
		 * @param string $aria Aria attributes.
		 * @param string $inv  aria-invalid attribute.
		 * @return string
		 */
		private function range_html( array $f, $fid, $name, $v, $aria, $inv ) {
			$min  = isset( $f['min'] ) && is_numeric( $f['min'] ) ? (float) $f['min'] : 0;
			$max  = isset( $f['max'] ) && is_numeric( $f['max'] ) ? (float) $f['max'] : 100;
			$step = isset( $f['step'] ) && is_numeric( $f['step'] ) && (float) $f['step'] > 0 ? (float) $f['step'] : 1;
			$unit = isset( $f['unit'] ) ? (string) $f['unit'] : '';
			$val  = ( null === $v || '' === $v || ! is_scalar( $v ) )
				? ( is_numeric( $f['default'] ) ? (float) $f['default'] : $min )
				: ( is_numeric( $v ) ? (float) $v : $min );
			$val  = max( $min, min( $max, $val ) );
			$disp = (string) $val;
			$out  = '<span class="wenpai-range" data-wenpai-range data-unit="' . esc_attr( $unit ) . '">';
			$out .= '<input type="range" id="' . esc_attr( $fid ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $disp ) . '"'
				. ' min="' . esc_attr( $min ) . '" max="' . esc_attr( $max ) . '" step="' . esc_attr( $step ) . '"' . $aria . $inv . '>';
			$out .= '<output class="wenpai-range__out">' . esc_html( $disp . $unit ) . '</output>';
			return $out . '</span>';
		}

		/**
		 * Wrap a control in Field addon affixes (Track E / E18).
		 *
		 * Ban InputGroup. Prefix/suffix hang on Field, not a third family.
		 *
		 * @param string $inner Control HTML.
		 * @param array  $f     Field schema.
		 * @return string
		 */
		private function addon_html( $inner, array $f ) {
			$pre = isset( $f['prefix'] ) ? (string) $f['prefix'] : '';
			$suf = isset( $f['suffix'] ) ? (string) $f['suffix'] : '';
			if ( '' === $pre && '' === $suf ) {
				return $inner;
			}
			$html = '<span class="field-addon">';
			if ( '' !== $pre ) {
				$html .= '<span class="field-addon-affix">' . esc_html( $pre ) . '</span>';
			}
			$html .= $inner;
			if ( '' !== $suf ) {
				$html .= '<span class="field-addon-affix">' . esc_html( $suf ) . '</span>';
			}
			return $html . '</span>';
		}

		/**
		 * Choice cards. A choice may be a label, or array( label, desc, icon
		 * (Wenpai_Admin_Icons name, or dashicons-{name}), dashicon ({name}),
		 * icon_html (SVG, filtered with wp_kses to an SVG allowlist), hint,
		 * disabled, locked ).
		 *
		 * @param array  $f    Field schema.
		 * @param string $name Input name.
		 * @param string $v    Current value.
		 * @param string $inv  aria-invalid attribute or ''.
		 * @return string
		 */
		private function choice_html( array $f, $name, $v, $inv ) {
			$choices = self::resolve_choices( $f );
			$n       = count( $choices );
			// Even counts (2, 4, ...) are two columns, so four cards are 2x2 instead of 3+1 (design wpslug-89-R2).
			$out = '<div class="choice-cards' . ( $n > 0 && 0 === $n % 2 ? ' cols-2' : '' ) . '">';
			foreach ( $choices as $k => $c ) {
				$c    = is_array( $c ) ? $c : array( 'label' => (string) $c );
				$on   = $v === (string) $k;
				$dis  = ! empty( $c['disabled'] );
				$icon = '';
				$dash = ! empty( $c['dashicon'] ) ? (string) $c['dashicon'] : ( ! empty( $c['icon'] ) && 0 === strpos( (string) $c['icon'], 'dashicons-' ) ? substr( (string) $c['icon'], 10 ) : '' );
				if ( ! empty( $c['icon_html'] ) ) {
					$icon = self::kses( (string) $c['icon_html'], 'svg' );
				} elseif ( '' !== $dash ) {
					$icon = '<span class="dashicons dashicons-' . esc_attr( sanitize_html_class( $dash ) ) . '"></span>';
				} elseif ( ! empty( $c['icon'] ) && class_exists( 'Wenpai_Admin_Icons', false ) ) {
					$icon = Wenpai_Admin_Icons::svg( (string) $c['icon'] );
				}
				$cls  = 'choice' . ( $on ? ' is-on' : '' ) . ( $dis ? ' is-soon' : '' ) . ( ! empty( $c['locked'] ) ? ' is-locked' : '' );
				$out .= '<label class="' . esc_attr( $cls ) . '">';
				$out .= '<input type="radio" name="' . esc_attr( $name ) . '" value="' . esc_attr( $k ) . '"' . checked( $on, true, false ) . disabled( $dis, true, false ) . $inv . '>';
				$out .= '' !== $icon ? '<span class="choice-ico" aria-hidden="true">' . $icon . '</span>' : '';
				$hint = isset( $c['hint'] ) && '' !== (string) $c['hint'] ? ' <span class="hint">' . esc_html( (string) $c['hint'] ) . '</span>' : '';
				$out .= '<span><span class="t">' . esc_html( self::choice_label( $c ) ) . $hint . '</span>';
				$out .= isset( $c['desc'] ) && '' !== (string) $c['desc'] ? '<span class="d">' . esc_html( (string) $c['desc'] ) . '</span>' : '';
				$out .= '</span></label>';
			}
			return $out . '</div>';
		}

		/**
		 * Read-only status block. Not submitted, not stored.
		 *
		 * 'status' => array|Closure returning array( level (ok|warn|bad|''), text,
		 * desc (string or list), link (array( url, label )) ). Or 'render' =>
		 * callable returning HTML; it is filtered with wp_kses (post tags + SVG),
		 * but should still be built by the plugin, not from user input.
		 *
		 * @param array  $f   Field schema.
		 * @param string $fid Field control id; the pill gets {fid}-pill.
		 * @return string
		 */
		private function status_html( array $f, $fid ) {
			if ( is_callable( $f['render'] ) ) {
				return '<div class="wenpai-field__status" data-wenpai-status>' . self::kses( (string) call_user_func( $f['render'], $f, $this ), 'post' ) . '</div>';
			}
			$st    = $f['status'] instanceof Closure ? call_user_func( $f['status'], $f, $this ) : $f['status'];
			$st    = is_array( $st ) ? $st : array();
			$level = isset( $st['level'] ) && in_array( $st['level'], array( 'ok', 'warn', 'bad', 'mute' ), true ) ? $st['level'] : 'mute';
			$out   = '<div class="wenpai-field__status" data-wenpai-status="' . esc_attr( $level ) . '">';
			if ( ! empty( $st['text'] ) ) {
				$out .= '<span class="pill ' . esc_attr( $level ) . '" id="' . esc_attr( $fid . '-pill' ) . '">' . esc_html( (string) $st['text'] ) . '</span>';
			}
			$link = '';
			if ( isset( $st['link']['url'], $st['link']['label'] ) ) {
				$target = isset( $st['link']['target'] ) ? trim( (string) $st['link']['target'] ) : '';
				// Keywords (_blank, _self, ...) compare and print lowercase; frame names keep their case.
				if ( in_array( strtolower( $target ), array( '_blank', '_self', '_parent', '_top' ), true ) ) {
					$target = strtolower( $target );
				}
				$blank = '_blank' === $target;
				$rel   = self::link_rel( isset( $st['link']['rel'] ) ? $st['link']['rel'] : null, $blank );
				$link  = ' <a href="' . esc_url( (string) $st['link']['url'] ) . '"'
					. ( '' !== $target ? ' target="' . esc_attr( $target ) . '"' : '' )
					. ( '' !== $rel ? ' rel="' . esc_attr( $rel ) . '"' : '' ) . '>'
					. esc_html( (string) $st['link']['label'] )
					. ( $blank ? '<span class="screen-reader-text">' . esc_html( $this->strings['new_tab'] ) . '</span>' : '' )
					. '</a>';
			}
			$lines = isset( $st['desc'] ) ? array_values( (array) $st['desc'] ) : array();
			$notes = '';
			foreach ( $lines as $i => $line ) {
				$notes .= '<p class="wenpai-field__note">' . esc_html( (string) $line ) . ( count( $lines ) - 1 === $i ? $link : '' ) . '</p>';
			}
			if ( empty( $lines ) && '' !== $link ) {
				$notes .= '<p class="wenpai-field__note">' . ltrim( $link ) . '</p>';
			}
			// P-7: a referral (Capability::status() with dismiss) gets 「不再提示」. It is a link
			// to admin-post (nonce) so it works without JS inside the settings form; kit JS
			// posts it in the background, removes the referral and moves focus on.
			if ( isset( $st['dismiss']['url'], $st['dismiss']['slot'] ) && '' !== (string) $st['dismiss']['url'] ) {
				$d     = $st['dismiss'];
				$label = isset( $d['label'] ) && '' !== (string) $d['label'] ? (string) $d['label'] : (string) $this->strings['dismiss'];
				// The accessible name says what goes (WCAG 2.4.4): 「不再提示：文派心思的推荐」, so two referrals on a page differ.
				$ctx   = isset( $d['context'] ) && '' !== (string) $d['context'] ? (string) $d['context'] : ( isset( $f['label'] ) ? (string) $f['label'] : '' );
				$sr    = '' !== $ctx && '' !== (string) $this->strings['dismiss_context'] ? '<span class="screen-reader-text">' . esc_html( sprintf( (string) $this->strings['dismiss_context'], $ctx ) ) . '</span>' : '';
				$notes = '<div class="wenpai-referral" data-wenpai-referral="' . esc_attr( (string) $d['slot'] ) . '">' . $notes
					. '<p class="wenpai-field__note wenpai-referral__dismiss"><a class="btn btn-ghost" href="' . esc_url( (string) $d['url'] ) . '" data-wenpai-dismiss data-wenpai-dismissed="' . esc_attr( (string) $this->strings['dismissed'] ) . '">' . esc_html( $label ) . $sr . '</a></p></div>';
			}
			return $out . $notes . '</div>';
		}

		/**
		 * The rel attribute of a status link. Each token keeps only [a-z-]; duplicates go. With
		 * target=_blank, noopener is always there; no rel given means
		 * 「noopener noreferrer」.
		 *
		 * @param mixed $rel   Caller rel (string or list) or null.
		 * @param bool  $blank Whether the link opens a new tab.
		 * @return string
		 */
		private static function link_rel( $rel, $blank ) {
			if ( null === $rel ) {
				return $blank ? 'noopener noreferrer' : '';
			}
			$words = array();
			foreach ( preg_split( '/\s+/', strtolower( implode( ' ', array_map( 'strval', (array) $rel ) ) ) ) as $w ) {
				$w = (string) preg_replace( '/[^a-z-]/', '', $w );
				if ( '' !== $w && ! in_array( $w, $words, true ) ) {
					$words[] = $w;
				}
			}
			if ( $blank && ! in_array( 'noopener', $words, true ) ) {
				$words[] = 'noopener';
			}
			return implode( ' ', $words );
		}

		/**
		 * Matrix table: one select per row.
		 *
		 * @param array  $f    Field schema.
		 * @param string $fid  Control id.
		 * @param string $name Input name.
		 * @param array  $v    Current map.
		 * @param array  $ctl  Control args; 'invalid_rows' gets aria-invalid, only those rows.
		 * @return string
		 */
		private function matrix_html( array $f, $fid, $name, array $v, array $ctl ) {
			$choices = self::resolve_choices( $f );
			if ( null !== $f['unset_value'] ) {
				// The "follow global" option always comes first.
				$uv    = (string) $f['unset_value'];
				$label = isset( $choices[ $uv ] ) && '' !== self::choice_label( $choices[ $uv ] ) ? $choices[ $uv ] : $this->strings['follow_global'];
				unset( $choices[ $uv ] );
				$choices = array( $uv => $label ) + $choices;
			}
			$fall    = null !== $f['unset_value'] ? (string) $f['unset_value'] : (string) key( $choices );
			$badrows = array_map( 'strval', $ctl['invalid_rows'] );
			$col     = $fid . '-col';
			$out     = '<table class="tbl wenpai-matrix"><thead><tr>';
			$out    .= '<th scope="col">' . esc_html( $f['row_label'] ) . '</th>';
			$out    .= '<th scope="col" id="' . esc_attr( $col ) . '">' . esc_html( $f['col_label'] ) . '</th>';
			$out    .= '</tr></thead><tbody>';
			foreach ( self::resolve( $f['rows'], $f ) as $row => $r ) {
				$rid  = $fid . '-r-' . sanitize_key( (string) $row );
				$cur  = isset( $v[ $row ] ) && array_key_exists( (string) $v[ $row ], $choices ) ? (string) $v[ $row ] : $fall;
				$desc = is_array( $r ) && isset( $r['desc'] ) ? (string) $r['desc'] : '';
				$bad  = in_array( (string) $row, $badrows, true );
				$inv  = $bad ? ' aria-invalid="true"' . ( '' !== $ctl['error_id'] ? ' aria-describedby="' . esc_attr( $ctl['error_id'] ) . '"' : '' ) : '';
				$out .= '<tr' . ( $bad ? ' class="is-invalid"' : '' ) . '><th scope="row" id="' . esc_attr( $rid ) . '"><span class="t">' . esc_html( self::choice_label( $r ) ) . '</span>' . ( '' !== $desc ? '<span class="d">' . esc_html( $desc ) . '</span>' : '' ) . '</th>';
				$out .= '<td data-label="' . esc_attr( $f['col_label'] ) . '"><select name="' . esc_attr( $name . '[' . $row . ']' ) . '" aria-labelledby="' . esc_attr( $rid . ' ' . $col ) . '"' . $inv . '>';
				foreach ( $choices as $k => $lab ) {
					$out .= '<option value="' . esc_attr( $k ) . '"' . selected( $cur, (string) $k, false ) . '>' . esc_html( self::choice_label( $lab ) ) . '</option>';
				}
				$out .= '</select></td></tr>';
			}
			return $out . '</tbody></table>';
		}

		/**
		 * Repeater control (Track D / E08): existing rows + one empty row;
		 * each row fieldset + legend「第 N 项」; delete「删除第 N 项」.
		 * presentation=card wraps the row body with existing .card classes
		 * (Card composition — never EndpointCard / ModuleCard / StrategyCard).
		 *
		 * @param string $id   Field id.
		 * @param array  $f    Schema.
		 * @param string $fid  Control id prefix.
		 * @param string $name Base input name.
		 * @param array  $v    Current rows.
		 * @return string
		 */
		private function repeater_html( $id, array $f, $fid, $name, array $v ) {
			$fields  = isset( $f['fields'] ) && is_array( $f['fields'] ) ? $f['fields'] : array();
			$max     = isset( $f['max_rows'] ) ? max( 1, (int) $f['max_rows'] ) : 20;
			$card    = isset( $f['presentation'] ) && 'card' === $f['presentation'];
			$rows    = array_values( $v );
			$n_saved = count( $rows );
			if ( $n_saved < $max ) {
				$rows[] = array(); // Trailing empty row for no-JS add-one-on-save.
			}
			$cls   = 'wenpai-repeater' . ( $card ? ' wenpai-repeater--cards' : '' );
			$html  = '<div class="' . esc_attr( $cls ) . '" data-wenpai-repeater data-wenpai-repeater-max="' . esc_attr( (string) $max ) . '" data-wenpai-repeater-name="' . esc_attr( $name ) . '" id="' . esc_attr( $fid ) . '-repeater">';
			$html .= '<div class="wenpai-repeater__rows" data-wenpai-repeater-rows>';
			foreach ( $rows as $i => $row ) {
				$html .= $this->repeater_row_html( $id, $f, $fid, $name, $fields, $i, is_array( $row ) ? $row : array(), $card );
			}
			$html     .= '</div>';
			$tpl_index = '__i__';
			$html     .= '<template data-wenpai-repeater-template>';
			$html     .= $this->repeater_row_html( $id, $f, $fid, $name, $fields, $tpl_index, array(), $card );
			$html     .= '</template>';
			$can_add   = $n_saved < $max;
			$html     .= '<p class="wenpai-repeater__toolbar">';
			$html     .= '<button type="button" class="btn btn-secondary" data-wenpai-repeater-add' . ( $can_add ? '' : ' disabled' ) . '>' . esc_html( $this->strings['repeater_add'] ) . '</button>';
			$html     .= '</p>';
			return $html . '</div>';
		}

		/**
		 * One repeater row.
		 *
		 * @param string     $id       Field id.
		 * @param array      $f        Parent schema.
		 * @param string     $fid      Prefix.
		 * @param string     $name     Base name.
		 * @param array      $fields   Nested schemas.
		 * @param int|string $index    Row index or template token.
		 * @param array      $row      Values.
		 * @param bool       $card     Card presentation.
		 * @return string
		 */
		private function repeater_row_html( $id, array $f, $fid, $name, array $fields, $index, array $row, $card ) {
			$n        = is_int( $index ) ? $index + 1 : 0;
			$legend   = is_int( $index )
				? sprintf( $this->strings['repeater_item'], $n )
				: $this->strings['repeater_item'];
			$del_lab  = is_int( $index )
				? sprintf( $this->strings['repeater_delete'], $n )
				: $this->strings['repeater_delete'];
			$row_id   = $fid . '-r-' . $index;
			$fs_class = 'wenpai-repeater__row' . ( $card ? ' card tight' : '' );
			$html     = '<fieldset class="' . esc_attr( $fs_class ) . '" data-wenpai-repeater-row data-wenpai-repeater-index="' . esc_attr( (string) $index ) . '" id="' . esc_attr( $row_id ) . '">';
			$html    .= '<legend class="wenpai-repeater__legend">' . esc_html( is_int( $index ) ? $legend : sprintf( $this->strings['repeater_item'], 0 ) ) . '</legend>';
			if ( $card ) {
				$html .= '<div class="card-head"><div><span class="card-title">' . esc_html( is_int( $index ) ? $legend : sprintf( $this->strings['repeater_item'], 0 ) ) . '</span></div></div>';
			}
			$html .= '<div class="wenpai-repeater__fields">';
			foreach ( $fields as $sfid => $sf ) {
				$val   = array_key_exists( $sfid, $row ) ? $row[ $sfid ] : $sf['default'];
				$cname = $name . '[' . $index . '][' . $sfid . ']';
				$cid   = $fid . '-' . $index . '-' . $sfid;
				$label = isset( $sf['label'] ) ? (string) $sf['label'] : $sfid;
				$html .= '<div class="wenpai-repeater__field wenpai-field wenpai-field--' . esc_attr( $sf['type'] ) . '">';
				$html .= '<label class="wenpai-field__label" for="' . esc_attr( $cid ) . '">' . esc_html( $label ) . '</label>';
				$html .= $this->repeater_subcontrol_html( $sf, $cid, $cname, $val );
				if ( ! empty( $sf['desc'] ) ) {
					$html .= '<p class="wenpai-field__desc">' . esc_html( (string) $sf['desc'] ) . '</p>';
				}
				$html .= '</div>';
			}
			$html .= '</div>';
			$html .= '<div class="wenpai-repeater__row-actions">';
			$html .= '<button type="button" class="btn btn-ghost" data-wenpai-repeater-up aria-label="' . esc_attr( $this->strings['repeater_move_up'] ) . '">' . esc_html( $this->strings['repeater_move_up'] ) . '</button>';
			$html .= '<button type="button" class="btn btn-ghost" data-wenpai-repeater-down aria-label="' . esc_attr( $this->strings['repeater_move_down'] ) . '">' . esc_html( $this->strings['repeater_move_down'] ) . '</button>';
			$html .= '<button type="button" class="btn btn-ghost" data-wenpai-repeater-remove>' . esc_html( is_int( $index ) ? $del_lab : sprintf( $this->strings['repeater_delete'], 0 ) ) . '</button>';
			$html .= '</div>';
			return $html . '</fieldset>';
		}

		/**
		 * Nested control inside a repeater row (lean type set).
		 *
		 * @param array  $sf   Subfield schema.
		 * @param string $cid  Input id.
		 * @param string $name Input name.
		 * @param mixed  $val  Value.
		 * @return string
		 */
		private function repeater_subcontrol_html( array $sf, $cid, $name, $val ) {
			switch ( $sf['type'] ) {
				case 'toggle':
					$html  = '<input type="hidden" name="' . esc_attr( $name ) . '" value="0">';
					$html .= '<input type="checkbox" role="switch" id="' . esc_attr( $cid ) . '" name="' . esc_attr( $name ) . '" value="1"' . checked( ! empty( $val ), true, false ) . '>';
					return $html;
				case 'number':
					return $this->addon_html(
						'<input type="number" id="' . esc_attr( $cid ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( is_scalar( $val ) ? $val : 0 ) . '"' . self::placeholder_attr( $sf )
						. ( isset( $sf['min'] ) && null !== $sf['min'] ? ' min="' . esc_attr( $sf['min'] ) . '"' : '' )
						. ( isset( $sf['max'] ) && null !== $sf['max'] ? ' max="' . esc_attr( $sf['max'] ) . '"' : '' ) . '>',
						$sf
					);
				case 'textarea':
					return '<textarea id="' . esc_attr( $cid ) . '" name="' . esc_attr( $name ) . '" rows="3"' . self::placeholder_attr( $sf ) . '>' . esc_textarea( is_scalar( $val ) ? (string) $val : '' ) . '</textarea>';
				case 'email':
					return '<input type="email" id="' . esc_attr( $cid ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( is_scalar( $val ) ? $val : '' ) . '"' . self::placeholder_attr( $sf ) . ' autocomplete="email">';
				case 'url':
					return '<input type="url" id="' . esc_attr( $cid ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( is_scalar( $val ) ? $val : '' ) . '"' . self::placeholder_attr( $sf ) . ' autocomplete="url">';
				case 'select':
					$html = '<select id="' . esc_attr( $cid ) . '" name="' . esc_attr( $name ) . '">';
					foreach ( self::resolve( $sf['choices'], $sf ) as $k => $lab ) {
						$html .= '<option value="' . esc_attr( $k ) . '"' . selected( (string) $val, (string) $k, false ) . '>' . esc_html( self::choice_label( $lab ) ) . '</option>';
					}
					return $html . '</select>';
				case 'secret':
					$saved = is_scalar( $val ) && '' !== (string) $val;
					return '<input type="password" id="' . esc_attr( $cid ) . '" name="' . esc_attr( $name ) . '" value=""' . self::placeholder_attr( $sf ) . ' autocomplete="new-password" spellcheck="false"' . ( $saved ? ' data-wenpai-saved' : '' ) . '>';
				default:
					return '<input type="text" id="' . esc_attr( $cid ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( is_scalar( $val ) ? $val : '' ) . '"' . self::placeholder_attr( $sf ) . ' autocomplete="off">';
			}
		}

		/**
		 * ` placeholder="…"` for a text-like control, or '' when the field sets none.
		 *
		 * @param array $f Field or repeater subfield schema; 'placeholder' is plain text.
		 * @return string
		 */
		private static function placeholder_attr( array $f ) {
			if ( ! isset( $f['placeholder'] ) || ! is_scalar( $f['placeholder'] ) || '' === (string) $f['placeholder'] ) {
				return '';
			}
			return ' placeholder="' . esc_attr( (string) $f['placeholder'] ) . '"';
		}

		/**
		 * A toggle's stored values when the field maps on/off to strings.
		 *
		 * @param array $f Field schema; 'values' => array( on, off ).
		 * @return array{0:string,1:string}|null Null keeps the boolean toggle.
		 */
		private static function toggle_values( array $f ) {
			if ( ! isset( $f['values'] ) || ! is_array( $f['values'] ) || 2 !== count( $f['values'] ) ) {
				return null;
			}
			$pair = array_values( $f['values'] );
			if ( ! is_scalar( $pair[0] ) || ! is_scalar( $pair[1] ) || (string) $pair[0] === (string) $pair[1] ) {
				return null;
			}
			return array( (string) $pair[0], (string) $pair[1] );
		}

		/**
		 * Return the label of a choice or row given as a string or array('label' => ...).
		 *
		 * @param mixed $c Choice.
		 * @return string
		 */
		private static function choice_label( $c ) {
			if ( is_array( $c ) ) {
				return isset( $c['label'] ) ? (string) $c['label'] : '';
			}
			return (string) $c;
		}

		/**
		 * The one kit switch: a native checkbox with role="switch", styled as a
		 * track. Works without JS. A hidden "0" input makes "off" submit.
		 *
		 * @param array $a {
		 *     Switch arguments.
		 *
		 *     @type string $id          Control id. Required.
		 *     @type string $name        Input name. Omit for a display-only switch.
		 *     @type bool   $checked     Current state.
		 *     @type string $on          State text when on (translated).
		 *     @type string $off         State text when off (translated).
		 *     @type bool   $disabled    Disabled.
		 *     @type string $labelledby  Id of the visible label, when not using <label for>.
		 *     @type string $describedby Space-separated ids.
		 *     @type bool   $invalid     Adds aria-invalid.
		 *     @type string $value       Submitted when on. Default "1".
		 *     @type string $off_value   Submitted when off (hidden input). Default "0".
		 * }
		 * @return string
		 */
		public static function switch_html( array $a ) {
			$a    = wp_parse_args(
				$a,
				array(
					'id'          => '',
					'name'        => '',
					'checked'     => false,
					'on'          => '',
					'off'         => '',
					'disabled'    => false,
					'labelledby'  => '',
					'describedby' => '',
					'invalid'     => false,
					'value'       => '1',
					'off_value'   => '0',
				)
			);
			$attr = ' id="' . esc_attr( $a['id'] ) . '"';
			if ( '' !== $a['name'] ) {
				$attr .= ' name="' . esc_attr( $a['name'] ) . '" value="' . esc_attr( (string) $a['value'] ) . '"';
			}
			if ( '' !== $a['labelledby'] ) {
				$attr .= ' aria-labelledby="' . esc_attr( $a['labelledby'] ) . '"';
			}
			if ( '' !== $a['describedby'] ) {
				$attr .= ' aria-describedby="' . esc_attr( $a['describedby'] ) . '"';
			}
			if ( $a['invalid'] ) {
				$attr .= ' aria-invalid="true"';
			}
			$out = '<span class="wenpai-switch">';
			if ( '' !== $a['name'] ) {
				$out .= '<input type="hidden" name="' . esc_attr( $a['name'] ) . '" value="' . esc_attr( (string) $a['off_value'] ) . '">';
			}
			$out .= '<input type="checkbox" role="switch" class="wenpai-switch__input"' . $attr . checked( (bool) $a['checked'], true, false ) . disabled( (bool) $a['disabled'], true, false ) . '>';
			$out .= '<span class="wenpai-switch__track" aria-hidden="true"></span>';
			if ( '' !== $a['on'] || '' !== $a['off'] ) {
				$out .= '<span class="wenpai-switch__state" aria-hidden="true"><span class="wenpai-switch__on">' . esc_html( $a['on'] ) . '</span><span class="wenpai-switch__off">' . esc_html( $a['off'] ) . '</span></span>';
			}
			return $out . '</span>';
		}

		// --- depends_on ---

		/**
		 * Normalize depends_on into a condition tree.
		 *
		 * Accepted: one condition array( field, value, not ); a list (all must
		 * hold); or a group array( 'mode' => hide|disable, 'relation' => AND|OR,
		 * 'rules' => array( conditions or nested groups ) ).
		 *
		 * @param mixed $dep Schema value.
		 * @return array|null Group array( mode, relation, rules ) or null.
		 */
		private static function dep_tree( $dep ) {
			if ( empty( $dep ) || ! is_array( $dep ) ) {
				return null;
			}
			$mode = isset( $dep['mode'] ) && in_array( $dep['mode'], array( 'hide', 'disable', 'exclude' ), true ) ? $dep['mode'] : 'hide';
			$node = self::dep_node( $dep );
			if ( null === $node ) {
				return null;
			}
			if ( ! isset( $node['rules'] ) ) {
				$node = array(
					'relation' => 'AND',
					'rules'    => array( $node ),
					'reason'   => '',
				);
			}
			$node['mode'] = $mode;
			return $node;
		}

		/**
		 * Normalize one node of a depends_on tree.
		 *
		 * @param mixed $n Condition, list or group.
		 * @return array|null
		 */
		private static function dep_node( $n ) {
			if ( ! is_array( $n ) ) {
				return null;
			}
			if ( isset( $n['field'] ) ) {
				$vals = array_key_exists( 'value', $n ) ? $n['value'] : true;
				$norm = array();
				foreach ( ( is_array( $vals ) ? $vals : array( $vals ) ) as $x ) {
					$norm[] = is_bool( $x ) ? ( $x ? '1' : '0' ) : (string) $x;
				}
				return array(
					'field'  => (string) $n['field'],
					'value'  => $norm,
					'not'    => ! empty( $n['not'] ),
					'reason' => isset( $n['reason'] ) ? (string) $n['reason'] : '',
				);
			}
			$relation = isset( $n['relation'] ) && 'OR' === strtoupper( (string) $n['relation'] ) ? 'OR' : 'AND';
			$list     = $n;
			if ( isset( $n['any'] ) && is_array( $n['any'] ) ) {
				$relation = 'OR';
				$list     = $n['any'];
			} elseif ( isset( $n['all'] ) && is_array( $n['all'] ) ) {
				$relation = 'AND';
				$list     = $n['all'];
			} elseif ( isset( $n['rules'] ) && is_array( $n['rules'] ) ) {
				$list = $n['rules'];
			}
			$rules = array();
			foreach ( $list as $k => $child ) {
				if ( in_array( $k, array( 'mode', 'relation', 'reason' ), true ) ) {
					continue;
				}
				$c = self::dep_node( $child );
				if ( null !== $c ) {
					$rules[] = $c;
				}
			}
			if ( empty( $rules ) ) {
				return null;
			}
			return array(
				'relation' => $relation,
				'rules'    => $rules,
				'reason'   => isset( $n['reason'] ) ? (string) $n['reason'] : '',
			);
		}

		/**
		 * Tree with field ids replaced by input names, for the JSON attribute.
		 *
		 * @param array $node Normalized node.
		 * @return array
		 */
		private function dep_json( array $node ) {
			if ( isset( $node['field'] ) ) {
				$c = array(
					'on'    => $this->field_name( $node['field'] ),
					'value' => $node['value'],
					'not'   => $node['not'],
				);
				if ( '' !== $node['reason'] ) {
					$c['reason'] = $node['reason'];
				}
				$saved = $this->saved_flag( $node );
				if ( null !== $saved ) {
					$c['saved'] = $saved;
				}
				return $c;
			}
			$out = array(
				'relation' => $node['relation'],
				'rules'    => array(),
			);
			if ( '' !== $node['reason'] ) {
				$out['reason'] = $node['reason'];
			}
			if ( isset( $node['mode'] ) ) {
				$out = array( 'mode' => $node['mode'] ) + $out;
			}
			foreach ( $node['rules'] as $r ) {
				$out['rules'][] = $this->dep_json( $r );
			}
			return $out;
		}

		/**
		 * Result of one condition against saved values, for kit JS to use when
		 * the trigger's control is not on the page. Only a boolean is emitted,
		 * never a value; conditions on secret fields emit nothing.
		 *
		 * @param array $node Normalized condition.
		 * @return bool|null
		 */
		private function saved_flag( array $node ) {
			if ( ! isset( $this->fields[ $node['field'] ] ) || self::is_secret( $this->fields[ $node['field'] ] ) ) {
				return null;
			}
			$values = $this->all();
			return $this->node_met( $node, $values, $this->inactive_fields( $values ) );
		}

		/**
		 * Data attributes for kit JS. The initial state (hidden / disabled) is
		 * printed by the caller from saved values, see server_off().
		 *
		 * Each condition also carries `saved` (data-wenpai-depends-saved="1|0"
		 * in the plain form): its result against saved values, used by kit JS
		 * when the trigger control is not on the page.
		 *
		 * One plain condition: data-wenpai-depends-on, data-wenpai-depends-value
		 * (a value, or a JSON list for "any of"), data-wenpai-depends-not,
		 * data-wenpai-depends-mode="disable". Anything else: data-wenpai-depends,
		 * a JSON tree {mode, relation, rules: [{on, value, not} | group]}.
		 *
		 * @param mixed $dep Schema depends_on.
		 * @return string
		 */
		private function depends_attrs( $dep ) {
			$tree = self::dep_tree( $dep );
			if ( null === $tree ) {
				return '';
			}
			if ( 1 === count( $tree['rules'] ) && isset( $tree['rules'][0]['field'] ) ) {
				$c   = $tree['rules'][0];
				$val = 1 === count( $c['value'] ) ? $c['value'][0] : wp_json_encode( $c['value'] );
				$why = '' !== $c['reason'] ? $c['reason'] : $tree['reason'];
				return ' data-wenpai-depends-on="' . esc_attr( $this->field_name( $c['field'] ) ) . '" data-wenpai-depends-value="' . esc_attr( $val ) . '"'
					. ( $c['not'] ? ' data-wenpai-depends-not' : '' )
					. ( in_array( $tree['mode'], array( 'disable', 'exclude' ), true ) ? ' data-wenpai-depends-mode="' . $tree['mode'] . '"' : '' )
					. ( in_array( $tree['mode'], array( 'disable', 'exclude' ), true ) && '' !== $why ? ' data-wenpai-depends-reason="' . esc_attr( $why ) . '"' : '' )
					. ( null !== $this->saved_flag( $c ) ? ' data-wenpai-depends-saved="' . ( $this->saved_flag( $c ) ? '1' : '0' ) . '"' : '' );
			}
			return ' data-wenpai-depends="' . esc_attr( wp_json_encode( $this->dep_json( $tree ) ) ) . '"';
		}

		/**
		 * Attributes for a hand-written block, card, section or tab link that
		 * should follow a field.
		 *
		 * The condition is checked against saved values and ' hidden inert
		 * data-wenpai-dep-hidden' is added when it fails, so the block is right
		 * without JS. Hand-written blocks only get hide mode on the server; put
		 * fields with mode=disable|exclude through render_field(). For exclude,
		 * kit JS disables the block's controls on boot — without JS they would
		 * still submit, so hand-written forms must validate server-side anyway.
		 *
		 * @param array $depends_on Same shape as the schema key.
		 * @param array $args       Optional. 'server' (bool, kept for 0.3.0-dev callers; always on now).
		 * @return string Leading space included.
		 */
		public function depends_on_attrs( array $depends_on, array $args = array() ) {
			$out = $this->depends_attrs( $depends_on );
			unset( $args );
			if ( ! $this->condition_met( $depends_on ) ) {
				$out .= ' hidden inert data-wenpai-dep-hidden';
			}
			return $out;
		}

		/**
		 * Evaluate depends_on against saved values (for tabs, sections and
		 * server-side decisions).
		 *
		 * @param array $depends_on Same shape as the schema key.
		 * @return bool True when there is no condition.
		 */
		public function condition_met( array $depends_on ) {
			$tree = self::dep_tree( $depends_on );
			return null === $tree ? true : $this->node_met( $tree, $this->all(), $this->inactive_fields( $this->all() ) );
		}

		/**
		 * Evaluate one node against values.
		 *
		 * @param array $node     Normalized node.
		 * @param array $values   Field values.
		 * @param array $inactive Field ids (keys) that are hidden or disabled; they never match.
		 * @return bool
		 */
		private function node_met( array $node, array $values, array $inactive = array() ) {
			if ( isset( $node['field'] ) ) {
				if ( ! array_key_exists( $node['field'], $values ) ) {
					return true;
				}
				if ( isset( $inactive[ $node['field'] ] ) ) {
					// A hidden or disabled trigger never satisfies a condition, `not` included (spec §12).
					return false;
				}
				$have = array();
				foreach ( ( is_array( $values[ $node['field'] ] ) ? $values[ $node['field'] ] : array( $values[ $node['field'] ] ) ) as $x ) {
					$have[] = is_bool( $x ) ? ( $x ? '1' : '0' ) : (string) $x;
				}
				$match = array() !== array_intersect( $have, $node['value'] );
				return $node['not'] ? ! $match : $match;
			}
			foreach ( $node['rules'] as $r ) {
				$ok = $this->node_met( $r, $values, $inactive );
				if ( 'OR' === $node['relation'] && $ok ) {
					return true;
				}
				if ( 'AND' === $node['relation'] && ! $ok ) {
					return false;
				}
			}
			return 'AND' === $node['relation'];
		}

		// --- AJAX actions (field buttons, plugin preview cards) ---

		/**
		 * Hook the kit 0.8 picker endpoints once per request. Fixed action names
		 * (wenpai_entity_lookup / wenpai_file_upload): several plugins vendoring
		 * this kit share one hook because the class is defined once.
		 */
		private static function ensure_ajax_endpoints() {
			if ( self::$ajax_endpoints || ! function_exists( 'add_action' ) ) {
				return;
			}
			self::$ajax_endpoints = true;
			add_action( 'wp_ajax_wenpai_entity_lookup', array( __CLASS__, 'ajax_entity_lookup' ) );
			add_action( 'wp_ajax_wenpai_file_upload', array( __CLASS__, 'ajax_file_upload' ) );
		}

		/**
		 * Enqueue the media modal assets, but only on pages that belong to a
		 * kit-registered plugin (Wenpai_Admin_Menu::owns_page). Schemas with a
		 * `media` field need wp.media for the picker; loading it on every admin
		 * page would cost every plugin visitor the media stack for nothing.
		 * Products rendering the form outside a kit-registered page call
		 * wp_enqueue_media() themselves.
		 */
		public static function maybe_enqueue_media() {
			if ( ! self::$needs_media || wp_doing_ajax() || ! function_exists( 'wp_enqueue_media' ) ) {
				return;
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only asset scoping, not form processing.
			$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
			if ( '' === $page || ! class_exists( 'Wenpai_Admin_Menu', false ) || ! Wenpai_Admin_Menu::owns_page( $page ) ) {
				return;
			}
			wp_enqueue_media();
		}

		/**
		 * The wp_ajax_wenpai_entity_lookup endpoint: entity picker search. Nonce action and
		 * capability depend on kind (post→edit_posts, term→manage_categories,
		 * user→list_users); failures wp_die(-1). Returns up to 20 items of
		 * array( id, label, meta ); empty q lists the most recent 20.
		 */
		public static function ajax_entity_lookup() {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verified on the next line.
			$nonce = isset( $_REQUEST['_ajax_nonce'] ) && is_scalar( $_REQUEST['_ajax_nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_REQUEST['_ajax_nonce'] ) ) : '';
			if ( ! function_exists( 'wp_verify_nonce' ) || false === wp_verify_nonce( $nonce, 'wenpai_entity_lookup' ) ) {
				wp_die( -1 );
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only lookup params, each sanitized.
			$kind = isset( $_REQUEST['kind'] ) && is_scalar( $_REQUEST['kind'] ) ? sanitize_key( wp_unslash( (string) $_REQUEST['kind'] ) ) : 'post';
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only lookup params, each sanitized.
			$object = isset( $_REQUEST['object'] ) && is_scalar( $_REQUEST['object'] ) ? sanitize_text_field( wp_unslash( (string) $_REQUEST['object'] ) ) : '';
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only lookup params, each sanitized.
			$q = isset( $_REQUEST['q'] ) && is_scalar( $_REQUEST['q'] ) ? sanitize_text_field( wp_unslash( (string) $_REQUEST['q'] ) ) : '';
			if ( ! in_array( $kind, array( 'post', 'term', 'user' ), true ) ) {
				wp_die( -1 );
			}
			$cap = 'post' === $kind ? 'edit_posts' : ( 'term' === $kind ? 'manage_categories' : 'list_users' );
			if ( ! function_exists( 'current_user_can' ) || ! current_user_can( $cap ) ) {
				wp_die( -1 );
			}
			$items = 'post' === $kind
				? self::lookup_posts( $object, $q )
				: ( 'term' === $kind ? self::lookup_terms( $object, $q ) : self::lookup_users( $q ) );
			wp_send_json_success( array( 'items' => $items ) );
		}

		/**
		 * Post lookup for ajax_entity_lookup(). object is a comma list of post
		 * types (empty = post); q empty lists the 20 newest.
		 *
		 * @param string $object_name Comma-separated post types.
		 * @param string $q      Search string.
		 * @return array<int, array{id: int, label: string, meta: string}>
		 */
		private static function lookup_posts( $object_name, $q ) {
			$types = array();
			foreach ( explode( ',', (string) $object_name ) as $t ) {
				$t = sanitize_key( $t );
				if ( '' !== $t ) {
					$types[] = $t;
				}
			}
			if ( array() === $types ) {
				$types = array( 'post' );
			}
			if ( ! function_exists( 'get_posts' ) ) {
				return array();
			}
			$args = array(
				'post_type'      => $types,
				'posts_per_page' => 20,
				'post_status'    => 'any',
				'orderby'        => 'date',
				'order'          => 'DESC',
			);
			if ( '' !== $q ) {
				$args['s'] = $q;
			}
			$items = array();
			foreach ( (array) get_posts( $args ) as $p ) {
				$id = is_object( $p ) && isset( $p->ID ) ? (int) $p->ID : 0;
				if ( $id <= 0 ) {
					continue;
				}
				$label = function_exists( 'get_the_title' ) ? (string) get_the_title( $p ) : ( isset( $p->post_title ) ? (string) $p->post_title : '' );
				$meta  = array();
				$ptype = isset( $p->post_type ) ? (string) $p->post_type : '';
				if ( '' !== $ptype && function_exists( 'get_post_type_object' ) ) {
					$pto = get_post_type_object( $ptype );
					if ( $pto && isset( $pto->labels->name ) ) {
						$meta[] = (string) $pto->labels->name;
					}
				}
				if ( function_exists( 'get_post_time' ) ) {
					$d = get_post_time( 'Y-m-d', false, $p );
					if ( $d ) {
						$meta[] = (string) $d;
					}
				}
				$items[] = array(
					'id'    => $id,
					'label' => $label,
					'meta'  => implode( ' · ', $meta ),
				);
			}
			return $items;
		}

		/**
		 * Term lookup for ajax_entity_lookup(). object is the taxonomy
		 * (empty = category).
		 *
		 * @param string $object_name Taxonomy name.
		 * @param string $q      Search string.
		 * @return array<int, array{id: int, label: string, meta: string}>
		 */
		private static function lookup_terms( $object_name, $q ) {
			$tax = sanitize_key( (string) $object_name );
			if ( '' === $tax ) {
				$tax = 'category';
			}
			if ( ! function_exists( 'get_terms' ) ) {
				return array();
			}
			$args = array(
				'taxonomy'   => $tax,
				'hide_empty' => false,
				'number'     => 20,
			);
			if ( '' !== $q ) {
				$args['search'] = $q;
			}
			$taxlabel = '';
			if ( function_exists( 'get_taxonomy' ) ) {
				$tx = get_taxonomy( $tax );
				if ( $tx && isset( $tx->label ) ) {
					$taxlabel = (string) $tx->label;
				}
			}
			$items = array();
			foreach ( (array) get_terms( $args ) as $t ) {
				$id = isset( $t->term_id ) ? (int) $t->term_id : ( isset( $t->id ) ? (int) $t->id : 0 );
				if ( $id <= 0 ) {
					continue;
				}
				$items[] = array(
					'id'    => $id,
					'label' => isset( $t->name ) ? (string) $t->name : '',
					'meta'  => $taxlabel,
				);
			}
			return $items;
		}

		/**
		 * User lookup for ajax_entity_lookup().
		 *
		 * @param string $q Search string.
		 * @return array<int, array{id: int, label: string, meta: string}>
		 */
		private static function lookup_users( $q ) {
			if ( ! function_exists( 'get_users' ) ) {
				return array();
			}
			$args = array( 'number' => 20 );
			if ( '' !== $q ) {
				$args['search'] = '*' . $q . '*';
			}
			$items = array();
			foreach ( (array) get_users( $args ) as $u ) {
				$id = isset( $u->ID ) ? (int) $u->ID : 0;
				if ( $id <= 0 ) {
					continue;
				}
				$items[] = array(
					'id'    => $id,
					'label' => isset( $u->display_name ) && '' !== (string) $u->display_name
						? (string) $u->display_name
						: ( isset( $u->user_login ) ? (string) $u->user_login : '' ),
					'meta'  => isset( $u->user_login ) ? (string) $u->user_login : '',
				);
			}
			return $items;
		}

		/**
		 * Mime whitelist of a file field for the upload endpoint: the render-time
		 * registry first, then every registered schema (admin-ajax.php never
		 * renders the page, so the registry alone would be empty there).
		 *
		 * @param string $key {option}__{field id} from $_POST['field'].
		 * @return mixed|null The field's 'mime' arg, or null when unknown.
		 */
		private static function file_field_mimes( $key ) {
			// The kit JS posts data-name, which is the input name
			// "{option}[{field}]"; the registry key is "{option}__{field}".
			$candidates = array( $key );
			if ( false !== strpos( $key, '[' ) ) {
				$candidates[] = (string) preg_replace( '/\[([^\]]*)\]/', '__$1', $key );
			}
			foreach ( $candidates as $cand ) {
				if ( isset( self::$file_fields[ $cand ] ) ) {
					return self::$file_fields[ $cand ];
				}
				foreach ( self::$registry as $option => $inst ) {
					foreach ( $inst->fields() as $fid => $f ) {
						if ( 'file' === $f['type'] && $option . '__' . $fid === $cand ) {
							return isset( $f['mime'] ) ? $f['mime'] : array();
						}
					}
				}
			}
			return null;
		}

		/**
		 * Image-only mime subset, the fallback when a mime whitelist matches
		 * nothing in wp_get_mime_types().
		 *
		 * @return array<string, string>
		 */
		private static function file_image_mimes() {
			return array(
				'jpg|jpeg|jpe' => 'image/jpeg',
				'png'          => 'image/png',
				'gif'          => 'image/gif',
				'webp'         => 'image/webp',
			);
		}

		/**
		 * The wp_ajax_wenpai_file_upload endpoint: upload one file for a file field. The
		 * 'field' POST param is the {option}__{field id} registry key; unknown
		 * keys wp_die(-1). On success returns the item array the control stores.
		 */
		public static function ajax_file_upload() {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verified on the next line.
			$nonce = isset( $_REQUEST['_ajax_nonce'] ) && is_scalar( $_REQUEST['_ajax_nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_REQUEST['_ajax_nonce'] ) ) : '';
			if ( ! function_exists( 'wp_verify_nonce' ) || false === wp_verify_nonce( $nonce, 'wenpai_file_upload' ) ) {
				wp_die( -1 );
			}
			if ( ! function_exists( 'current_user_can' ) || ! current_user_can( 'upload_files' ) ) {
				wp_die( -1 );
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- registry key, resolved below.
			$key  = isset( $_POST['field'] ) && is_scalar( $_POST['field'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['field'] ) ) : '';
			$mime = self::file_field_mimes( $key );
			if ( null === $mime ) {
				wp_die( -1 );
			}
			if ( ! isset( $_FILES['file'] ) || ! is_array( $_FILES['file'] ) || ! function_exists( 'wp_handle_upload' ) ) {
				wp_send_json_error( array( 'message' => '' ) );
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce checked above; wp_handle_upload validates the upload itself.
			$uploaded = wp_handle_upload(
				$_FILES['file'],
				array(
					'test_form' => false,
					'mimes'     => self::file_allowed_mimes( $mime ),
				)
			);
			if ( ! is_array( $uploaded ) || isset( $uploaded['error'] ) || empty( $uploaded['file'] ) ) {
				wp_send_json_error(
					array(
						'message' => is_array( $uploaded ) && isset( $uploaded['error'] ) ? (string) $uploaded['error'] : '',
					)
				);
			}
			$file = (string) $uploaded['file'];
			$id   = 0;
			if ( function_exists( 'wp_insert_attachment' ) ) {
				$base = isset( $uploaded['name'] ) ? (string) $uploaded['name'] : basename( $file );
				$id   = (int) wp_insert_attachment(
					array(
						'post_mime_type' => isset( $uploaded['type'] ) ? (string) $uploaded['type'] : '',
						'post_title'     => sanitize_text_field( (string) preg_replace( '/\.[^.]+$/', '', $base ) ),
						'post_status'    => 'inherit',
					),
					$file
				);
				if ( $id > 0 && function_exists( 'wp_update_attachment_metadata' ) ) {
					$meta = function_exists( 'wp_generate_attachment_metadata' ) ? wp_generate_attachment_metadata( $id, $file ) : array();
					wp_update_attachment_metadata( $id, is_array( $meta ) ? $meta : array() );
				}
			}
			$url  = $id > 0 && function_exists( 'wp_get_attachment_url' ) ? (string) wp_get_attachment_url( $id ) : '';
			$size = function_exists( 'filesize' ) ? (int) @filesize( $file ) : 0; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- the tmp file may be gone already.
			wp_send_json_success(
				array(
					'item' => array(
						'id'   => $id,
						'url'  => $url,
						'name' => isset( $uploaded['name'] ) ? sanitize_text_field( (string) $uploaded['name'] ) : sanitize_text_field( basename( $file ) ),
						'size' => $size,
						'type' => isset( $uploaded['type'] ) ? (string) $uploaded['type'] : '',
					),
				)
			);
		}

		/**
		 * Mime whitelist for a file field's 'mime' arg: empty = everything in
		 * wp_get_mime_types(); ext=>mime map used as-is; a list of extensions
		 * picks the matching wp_get_mime_types() entries (image subset when
		 * nothing matches).
		 *
		 * @param mixed $mime The field's 'mime' arg.
		 * @return array<string, string>
		 */
		private static function file_allowed_mimes( $mime ) {
			$all = function_exists( 'wp_get_mime_types' ) ? (array) wp_get_mime_types() : array();
			if ( array() === $all ) {
				$all = self::file_image_mimes();
			}
			if ( ! is_array( $mime ) || array() === $mime ) {
				return $all;
			}
			$map = false;
			foreach ( array_keys( $mime ) as $k ) {
				if ( ! is_int( $k ) ) {
					$map = true;
					break;
				}
			}
			if ( $map ) {
				return $mime;
			}
			$exts = array();
			foreach ( $mime as $e ) {
				$e = strtolower( (string) preg_replace( '/[^a-zA-Z0-9.]/', '', (string) $e ) );
				if ( '' !== $e ) {
					$exts[] = $e;
				}
			}
			$out = array();
			foreach ( $all as $ext_key => $mime_type ) {
				foreach ( explode( '|', (string) $ext_key ) as $e ) {
					if ( in_array( strtolower( $e ), $exts, true ) ) {
						$out[ $ext_key ] = $mime_type;
						break;
					}
				}
			}
			return array() === $out ? self::file_image_mimes() : $out;
		}

		/**
		 * Register a wp_ajax_{action} handler with nonce and capability checks.
		 *
		 * The handler gets ( $values, $settings ): the posted values of this
		 * option, sanitized per field, where an empty secret means the saved
		 * one. It returns true, false, a WP_Error, a message string (success),
		 * or array( 'ok' => bool, 'message' => string, 'data' => mixed ).
		 *
		 * @param string   $action  AJAX action; also the nonce action unless 'nonce' is given.
		 * @param callable $handler Handler.
		 * @param array    $args    Optional. 'capability' (default manage_options), 'nonce'.
		 */
		public function ajax_action( $action, $handler, array $args = array() ) {
			$cap   = isset( $args['capability'] ) ? (string) $args['capability'] : 'manage_options';
			$nonce = isset( $args['nonce'] ) ? (string) $args['nonce'] : (string) $action;
			$self  = $this;
			add_action(
				'wp_ajax_' . $action,
				function () use ( $self, $nonce, $handler, $cap ) {
					$self->handle_ajax( $nonce, $handler, $cap );
				}
			);
		}

		/**
		 * Run one AJAX action and send JSON. Public only for ajax_action().
		 *
		 * @param string   $nonce   Nonce action.
		 * @param callable $handler Handler.
		 * @param string   $cap     Capability.
		 */
		public function handle_ajax( $nonce, $handler, $cap ) {
			if ( ! check_ajax_referer( $nonce, '_wpnonce', false ) || ! current_user_can( $cap ) ) {
				wp_send_json_error( array( 'message' => $this->strings['action_denied'] ), 403 );
			}
			$res = call_user_func( $handler, $this->posted_values(), $this );
			$out = self::action_result( $res, $this->strings );
			if ( $out['ok'] ) {
				wp_send_json_success( $out );
			}
			wp_send_json_error( $out );
		}

		/**
		 * Normalize a handler result.
		 *
		 * @param mixed $res     Handler return value.
		 * @param array $strings UI strings.
		 * A WP_Error sends only its code and message to the page by default. Its
		 * data stays on the server (it may hold an HTTP response body, keys or
		 * signed URLs), except what the handler puts under the 'public' key
		 * ( new WP_Error( 'quota', $msg, array( 'public' => array( 'retry' => 60 ) ) ) )
		 * or what the 'wenpai_admin_ui_action_error_data' filter returns.
		 *
		 * @return array{ok: bool, message: string, code: string, data: mixed}
		 */
		public static function action_result( $res, array $strings = array() ) {
			$strings = array_merge( self::default_strings(), $strings );
			if ( is_object( $res ) && is_a( $res, 'WP_Error' ) ) {
				$msg    = (string) $res->get_error_message();
				$raw    = $res->get_error_data();
				$public = is_array( $raw ) && array_key_exists( 'public', $raw ) ? $raw['public'] : null;
				if ( function_exists( 'apply_filters' ) ) {
					/**
					 * Error data of a failed field action that may go to the page.
					 *
					 * @param mixed    $public Default: the 'public' key of the error data, else null.
					 * @param WP_Error $res    The handler's error.
					 */
					$public = apply_filters( 'wenpai_admin_ui_action_error_data', $public, $res );
				}
				return array(
					'ok'      => false,
					'message' => '' !== $msg ? $msg : $strings['action_failed'],
					'code'    => (string) $res->get_error_code(),
					'data'    => self::json_safe( $public ),
				);
			}
			if ( is_array( $res ) ) {
				$ok = ! empty( $res['ok'] );
				return array(
					'ok'      => $ok,
					'message' => isset( $res['message'] ) && '' !== (string) $res['message'] ? (string) $res['message'] : ( $ok ? $strings['action_ok'] : $strings['action_failed'] ),
					'code'    => isset( $res['code'] ) && is_scalar( $res['code'] ) ? (string) $res['code'] : '',
					'data'    => isset( $res['data'] ) ? $res['data'] : null,
				);
			}
			if ( is_string( $res ) && '' !== $res ) {
				return array(
					'ok'      => true,
					'message' => $res,
					'code'    => '',
					'data'    => null,
				);
			}
			$ok = true === $res;
			return array(
				'ok'      => $ok,
				'message' => $ok ? $strings['action_ok'] : $strings['action_failed'],
				'code'    => '',
				'data'    => null,
			);
		}

		/**
		 * WP_Error data for the JSON response: scalars and arrays of them only.
		 * Objects (a WP_HTTP response, a resource) are dropped, so internals do
		 * not leak to the page.
		 *
		 * @param mixed $data Error data.
		 * @param int   $depth Recursion guard.
		 * @return mixed
		 */
		private static function json_safe( $data, $depth = 0 ) {
			if ( null === $data || is_scalar( $data ) ) {
				return $data;
			}
			if ( ! is_array( $data ) || $depth > 4 ) {
				return null;
			}
			$out = array();
			foreach ( $data as $k => $v ) {
				if ( null === $v || is_scalar( $v ) || is_array( $v ) ) {
					$out[ $k ] = self::json_safe( $v, $depth + 1 );
				}
			}
			return $out;
		}

		/**
		 * Posted values of this option for an AJAX action: fields that were not
		 * sent keep their saved value; an empty secret means the saved one.
		 * Nonce is checked by handle_ajax() before this runs.
		 *
		 * @param array|null $raw Optional raw values (tests); default $_POST[option].
		 * @return array
		 */
		public function posted_values( $raw = null ) {
			if ( null === $raw ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce checked in handle_ajax(); each field is sanitized below.
				$raw = isset( $_POST[ $this->option ] ) && is_array( $_POST[ $this->option ] ) ? wp_unslash( $_POST[ $this->option ] ) : array();
			}
			$saved = $this->all();
			$out   = array();
			foreach ( $this->fields as $id => $f ) {
				if ( ! self::stores( $f ) ) {
					continue;
				}
				$posted = $this->input_value( is_array( $raw ) ? $raw : array(), $id );
				if ( null === $posted ) {
					$out[ $id ] = $saved[ $id ];
					continue;
				}
				if ( self::is_secret( $f ) && is_scalar( $posted ) && '' === trim( (string) $posted ) ) {
					$out[ $id ] = $saved[ $id ];
					continue;
				}
				$out[ $id ] = $this->sanitize_field( $f, $posted );
			}
			return $out;
		}

		/**
		 * Kit notice for the settings page. One notice only, also on Settings
		 * submenu pages: claim_core_notices() keeps core from repeating it.
		 * - errors: role="alert" summary with an in-page link per field (at most
		 *   five, then 「等 N 项」), plus 「其余设置已保存。」 when the rest was saved;
		 * - otherwise a saved receipt (role="status") that fades after 5 s.
		 *
		 * @param bool|null $updated Whether the form was just saved. Null reads
		 *                           settings-updated from the options.php redirect.
		 * @return string
		 */
		public function notices_html( $updated = null ) {
			if ( null === $updated ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only flag set by options.php redirect.
				$updated = isset( $_GET['settings-updated'] ) && 'true' === sanitize_text_field( wp_unslash( $_GET['settings-updated'] ) );
			}
			// Track C: admin-post Action one-shot notice wins (single receipt; no third toast).
			if ( class_exists( 'Wenpai_Admin_Action', false ) ) {
				$action_notice = Wenpai_Admin_Action::notice_html();
				if ( '' !== $action_notice ) {
					return $action_notice;
				}
			}
			$tools = $this->tools_notice_html();
			if ( '' !== $tools ) {
				return $tools;
			}
			$errs = $this->errors();
			$icon = class_exists( 'Wenpai_Admin_Icons', false ) ? Wenpai_Admin_Icons::svg( 'info' ) : '';
			$form = $this->form_error;
			if ( '' === $form ) {
				foreach ( $this->own_settings_errors() as $e ) {
					if ( isset( $e['code'], $e['message'] ) && 'wenpai_tab' === $e['code'] ) {
						$form = (string) $e['message'];
					}
				}
			}
			if ( '' !== $form ) {
				return self::ui_notice(
					array(
						'text'  => $form,
						'level' => 'bad',
					)
				);
			}
			if ( ! empty( $errs ) ) {
				$items = '';
				$n     = 0;
				foreach ( array_keys( $errs ) as $id ) {
					if ( ++$n > 5 ) {
						break;
					}
					$label  = isset( $this->fields[ $id ] ) ? $this->fields[ $id ]['label'] : $id;
					$items .= '<li><a href="#' . esc_attr( $this->field_id( $id ) ) . '">' . esc_html( $label ) . '</a></li>';
				}
				if ( count( $errs ) > 5 ) {
					$items .= '<li>' . esc_html( sprintf( $this->strings['invalid_more'], count( $errs ) ) ) . '</li>';
				}
				$html  = '<div class="wenpai-notice-wrap"><div class="notice bad wenpai-error-summary" role="alert">' . $icon . '<div class="wenpai-error-summary__body">';
				$html .= '<p>' . esc_html( sprintf( $this->strings['invalid'], count( $errs ) ) ) . '</p>';
				$html .= '<ul class="wenpai-error-links">' . $items . '</ul>';
				if ( $updated ) {
					$html .= '<p>' . esc_html( $this->strings['partial_saved'] ) . '</p>';
				}
				return $html . '</div></div></div>';
			}
			if ( $updated ) {
				// data-wenpai-fade sits on the notice: core moves .notice out of a plain .wrap container.
				return self::ui_notice(
					array(
						'text'  => $this->strings['saved'],
						'level' => 'ok',
						'fade'  => true,
						'attrs' => array( 'data-wenpai-announce' => true ),
					)
				);
			}
			return '';
		}

		/**
		 * Tools-page result from the admin-post redirect. Empty when the query
		 * is missing, for another option, or an unknown code.
		 *
		 * @return string
		 */
		private function tools_notice_html() {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only flags set by tools redirect.
			if ( ! isset( $_GET['wenpai_tools'], $_GET['wenpai_tools_option'] ) ) {
				return '';
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only flags set by tools redirect.
			$option = sanitize_text_field( wp_unslash( $_GET['wenpai_tools_option'] ) );
			if ( $this->option !== $option ) {
				return '';
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only flags set by tools redirect.
			$code = sanitize_text_field( wp_unslash( $_GET['wenpai_tools'] ) );
			$map  = array(
				'imported'         => array( 'ok', 'status' ),
				'imported-partial' => array( 'ok', 'status' ),
				'import-invalid'   => array( 'bad', 'alert' ),
				'import-newer'     => array( 'bad', 'alert' ),
				'import-size'      => array( 'bad', 'alert' ),
				'import-confirm'   => array( 'bad', 'alert' ),
				'reset'            => array( 'ok', 'status' ),
				'reset-confirm'    => array( 'bad', 'alert' ),
			);
			if ( ! isset( $map[ $code ], $this->strings[ $code ] ) ) {
				return '';
			}
			$msg = $this->strings[ $code ];
			if ( 'imported-partial' === $code ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only count set by tools redirect.
				$n   = isset( $_GET['wenpai_tools_n'] ) ? absint( wp_unslash( $_GET['wenpai_tools_n'] ) ) : 0;
				$msg = sprintf( $msg, $n );
			}
			$args = array(
				'text'  => $msg,
				'level' => $map[ $code ][0],
			);
			if ( 'status' === $map[ $code ][1] ) {
				$args['fade']  = true;
				$args['attrs'] = array( 'data-wenpai-announce' => true );
			}
			return self::ui_notice( $args );
		}

		/**
		 * Notice markup via Wenpai_Admin_UI when loaded.
		 *
		 * @param array $args notice() args.
		 * @return string
		 */
		private static function ui_notice( array $args ) {
			self::ensure_ui();
			return class_exists( 'Wenpai_Admin_UI', false ) ? Wenpai_Admin_UI::notice_html( $args ) : '';
		}

		// --- Tabs decided by saved values ---

		/**
		 * Drop tabs whose 'depends_on' fails against saved values. A hidden tab is
		 * not output at all (no live JS switching). When the requested tab is
		 * hidden, fall back to the first visible one with an info notice.
		 *
		 * @param array  $tabs    Tab id => label, or id => array( 'label', 'depends_on', ... ).
		 * @param string $current Requested tab id.
		 * @return array{tabs: array, current: string, notice: string, errors: array} `errors` maps each shown tab id to its field-error count (kit 0.10) for nav badges.
		 */
		public function visible_tabs( array $tabs, $current ) {
			$shown = array();
			foreach ( $tabs as $tid => $t ) {
				if ( is_array( $t ) && ! empty( $t['depends_on'] ) && ! $this->condition_met( (array) $t['depends_on'] ) ) {
					continue;
				}
				if ( is_array( $t ) ) {
					unset( $t['depends_on'] );
				}
				$shown[ $tid ] = $t;
			}
			$current = (string) $current;
			$notice  = '';
			if ( ! isset( $shown[ $current ] ) && ! empty( $shown ) ) {
				$first = (string) key( $shown );
				if ( isset( $tabs[ $current ] ) ) {
					$from   = is_array( $tabs[ $current ] ) ? ( isset( $tabs[ $current ]['label'] ) ? (string) $tabs[ $current ]['label'] : $current ) : (string) $tabs[ $current ];
					$to     = is_array( $shown[ $first ] ) ? ( isset( $shown[ $first ]['label'] ) ? (string) $shown[ $first ]['label'] : $first ) : (string) $shown[ $first ];
					$icon   = class_exists( 'Wenpai_Admin_Icons', false ) ? Wenpai_Admin_Icons::svg( 'info' ) : '';
					$notice = '<div class="wenpai-notice-wrap"><div class="notice info" role="status">' . $icon . '<span>' . esc_html( sprintf( $this->strings['tab_hidden'], $from, $to ) ) . '</span></div></div>';
				}
				$current = $first;
			}
			// 0.10: per-tab error counts so tab nav can badge the tabs that need attention.
			$counts = array();
			$errs   = $this->errors();
			foreach ( array_keys( $shown ) as $tid ) {
				$counts[ $tid ] = 0;
			}
			foreach ( $errs as $fid => $e ) {
				if ( ! isset( $this->fields[ $fid ] ) ) {
					continue;
				}
				foreach ( (array) $this->fields[ $fid ]['tab'] as $ft ) {
					if ( isset( $counts[ $ft ] ) ) {
						++$counts[ $ft ];
					}
				}
			}
			return array(
				'tabs'    => $shown,
				'current' => $current,
				'notice'  => $notice,
				'errors'  => $counts,
			);
		}
	}
}
