<?php
/**
 * Markup helpers for kit surfaces (tile, pill, notice, card, …).
 *
 * @package Wenpai_Admin_UI
 * @since 0.3.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Wenpai_Admin_UI', false ) ) {
	/**
	 * Static HTML helpers. foo() echoes, foo_html() returns the same string.
	 */
	class Wenpai_Admin_UI {

		/**
		 * Tile / pill / notice states from the interface.
		 *
		 * @var string[]
		 */
		private static $tile_states = array( '', 'ok', 'warn', 'bad', 'accent' );

		/**
		 * Pill states. mute is pill-only.
		 *
		 * @var string[]
		 */
		private static $pill_states = array( '', 'ok', 'warn', 'bad', 'mute' );

		/**
		 * Progressbar states (Track E / E14). Ban ProgressCard.
		 *
		 * @var string[]
		 */
		private static $progress_states = array( '', 'warn', 'bad' );

		/**
		 * Notice levels.
		 *
		 * @var string[]
		 */
		private static $notice_levels = array( 'info', 'ok', 'warn', 'bad' );

		/**
		 * Button variants.
		 *
		 * @var string[]
		 */
		private static $button_variants = array( 'primary', 'secondary', 'ghost', 'danger' );

		/**
		 * Dashboard widget ids already registered by dashboard_widget() (0.8).
		 * Repeat calls with the same id are ignored so the helper is idempotent.
		 *
		 * @var array<string, bool>
		 */
		private static $dashboard_widgets = array();

		/**
		 * Filter caller HTML: post tags plus kit icon SVG, or SVG only.
		 *
		 * Same allowlist Settings used to keep as private kses(). Without
		 * wp_kses (outside WP) the string is escaped.
		 *
		 * @param string $html    Caller HTML.
		 * @param string $context 'svg', 'post', 'control' (post + form controls),
		 *                        'chart' (post + canvas and svg text) or 'logo'
		 *                        (kit SVG plus img with src/alt/width/height/class/
		 *                        loading/decoding only: brand marks).
		 * @return string
		 */
		public static function kses( $html, $context ) {
			if ( ! function_exists( 'wp_kses' ) ) {
				return esc_html( $html );
			}
			$svg   = array(
				'svg'            => array(
					'class'               => true,
					'viewbox'             => true,
					'width'               => true,
					'height'              => true,
					'fill'                => true,
					'stroke'              => true,
					'stroke-width'        => true,
					'stroke-linecap'      => true,
					'stroke-linejoin'     => true,
					'aria-hidden'         => true,
					'focusable'           => true,
					'role'                => true,
					'xmlns'               => true,
					'preserveaspectratio' => true,
				),
				'g'              => array(
					'fill'      => true,
					'stroke'    => true,
					'transform' => true,
				),
				'path'           => array(
					'd'               => true,
					'fill'            => true,
					'fill-rule'       => true,
					'clip-rule'       => true,
					'stroke'          => true,
					'stroke-width'    => true,
					'stroke-linecap'  => true,
					'stroke-linejoin' => true,
				),
				'circle'         => array(
					'cx' => true,
					'cy' => true,
					'r'  => true,
				),
				'ellipse'        => array(
					'cx' => true,
					'cy' => true,
					'rx' => true,
					'ry' => true,
				),
				'rect'           => array(
					'x'      => true,
					'y'      => true,
					'width'  => true,
					'height' => true,
					'rx'     => true,
					'ry'     => true,
				),
				'line'           => array(
					'x1' => true,
					'y1' => true,
					'x2' => true,
					'y2' => true,
				),
				'polyline'       => array( 'points' => true ),
				'polygon'        => array( 'points' => true ),
				'title'          => array(),
				'defs'           => array(),
				'lineargradient' => array(
					'id' => true,
					'x1' => true,
					'y1' => true,
					'x2' => true,
					'y2' => true,
				),
				'stop'           => array(
					'offset'       => true,
					'stop-color'   => true,
					'stop-opacity' => true,
				),
			);
			$paint = array(
				'fill'            => true,
				'fill-opacity'    => true,
				'fill-rule'       => true,
				'opacity'         => true,
				'stroke'          => true,
				'stroke-width'    => true,
				'stroke-opacity'  => true,
				'stroke-linecap'  => true,
				'stroke-linejoin' => true,
				'transform'       => true,
			);
			foreach ( array( 'svg', 'g', 'path', 'circle', 'ellipse', 'rect', 'line', 'polyline', 'polygon', 'lineargradient', 'stop' ) as $tag ) {
				$svg[ $tag ] = array_merge( $svg[ $tag ], $paint );
			}
			if ( 'logo' === $context ) {
				$svg['img'] = array_fill_keys( array( 'src', 'alt', 'width', 'height', 'class', 'loading', 'decoding' ), true );
				return wp_kses( $html, $svg );
			}
			$allowed = 'svg' === $context ? $svg : array_merge( wp_kses_allowed_html( 'post' ), $svg );
			if ( 'control' === $context || 'pane' === $context ) {
				$allowed = array_merge( $allowed, self::control_tags() );
			}
			if ( 'chart' === $context || 'pane' === $context ) {
				$allowed = array_merge( $allowed, self::chart_tags( $paint ) );
				foreach ( array( 'path', 'line', 'polyline', 'polygon', 'circle', 'ellipse', 'rect' ) as $tag ) {
					$allowed[ $tag ]['vector-effect'] = true;
				}
			}
			return wp_kses( $html, $allowed );
		}

		/**
		 * Form controls for kses( …, 'control' ): what Settings::render_control_html()
		 * prints (switch, inputs, selects, textareas) plus the kit's data-* and aria-* hooks.
		 *
		 * @return array<string, array<string, bool>>
		 */
		private static function control_tags() {
			$common = array_fill_keys(
				array( 'class', 'id', 'role', 'hidden', 'inert', 'title', 'dir', 'lang', 'tabindex', 'style', 'data-*', 'aria-label', 'aria-labelledby', 'aria-describedby', 'aria-hidden', 'aria-invalid', 'aria-checked', 'aria-selected', 'aria-disabled', 'aria-expanded', 'aria-controls', 'aria-live', 'aria-busy', 'aria-required', 'aria-pressed', 'aria-valuenow', 'aria-valuemin', 'aria-valuemax', 'aria-valuetext' ),
				true
			);
			$tags   = array(
				'a'        => array_fill_keys( array( 'href', 'target', 'rel' ), true ),
				'span'     => array(),
				'div'      => array(),
				'label'    => array( 'for' => true ),
				'input'    => array_fill_keys( array( 'type', 'name', 'value', 'checked', 'disabled', 'readonly', 'required', 'min', 'max', 'step', 'minlength', 'maxlength', 'pattern', 'placeholder', 'autocomplete', 'inputmode', 'size', 'list', 'multiple', 'accept', 'form', 'spellcheck' ), true ),
				'select'   => array_fill_keys( array( 'name', 'disabled', 'required', 'multiple', 'size', 'autocomplete', 'form' ), true ),
				'option'   => array_fill_keys( array( 'value', 'selected', 'disabled', 'label' ), true ),
				'optgroup' => array_fill_keys( array( 'label', 'disabled' ), true ),
				'textarea' => array_fill_keys( array( 'name', 'rows', 'cols', 'disabled', 'readonly', 'required', 'placeholder', 'maxlength', 'spellcheck', 'autocomplete', 'wrap' ), true ),
				'button'   => array_fill_keys( array( 'type', 'name', 'value', 'disabled', 'form' ), true ),
				'form'     => array_fill_keys( array( 'method', 'action', 'enctype', 'target' ), true ),
				'fieldset' => array_fill_keys( array( 'disabled', 'name' ), true ),
				'legend'   => array(),
				'noscript' => array(),
			);
			foreach ( $tags as $tag => $attrs ) {
				$tags[ $tag ] = array_merge( $common, $attrs );
			}
			return $tags;
		}

		/**
		 * Extra tags for kses( …, 'chart' ): what a chart_frame() plot needs on top
		 * of post tags and kit SVG. canvas for script-drawn charts, svg text labels.
		 *
		 * @param array<string, bool> $paint SVG paint attributes shared with kses().
		 * @return array<string, array<string, bool>>
		 */
		private static function chart_tags( array $paint ) {
			$text = array_merge(
				$paint,
				array_fill_keys( array( 'class', 'x', 'y', 'dx', 'dy', 'text-anchor', 'dominant-baseline', 'font-size' ), true )
			);
			return array(
				'canvas' => array_fill_keys( array( 'class', 'id', 'width', 'height', 'role', 'aria-label', 'aria-hidden', 'data-*' ), true ),
				'text'   => $text,
				'tspan'  => $text,
			);
		}

		/**
		 * Echo already-escaped helper HTML.
		 *
		 * @param string $html Markup from *_html().
		 */
		private static function emit( $html ) {
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kit-escaped.
		}

		/**
		 * Echo a tile.
		 *
		 * @param array $args Arguments.
		 */
		public static function tile( $args = array() ) {
			self::emit( self::tile_html( $args ) );
		}

		/**
		 * Return tile markup.
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type string $state ok|warn|bad|accent|''. mute becomes ''.
		 *     @type string $icon  Wenpai_Admin_Icons name; unknown → empty tile.
		 *     @type string $icon_html Raw product SVG (kses svg), wins over $icon.
		 *     @type string $class Extra classes.
		 *     @type array  $attrs Extra attributes (id, data-*, aria-*, role, hidden).
		 * }
		 * @return string
		 */
		public static function tile_html( $args = array() ) {
			$args  = self::args( $args, 'Wenpai_Admin_UI::tile' );
			$state = self::state( isset( $args['state'] ) ? $args['state'] : '', self::$tile_states, '', 'Wenpai_Admin_UI::tile', true );
			$icon  = self::icon_svg( isset( $args['icon'] ) ? $args['icon'] : '' );
			if ( isset( $args['icon_html'] ) && '' !== (string) $args['icon_html'] ) {
				$icon = self::kses( (string) $args['icon_html'], 'svg' );
			}
			$class = self::classes( array( 'tile', $state, self::extra_class( $args ) ) );
			return '<span class="' . esc_attr( $class ) . '"' . self::extra_attrs( $args ) . '>' . $icon . '</span>';
		}

		/**
		 * Echo a pill.
		 *
		 * @param array $args Arguments.
		 */
		public static function pill( $args = array() ) {
			self::emit( self::pill_html( $args ) );
		}

		/**
		 * Return pill markup.
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type string $text  Label.
		 *     @type string $state ok|warn|bad|mute|''.
		 *     @type string $class Extra classes.
		 *     @type array  $attrs Extra attributes.
		 * }
		 * @return string
		 */
		public static function pill_html( $args = array() ) {
			$args  = self::args( $args, 'Wenpai_Admin_UI::pill' );
			$state = self::state( isset( $args['state'] ) ? $args['state'] : '', self::$pill_states, '', 'Wenpai_Admin_UI::pill' );
			$text  = isset( $args['text'] ) ? (string) $args['text'] : '';
			$class = self::classes( array( 'pill', $state, self::extra_class( $args ) ) );
			return '<span class="' . esc_attr( $class ) . '"' . self::extra_attrs( $args ) . '>' . esc_html( $text ) . '</span>';
		}

		/**
		 * Echo a notice.
		 *
		 * @param array $args Arguments.
		 */
		public static function notice( $args = array() ) {
			self::emit( self::notice_html( $args ) );
		}

		/**
		 * Return notice markup.
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type string $text      Plain text (escaped).
		 *     @type string $text_html Caller HTML (kses post).
		 *     @type string $level     info|ok|warn|bad. Default info.
		 *     @type bool   $fade      Adds data-wenpai-fade.
		 *     @type bool   $wrap      Wrap in .wenpai-notice-wrap. Default true.
		 *     @type bool   $native    For core screens outside .wenpai-app (media library):
		 *                             adds .wenpai-native (token scope) to the wrap, or to
		 *                             the .notice when wrap is false.
		 *     @type string $class     Extra classes on .notice.
		 *     @type array  $attrs     Extra attributes on .notice.
		 * }
		 * @return string
		 */
		public static function notice_html( $args = array() ) {
			$args  = self::args( $args, 'Wenpai_Admin_UI::notice' );
			$level = isset( $args['level'] ) ? (string) $args['level'] : 'info';
			if ( ! in_array( $level, self::$notice_levels, true ) ) {
				self::doing_it_wrong( sprintf( 'Invalid notice level 「%s」.', $level ), 'Wenpai_Admin_UI::notice' );
				$level = 'info';
			}
			$inner  = array_key_exists( 'text_html', $args )
				? self::kses( (string) $args['text_html'], 'post' )
				: esc_html( isset( $args['text'] ) ? (string) $args['text'] : '' );
			$icon   = self::icon_svg( 'info' );
			$wrap   = ! array_key_exists( 'wrap', $args ) || $args['wrap'];
			$native = ! empty( $args['native'] );
			$class  = self::classes( array( 'notice', $level, $native && ! $wrap ? 'wenpai-native' : '', self::extra_class( $args ) ) );
			$role   = '';
			if ( 'bad' === $level ) {
				$role = 'alert';
			} elseif ( 'ok' === $level || 'warn' === $level ) {
				$role = 'status';
			}
			$attr = ' class="' . esc_attr( $class ) . '"';
			if ( '' !== $role ) {
				$attr .= ' role="' . esc_attr( $role ) . '"';
			}
			$skip = array( 'role' );
			if ( ! empty( $args['fade'] ) ) {
				$attr  .= ' data-wenpai-fade';
				$skip[] = 'data-wenpai-fade';
			}
			$attr .= self::extra_attrs( $args, $skip );
			$html  = '<div' . $attr . '>' . $icon . '<span>' . $inner . '</span></div>';
			if ( ! $wrap ) {
				return $html;
			}
			return '<div class="' . ( $native ? 'wenpai-native wenpai-notice-wrap' : 'wenpai-notice-wrap' ) . '">' . $html . '</div>';
		}

		/**
		 * Echo the opening card markup.
		 *
		 * @param array $args Arguments.
		 */
		public static function card_open( $args = array() ) {
			self::emit( self::card_open_html( $args ) );
		}

		/**
		 * Return the opening card markup.
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type string $title    Card title.
		 *     @type string $sub      Subtitle (escaped).
		 *     @type string $sub_html Subtitle HTML (kses post).
		 *     @type string $icon     Tile icon.
		 *     @type string $state    Tile state.
		 *     @type bool   $tile     False: no tile in the head (title block only); icon,
		 *                            icon_html and state are ignored. Default true.
		 *     @type bool   $tight    .card.tight.
		 *     @type int    $heading  Title level, default 2.
		 *     @type string $class    Extra classes on section.card.
		 *     @type array  $attrs    Extra attributes on section.card.
		 *     @type string $head_actions_html Right slot HTML (kses post) after the title block,
		 *                                wrapped in .head-actions (0.5.0).
		 *     @type string $head_control_html Right slot with form controls (kses control, as
		 *                                row control_html), e.g. list_bar_html(). Printed in
		 *                                div.head-actions after head_actions_html.
		 * }
		 * @return string
		 */
		public static function card_open_html( $args = array() ) {
			$args    = self::args( $args, 'Wenpai_Admin_UI::card_open' );
			$tight   = ! empty( $args['tight'] );
			$class   = self::classes( array( 'card', $tight ? 'tight' : '', self::extra_class( $args ) ) );
			$html    = '<section class="' . esc_attr( $class ) . '"' . self::extra_attrs( $args ) . '>';
			$title   = isset( $args['title'] ) ? (string) $args['title'] : '';
			$has_sub = array_key_exists( 'sub_html', $args ) || ( isset( $args['sub'] ) && '' !== (string) $args['sub'] );
			$no_tile = isset( $args['tile'] ) && false === $args['tile'];
			$icon    = ! $no_tile && isset( $args['icon'] ) ? (string) $args['icon'] : '';
			if ( '' === $title && ! $has_sub && '' === $icon ) {
				return $html;
			}
			$heading = self::heading( isset( $args['heading'] ) ? $args['heading'] : 2, 'Wenpai_Admin_UI::card_open' );
			$tile    = $no_tile ? '' : self::tile_html(
				array(
					'icon'      => $icon,
					'state'     => isset( $args['state'] ) ? $args['state'] : '',
					'icon_html' => isset( $args['icon_html'] ) ? $args['icon_html'] : '',
				)
			);
			$html   .= '<div class="card-head">' . $tile . '<div>';
			if ( '' !== $title ) {
				$html .= '<h' . $heading . ' class="card-title">' . esc_html( $title ) . '</h' . $heading . '>';
			}
			if ( array_key_exists( 'sub_html', $args ) ) {
				$sub = self::kses( (string) $args['sub_html'], 'post' );
				if ( '' !== $sub ) {
					$html .= '<p class="card-sub">' . $sub . '</p>';
				}
			} elseif ( isset( $args['sub'] ) && '' !== (string) $args['sub'] ) {
				$html .= '<p class="card-sub">' . esc_html( (string) $args['sub'] ) . '</p>';
			}
			$html   .= '</div>';
			$has_act = isset( $args['head_actions_html'] ) && '' !== (string) $args['head_actions_html'];
			$actions = $has_act ? self::kses( (string) $args['head_actions_html'], 'post' ) : '';
			$control = isset( $args['head_control_html'] ) && '' !== (string) $args['head_control_html'] ? self::kses( (string) $args['head_control_html'], 'control' ) : '';
			if ( '' !== $control ) {
				// A form (list_bar_html) cannot sit in a span.
				$html .= '<div class="head-actions">' . $actions . $control . '</div>';
			} elseif ( $has_act ) {
				$html .= '<span class="head-actions">' . $actions . '</span>';
			}
			return $html . '</div>';
		}

		/**
		 * Close a card opened with card_open().
		 */
		public static function card_close() {
			self::emit( self::card_close_html() );
		}

		/**
		 * Return the closing card tag.
		 *
		 * @return string
		 */
		public static function card_close_html() {
			return '</section>';
		}

		/**
		 * Echo a card.
		 *
		 * @param array $args Arguments.
		 */
		public static function card( $args = array() ) {
			self::emit( self::card_html( $args ) );
		}

		/**
		 * Return a full card.
		 *
		 * The body_html arg (and a string foot) goes through kses( …, 'post' ): wp_kses
		 * post tags plus kit SVG. That list has no input, select or option, so
		 * form controls are stripped. For a card that holds a form, use
		 * card_open_html() / card_close_html() and write the form between them.
		 *
		 * @param array $args card_open() args plus body_html and foot.
		 * @return string
		 */
		public static function card_html( $args = array() ) {
			$args = self::args( $args, 'Wenpai_Admin_UI::card' );
			$html = self::card_open_html( $args );
			if ( isset( $args['body_html'] ) ) {
				$html .= self::kses( (string) $args['body_html'], 'post' );
			}
			if ( isset( $args['foot'] ) ) {
				if ( is_array( $args['foot'] ) ) {
					$html .= self::card_foot_html( $args['foot'] );
				} else {
					$html .= self::kses( (string) $args['foot'], 'post' );
				}
			}
			return $html . self::card_close_html();
		}

		/**
		 * Echo a card footer.
		 *
		 * @param array $args Arguments.
		 */
		public static function card_foot( $args = array() ) {
			self::emit( self::card_foot_html( $args ) );
		}

		/**
		 * Return card footer markup.
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type string $meta         Meta text, wrapped in span.meta.
		 *     @type string $meta_html    Meta HTML (kses post), used instead of meta.
		 *     @type array  $actions      Button argument arrays. With start and meta, two or
		 *                                more buttons are wrapped in one span so they stay together.
		 *     @type string $actions_html Extra action HTML (kses post), printed as is after actions.
		 *     @type bool   $start        .card-foot.start.
		 *     @type string $class        Extra classes.
		 *     @type array  $attrs        Extra attributes.
		 * }
		 * @return string
		 */
		public static function card_foot_html( $args = array() ) {
			$args  = self::args( $args, 'Wenpai_Admin_UI::card_foot' );
			$start = ! empty( $args['start'] );
			$class = self::classes( array( 'card-foot', $start ? 'start' : '', self::extra_class( $args ) ) );
			$html  = '<div class="' . esc_attr( $class ) . '"' . self::extra_attrs( $args ) . '>';
			if ( array_key_exists( 'meta_html', $args ) ) {
				$html .= self::kses( (string) $args['meta_html'], 'post' );
			} elseif ( isset( $args['meta'] ) && '' !== (string) $args['meta'] ) {
				$html .= '<span class="meta">' . esc_html( (string) $args['meta'] ) . '</span>';
			}
			$buttons = array();
			if ( isset( $args['actions'] ) && is_array( $args['actions'] ) ) {
				foreach ( $args['actions'] as $btn ) {
					if ( is_array( $btn ) ) {
						$buttons[] = self::button_html( $btn );
					}
				}
			}
			// .start spreads children with space-between: meta left, one action group right.
			$has_meta = array_key_exists( 'meta_html', $args ) || ( isset( $args['meta'] ) && '' !== (string) $args['meta'] );
			if ( $start && $has_meta && count( $buttons ) > 1 ) {
				$html .= '<span>' . implode( '', $buttons ) . '</span>';
			} else {
				$html .= implode( '', $buttons );
			}
			if ( isset( $args['actions_html'] ) ) {
				$html .= self::kses( (string) $args['actions_html'], 'post' );
			}
			return $html . '</div>';
		}

		/**
		 * Echo an async task block (progress + counts + live meta).
		 *
		 * @param array $args Arguments.
		 */
		public static function task( $args = array() ) {
			self::emit( self::task_html( $args ) );
		}

		/**
		 * Return task block markup. Product wraps it in card_open / card_foot.
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type int    $now    Current progress value.
		 *     @type int    $total  Maximum value.
		 *     @type bool   $stopped Bar turns warn (paused / stalled).
		 *     @type bool   $bad    Bar turns bad (failed batch).
		 *     @type array  $counts Pill argument arrays (ok / pending / failed / state).
		 *     @type string $meta   Live region sentence (aria-live polite).
		 *     @type string $meta_html Same sentence as HTML (kses post), wins over meta:
		 *                         e.g. numbers in <b>.
		 *     @type bool   $bar    false drops the progress bar (counts and sentence only).
		 *     @type string $class  Extra classes on .wenpai-task.
		 *     @type array  $attrs  Extra attributes.
		 * }
		 * @return string
		 */
		public static function task_html( $args = array() ) {
			$args  = self::args( $args, 'Wenpai_Admin_UI::task' );
			$now   = (int) ( isset( $args['now'] ) ? $args['now'] : 0 );
			$total = (int) ( isset( $args['total'] ) ? $args['total'] : 0 );
			$pct   = $total > 0 ? min( 100, (int) round( $now * 100 / $total ) ) : 0;
			$bar   = 'wenpai-task-bar';
			if ( ! empty( $args['stopped'] ) ) {
				$bar .= ' is-stopped';
			}
			if ( ! empty( $args['bad'] ) ) {
				$bar .= ' is-bad';
			}
			$now      = (int) ( isset( $args['now'] ) ? $args['now'] : 0 );
			$total    = (int) ( isset( $args['total'] ) ? $args['total'] : 0 );
			$pct      = $total > 0 ? min( 100, (int) round( $now * 100 / $total ) ) : 0;
			$bar_html = '<div class="' . esc_attr( $bar ) . '" role="progressbar" aria-valuemin="0" aria-valuemax="' . esc_attr( (string) $total ) . '" aria-valuenow="' . esc_attr( (string) $now ) . '"><i style="width:' . $pct . '%"></i></div>';
			if ( array_key_exists( 'bar', $args ) && false === $args['bar'] ) {
				$bar_html = '';
			}
			$counts = isset( $args['counts'] ) && is_array( $args['counts'] ) ? $args['counts'] : array();
			$html   = '<div class="' . esc_attr( self::classes( array( 'wenpai-task', self::extra_class( $args ) ) ) ) . '"' . self::extra_attrs( $args ) . '>' . $bar_html;
			if ( array() !== $counts ) {
				$html .= '<div class="wenpai-task-counts">';
				foreach ( $counts as $pill ) {
					if ( is_array( $pill ) ) {
						$html .= self::pill_html( $pill );
					}
				}
				$html .= '</div>';
			}
			if ( isset( $args['meta_html'] ) && '' !== (string) $args['meta_html'] ) {
				$html .= '<p class="wenpai-task-meta" aria-live="polite">' . self::kses( (string) $args['meta_html'], 'post' ) . '</p>';
			} elseif ( isset( $args['meta'] ) && '' !== (string) $args['meta'] ) {
				$html .= '<p class="wenpai-task-meta" aria-live="polite">' . esc_html( (string) $args['meta'] ) . '</p>';
			}
			return $html . '</div>';
		}

		/**
		 * Echo a read-only log list.
		 *
		 * @param array $args Arguments.
		 */
		public static function log_list( $args = array() ) {
			self::emit( self::log_list_html( $args ) );
		}

		/**
		 * Return log list markup.
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type array $items array of array{ tone, label, count?, when?, detail }.
		 *                        Optional item keys:
		 *                        'logo' / 'logo_html' brand mark before the label
		 *                        (logo_html: svg or img, kses logo; wins over logo);
		 *                        'pill' => false prints the label as plain text, no pill;
		 *                        'meta' plain text after the label (e.g. #128);
		 *                        'aside' list of plain strings in a right column
		 *                        (tokens, latency).
		 *     @type array $empty Optional empty_state arguments when items is empty.
		 *     @type string $class Extra classes on ul.wenpai-log.
		 *     @type array $attrs  Extra attributes.
		 * }
		 * @return string
		 */
		public static function log_list_html( $args = array() ) {
			$args  = self::args( $args, 'Wenpai_Admin_UI::log_list' );
			$items = isset( $args['items'] ) && is_array( $args['items'] ) ? $args['items'] : array();
			if ( array() === $items ) {
				return isset( $args['empty'] ) && is_array( $args['empty'] ) ? self::empty_state_html( $args['empty'] ) : '';
			}
			$html = '<ul class="' . esc_attr( self::classes( array( 'wenpai-log', self::extra_class( $args ) ) ) ) . '"' . self::extra_attrs( $args ) . '>';
			foreach ( $items as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$aside = array();
				if ( isset( $item['aside'] ) ) {
					foreach ( (array) $item['aside'] as $bit ) {
						if ( is_scalar( $bit ) && '' !== (string) $bit ) {
							$aside[] = '<span>' . esc_html( (string) $bit ) . '</span>';
						}
					}
				}
				$html .= array() === $aside ? '<li><div class="n">' : '<li class="has-aside"><div class="n">';
				$html .= self::logo_slot( $item, 'sm' );
				$label = isset( $item['label'] ) ? (string) $item['label'] : '';
				if ( array_key_exists( 'pill', $item ) && false === $item['pill'] ) {
					$html .= '<span class="wenpai-log__label">' . esc_html( $label ) . '</span>';
				} else {
					$html .= self::pill_html(
						array(
							'state' => isset( $item['tone'] ) ? (string) $item['tone'] : '',
							'text'  => $label,
						)
					);
				}
				if ( ! empty( $item['count'] ) && 1 < (int) $item['count'] ) {
					$html .= '<span class="meta"> ×' . (int) $item['count'] . '</span>';
				}
				if ( isset( $item['meta'] ) && '' !== (string) $item['meta'] ) {
					$html .= '<span class="meta">' . esc_html( (string) $item['meta'] ) . '</span>';
				}
				$html .= '</div>';
				if ( array() !== $aside ) {
					$html .= '<div class="wenpai-log__aside">' . implode( '', $aside ) . '</div>';
				}
				if ( isset( $item['when'] ) && '' !== (string) $item['when'] ) {
					$html .= '<div class="when">' . esc_html( (string) $item['when'] ) . '</div>';
				}
				$html .= '<div class="p">' . esc_html( isset( $item['detail'] ) ? (string) $item['detail'] : '' ) . '</div></li>';
			}
			return $html . '</ul>';
		}

		/**
		 * Attributes that wire an element to the kit confirm dialog (0.5.3).
		 *
		 * @param string $text Confirmation sentence shown in the dialog body.
		 * @param array  $args Optional. title / gate / danger / ok_label / cancel_label.
		 * @return string Escaped attribute string with a leading space.
		 */
		public static function confirm_attrs( $text, $args = array() ) {
			$out  = ' data-wenpai-confirm="' . esc_attr( (string) $text ) . '"';
			$args = is_array( $args ) ? $args : array();
			if ( ! empty( $args['title'] ) ) {
				$out .= ' data-wenpai-confirm-title="' . esc_attr( (string) $args['title'] ) . '"';
			}
			if ( ! empty( $args['gate'] ) ) {
				$out .= ' data-wenpai-confirm-gate="' . esc_attr( (string) $args['gate'] ) . '"';
			}
			if ( ! empty( $args['danger'] ) ) {
				$out .= ' data-wenpai-confirm-danger="1"';
			}
			if ( ! empty( $args['ok_label'] ) ) {
				$out .= ' data-wenpai-confirm-ok="' . esc_attr( (string) $args['ok_label'] ) . '"';
			}
			if ( ! empty( $args['cancel_label'] ) ) {
				$out .= ' data-wenpai-confirm-cancel="' . esc_attr( (string) $args['cancel_label'] ) . '"';
			}
			return $out;
		}

		/**
		 * Open a wizard page shell (narrow column + steps + optional jump notice).
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type array  $steps  steps() items.
		 *     @type int    $current Current step index for steps().
		 *     @type string $notice Optional jump-back sentence (kit notice, info).
		 *     @type string $class  Extra classes on .wenpai-wizard.
		 * }
		 * @return void
		 */
		public static function wizard_open( $args = array() ) {
			$class = self::classes( array( 'wenpai-wizard', self::extra_class( $args ) ) );
			echo '<div class="' . esc_attr( $class ) . '"' . self::extra_attrs( $args ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- extra_attrs escapes each value.
			if ( isset( $args['steps'] ) && is_array( $args['steps'] ) ) {
				self::steps(
					array(
						'items'   => $args['steps'],
						'current' => isset( $args['current'] ) ? (int) $args['current'] : 0,
					)
				);
			}
			if ( isset( $args['notice'] ) && '' !== (string) $args['notice'] ) {
				self::notice(
					array(
						'level' => 'info',
						'text'  => (string) $args['notice'],
						'wrap'  => false,
					)
				);
			}
		}

		/**
		 * Close the wizard shell.
		 *
		 * @return void
		 */
		public static function wizard_close() {
			echo '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static closing tag.
		}

		/**
		 * Echo a media-library column cell (pill + one-line key).
		 *
		 * @param array $args Arguments.
		 */
		public static function native_column( $args = array() ) {
			self::emit( self::native_column_html( $args ) );
		}

		/**
		 * Return column cell markup.
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type array  $pill Pill arguments (text, state).
		 *     @type string $code Optional object key shown as one-line code chip.
		 *     @type string $meta Optional extra meta line.
		 * }
		 * @return string
		 */
		public static function native_column_html( $args = array() ) {
			$args = self::args( $args, 'Wenpai_Admin_UI::native_column' );
			$html = '<div class="wenpai-native wenpai-native-col">';
			if ( isset( $args['pill'] ) && is_array( $args['pill'] ) ) {
				$html .= self::pill_html( $args['pill'] );
			}
			if ( isset( $args['code'] ) && '' !== (string) $args['code'] ) {
				$html .= '<code>' . esc_html( (string) $args['code'] ) . '</code>';
			}
			if ( isset( $args['meta'] ) && '' !== (string) $args['meta'] ) {
				$html .= '<span class="meta">' . esc_html( (string) $args['meta'] ) . '</span>';
			}
			return $html . '</div>';
		}

		/**
		 * Echo an attachment metabox body (status + lines + actions + note).
		 *
		 * @param array $args Arguments.
		 */
		public static function native_metabox( $args = array() ) {
			self::emit( self::native_metabox_html( $args ) );
		}

		/**
		 * Return metabox body markup. Product registers the metabox itself.
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type array  $pill    Status pill arguments.
		 *     @type array  $lines   array of array{ kind: meta|code|link, text, href? }.
		 *     @type array  $actions Button argument arrays (href renders <a>).
		 *     @type string $note    Closing warning sentence.
		 * }
		 * @return string
		 */
		public static function native_metabox_html( $args = array() ) {
			$args = self::args( $args, 'Wenpai_Admin_UI::native_metabox' );
			$html = '<div class="wenpai-native wenpai-native-metabox">';
			if ( isset( $args['pill'] ) && is_array( $args['pill'] ) ) {
				$html .= '<div class="head">' . self::pill_html( $args['pill'] ) . '</div>';
			}
			$lines = isset( $args['lines'] ) && is_array( $args['lines'] ) ? $args['lines'] : array();
			foreach ( $lines as $line ) {
				if ( ! is_array( $line ) ) {
					continue;
				}
				$kind = isset( $line['kind'] ) ? (string) $line['kind'] : 'meta';
				$text = isset( $line['text'] ) ? (string) $line['text'] : '';
				if ( 'code' === $kind ) {
					$html .= '<p class="meta"><code>' . esc_html( $text ) . '</code></p>';
				} elseif ( 'link' === $kind && ! empty( $line['href'] ) ) {
					$html .= '<p class="meta"><a href="' . esc_url( (string) $line['href'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $text ) . '</a></p>';
				} else {
					$html .= '<p class="meta">' . esc_html( $text ) . '</p>';
				}
			}
			$actions = isset( $args['actions'] ) && is_array( $args['actions'] ) ? $args['actions'] : array();
			if ( array() !== $actions ) {
				$html .= '<div class="actions">';
				foreach ( $actions as $btn ) {
					if ( is_array( $btn ) ) {
						$html .= self::button_html( $btn );
					}
				}
				$html .= '</div>';
			}
			if ( isset( $args['note'] ) && '' !== (string) $args['note'] ) {
				$html .= '<p class="meta">' . esc_html( (string) $args['note'] ) . '</p>';
			}
			return $html . '</div>';
		}

		/**
		 * Echo a section title.
		 *
		 * @param array $args Arguments.
		 */
		public static function section_title( $args = array() ) {
			self::emit( self::section_title_html( $args ) );
		}

		/**
		 * Return section title markup.
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type string $title   Title.
		 *     @type string $desc    Optional description.
		 *     @type string $desc_html Optional description HTML (kses post), wins over $desc.
		 *     @type int    $heading Level, default 2.
		 *     @type string $class   Extra classes on the heading.
		 *     @type array  $attrs   Extra attributes on the heading.
		 * }
		 * @return string
		 */
		public static function section_title_html( $args = array() ) {
			$args    = self::args( $args, 'Wenpai_Admin_UI::section_title' );
			$heading = self::heading( isset( $args['heading'] ) ? $args['heading'] : 2, 'Wenpai_Admin_UI::section_title' );
			$title   = isset( $args['title'] ) ? (string) $args['title'] : '';
			$class   = self::classes( array( 'section-title', self::extra_class( $args ) ) );
			$html    = '<h' . $heading . ' class="' . esc_attr( $class ) . '"' . self::extra_attrs( $args ) . '>' . esc_html( $title ) . '</h' . $heading . '>';
			if ( isset( $args['desc_html'] ) && '' !== (string) $args['desc_html'] ) {
				$html .= '<p class="section-desc">' . self::kses( (string) $args['desc_html'], 'post' ) . '</p>';
			} elseif ( isset( $args['desc'] ) && '' !== (string) $args['desc'] ) {
				$html .= '<p class="section-desc">' . esc_html( (string) $args['desc'] ) . '</p>';
			}
			return $html;
		}

		/**
		 * Echo a status row.
		 *
		 * @param array $args Arguments.
		 */
		public static function row( $args = array() ) {
			self::emit( self::row_html( $args ) );
		}

		/**
		 * Return status row markup.
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type string $title      .t text.
		 *     @type string $title_html .t HTML (kses post), wins over title: name plus
		 *                              a pill and a version, e.g. 「GEO <pill>核心</pill> v1.2」.
		 *     @type string $desc       .d text.
		 *     @type string $desc_html  .d HTML (kses post), wins over desc.
		 *     @type bool   $desc_is_url The description is a URL or path: .d.is-url, no
		 *                              46ch cap, breaks anywhere. Default false.
		 *     @type string $icon       Tile icon.
		 *     @type string $state      Tile state.
		 *     @type string $logo       Brand mark text (one or two characters) instead of the tile.
		 *     @type string $logo_html  Brand mark svg / img (kses logo) instead of the tile;
		 *                              wins over logo. A brand mark says who, the tile says state.
		 *     @type array  $pill       array( text, state ) or keyed.
		 *     @type string $title_id   id on .t, for the control's aria-labelledby.
		 *     @type string $desc_id    id on .d, for the control's aria-describedby.
		 *     @type string $right_html Extra .r HTML (kses post: form controls are stripped).
		 *     @type string $control_html Control row: control in .r, kses with form
		 *                                controls kept (e.g. $fw->render_control_html()).
		 *                                Outputs a bare .simple-row; href is ignored.
		 *     @type string $href       Whole row is a link (.is-action); else .is-static.
		 *     @type string $class      Extra classes.
		 *     @type array  $attrs      Extra attributes.
		 * }
		 * @return string
		 */
		public static function row_html( $args = array() ) {
			$args    = self::args( $args, 'Wenpai_Admin_UI::row' );
			$href    = isset( $args['href'] ) ? (string) $args['href'] : '';
			$control = array_key_exists( 'control_html', $args );
			if ( $control && '' !== $href ) {
				self::doing_it_wrong( 'row control_html and href cannot be combined; href is ignored.', 'Wenpai_Admin_UI::row' );
				$href = '';
			}
			$mod   = '' !== $href ? 'is-action' : 'is-static';
			$mod   = $control ? '' : $mod;
			$class = self::classes( array( 'simple-row', $mod, self::extra_class( $args ) ) );
			$tile  = self::logo_slot( $args, '' );
			if ( '' === $tile ) {
				$tile = self::tile_html(
					array(
						'icon'      => isset( $args['icon'] ) ? $args['icon'] : '',
						'state'     => isset( $args['state'] ) ? $args['state'] : '',
						'icon_html' => isset( $args['icon_html'] ) ? $args['icon_html'] : '',
					)
				);
			}
			$d_cls = ! empty( $args['desc_is_url'] ) ? 'd is-url' : 'd';
			$body  = '<div>';
			if ( isset( $args['title_html'] ) && '' !== (string) $args['title_html'] ) {
				$body .= '<div class="t"' . self::id_attr( $args, 'title_id' ) . '>' . self::kses( (string) $args['title_html'], 'post' ) . '</div>';
			} elseif ( isset( $args['title'] ) && '' !== (string) $args['title'] ) {
				$body .= '<div class="t"' . self::id_attr( $args, 'title_id' ) . '>' . esc_html( (string) $args['title'] ) . '</div>';
			}
			if ( array_key_exists( 'desc_html', $args ) && '' !== (string) $args['desc_html'] ) {
				$body .= '<div class="' . $d_cls . '"' . self::id_attr( $args, 'desc_id' ) . '>' . self::kses( (string) $args['desc_html'], 'post' ) . '</div>';
			} elseif ( isset( $args['desc'] ) && '' !== (string) $args['desc'] ) {
				$body .= '<div class="' . $d_cls . '"' . self::id_attr( $args, 'desc_id' ) . '>' . esc_html( (string) $args['desc'] ) . '</div>';
			}
			$body .= '</div>';
			$right = '';
			if ( isset( $args['pill'] ) && is_array( $args['pill'] ) ) {
				$pill   = self::item_kv( $args['pill'], array( 'text', 'state' ) );
				$right .= self::pill_html( $pill );
			}
			if ( isset( $args['right_html'] ) ) {
				$right .= self::kses( (string) $args['right_html'], 'post' );
			}
			if ( $control ) {
				$right .= self::kses( (string) $args['control_html'], 'control' );
			}
			if ( '' !== $right ) {
				$body .= '<div class="r">' . $right . '</div>';
			}
			if ( '' !== $href ) {
				return '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $href ) . '"' . self::extra_attrs( $args ) . '>' . $tile . $body . '</a>';
			}
			return '<div class="' . esc_attr( $class ) . '"' . self::extra_attrs( $args ) . '>' . $tile . $body . '</div>';
		}

		/**
		 * Echo a next-step bar.
		 *
		 * @param array $args Arguments.
		 */
		public static function next( $args = array() ) {
			self::emit( self::next_html( $args ) );
		}

		/**
		 * Return next-step bar markup.
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type string $title   Strong text.
		 *     @type string $desc    .meta text.
		 *     @type string $icon    Tile icon.
		 *     @type array  $actions Button argument arrays.
		 *     @type string $class   Extra classes.
		 *     @type array  $attrs   Extra attributes.
		 * }
		 * @return string
		 */
		public static function next_html( $args = array() ) {
			$args  = self::args( $args, 'Wenpai_Admin_UI::next' );
			$class = self::classes( array( 'next', self::extra_class( $args ) ) );
			$html  = '<div class="' . esc_attr( $class ) . '"' . self::extra_attrs( $args ) . '>';
			$html .= self::tile_html(
				array(
					'icon'      => isset( $args['icon'] ) ? $args['icon'] : '',
					'state'     => isset( $args['state'] ) ? $args['state'] : '',
					'icon_html' => isset( $args['icon_html'] ) ? $args['icon_html'] : '',
				)
			);
			$html .= '<div>';
			if ( isset( $args['title'] ) && '' !== (string) $args['title'] ) {
				$html .= '<strong>' . esc_html( (string) $args['title'] ) . '</strong>';
			}
			if ( isset( $args['desc'] ) && '' !== (string) $args['desc'] ) {
				$html .= '<div class="meta">' . esc_html( (string) $args['desc'] ) . '</div>';
			}
			$html .= '</div>';
			if ( isset( $args['actions'] ) && is_array( $args['actions'] ) && array() !== $args['actions'] ) {
				$html .= '<div class="next-actions">';
				foreach ( $args['actions'] as $btn ) {
					if ( is_array( $btn ) ) {
						$html .= self::button_html( $btn );
					}
				}
				$html .= '</div>';
			}
			return $html . '</div>';
		}

		/**
		 * Echo an empty state.
		 *
		 * @param array $args Arguments.
		 */
		public static function empty_state( $args = array() ) {
			self::emit( self::empty_state_html( $args ) );
		}

		/**
		 * Return empty state markup.
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type string $text   Main line.
		 *     @type string $meta   Secondary line.
		 *     @type string $icon   Optional tile.
		 *     @type array  $action Optional button args.
		 *     @type string $class  Extra classes.
		 *     @type array  $attrs  Extra attributes.
		 * }
		 * @return string
		 */
		public static function empty_state_html( $args = array() ) {
			$args  = self::args( $args, 'Wenpai_Admin_UI::empty_state' );
			$class = self::classes( array( 'empty', self::extra_class( $args ) ) );
			$html  = '<div class="' . esc_attr( $class ) . '"' . self::extra_attrs( $args ) . '>';
			if ( isset( $args['icon'] ) && '' !== (string) $args['icon'] ) {
				$html .= self::tile_html(
					array(
						'icon'      => $args['icon'],
						'state'     => isset( $args['state'] ) ? $args['state'] : '',
						'icon_html' => isset( $args['icon_html'] ) ? $args['icon_html'] : '',
					)
				);
			}
			$html .= '<p>' . esc_html( isset( $args['text'] ) ? (string) $args['text'] : '' ) . '</p>';
			if ( isset( $args['meta'] ) && '' !== (string) $args['meta'] ) {
				$html .= '<p class="meta">' . esc_html( (string) $args['meta'] ) . '</p>';
			}
			if ( isset( $args['action'] ) && is_array( $args['action'] ) ) {
				$html .= self::button_html( $args['action'] );
			}
			return $html . '</div>';
		}

		/**
		 * Echo a StatArea card (P2-charts: label + value + desc + optional Area SVG).
		 *
		 * @param array $args Arguments.
		 */
		public static function stat_area( $args = array() ) {
			self::emit( self::stat_area_html( $args ) );
		}

		/**
		 * Return StatArea markup. Area only (no Chart.js). SVG when !empty and points>=2.
		 *
		 * @param array $args {
		 *     Arguments for the StatArea card.
		 *     @type string $label  Visible label.
		 *     @type string $value  Big number or empty-state text.
		 *     @type string $unit   Optional unit beside value.
		 *     @type string $delta  Optional positive delta text (.delta; color --wenpai-ok-ink).
		 *     @type string $desc   Description (.d); when empty, say when data appears.
		 *     @type array  $points Number list for Area sparkline.
		 *     @type bool   $empty  True → .v.none, no SVG.
		 *     @type bool   $busy   True → .is-busy + spin line (no .btn.is-loading).
		 *     @type string $icon   Optional icon name for .k.
		 *     @type string $class  Extra classes.
		 *     @type array  $attrs  Extra attributes.
		 * }
		 * @return string
		 */
		public static function stat_area_html( $args = array() ) {
			$args  = self::args( $args, 'Wenpai_Admin_UI::stat_area' );
			$empty = ! empty( $args['empty'] );
			$busy  = ! empty( $args['busy'] );
			$class = self::classes(
				array(
					'card',
					'stat-a',
					$busy ? 'is-busy' : '',
					self::extra_class( $args ),
				)
			);
			$attrs = $args;
			if ( $busy ) {
				if ( ! isset( $attrs['attrs'] ) || ! is_array( $attrs['attrs'] ) ) {
					$attrs['attrs'] = array();
				}
				$attrs['attrs']['aria-busy'] = 'true';
			}
			$html  = '<article class="' . esc_attr( $class ) . '"' . self::extra_attrs( $attrs ) . '>';
			$html .= '<div class="k">';
			if ( isset( $args['icon'] ) && '' !== (string) $args['icon'] ) {
				$html .= self::icon_svg( (string) $args['icon'] );
			}
			$html .= esc_html( isset( $args['label'] ) ? (string) $args['label'] : '' );
			$html .= '</div>';

			$value = isset( $args['value'] ) ? (string) $args['value'] : '';
			if ( $empty ) {
				$html .= '<div class="v none">' . esc_html( $value ) . '</div>';
			} else {
				$html .= '<div class="v">' . esc_html( $value );
				if ( isset( $args['unit'] ) && '' !== (string) $args['unit'] ) {
					$html .= '<small>' . esc_html( (string) $args['unit'] ) . '</small>';
				}
				if ( isset( $args['delta'] ) && '' !== (string) $args['delta'] ) {
					$html .= '<span class="delta">' . esc_html( (string) $args['delta'] ) . '</span>';
				}
				$html .= '</div>';
			}

			$html .= '<div class="d">' . esc_html( isset( $args['desc'] ) ? (string) $args['desc'] : '' ) . '</div>';

			if ( $busy ) {
				$html .= '<div class="stat-a-busy"><span class="spin" aria-hidden="true"></span>' . esc_html( '正在…' ) . '</div>';
			}

			$points = isset( $args['points'] ) && is_array( $args['points'] ) ? $args['points'] : array();
			if ( ! $empty && count( $points ) >= 2 ) {
				$html .= self::area_svg_html( $points );
			}

			return $html . '</article>';
		}

		/**
		 * Inline Area sparkline SVG (viewBox 300×44). Trusted path math; kses for safety.
		 *
		 * @param array $points Numbers.
		 * @return string
		 */
		private static function area_svg_html( array $points ) {
			$w  = 300;
			$h  = 44;
			$n  = count( $points );
			$mx = 0.0;
			foreach ( $points as $p ) {
				$v = (float) $p;
				if ( $v > $mx ) {
					$mx = $v;
				}
			}
			if ( $mx <= 0 ) {
				$mx = 1.0;
			}
			$coords = array();
			for ( $i = 0; $i < $n; $i++ ) {
				$x        = ( 1 === $n ) ? 0.0 : ( $i * $w ) / ( $n - 1 );
				$y        = $h - 6 - ( ( (float) $points[ $i ] ) / $mx ) * ( $h - 12 );
				$coords[] = sprintf( '%.1f,%.1f', $x, $y );
			}
			$d    = 'M' . implode( ' L', $coords );
			$fill = $d . ' L' . $w . ',' . $h . ' L0,' . $h . ' Z';
			$gid  = 'wenpai-area-' . substr( md5( $d ), 0, 8 );
			$svg  = '<svg class="area" viewBox="0 0 300 44" preserveAspectRatio="none" aria-hidden="true">';
			$svg .= '<defs><linearGradient id="' . esc_attr( $gid ) . '" x1="0" y1="0" x2="0" y2="1">';
			$svg .= '<stop offset="0" stop-color="var(--wenpai-accent)" stop-opacity=".18" />';
			$svg .= '<stop offset="1" stop-color="var(--wenpai-accent)" stop-opacity="0" />';
			$svg .= '</linearGradient></defs>';
			$svg .= '<path d="' . esc_attr( $fill ) . '" fill="url(#' . esc_attr( $gid ) . ')" />';
			$svg .= '<path d="' . esc_attr( $d ) . '" fill="none" stroke="var(--wenpai-accent)" stroke-width="1.75" stroke-linejoin="round" />';
			$svg .= '</svg>';
			return self::kses( $svg, 'svg' );
		}

		/**
		 * Echo a progressbar (Track E / E14 · P2x). Budget occupancy display.
		 * Not StatArea. Ban ProgressCard.
		 *
		 * @param array $args Arguments.
		 */
		public static function progressbar( $args = array() ) {
			self::emit( self::progressbar_html( $args ) );
		}

		/**
		 * Return progressbar markup. role=progressbar + aria-valuenow/min/max.
		 *
		 * Batch-task tick loops stay in the product (ARCHITECTURE §1.11).
		 * This helper is display only.
		 *
		 * @param array $args {
		 *     Arguments for the progressbar.
		 *     @type float  $value          Current occupancy. Ignored if indeterminate.
		 *     @type float  $min            Default 0.
		 *     @type float  $max            Default 100.
		 *     @type string $state          ''|warn|bad.
		 *     @type string $label          aria-label (e.g. 预算占用).
		 *     @type string $text           Visible percent; default derived from value/max.
		 *     @type bool   $indeterminate  True → omit aria-valuenow; .is-indeterminate.
		 *     @type string $class          Extra classes on .progress.
		 *     @type array  $attrs          Extra attributes on the progressbar node.
		 * }
		 * @return string
		 */
		public static function progressbar_html( $args = array() ) {
			$args  = self::args( $args, 'Wenpai_Admin_UI::progressbar' );
			$state = self::state( isset( $args['state'] ) ? $args['state'] : '', self::$progress_states, '', 'Wenpai_Admin_UI::progressbar' );
			$min   = isset( $args['min'] ) ? (float) $args['min'] : 0.0;
			$max   = isset( $args['max'] ) ? (float) $args['max'] : 100.0;
			if ( $max <= $min ) {
				self::doing_it_wrong( 'max must be greater than min.', 'Wenpai_Admin_UI::progressbar' );
				$max = $min + 1.0;
			}
			$indet = ! empty( $args['indeterminate'] );
			$value = isset( $args['value'] ) ? (float) $args['value'] : 0.0;
			$span  = $max - $min;
			$raw   = $indet ? 0.0 : ( ( $value - $min ) / $span ) * 100.0;
			$fill  = max( 0.0, min( 100.0, $raw ) );
			$now   = max( $min, min( $max, $value ) );
			if ( array_key_exists( 'text', $args ) ) {
				$text = (string) $args['text'];
			} elseif ( $indet ) {
				$text = '';
			} else {
				$text = ( abs( $raw - round( $raw ) ) < 0.05 )
					? (string) (int) round( $raw ) . '%'
					: rtrim( rtrim( sprintf( '%.1F', $raw ), '0' ), '.' ) . '%';
			}
			$class = self::classes(
				array(
					'progress',
					$state,
					$indet ? 'is-indeterminate' : '',
					self::extra_class( $args ),
				)
			);
			$aria  = ' role="progressbar"';
			$aria .= ' aria-valuemin="' . esc_attr( self::progress_number( $min ) ) . '"';
			$aria .= ' aria-valuemax="' . esc_attr( self::progress_number( $max ) ) . '"';
			if ( ! $indet ) {
				$aria .= ' aria-valuenow="' . esc_attr( self::progress_number( $now ) ) . '"';
				if ( '' !== $text ) {
					$aria .= ' aria-valuetext="' . esc_attr( $text ) . '"';
				}
			}
			if ( isset( $args['label'] ) && '' !== (string) $args['label'] ) {
				$aria .= ' aria-label="' . esc_attr( (string) $args['label'] ) . '"';
			}
			$html  = '<div class="progress-wrap">';
			$html .= '<div class="' . esc_attr( $class ) . '"' . $aria . self::extra_attrs( $args, array( 'role' ) ) . '>';
			if ( $indet ) {
				$html .= '<i aria-hidden="true"></i>';
			} else {
				$html .= '<i aria-hidden="true" style="width:' . esc_attr( self::progress_number( $fill ) ) . '%"></i>';
			}
			$html .= '</div>';
			if ( '' !== $text ) {
				$html .= '<span class="progress-pct">' . esc_html( $text ) . '</span>';
			}
			return $html . '</div>';
		}

		/**
		 * Format a number for attributes (dot decimal, trim trailing zeros).
		 *
		 * @param float $n Number.
		 * @return string
		 */
		private static function progress_number( $n ) {
			if ( abs( $n - round( $n ) ) < 0.0005 ) {
				return (string) (int) round( $n );
			}
			return rtrim( rtrim( sprintf( '%.2F', $n ), '0' ), '.' );
		}

		/**
		 * Echo a button or button-styled link.
		 *
		 * @param array $args Arguments.
		 */
		public static function button( $args = array() ) {
			self::emit( self::button_html( $args ) );
		}

		/**
		 * Return button markup.
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type string $label      Button text.
		 *     @type string $variant    primary|secondary|ghost|danger. Default secondary.
		 *     @type string $href       Output <a> when set.
		 *     @type string $target     For <a>; _blank adds rel and screen-reader hint.
		 *     @type string $type       button|submit for <button>. Default button.
		 *     @type string $name       <button> name.
		 *     @type string $value      <button> value.
		 *     @type bool   $disabled   Disabled. With href: <a role="link" aria-disabled="true"
		 *                              tabindex="-1"> and no href, target or rel.
		 *     @type bool   $need_apply data-wenpai-need-apply.
		 *     @type string $busy_label data-wenpai-busy.
		 *     @type string $new_tab_label Screen-reader hint after a target="_blank" link.
		 *                                 Default 「（在新标签页打开）」 (same as Shell new_tab_label
		 *                                 and Settings strings['new_tab']); '' prints none.
		 *     @type string $class      Extra classes.
		 *     @type array  $attrs      Extra attributes.
		 * }
		 * @return string
		 */
		public static function button_html( $args = array() ) {
			$args    = self::args( $args, 'Wenpai_Admin_UI::button' );
			$variant = isset( $args['variant'] ) ? (string) $args['variant'] : 'secondary';
			if ( ! in_array( $variant, self::$button_variants, true ) ) {
				self::doing_it_wrong( sprintf( 'Invalid button variant 「%s」.', $variant ), 'Wenpai_Admin_UI::button' );
				$variant = 'secondary';
			}
			$label = isset( $args['label'] ) ? (string) $args['label'] : '';
			$class = self::classes( array( 'btn', 'btn-' . $variant, self::extra_class( $args ) ) );
			$href  = isset( $args['href'] ) ? (string) $args['href'] : '';
			$skip  = array();
			$extra = '';
			if ( ! empty( $args['need_apply'] ) ) {
				$extra .= ' data-wenpai-need-apply';
			}
			if ( isset( $args['busy_label'] ) && '' !== (string) $args['busy_label'] ) {
				$extra .= ' data-wenpai-busy="' . esc_attr( (string) $args['busy_label'] ) . '"';
			}
			if ( '' !== $href ) {
				$target = isset( $args['target'] ) ? trim( (string) $args['target'] ) : '';
				if ( in_array( strtolower( $target ), array( '_blank', '_self', '_parent', '_top' ), true ) ) {
					$target = strtolower( $target );
				}
				if ( ! empty( $args['disabled'] ) ) {
					// No href, out of the Tab order: a disabled link must not navigate or take focus.
					// Styled by .btn[aria-disabled="true"]. Without permission, do not call button() at all.
					return '<a class="' . esc_attr( $class ) . '" role="link" aria-disabled="true" tabindex="-1"' . self::extra_attrs( $args, array( 'role', 'aria-disabled' ) ) . '>' . esc_html( $label ) . '</a>';
				}
				$html = '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $href ) . '"';
				if ( '' !== $target ) {
					$html .= ' target="' . esc_attr( $target ) . '"';
				}
				$hint = '';
				if ( '_blank' === $target ) {
					$html .= ' rel="noopener noreferrer"';
					$tab   = array_key_exists( 'new_tab_label', $args ) ? (string) $args['new_tab_label'] : '（在新标签页打开）';
					$hint  = '' === $tab ? '' : '<span class="screen-reader-text">' . esc_html( $tab ) . '</span>';
				}
				$html .= $extra . self::extra_attrs( $args, $skip ) . '>' . esc_html( $label ) . $hint . '</a>';
				return $html;
			}
			$type = isset( $args['type'] ) ? (string) $args['type'] : 'button';
			if ( ! in_array( $type, array( 'button', 'submit' ), true ) ) {
				self::doing_it_wrong( sprintf( 'Invalid button type 「%s」.', $type ), 'Wenpai_Admin_UI::button' );
				$type = 'button';
			}
			$html = '<button type="' . esc_attr( $type ) . '" class="' . esc_attr( $class ) . '"';
			if ( isset( $args['name'] ) && '' !== (string) $args['name'] ) {
				$html .= ' name="' . esc_attr( (string) $args['name'] ) . '"';
			}
			$form_id = '';
			if ( isset( $args['form'] ) && '' !== (string) $args['form'] ) {
				$form_id = (string) $args['form'];
			} elseif ( isset( $args['attrs']['form'] ) && is_scalar( $args['attrs']['form'] ) && '' !== (string) $args['attrs']['form'] ) {
				$form_id = (string) $args['attrs']['form'];
			}
			if ( '' !== $form_id ) {
				$html .= ' form="' . esc_attr( $form_id ) . '"';
			}
			if ( array_key_exists( 'value', $args ) ) {
				$html .= ' value="' . esc_attr( (string) $args['value'] ) . '"';
			}
			if ( ! empty( $args['disabled'] ) ) {
				$html .= ' disabled';
			}
			$html .= $extra . self::extra_attrs( $args, $skip ) . '>' . esc_html( $label ) . '</button>';
			return $html;
		}

		/**
		 * Echo a confirmation gate.
		 *
		 * @param array $args Arguments.
		 */
		public static function gate( $args = array() ) {
			self::emit( self::gate_html( $args ) );
		}

		/**
		 * Return confirmation gate markup.
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type string $name  Checkbox name.
		 *     @type string $label Confirmation sentence.
		 *     @type string $note  Optional p.meta after the gate.
		 *     @type string $text_id Wraps the label text in <span id>; point the button's
		 *                           aria-describedby here (settings-0.3.0.md §8).
		 *     @type string $id    Optional id on the checkbox itself.
		 *     @type string $class Extra classes on .wenpai-gate.
		 *     @type array  $attrs Extra attributes on the checkbox.
		 * }
		 * @return string
		 */
		public static function gate_html( $args = array() ) {
			$args  = self::args( $args, 'Wenpai_Admin_UI::gate' );
			$name  = isset( $args['name'] ) ? (string) $args['name'] : '';
			$label = isset( $args['label'] ) ? (string) $args['label'] : '';
			$id    = isset( $args['id'] ) ? (string) $args['id'] : '';
			$class = self::classes( array( 'wenpai-gate', self::extra_class( $args ) ) );
			$html  = '<div class="' . esc_attr( $class ) . '"><label><input type="checkbox"';
			if ( '' !== $name ) {
				$html .= ' name="' . esc_attr( $name ) . '"';
			}
			$html .= ' value="1"';
			if ( '' !== $id ) {
				$html .= ' id="' . esc_attr( $id ) . '"';
			}
			$text = esc_html( $label );
			if ( isset( $args['text_id'] ) && '' !== (string) $args['text_id'] ) {
				$text = '<span id="' . esc_attr( (string) $args['text_id'] ) . '">' . $text . '</span>';
			}
			$html .= self::extra_attrs( $args, array( 'id' ) ) . '> ' . $text . '</label></div>';
			if ( isset( $args['note'] ) && '' !== (string) $args['note'] ) {
				$html .= '<p class="meta">' . esc_html( (string) $args['note'] ) . '</p>';
			}
			return $html;
		}

		/**
		 * Echo a simple/advanced switch.
		 *
		 * @param array $args Arguments.
		 */
		public static function mode_switch( $args = array() ) {
			self::emit( self::mode_switch_html( $args ) );
		}

		/**
		 * Return mode switch markup.
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type array  $items array of array( label, href, current ).
		 *     @type string $icon  Optional icon before .seg.
		 *     @type string $label aria-label; also adds role="group" so it is announced.
		 *     @type bool   $wrap  Output outer .mode. Default true.
		 *     @type string $class Extra classes on .mode (or .seg when wrap is false).
		 *     @type array  $attrs Extra attributes on that element.
		 * }
		 * @return string
		 */
		public static function mode_switch_html( $args = array() ) {
			$args  = self::args( $args, 'Wenpai_Admin_UI::mode_switch' );
			$items = isset( $args['items'] ) && is_array( $args['items'] ) ? $args['items'] : array();
			$links = '';
			foreach ( $items as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$row = self::item_kv( $item, array( 'label', 'href', 'current' ) );
				if ( '' === (string) $row['href'] ) {
					self::doing_it_wrong( 'mode_switch item needs href.', 'Wenpai_Admin_UI::mode_switch' );
					continue;
				}
				$on     = ! empty( $row['current'] );
				$links .= '<a href="' . esc_url( (string) $row['href'] ) . '"';
				if ( $on ) {
					$links .= ' class="on" aria-current="page"';
				}
				$links .= '>' . esc_html( (string) $row['label'] ) . '</a>';
			}
			$icon = '';
			if ( isset( $args['icon'] ) && '' !== (string) $args['icon'] ) {
				$icon = self::icon_svg( (string) $args['icon'] );
			}
			$aria = '';
			$skip = array();
			if ( isset( $args['label'] ) && '' !== (string) $args['label'] ) {
				// aria-label is ignored on a role-less div/span; group makes screen readers announce it.
				$aria = ' role="group" aria-label="' . esc_attr( (string) $args['label'] ) . '"';
				$skip = array( 'role', 'aria-label' );
			}
			$wrap = ! array_key_exists( 'wrap', $args ) || $args['wrap'];
			if ( ! $wrap ) {
				$class = self::classes( array( 'seg', self::extra_class( $args ) ) );
				return $icon . '<span class="' . esc_attr( $class ) . '"' . $aria . self::extra_attrs( $args, $skip ) . '>' . $links . '</span>';
			}
			$class = self::classes( array( 'mode', self::extra_class( $args ) ) );
			return '<div class="' . esc_attr( $class ) . '"' . $aria . self::extra_attrs( $args, $skip ) . '>' . $icon . '<span class="seg">' . $links . '</span></div>';
		}

		/**
		 * Current display mode from ?mode= (0.9). Only 「simple」 and
		 * 「advanced」 exist; anything else (or no param) is 「simple」.
		 *
		 * @return string 'simple'|'advanced'
		 */
		public static function mode() {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$mode = isset( $_GET['mode'] ) ? sanitize_key( (string) wp_unslash( $_GET['mode'] ) ) : '';
			return 'advanced' === $mode ? 'advanced' : 'simple';
		}

		/**
		 * Attribute string for hand-written markup that belongs to one display
		 * mode (0.9). Always prints data-wenpai-mode; when $mode is not the
		 * current mode also prints data-wenpai-view-hidden + hidden, so the
		 * block stays hidden without JS and kit JS keeps it hidden when
		 * depends_on re-evaluates.
		 *
		 * Usage: <div class="x"<?php echo Wenpai_Admin_UI::mode_attrs( 'advanced' ); ?>>
		 * The element still submits its inputs (hidden, not disabled).
		 *
		 * @param string $mode 'simple'|'advanced'.
		 * @return string
		 */
		public static function mode_attrs( $mode ) {
			$mode = 'advanced' === $mode ? 'advanced' : 'simple';
			$out  = ' data-wenpai-mode="' . $mode . '"';
			if ( self::mode() !== $mode ) {
				$out .= ' data-wenpai-view-hidden hidden';
			}
			return $out;
		}

		/**
		 * Resolve the current ?section= against a group's id list (0.9).
		 *
		 * With $groups (id => label map): an unknown or missing section falls
		 * back to $fallback when it is a valid id, else the first key.
		 * Without $groups: returns the sanitized raw section, or $fallback.
		 *
		 * @param array  $groups   Optional id => label map of valid sections.
		 * @param string $fallback Fallback section id.
		 * @return string
		 */
		public static function section( $groups = array(), $fallback = '' ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$raw = isset( $_GET['section'] ) ? sanitize_key( (string) wp_unslash( $_GET['section'] ) ) : '';
			if ( ! is_array( $groups ) || array() === $groups ) {
				return '' !== $raw ? $raw : (string) $fallback;
			}
			if ( isset( $groups[ $raw ] ) ) {
				return $raw;
			}
			if ( '' !== (string) $fallback && isset( $groups[ (string) $fallback ] ) ) {
				return (string) $fallback;
			}
			$ids = array_keys( $groups );
			return (string) reset( $ids );
		}

		/**
		 * Attribute string for one group panel inside a [data-wenpai-groups]
		 * container (0.9). Pass the section id resolved by section(); when it
		 * differs from $current the panel prints data-wenpai-view-hidden +
		 * hidden (hidden without JS, kept hidden by kit JS). An empty $current
		 * means "no constraint": the group attr prints without hiding.
		 *
		 * Usage:
		 *   <div data-wenpai-groups data-wenpai-groups-default="connect">
		 *     <div class="x"<?php echo Wenpai_Admin_UI::group_attrs( 'connect', $section ); ?>>…</div>
		 *   </div>
		 * Pair with subnav_html( array( 'slug' => …, 'current' => $section ) )
		 * or explicit item urls carrying ?section=id.
		 *
		 * @param string $id      Group id (sanitized).
		 * @param string $current Current section id from section().
		 * @return string
		 */
		public static function group_attrs( $id, $current ) {
			$id      = sanitize_key( (string) $id );
			$current = sanitize_key( (string) $current );
			$out     = ' data-wenpai-group="' . esc_attr( $id ) . '"';
			if ( '' !== $current && $current !== $id ) {
				$out .= ' data-wenpai-view-hidden hidden';
			}
			return $out;
		}

		/**
		 * Echo in-tab secondary navigation (D-24 / Track A).
		 *
		 * Real links with ?section= (via Menu::url when slug is set). Not role=tablist.
		 *
		 * @param array $args Arguments.
		 */
		public static function subnav( $args = array() ) {
			self::emit( self::subnav_html( $args ) );
		}

		/**
		 * Return in-tab secondary navigation markup.
		 *
		 * Shell tabs stay ≤4; conditional modules (e.g. 7) use this .seg chain under the active tab.
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type array  $items   array of array( id, label, url|href?, badge?, current? ). Positional id/label/url ok.
		 *                           badge: int count or array( count, label ) for a screen-reader suffix (0.10).
		 *                           When url/href empty and $slug is set, builds Menu::url( $slug, query+section ).
		 *     @type string $current Current section id. Also honors item['current'].
		 *     @type string $slug    Optional page slug for Wenpai_Admin_Menu::url.
		 *     @type array  $query   Extra query args preserved on each Menu::url link (e.g. tab).
		 *     @type string $label   nav aria-label. Default 「二级导航」; '' prints none.
		 *     @type string $class   Extra classes on nav.wenpai-subnav.
		 *     @type array  $attrs   Extra attributes on nav.
		 * }
		 * @return string
		 */
		public static function subnav_html( $args = array() ) {
			$args    = self::args( $args, 'Wenpai_Admin_UI::subnav' );
			$items   = isset( $args['items'] ) && is_array( $args['items'] ) ? $args['items'] : array();
			$current = isset( $args['current'] ) ? (string) $args['current'] : '';
			$slug    = isset( $args['slug'] ) ? sanitize_key( (string) $args['slug'] ) : '';
			$query   = isset( $args['query'] ) && is_array( $args['query'] ) ? $args['query'] : array();
			$links   = '';
			foreach ( $items as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$row = self::item_kv( $item, array( 'id', 'label', 'url' ) );
				if ( '' === (string) $row['url'] && array_key_exists( 'href', $item ) ) {
					$row['url'] = $item['href'];
				}
				$id  = sanitize_key( (string) $row['id'] );
				$url = (string) $row['url'];
				if ( '' === $url ) {
					if ( '' === $slug || '' === $id || ! class_exists( 'Wenpai_Admin_Menu', false ) ) {
						self::doing_it_wrong( 'subnav item needs url/href, or slug+id with Wenpai_Admin_Menu.', 'Wenpai_Admin_UI::subnav' );
						continue;
					}
					$url = Wenpai_Admin_Menu::url( $slug, array_merge( $query, array( 'section' => $id ) ) );
				}
				$on     = ! empty( $item['current'] ) || ( '' !== $current && sanitize_key( $current ) === $id );
				$links .= '<a href="' . esc_url( $url ) . '"';
				if ( $on ) {
					$links .= ' class="on" aria-current="page"';
				}
				$links .= '>' . esc_html( (string) $row['label'] );
				// 0.10: optional attention badge (e.g. per-tab error count from
				// Wenpai_Admin_Settings::visible_tabs()['errors']). `badge` is an
				// int, or array( 'count' => n, 'label' => screen-reader text ).
				$badge = isset( $item['badge'] ) ? $item['badge'] : 0;
				$bn    = is_array( $badge ) ? ( isset( $badge['count'] ) ? (int) $badge['count'] : 0 ) : (int) $badge;
				if ( $bn > 0 ) {
					$blabel = is_array( $badge ) && isset( $badge['label'] ) ? (string) $badge['label'] : '';
					$links .= ' <span class="wenpai-subnav__badge" aria-hidden="true">' . $bn . '</span>';
					if ( '' !== $blabel ) {
						$links .= '<span class="screen-reader-text"> ' . esc_html( $blabel ) . '</span>';
					}
				}
				$links .= '</a>';
			}
			$class = self::classes( array( 'wenpai-subnav', self::extra_class( $args ) ) );
			$aria  = '';
			$skip  = array();
			if ( ! array_key_exists( 'label', $args ) ) {
				$args['label'] = '二级导航';
			}
			if ( '' !== (string) $args['label'] ) {
				$aria = ' aria-label="' . esc_attr( (string) $args['label'] ) . '"';
				$skip = array( 'aria-label' );
			}
			return '<nav class="' . esc_attr( $class ) . '"' . $aria . self::extra_attrs( $args, $skip ) . '><span class="seg">' . $links . '</span></nav>';
		}

		/**
		 * Echo a segmented radio group.
		 *
		 * @param array $args Arguments.
		 */
		public static function seg_radio( $args = array() ) {
			self::emit( self::seg_radio_html( $args ) );
		}

		/**
		 * Return a segmented radio group: native radios drawn as .seg (D-33).
		 *
		 * For two to four mutually exclusive options that read best side by
		 * side. Arrow keys, screen readers and form submit come from the native
		 * radios; no JS. Navigation stays mode_switch() / subnav().
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type string $name     Input name. Required.
		 *     @type string $value    Checked value. Unknown or '' checks nothing.
		 *     @type array  $items    value => label, or a list of array( value, label, disabled? ).
		 *     @type string $label    Group name (role=radiogroup + aria-label). '' prints
		 *                            neither, for use inside a fieldset with a legend.
		 *     @type bool   $disabled Disable every option.
		 *     @type string $class    Extra classes on .seg.
		 *     @type array  $attrs    Extra attributes on .seg.
		 * }
		 * @return string
		 */
		public static function seg_radio_html( $args = array() ) {
			$args  = self::args( $args, 'Wenpai_Admin_UI::seg_radio' );
			$name  = isset( $args['name'] ) ? (string) $args['name'] : '';
			$value = isset( $args['value'] ) ? (string) $args['value'] : '';
			$items = isset( $args['items'] ) && is_array( $args['items'] ) ? $args['items'] : array();
			if ( '' === $name ) {
				self::doing_it_wrong( 'seg_radio needs a name.', 'Wenpai_Admin_UI::seg_radio' );
			}
			$label = isset( $args['label'] ) ? (string) $args['label'] : '';
			$aria  = '' !== $label ? ' role="radiogroup" aria-label="' . esc_attr( $label ) . '"' : '';
			$off   = ! empty( $args['disabled'] );
			$class = self::classes( array( 'seg', 'seg--radio', self::extra_class( $args ) ) );
			$html  = '<div class="' . esc_attr( $class ) . '"' . $aria . self::extra_attrs( $args, '' !== $label ? array( 'role', 'aria-label' ) : array() ) . '>';
			foreach ( $items as $key => $item ) {
				if ( is_array( $item ) ) {
					$kv   = self::item_kv( $item, array( 'value', 'label', 'disabled' ) );
					$val  = (string) $kv['value'];
					$text = (string) $kv['label'];
					$dis  = $off || ! empty( $kv['disabled'] );
				} else {
					$val  = (string) $key;
					$text = (string) $item;
					$dis  = $off;
				}
				$html .= '<label><input type="radio" name="' . esc_attr( $name ) . '" value="' . esc_attr( $val ) . '"'
					. ( '' !== $value && $val === $value ? ' checked' : '' )
					. ( $dis ? ' disabled' : '' ) . '><span>' . esc_html( $text ) . '</span></label>';
			}
			return $html . '</div>';
		}

		/**
		 * Echo wizard steps.
		 *
		 * @param array $args Arguments.
		 */
		public static function steps( $args = array() ) {
			self::emit( self::steps_html( $args ) );
		}

		/**
		 * Return wizard steps markup.
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type array  $items   Labels, or array( label, 'no' => step mark ) where 'no'
		 *                           replaces the number on a step that is not done (e.g. ↩).
		 *     @type int    $current 0-based current index. -1: nothing started yet (or the
		 *                           run was cancelled), every step pending.
		 *     @type string $label   nav aria-label. Default 「设置向导步骤」; '' prints none.
		 *     @type string $done_label Screen-reader text after a done step's ✓ (aria-hidden).
		 *                              Default 「已完成」; '' prints none.
		 *     @type string $class   Extra classes.
		 *     @type array  $attrs   Extra attributes.
		 * }
		 * @return string
		 */
		public static function steps_html( $args = array() ) {
			$args  = self::args( $args, 'Wenpai_Admin_UI::steps' );
			$items = isset( $args['items'] ) && is_array( $args['items'] ) ? array_values( $args['items'] ) : array();
			$n     = count( $items );
			$cur   = isset( $args['current'] ) ? (int) $args['current'] : 0;
			if ( $n > 0 && ( $cur < -1 || $cur >= $n ) ) {
				self::doing_it_wrong( 'steps current is out of range.', 'Wenpai_Admin_UI::steps' );
				$cur = max( 0, min( $n - 1, $cur ) );
			}
			$class = self::classes( array( 'steps', self::extra_class( $args ) ) );
			$name  = array_key_exists( 'label', $args ) ? (string) $args['label'] : '设置向导步骤';
			$aria  = '' !== $name ? ' aria-label="' . esc_attr( $name ) . '"' : '';
			$done  = array_key_exists( 'done_label', $args ) ? (string) $args['done_label'] : '已完成';
			$tick  = '<span aria-hidden="true">✓</span>' . ( '' !== $done ? '<span class="screen-reader-text">' . esc_html( $done ) . '</span>' : '' );
			$html  = '<nav class="' . esc_attr( $class ) . '"' . $aria . self::extra_attrs( $args, array( 'aria-label' ) ) . '>';
			foreach ( $items as $i => $item ) {
				if ( $i > 0 ) {
					$html .= '<span class="bar" aria-hidden="true"></span>';
				}
				$label = is_array( $item ) ? (string) ( isset( $item['label'] ) ? $item['label'] : ( isset( $item[0] ) ? $item[0] : '' ) ) : (string) $item;
				$st    = 'st';
				$num   = esc_html( (string) ( $i + 1 ) );
				if ( is_array( $item ) && isset( $item['no'] ) && '' !== (string) $item['no'] ) {
					$num = esc_html( (string) $item['no'] );
				}
				$now = '';
				if ( $i < $cur ) {
					$st .= ' done';
					$num = $tick;
				} elseif ( $i === $cur ) {
					$st .= ' cur';
					$now = ' aria-current="step"';
				}
				$html .= '<span class="' . esc_attr( $st ) . '"' . $now . '><span class="n">' . $num . '</span><span>' . esc_html( $label ) . '</span></span>';
			}
			return $html . '</nav>';
		}

		/**
		 * Echo a table.
		 *
		 * @param array $args Arguments.
		 */
		public static function table( $args = array() ) {
			self::emit( self::table_html( $args ) );
		}

		/**
		 * Return table markup.
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type array  $columns key => label, or key => array( label, num ).
		 *     @type array  $rows    Row maps keyed by column key. A cell may be
		 *                           array( 'html' => … ) for rich content (pill,
		 *                           buttons, row action forms; kses control).
		 *     @type string $caption Caption; screen-reader only by default.
		 *     @type bool   $caption_visible Show the caption on screen (components.md 表格).
		 *     @type array  $empty   empty_state() args when there are no rows.
		 *     @type string $class   Extra classes.
		 *     @type array  $attrs   Extra attributes.
		 * }
		 * @return string
		 */
		public static function table_html( $args = array() ) {
			$args = self::args( $args, 'Wenpai_Admin_UI::table' );
			$rows = isset( $args['rows'] ) && is_array( $args['rows'] ) ? $args['rows'] : array();
			if ( array() === $rows && isset( $args['empty'] ) ) {
				$empty = $args['empty'];
				return self::empty_state_html( is_array( $empty ) ? $empty : array( 'text' => (string) $empty ) );
			}
			$columns = isset( $args['columns'] ) && is_array( $args['columns'] ) ? $args['columns'] : array();
			$cols    = array();
			foreach ( $columns as $key => $col ) {
				if ( is_array( $col ) ) {
					$kv     = self::item_kv( $col, array( 'label', 'num' ) );
					$cols[] = array(
						'key'   => (string) $key,
						'label' => (string) $kv['label'],
						'num'   => ! empty( $kv['num'] ),
					);
				} else {
					$cols[] = array(
						'key'   => (string) $key,
						'label' => (string) $col,
						'num'   => false,
					);
				}
			}
			$class = self::classes( array( 'tbl', self::extra_class( $args ) ) );
			$html  = '<table class="' . esc_attr( $class ) . '"' . self::extra_attrs( $args ) . '>';
			if ( isset( $args['caption'] ) && '' !== (string) $args['caption'] ) {
				$cap   = empty( $args['caption_visible'] ) ? ' class="screen-reader-text"' : '';
				$html .= '<caption' . $cap . '>' . esc_html( (string) $args['caption'] ) . '</caption>';
			}
			$html .= '<thead><tr>';
			foreach ( $cols as $col ) {
				$html .= $col['num']
					? '<th scope="col" class="num">' . esc_html( $col['label'] ) . '</th>'
					: '<th scope="col">' . esc_html( $col['label'] ) . '</th>';
			}
			$html .= '</tr></thead><tbody>';
			foreach ( $rows as $row ) {
				$row   = is_array( $row ) ? $row : array();
				$html .= '<tr>';
				foreach ( $cols as $col ) {
					$cell = array_key_exists( $col['key'], $row ) ? $row[ $col['key'] ] : '';
					if ( is_array( $cell ) && isset( $cell['html'] ) ) {
						// Rich cell (0.7): pills, buttons, row action forms. kses control keeps form controls.
						$cell  = self::kses( (string) $cell['html'], 'control' );
						$html .= $col['num']
							? '<td class="num">' . $cell . '</td>'
							: '<td>' . $cell . '</td>';
						continue;
					}
					$cell  = is_scalar( $cell ) ? (string) $cell : '';
					$html .= $col['num']
						? '<td class="num">' . esc_html( $cell ) . '</td>'
						: '<td>' . esc_html( $cell ) . '</td>';
				}
				$html .= '</tr>';
			}
			return $html . '</tbody></table>';
		}

		/**
		 * Echo a WP_List_Table inside the kit shell (Track 0.4-list · D-22).
		 *
		 * Does not rewrite WP_List_Table. Skin only.
		 *
		 * @param object $table Table with display().
		 * @param array  $args  Arguments.
		 */
		public static function list_table( $table, $args = array() ) {
			self::emit( self::list_table_html( $table, $args ) );
		}

		/**
		 * Return WP_List_Table wrap markup.
		 *
		 * Render views() + search_box() + display() inside one GET form with hidden page.
		 * Pagination / bulk / sort / screen options stay WP core.
		 *
		 * @param object $table Table implementing display() (WP_List_Table).
		 * @param array  $args {
		 *     Arguments for the list-table wrapper.
		 *     @type bool   $search       Default true.
		 *     @type bool   $views        Default true.
		 *     @type string $page         Hidden input name=page.
		 *     @type string $search_label search_box() text.
		 *     @type string $search_id    search_box() input id.
		 *     @type array  $hidden       Extra hidden GET fields.
		 *     @type string $class        Extra classes on .list-table.
		 *     @type array  $attrs        Extra attributes.
		 * }
		 * @return string
		 */
		public static function list_table_html( $table, $args = array() ) {
			$args = self::args( $args, 'Wenpai_Admin_UI::list_table' );
			if ( ! is_object( $table ) || ! method_exists( $table, 'display' ) ) {
				self::doing_it_wrong( 'table must implement display() (WP_List_Table). Do not rewrite List Table.', 'Wenpai_Admin_UI::list_table' );
				return '';
			}
			$search = ! isset( $args['search'] ) || $args['search'];
			$views  = ! isset( $args['views'] ) || $args['views'];
			$page   = isset( $args['page'] ) ? (string) $args['page'] : '';
			$class  = self::classes( array( 'list-table', self::extra_class( $args ) ) );
			ob_start();
			echo '<div class="' . esc_attr( $class ) . '"' . self::extra_attrs( $args ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- class and attributes are escaped by their helpers.
			echo '<form method="get">';
			if ( '' !== $page ) {
				echo '<input type="hidden" name="page" value="' . esc_attr( $page ) . '" />';
			}
			if ( isset( $args['hidden'] ) && is_array( $args['hidden'] ) ) {
				foreach ( $args['hidden'] as $hk => $hv ) {
					if ( ! is_scalar( $hv ) ) {
						continue;
					}
					echo '<input type="hidden" name="' . esc_attr( (string) $hk ) . '" value="' . esc_attr( (string) $hv ) . '" />';
				}
			}
			if ( $views && method_exists( $table, 'views' ) ) {
				$table->views();
			}
			if ( $search && method_exists( $table, 'search_box' ) ) {
				$label = isset( $args['search_label'] ) ? (string) $args['search_label'] : '搜索';
				$sid   = isset( $args['search_id'] ) ? (string) $args['search_id'] : 'wenpai-list';
				$table->search_box( $label, $sid );
			}
			$table->display();
			echo '</form></div>';
			return ob_get_clean();
		}

		/**
		 * Echo a lightweight list filter bar (0.7).
		 *
		 * @param array $args Arguments.
		 */
		public static function list_bar( $args = array() ) {
			self::emit( self::list_bar_html( $args ) );
		}

		/**
		 * Return list filter bar markup: a GET form with filter selects, hidden
		 * params and an optional count. Kit JS submits the form on select change;
		 * a noscript submit button covers no-JS.
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type array  $filters array of array( name, label, options, current?, attrs? ).
		 *                           options: value => label map, or a list of
		 *                           array( value, label ) / plain strings.
		 *     @type array  $hidden  Extra hidden GET fields (name => scalar value).
		 *     @type string $page    Hidden input name=page.
		 *     @type string $action  Form action URL (default: current URL).
		 *     @type string $count   Count text, e.g. 「5 条」.
		 *     @type string $count_html Count HTML (kses post), wins over $count.
		 *     @type string $submit_label noscript submit label. Default 「筛选」.
		 *     @type string $class   Extra classes on the form.
		 *     @type array  $attrs   Extra attributes on the form.
		 * }
		 * @return string
		 */
		public static function list_bar_html( $args = array() ) {
			$args    = self::args( $args, 'Wenpai_Admin_UI::list_bar' );
			$filters = isset( $args['filters'] ) && is_array( $args['filters'] ) ? $args['filters'] : array();
			$hidden  = isset( $args['hidden'] ) && is_array( $args['hidden'] ) ? $args['hidden'] : array();
			$page    = isset( $args['page'] ) ? (string) $args['page'] : '';
			$action  = isset( $args['action'] ) ? (string) $args['action'] : '';
			$submit  = isset( $args['submit_label'] ) && '' !== (string) $args['submit_label'] ? (string) $args['submit_label'] : '筛选';
			$class   = self::classes( array( 'wenpai-list-bar', self::extra_class( $args ) ) );
			$html    = '<form class="' . esc_attr( $class ) . '" method="get" action="' . esc_url( $action ) . '"' . self::extra_attrs( $args ) . '>';
			if ( '' !== $page ) {
				$html .= '<input type="hidden" name="page" value="' . esc_attr( $page ) . '" />';
			}
			foreach ( $hidden as $hk => $hv ) {
				if ( is_scalar( $hv ) ) {
					$html .= '<input type="hidden" name="' . esc_attr( (string) $hk ) . '" value="' . esc_attr( (string) $hv ) . '" />';
				}
			}
			foreach ( $filters as $filter ) {
				if ( ! is_array( $filter ) || empty( $filter['name'] ) ) {
					continue;
				}
				$name    = (string) $filter['name'];
				$label   = isset( $filter['label'] ) ? (string) $filter['label'] : $name;
				$options = isset( $filter['options'] ) && is_array( $filter['options'] ) ? $filter['options'] : array();
				$current = isset( $filter['current'] ) ? (string) $filter['current'] : '';
				$fattrs  = isset( $filter['attrs'] ) && is_array( $filter['attrs'] ) ? $filter['attrs'] : array();
				$sattrs  = '';
				foreach ( $fattrs as $an => $av ) {
					$an = strtolower( (string) $an );
					if ( 0 === strpos( $an, 'data-' ) || 0 === strpos( $an, 'aria-' ) ) {
						$sattrs .= ' ' . $an . '="' . esc_attr( is_scalar( $av ) ? (string) $av : '' ) . '"';
					}
				}
				$html .= '<span class="wenpai-filter">'
					. '<label class="screen-reader-text" for="' . esc_attr( 'wenpai-filter-' . $name ) . '">' . esc_html( $label ) . '</label>'
					. '<select id="' . esc_attr( 'wenpai-filter-' . $name ) . '" name="' . esc_attr( $name ) . '"' . $sattrs . '>';
				foreach ( $options as $ok => $ol ) {
					$pair  = is_array( $ol )
						? self::item_kv( $ol, array( 'value', 'label' ) )
						: array(
							'value' => $ok,
							'label' => $ol,
						);
					$oval  = is_scalar( $pair['value'] ) ? (string) $pair['value'] : '';
					$html .= '<option value="' . esc_attr( $oval ) . '"' . selected( $current, $oval, false ) . '>' . esc_html( is_scalar( $pair['label'] ) ? (string) $pair['label'] : $oval ) . '</option>';
				}
				$html .= '</select></span>';
			}
			if ( isset( $args['count_html'] ) && '' !== (string) $args['count_html'] ) {
				$html .= '<span class="wenpai-list-bar__count">' . self::kses( (string) $args['count_html'], 'post' ) . '</span>';
			} elseif ( isset( $args['count'] ) && '' !== (string) $args['count'] ) {
				$html .= '<span class="wenpai-list-bar__count">' . esc_html( (string) $args['count'] ) . '</span>';
			}
			$html .= '<noscript><button type="submit" class="btn btn-ghost">' . esc_html( $submit ) . '</button></noscript>';
			return $html . '</form>';
		}

		/**
		 * Echo a table row-actions cluster (0.7).
		 *
		 * @param array $args Arguments.
		 */
		public static function row_actions( $args = array() ) {
			self::emit( self::row_actions_html( $args ) );
		}

		/**
		 * Return row-actions cluster markup for a table cell.
		 *
		 * Each item is a button_html() argument array, an array with 'html'
		 * (kses control: forms and inputs allowed), or a raw HTML string
		 * (kses control).
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type array $items Action items.
		 *     @type string $class Extra classes.
		 *     @type array  $attrs Extra attributes.
		 * }
		 * @return string
		 */
		public static function row_actions_html( $args = array() ) {
			$args  = self::args( $args, 'Wenpai_Admin_UI::row_actions' );
			$items = isset( $args['items'] ) && is_array( $args['items'] ) ? $args['items'] : array();
			$html  = '';
			foreach ( $items as $item ) {
				if ( is_string( $item ) ) {
					$html .= self::kses( $item, 'control' );
				} elseif ( is_array( $item ) ) {
					if ( isset( $item['html'] ) ) {
						$html .= self::kses( (string) $item['html'], 'control' );
					} else {
						$html .= self::button_html( $item );
					}
				}
			}
			if ( '' === $html ) {
				return '';
			}
			$class = self::classes( array( 'wenpai-row-actions', self::extra_class( $args ) ) );
			return '<div class="' . esc_attr( $class ) . '"' . self::extra_attrs( $args ) . '>' . $html . '</div>';
		}

		/**
		 * Echo a suggest dropdown box (0.7).
		 *
		 * @param array $args Arguments.
		 */
		public static function suggest_out( $args = array() ) {
			self::emit( self::suggest_out_html( $args ) );
		}

		/**
		 * Return suggest dropdown markup. The product owns the input and the
		 * fetch; this is the result slot. Kit JS moves .is-active with
		 * ArrowUp/ArrowDown and activates the item on Enter.
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type array  $items array of array( label, meta?, html?, active? ).
		 *                        'html' items pass kses control.
		 *     @type string $empty Empty-state text (shown when no items).
		 *     @type string $empty_html Empty-state HTML (kses post), wins over $empty.
		 *     @type bool   $hidden Start hidden (default false).
		 *     @type string $id    Element id (for aria-controls).
		 *     @type string $class Extra classes.
		 *     @type array  $attrs Extra attributes.
		 * }
		 * @return string
		 */
		public static function suggest_out_html( $args = array() ) {
			$args   = self::args( $args, 'Wenpai_Admin_UI::suggest_out' );
			$items  = isset( $args['items'] ) && is_array( $args['items'] ) ? $args['items'] : array();
			$hid    = empty( $args['hidden'] ) ? '' : ' hidden';
			$idattr = self::id_attr( $args, 'id' );
			$class  = self::classes( array( 'wenpai-suggest__out', self::extra_class( $args ) ) );
			$html   = '<div class="' . esc_attr( $class ) . '"' . $idattr . $hid . ' aria-live="polite"' . self::extra_attrs( $args, array( 'id' ) ) . '>';
			$has    = false;
			$html  .= '<ul class="wenpai-suggest__list">';
			foreach ( $items as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$has    = true;
				$active = ! empty( $item['active'] ) ? ' class="is-active"' : '';
				$html  .= '<li' . $active . '>';
				if ( isset( $item['html'] ) ) {
					$html .= self::kses( (string) $item['html'], 'control' );
				} else {
					$label = isset( $item['label'] ) ? (string) $item['label'] : '';
					$meta  = isset( $item['meta'] ) ? (string) $item['meta'] : '';
					$html .= '<button type="button" class="wenpai-suggest__item">' . esc_html( $label );
					if ( '' !== $meta ) {
						$html .= '<span class="wenpai-suggest__meta">' . esc_html( $meta ) . '</span>';
					}
					$html .= '</button>';
				}
				$html .= '</li>';
			}
			$html .= '</ul>';
			if ( ! $has ) {
				if ( isset( $args['empty_html'] ) && '' !== (string) $args['empty_html'] ) {
					$html .= '<p class="wenpai-suggest__empty">' . self::kses( (string) $args['empty_html'], 'post' ) . '</p>';
				} elseif ( isset( $args['empty'] ) && '' !== (string) $args['empty'] ) {
					$html .= '<p class="wenpai-suggest__empty">' . esc_html( (string) $args['empty'] ) . '</p>';
				}
			}
			return $html . '</div>';
		}

		/**
		 * Echo a preview output block (0.7).
		 *
		 * @param array $args Arguments.
		 */
		public static function preview( $args = array() ) {
			self::emit( self::preview_html( $args ) );
		}

		/**
		 * Return preview output markup: key/value rows (or a single text/html line) inside a state frame.
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type string $state '', 'ok' or 'bad'.
		 *     @type array  $rows  array of array( k, v, v_html? ). v_html is kses post.
		 *     @type string $text  Single-line content (escaped). Wins over $rows.
		 *     @type string $html  Single-line content, kses post. Wins over $rows.
		 *     @type string $variant '', 'line' (dashed frame, no fill; for single-value previews).
		 *     @type string $class Extra classes.
		 *     @type array  $attrs Extra attributes.
		 * }
		 * @return string
		 */
		public static function preview_html( $args = array() ) {
			$args  = self::args( $args, 'Wenpai_Admin_UI::preview' );
			$state = isset( $args['state'] ) ? (string) $args['state'] : '';
			if ( ! in_array( $state, array( '', 'ok', 'bad' ), true ) ) {
				self::doing_it_wrong( 'preview state must be ok or bad.', 'Wenpai_Admin_UI::preview' );
				$state = '';
			}
			$rows    = isset( $args['rows'] ) && is_array( $args['rows'] ) ? $args['rows'] : array();
			$variant = isset( $args['variant'] ) ? (string) $args['variant'] : '';
			if ( ! in_array( $variant, array( '', 'line' ), true ) ) {
				self::doing_it_wrong( 'preview variant must be line.', 'Wenpai_Admin_UI::preview' );
				$variant = '';
			}
			$class = self::classes( array( 'wenpai-preview', 'line' === $variant ? 'wenpai-preview--line' : '', '' !== $state ? 'is-' . $state : '', self::extra_class( $args ) ) );
			$html  = '<div class="' . esc_attr( $class ) . '" aria-live="polite"' . self::extra_attrs( $args ) . '>';
			if ( isset( $args['html'] ) && '' !== (string) $args['html'] ) {
				$html .= '<p class="wenpai-preview__text">' . self::kses( (string) $args['html'], 'post' ) . '</p>';
			} elseif ( isset( $args['text'] ) && '' !== (string) $args['text'] ) {
				$html .= '<p class="wenpai-preview__text">' . esc_html( (string) $args['text'] ) . '</p>';
			} else {
				foreach ( $rows as $row ) {
					if ( ! is_array( $row ) ) {
						continue;
					}
					$kv    = self::item_kv( $row, array( 'k', 'v' ) );
					$html .= '<div class="wenpai-preview__row"><span class="wenpai-preview__k">' . esc_html( (string) $kv['k'] ) . '</span><span class="wenpai-preview__v">';
					if ( isset( $row['v_html'] ) && '' !== (string) $row['v_html'] ) {
						$html .= self::kses( (string) $row['v_html'], 'post' );
					} else {
						$html .= esc_html( (string) $kv['v'] );
					}
					$html .= '</span></div>';
				}
			}
			return $html . '</div>';
		}

		/**
		 * Echo a help page skeleton (0.7).
		 *
		 * @param array $args Arguments.
		 */
		public static function help( $args = array() ) {
			self::emit( self::help_html( $args ) );
		}

		/**
		 * Return help page markup: titled groups of numbered steps plus links.
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type array  $groups array of array( title, numbered?, steps ).
		 *                        steps: array of array( title, body?, body_html?, no? ).
		 *                        Steps auto-number inside a numbered group;
		 *                        'no' overrides, numbered=false prints 「·」.
		 *                        Optional group keys: 'icon' / 'icon_html' (tile before
		 *                        the title, as tile()), 'desc' (plain subtitle),
		 *                        'collapsible' (group becomes details, title row is the
		 *                        summary and ends with the kit chevron, D-31), 'open'
		 *                        (start expanded; collapsible only).
		 *     @type array  $links  array of array( label, href ) or strings (label only).
		 *     @type string $class  Extra classes.
		 *     @type array  $attrs  Extra attributes.
		 * }
		 * @return string
		 */
		public static function help_html( $args = array() ) {
			$args   = self::args( $args, 'Wenpai_Admin_UI::help' );
			$groups = isset( $args['groups'] ) && is_array( $args['groups'] ) ? $args['groups'] : array();
			$class  = self::classes( array( 'wenpai-help', self::extra_class( $args ) ) );
			$html   = '<div class="' . esc_attr( $class ) . '"' . self::extra_attrs( $args ) . '>';
			foreach ( $groups as $group ) {
				if ( ! is_array( $group ) ) {
					continue;
				}
				$title    = isset( $group['title'] ) ? (string) $group['title'] : '';
				$numbered = ! isset( $group['numbered'] ) || $group['numbered'];
				$steps    = isset( $group['steps'] ) && is_array( $group['steps'] ) ? $group['steps'] : array();
				$fold     = ! empty( $group['collapsible'] );
				$icon     = ( isset( $group['icon'] ) && '' !== (string) $group['icon'] ) || ( isset( $group['icon_html'] ) && '' !== (string) $group['icon_html'] );
				$desc     = isset( $group['desc'] ) ? (string) $group['desc'] : '';
				if ( $fold ) {
					$html .= '<details class="wenpai-help__group is-fold"' . ( empty( $group['open'] ) ? '' : ' open' ) . '><summary class="wenpai-help__head">';
				} else {
					$html .= '<div class="wenpai-help__group">';
					if ( $icon || '' !== $desc ) {
						$html .= '<div class="wenpai-help__head">';
					}
				}
				if ( $icon ) {
					$html .= self::tile_html(
						array(
							'icon'      => isset( $group['icon'] ) ? $group['icon'] : '',
							'icon_html' => isset( $group['icon_html'] ) ? $group['icon_html'] : '',
						)
					);
				}
				if ( '' !== $title ) {
					$html .= '<h3>' . esc_html( $title ) . '</h3>';
				}
				if ( '' !== $desc ) {
					$html .= '<span class="wenpai-help__desc">' . esc_html( $desc ) . '</span>';
				}
				if ( $fold ) {
					if ( class_exists( 'Wenpai_Admin_Icons', false ) && Wenpai_Admin_Icons::has( 'chevron-down' ) ) {
						$html .= Wenpai_Admin_Icons::svg( 'chevron-down', 'wenpai-ico wenpai-fold__chevron' );
					}
					$html .= '</summary><div class="wenpai-help__steps">';
				} elseif ( $icon || '' !== $desc ) {
					$html .= '</div>';
				}
				$i = 0;
				foreach ( $steps as $step ) {
					if ( ! is_array( $step ) ) {
						continue;
					}
					++$i;
					$no = $numbered ? (string) $i : '·';
					if ( isset( $step['no'] ) && '' !== (string) $step['no'] ) {
						$no = (string) $step['no'];
					}
					$html .= '<div class="wenpai-help__step"><span class="wenpai-help__no">' . esc_html( $no ) . '</span><div class="wenpai-help__body">';
					if ( isset( $step['title'] ) && '' !== (string) $step['title'] ) {
						$html .= '<h4>' . esc_html( (string) $step['title'] ) . '</h4>';
					}
					if ( isset( $step['body_html'] ) && '' !== (string) $step['body_html'] ) {
						$html .= self::kses( (string) $step['body_html'], 'post' );
					} elseif ( isset( $step['body'] ) && '' !== (string) $step['body'] ) {
						$html .= '<p>' . esc_html( (string) $step['body'] ) . '</p>';
					}
					$html .= '</div></div>';
				}
				$html .= $fold ? '</div></details>' : '</div>';
			}
			$links = isset( $args['links'] ) && is_array( $args['links'] ) ? $args['links'] : array();
			if ( array() !== $links ) {
				$html .= '<div class="wenpai-help__links">';
				foreach ( $links as $link ) {
					if ( is_string( $link ) ) {
						$html .= '<span>' . esc_html( $link ) . '</span>';
						continue;
					}
					if ( ! is_array( $link ) || empty( $link['href'] ) ) {
						continue;
					}
					$html .= '<a href="' . esc_url( (string) $link['href'] ) . '">' . esc_html( isset( $link['label'] ) ? (string) $link['label'] : (string) $link['href'] ) . '</a>';
				}
				$html .= '</div>';
			}
			return $html . '</div>';
		}

		/**
		 * Echo a copy-to-clipboard button (0.8).
		 *
		 * @param array $args Arguments.
		 */
		public static function copy_button( $args = array() ) {
			self::emit( self::copy_button_html( $args ) );
		}

		/**
		 * Return copy button markup. Kit JS handles the click: it writes
		 * data-wenpai-copy-text verbatim, or reads textContent/value from the
		 * data-wenpai-copy-target selector. A target starting with "self:"
		 * resolves inside the closest .wenpai-code ancestor (code_block uses
		 * "self:code").
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type string $text       Literal text to copy (data-wenpai-copy-text).
		 *     @type string $target     CSS selector to copy from (data-wenpai-copy-target).
		 *                              text and target together → doing_it_wrong, text wins.
		 *     @type string $label      Button text and data-label. Default 「复制」.
		 *     @type string $done_label data-done-label while .is-done. Default 「已复制」.
		 *     @type string $variant    primary|secondary|ghost|danger|'' (default secondary), or
		 *                              text: a token chip (no .btn) whose label defaults to
		 *                              $text, plus a screen-reader hint (strings copy_hint).
		 *     @type array  $strings    Optional copy_hint override.
		 *     @type string $class      Extra classes.
		 *     @type array  $attrs      Extra attributes.
		 * }
		 * @return string
		 */
		public static function copy_button_html( $args = array() ) {
			$args   = self::args( $args, 'Wenpai_Admin_UI::copy_button' );
			$text   = isset( $args['text'] ) ? (string) $args['text'] : '';
			$target = isset( $args['target'] ) ? trim( (string) $args['target'] ) : '';
			if ( '' !== $text && '' !== $target ) {
				self::doing_it_wrong( 'copy_button text and target cannot be combined; text wins.', 'Wenpai_Admin_UI::copy_button' );
				$target = '';
			}
			if ( '' === $text && '' === $target ) {
				self::doing_it_wrong( 'copy_button needs text or target.', 'Wenpai_Admin_UI::copy_button' );
			}
			$variant = isset( $args['variant'] ) ? (string) $args['variant'] : '';
			if ( '' === $variant ) {
				$variant = 'secondary';
			}
			$token = 'text' === $variant;
			if ( ! $token && ! in_array( $variant, self::$button_variants, true ) ) {
				self::doing_it_wrong( sprintf( 'Invalid button variant 「%s」.', $variant ), 'Wenpai_Admin_UI::copy_button' );
				$variant = 'secondary';
			}
			$label = isset( $args['label'] ) && '' !== (string) $args['label'] ? (string) $args['label'] : '复制';
			if ( $token && ( ! isset( $args['label'] ) || '' === (string) $args['label'] ) && '' !== $text ) {
				$label = $text;
			}
			$done  = isset( $args['done_label'] ) ? (string) $args['done_label'] : '已复制';
			$class = $token
				? self::classes( array( 'wenpai-copy', 'wenpai-copy--text', self::extra_class( $args ) ) )
				: self::classes( array( 'btn', 'btn-' . $variant, 'wenpai-copy', self::extra_class( $args ) ) );
			$html  = '<button type="button" class="' . esc_attr( $class ) . '"';
			if ( '' !== $text ) {
				$html .= ' data-wenpai-copy-text="' . esc_attr( $text ) . '"';
			} elseif ( '' !== $target ) {
				$html .= ' data-wenpai-copy-target="' . esc_attr( $target ) . '"';
			}
			$html .= ' data-label="' . esc_attr( $label ) . '" data-done-label="' . esc_attr( $done ) . '"';
			$html .= self::extra_attrs( $args ) . '>' . esc_html( $label );
			if ( $token ) {
				$html .= '<span class="screen-reader-text">' . esc_html( self::ui_string( $args, 'copy_hint', '（点击复制）' ) ) . '</span>';
			}
			return $html . '</button>';
		}

		/**
		 * Echo a code block with optional language tag and copy button (0.8).
		 *
		 * @param array $args Arguments.
		 */
		public static function code_block( $args = array() ) {
			self::emit( self::code_block_html( $args ) );
		}

		/**
		 * Return code block markup.
		 *
		 * The code text is esc_html'd as a whole: it is code content, not HTML,
		 * so kses does not apply. lang feeds a language-{lang} class (sanitized)
		 * and the bar label.
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type string $code       Code content (required; missing → doing_it_wrong + '').
		 *     @type string $lang       Language name, e.g. php. Adds .wenpai-code__lang
		 *                              and class="language-{lang}" on <code>.
		 *     @type bool   $copy       Add a copy_button targeting "self:code". Default true.
		 *     @type int    $max_height Max height in px; adds inline max-height + overflow.
		 *     @type string $class       Extra classes on .wenpai-code.
		 *     @type array  $attrs       Extra attributes on .wenpai-code.
		 * }
		 * @return string
		 */
		public static function code_block_html( $args = array() ) {
			$args = self::args( $args, 'Wenpai_Admin_UI::code_block' );
			if ( ! array_key_exists( 'code', $args ) ) {
				self::doing_it_wrong( 'code_block needs code.', 'Wenpai_Admin_UI::code_block' );
				return '';
			}
			$lang = isset( $args['lang'] ) ? (string) $args['lang'] : '';
			$copy = ! array_key_exists( 'copy', $args ) || $args['copy'];
			$bar  = '';
			if ( '' !== $lang || $copy ) {
				$bar = '<div class="wenpai-code__bar"><span class="wenpai-code__lang">' . esc_html( $lang ) . '</span>';
				if ( $copy ) {
					$bar .= self::copy_button_html(
						array(
							'target'  => 'self:code',
							'variant' => 'ghost',
						)
					);
				}
				$bar .= '</div>';
			}
			$code_attr = '';
			if ( '' !== $lang ) {
				$code_attr = ' class="language-' . esc_attr( sanitize_html_class( $lang ) ) . '"';
			}
			$wrap = '<div class="' . esc_attr( self::classes( array( 'wenpai-code', self::extra_class( $args ) ) ) ) . '"';
			if ( isset( $args['max_height'] ) && (float) $args['max_height'] > 0 ) {
				$wrap .= ' style="max-height:' . esc_attr( (string) (int) $args['max_height'] ) . 'px;overflow:auto"';
			}
			$wrap .= self::extra_attrs( $args ) . '>';
			return $wrap . $bar . '<pre><code' . $code_attr . '>' . esc_html( (string) $args['code'] ) . '</code></pre></div>';
		}

		/**
		 * Echo a one-line code value with a copy button (0.9).
		 *
		 * For short inline values (webhook URL, key, command). Multi-line or
		 * language-tagged content belongs to code_block.
		 *
		 * @param array $args Arguments.
		 */
		public static function code_inline( $args = array() ) {
			self::emit( self::code_inline_html( $args ) );
		}

		/**
		 * Return one-line code + copy markup.
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type string $code    Code content (required; missing → doing_it_wrong + '').
		 *     @type bool   $copy    Add a ghost copy_button with the literal text. Default true.
		 *     @type array  $button  Extra copy_button args (label, done_label, variant…);
		 *                           text is always the code value, variant defaults ghost.
		 *     @type string $class   Extra classes on the .wenpai-code-row wrapper.
		 *     @type array  $attrs   Extra attributes on the wrapper.
		 * }
		 * @return string
		 */
		public static function code_inline_html( $args = array() ) {
			$args = self::args( $args, 'Wenpai_Admin_UI::code_inline' );
			if ( ! array_key_exists( 'code', $args ) ) {
				self::doing_it_wrong( 'code_inline needs code.', 'Wenpai_Admin_UI::code_inline' );
				return '';
			}
			$code = (string) $args['code'];
			$html = '<span class="' . esc_attr( self::classes( array( 'wenpai-code-row', self::extra_class( $args ) ) ) ) . '"' . self::extra_attrs( $args ) . '>'
				. '<code class="wenpai-code">' . esc_html( $code ) . '</code>';
			if ( ! array_key_exists( 'copy', $args ) || $args['copy'] ) {
				$btn         = isset( $args['button'] ) && is_array( $args['button'] ) ? $args['button'] : array();
				$btn         = array_merge( array( 'variant' => 'ghost' ), $btn );
				$btn['text'] = $code;
				$html       .= self::copy_button_html( $btn );
			}
			return $html . '</span>';
		}

		/**
		 * Echo a status list (0.8).
		 *
		 * @param array $args Arguments.
		 */
		public static function status_list( $args = array() ) {
			self::emit( self::status_list_html( $args ) );
		}

		/**
		 * Return status list markup: a <dl> of label/value rows with an
		 * optional state chip (.wenpai-status-list__state.is-{state}).
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type array $items array of array(
		 *                       'label'       => row label (dt),
		 *                       'value'       => row value (dd, escaped),
		 *                       'value_html'  => row value HTML (kses post), wins over value,
		 *                       'state'       => ''|ok|warn|bad|accent → is-{state} chip,
		 *                       'state_label' => chip text (default: the state token),
		 *                       'desc'        => optional .meta note inside dd,
		 *                     ).
		 *     @type string $class Extra classes on dl.
		 *     @type array  $attrs Extra attributes on dl.
		 * }
		 * @return string
		 */
		public static function status_list_html( $args = array() ) {
			$args  = self::args( $args, 'Wenpai_Admin_UI::status_list' );
			$items = isset( $args['items'] ) && is_array( $args['items'] ) ? $args['items'] : array();
			$html  = '<dl class="' . esc_attr( self::classes( array( 'wenpai-status-list', self::extra_class( $args ) ) ) ) . '"' . self::extra_attrs( $args ) . '>';
			foreach ( $items as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$kv    = self::item_kv( $item, array( 'label', 'value', 'state' ) );
				$state = self::state( $kv['state'], array( '', 'ok', 'warn', 'bad', 'accent' ), '', 'Wenpai_Admin_UI::status_list' );
				$html .= '<div class="wenpai-status-list__row"><dt>' . esc_html( (string) $kv['label'] ) . '</dt><dd>';
				if ( isset( $item['value_html'] ) && '' !== (string) $item['value_html'] ) {
					$html .= self::kses( (string) $item['value_html'], 'post' );
				} else {
					$html .= esc_html( (string) $kv['value'] );
				}
				if ( '' !== $state ) {
					$stext = isset( $item['state_label'] ) && '' !== (string) $item['state_label'] ? (string) $item['state_label'] : $state;
					$html .= ' <span class="wenpai-status-list__state is-' . esc_attr( $state ) . '">' . esc_html( $stext ) . '</span>';
				}
				if ( isset( $item['desc'] ) && '' !== (string) $item['desc'] ) {
					$html .= ' <span class="meta">' . esc_html( (string) $item['desc'] ) . '</span>';
				}
				$html .= '</dd></div>';
			}
			return $html . '</dl>';
		}

		/**
		 * Echo a license / plan card (0.8).
		 *
		 * @param array $args Arguments.
		 */
		public static function license_card( $args = array() ) {
			self::emit( self::license_card_html( $args ) );
		}

		/**
		 * Return license card markup.
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type string $title        Plan name (h3 in .wenpai-license__head).
		 *     @type string $status       ''|ok|warn|bad → .wenpai-license__state.is-{status}.
		 *     @type string $status_label Chip text (default: the status token).
		 *     @type string $pitch        Marketing line (.wenpai-license__pitch).
		 *     @type array  $meta         Rows: label => value map, or a list of
		 *                                array( 'label', 'value', 'value_html'? ).
		 *                                value_html is kses post.
		 *     @type array  $actions      button() argument arrays (.wenpai-license__actions).
		 *     @type string $class        Extra classes on .wenpai-license.
		 *     @type array  $attrs        Extra attributes on .wenpai-license.
		 * }
		 * @return string
		 */
		public static function license_card_html( $args = array() ) {
			$args   = self::args( $args, 'Wenpai_Admin_UI::license_card' );
			$title  = isset( $args['title'] ) ? (string) $args['title'] : '';
			$status = isset( $args['status'] ) ? (string) $args['status'] : '';
			if ( ! in_array( $status, array( '', 'ok', 'warn', 'bad' ), true ) ) {
				self::doing_it_wrong( sprintf( 'Invalid license status 「%s」.', $status ), 'Wenpai_Admin_UI::license_card' );
				$status = '';
			}
			$meta  = isset( $args['meta'] ) && is_array( $args['meta'] ) ? $args['meta'] : array();
			$class = self::classes( array( 'wenpai-license', self::extra_class( $args ) ) );
			$html  = '<div class="' . esc_attr( $class ) . '"' . self::extra_attrs( $args ) . '>';
			if ( '' !== $title || '' !== $status ) {
				$html .= '<div class="wenpai-license__head">';
				if ( '' !== $title ) {
					$html .= '<h3>' . esc_html( $title ) . '</h3>';
				}
				if ( '' !== $status ) {
					$stext = isset( $args['status_label'] ) && '' !== (string) $args['status_label'] ? (string) $args['status_label'] : $status;
					$html .= '<span class="wenpai-license__state is-' . esc_attr( $status ) . '">' . esc_html( $stext ) . '</span>';
				}
				$html .= '</div>';
			}
			if ( isset( $args['pitch'] ) && '' !== (string) $args['pitch'] ) {
				$html .= '<p class="wenpai-license__pitch">' . esc_html( (string) $args['pitch'] ) . '</p>';
			}
			if ( array() !== $meta ) {
				$html .= '<dl class="wenpai-license__meta">';
				foreach ( $meta as $mk => $mv ) {
					if ( is_array( $mv ) ) {
						$row = self::item_kv( $mv, array( 'label', 'value' ) );
						$lab = '' !== (string) $row['label'] ? (string) $row['label'] : (string) $mk;
						$val = isset( $mv['value_html'] ) && '' !== (string) $mv['value_html']
							? self::kses( (string) $mv['value_html'], 'post' )
							: esc_html( (string) $row['value'] );
					} else {
						$lab = (string) $mk;
						$val = esc_html( (string) $mv );
					}
					$html .= '<div><dt>' . esc_html( $lab ) . '</dt><dd>' . $val . '</dd></div>';
				}
				$html .= '</dl>';
			}
			$actions = isset( $args['actions'] ) && is_array( $args['actions'] ) ? $args['actions'] : array();
			$btns    = '';
			foreach ( $actions as $btn ) {
				if ( is_array( $btn ) ) {
					$btns .= self::button_html( $btn );
				}
			}
			if ( '' !== $btns ) {
				$html .= '<div class="wenpai-license__actions">' . $btns . '</div>';
			}
			return $html . '</div>';
		}

		/**
		 * Register a wp-admin dashboard widget (0.8).
		 *
		 * Wraps wp_add_dashboard_widget() on wp_dashboard_setup. The product's
		 * $callback prints the body; call dashboard_widget_html() inside it for
		 * the .wenpai-dash card. Repeated calls with the same $id are ignored
		 * (idempotent). Multisite: wp_dashboard_setup does not fire on the
		 * network dashboard (wp_network_dashboard_setup); products needing a
		 * network-admin widget hook that action themselves.
		 *
		 * @param string          $id       Widget id.
		 * @param string          $title    Widget title.
		 * @param callable        $callback Render callback.
		 * @param callable|string $args     Passed as wp_add_dashboard_widget()'s
		 *                                  fourth argument (control callback / args).
		 */
		public static function dashboard_widget( $id, $title, $callback, $args = array() ) {
			$id = (string) $id;
			if ( '' === $id ) {
				self::doing_it_wrong( 'dashboard_widget needs an id.', 'Wenpai_Admin_UI::dashboard_widget' );
				return;
			}
			if ( ! is_callable( $callback ) ) {
				self::doing_it_wrong( 'dashboard_widget callback must be callable.', 'Wenpai_Admin_UI::dashboard_widget' );
				return;
			}
			if ( isset( self::$dashboard_widgets[ $id ] ) ) {
				return;
			}
			self::$dashboard_widgets[ $id ] = true;
			add_action(
				'wp_dashboard_setup',
				function () use ( $id, $title, $callback, $args ) {
					if ( function_exists( 'wp_add_dashboard_widget' ) ) {
						wp_add_dashboard_widget( $id, $title, $callback, $args );
					}
				}
			);
		}

		/**
		 * Return dashboard widget body markup (.wenpai-dash card, 0.8).
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type string $title     Card title (h3 in .wenpai-dash__head).
		 *     @type string $desc      Description (.wenpai-dash__desc).
		 *     @type string $desc_html Description HTML (kses post), wins over desc.
		 *     @type array  $stats     array of array( 'num'|'value', 'label' ) →
		 *                             .wenpai-dash__stats > .wenpai-dash__stat (.n + .label).
		 *     @type string $body_html Body HTML (kses post).
		 *     @type string $class     Extra classes on .wenpai-dash.
		 *     @type array  $attrs     Extra attributes on .wenpai-dash.
		 * }
		 * @return string
		 */
		public static function dashboard_widget_html( $args = array() ) {
			$args  = self::args( $args, 'Wenpai_Admin_UI::dashboard_widget' );
			$title = isset( $args['title'] ) ? (string) $args['title'] : '';
			$desc  = '';
			if ( isset( $args['desc_html'] ) && '' !== (string) $args['desc_html'] ) {
				$desc = '<p class="wenpai-dash__desc">' . self::kses( (string) $args['desc_html'], 'post' ) . '</p>';
			} elseif ( isset( $args['desc'] ) && '' !== (string) $args['desc'] ) {
				$desc = '<p class="wenpai-dash__desc">' . esc_html( (string) $args['desc'] ) . '</p>';
			}
			$html = '<div class="' . esc_attr( self::classes( array( 'wenpai-native', 'wenpai-dash', self::extra_class( $args ) ) ) ) . '"' . self::extra_attrs( $args ) . '>';
			if ( '' !== $title || '' !== $desc ) {
				$html .= '<div class="wenpai-dash__head">';
				if ( '' !== $title ) {
					$html .= '<h3 class="wenpai-dash__title">' . esc_html( $title ) . '</h3>';
				}
				$html .= $desc . '</div>';
			}
			$stats = isset( $args['stats'] ) && is_array( $args['stats'] ) ? $args['stats'] : array();
			if ( array() !== $stats ) {
				$html .= '<div class="wenpai-dash__stats">';
				foreach ( $stats as $stat ) {
					if ( ! is_array( $stat ) ) {
						continue;
					}
					$kv    = self::item_kv( $stat, array( 'num', 'label' ) );
					$num   = '' !== (string) $kv['num'] ? $kv['num'] : ( isset( $stat['value'] ) ? $stat['value'] : ( isset( $stat[0] ) ? $stat[0] : '' ) );
					$lab   = '' !== (string) $kv['label'] ? $kv['label'] : ( isset( $stat['label'] ) ? $stat['label'] : ( isset( $stat[1] ) ? $stat[1] : '' ) );
					$html .= '<div class="wenpai-dash__stat"><span class="n">' . esc_html( (string) $num ) . '</span><span class="label">' . esc_html( (string) $lab ) . '</span></div>';
				}
				$html .= '</div>';
			}
			if ( isset( $args['body_html'] ) ) {
				$html .= self::kses( (string) $args['body_html'], 'post' );
			}
			return $html . '</div>';
		}

		/**
		 * Echo a quick-edit fieldset for WP inline edit (0.8).
		 *
		 * @param array $args Arguments.
		 */
		public static function quick_edit_box( $args = array() ) {
			self::emit( self::quick_edit_box_html( $args ) );
		}

		/**
		 * Return quick-edit fieldset markup.
		 *
		 * Hook usage: add_action( 'quick_edit_custom_box', … ) prints this inside
		 * the column div; WP core inlineEditPost shows/hides it. Populating the
		 * controls on row open is the product's job.
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type string $legend Fieldset legend (.inline-edit-heading).
		 *     @type array  $fields array of array( 'name', 'label', 'control_html' ).
		 *                           control_html is kses 'control' (form controls kept).
		 *                           name lands on the row as data-name for product JS.
		 * }
		 * @return string
		 */
		public static function quick_edit_box_html( $args = array() ) {
			$args   = self::args( $args, 'Wenpai_Admin_UI::quick_edit_box' );
			$legend = isset( $args['legend'] ) ? (string) $args['legend'] : '';
			$fields = isset( $args['fields'] ) && is_array( $args['fields'] ) ? $args['fields'] : array();
			$html   = '<fieldset class="wenpai-native inline-edit-col-inline wenpai-qe"><legend class="inline-edit-heading">' . esc_html( $legend ) . '</legend>';
			foreach ( $fields as $field ) {
				if ( ! is_array( $field ) ) {
					continue;
				}
				$kv    = self::item_kv( $field, array( 'name', 'label', 'control_html' ) );
				$ctl   = isset( $field['control_html'] ) ? self::kses( (string) $field['control_html'], 'control' ) : '';
				$html .= '<div class="wenpai-qe__row" data-name="' . esc_attr( (string) $kv['name'] ) . '"><label>' . esc_html( (string) $kv['label'] ) . '</label>' . $ctl . '</div>';
			}
			return $html . '</fieldset>';
		}

		/**
		 * Echo plugins.php-style inline action links (0.8).
		 *
		 * @param array $items Items array; see action_links_html().
		 */
		public static function action_links( $items ) {
			self::emit( self::action_links_html( $items ) );
		}

		/**
		 * Return inline action links joined by ' | ' (plugins.php row convention).
		 *
		 * Items whose capability the current user lacks are skipped silently.
		 *
		 * @param array $items array of array( 'label', 'href', 'capability'?, 'current'? ).
		 *                     capability empty → no check; current → aria-current="page".
		 * @return string
		 */
		public static function action_links_html( $items ) {
			if ( ! is_array( $items ) ) {
				self::doing_it_wrong( 'action_links needs an items array.', 'Wenpai_Admin_UI::action_links' );
				return '';
			}
			$links = array();
			foreach ( $items as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$kv = self::item_kv( $item, array( 'label', 'href', 'capability', 'current' ) );
				if ( '' !== (string) $kv['capability'] && function_exists( 'current_user_can' ) && ! current_user_can( (string) $kv['capability'] ) ) {
					continue;
				}
				$href = (string) $kv['href'];
				if ( '' === $href ) {
					continue;
				}
				$link = '<a href="' . esc_url( $href ) . '"';
				if ( ! empty( $kv['current'] ) ) {
					$link .= ' aria-current="page"';
				}
				$link   .= '>' . esc_html( (string) $kv['label'] ) . '</a>';
				$links[] = $link;
			}
			return implode( ' | ', $links );
		}

		/**
		 * Echo a fold list (page pattern).
		 *
		 * @param array $args Arguments.
		 */
		public static function fold_list( $args = array() ) {
			self::emit( self::fold_list_html( $args ) );
		}

		/**
		 * Return fold list markup: .wenpai-folds > details.wenpai-fold rows.
		 *
		 * One row per object of the same kind (providers, channels). Collapsed
		 * rows show title, desc and one status pill; the body holds the settings.
		 * The chevron is the kit's own (decisions.md D-31).
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type array  $items array of array(
		 *                        'title'     => row title,
		 *                        'desc'      => one-line summary under the title,
		 *                        'logo'      => one or two characters for the brand mark,
		 *                        'logo_html' => svg / img for the brand mark (kses post), wins over logo,
		 *                        'pill'      => array( 'label', 'tone' ) status pill (tone: ''|ok|warn|bad|mute),
		 *                        'body_html' => expanded content (kses control: post tags + form controls),
		 *                        'open'      => bool, start expanded,
		 *                        'id'        => id on the details element,
		 *                      ).
		 *     @type string $class Extra classes on .wenpai-folds.
		 *     @type array  $attrs Extra attributes on .wenpai-folds.
		 * }
		 * @return string
		 */
		public static function fold_list_html( $args = array() ) {
			$args    = self::args( $args, 'Wenpai_Admin_UI::fold_list' );
			$items   = isset( $args['items'] ) && is_array( $args['items'] ) ? $args['items'] : array();
			$chevron = '';
			if ( class_exists( 'Wenpai_Admin_Icons', false ) && Wenpai_Admin_Icons::has( 'chevron-down' ) ) {
				$chevron = Wenpai_Admin_Icons::svg( 'chevron-down', 'wenpai-ico wenpai-fold__chevron' );
			}
			$html = '<div class="' . esc_attr( self::classes( array( 'wenpai-folds', self::extra_class( $args ) ) ) ) . '"' . self::extra_attrs( $args ) . '>';
			foreach ( $items as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$html .= '<details class="wenpai-fold"' . self::id_attr( $item, 'id' ) . ( empty( $item['open'] ) ? '' : ' open' ) . '><summary>';
				if ( ( isset( $item['logo_html'] ) && '' !== (string) $item['logo_html'] ) || ( isset( $item['logo'] ) && '' !== (string) $item['logo'] ) ) {
					$html .= self::logo_html(
						array(
							'text' => isset( $item['logo'] ) ? (string) $item['logo'] : '',
							'html' => isset( $item['logo_html'] ) ? (string) $item['logo_html'] : '',
						)
					);
				} elseif ( ( isset( $item['icon_html'] ) && '' !== (string) $item['icon_html'] ) || ( isset( $item['icon'] ) && '' !== (string) $item['icon'] ) ) {
					$html .= self::tile_html(
						array(
							'icon'  => isset( $item['icon'] ) ? (string) $item['icon'] : '',
							'html'  => isset( $item['icon_html'] ) ? (string) $item['icon_html'] : '',
							'state' => isset( $item['state'] ) ? (string) $item['state'] : '',
						)
					);
				}
				$html .= '<span class="wenpai-fold__text"><span class="wenpai-fold__title">' . esc_html( isset( $item['title'] ) ? (string) $item['title'] : '' ) . '</span>';
				if ( isset( $item['desc'] ) && '' !== (string) $item['desc'] ) {
					$html .= '<span class="wenpai-fold__desc">' . esc_html( (string) $item['desc'] ) . '</span>';
				}
				$html .= '</span>';
				if ( isset( $item['pill'] ) && is_array( $item['pill'] ) ) {
					$html .= '<span class="wenpai-fold__side">' . self::pill_html( self::pill_args( $item['pill'] ) ) . '</span>';
				}
				$html .= $chevron . '</summary><div class="wenpai-fold__body">';
				if ( isset( $item['body_html'] ) ) {
					$html .= self::kses( (string) $item['body_html'], 'control' );
				}
				$html .= '</div></details>';
			}
			return $html . '</div>';
		}

		/**
		 * Echo a stat bar (page pattern).
		 *
		 * @param array $args Arguments.
		 */
		public static function statbar( $args = array() ) {
			self::emit( self::statbar_html( $args ) );
		}

		/**
		 * Return stat bar markup: dl.wenpai-statbar with three to five peer numbers.
		 *
		 * No trend line here; a number with a sparkline belongs to stat_area().
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type array  $items      array of array(
		 *                             'label'      => what the number is (dt),
		 *                             'value'      => the number, already formatted,
		 *                             'unit'       => optional unit beside the value,
		 *                             'desc'       => optional note under the number,
		 *                             'empty'      => bool, no data yet: empty_text replaces value and unit,
		 *                             'empty_text' => per-item override of the empty text,
		 *                           ).
		 *     @type string $empty_text Text for empty items. Default 「还没有数据」.
		 *     @type array  $strings    Override kit strings (statbar_empty).
		 *     @type string $class      Extra classes on dl.
		 *     @type array  $attrs      Extra attributes on dl.
		 * }
		 * @return string
		 */
		public static function statbar_html( $args = array() ) {
			$args  = self::args( $args, 'Wenpai_Admin_UI::statbar' );
			$items = isset( $args['items'] ) && is_array( $args['items'] ) ? $args['items'] : array();
			$none  = isset( $args['empty_text'] ) && '' !== (string) $args['empty_text'] ? (string) $args['empty_text'] : self::ui_string( $args, 'statbar_empty', '还没有数据' );
			$html  = '<dl class="' . esc_attr( self::classes( array( 'wenpai-statbar', self::extra_class( $args ) ) ) ) . '"' . self::extra_attrs( $args ) . '>';
			foreach ( $items as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$html     .= '<div class="wenpai-statbar__item"><dt>' . esc_html( isset( $item['label'] ) ? (string) $item['label'] : '' ) . '</dt>';
				$val_attrs = isset( $item['value_attrs'] ) && is_array( $item['value_attrs'] ) ? self::extra_attrs( array( 'attrs' => $item['value_attrs'] ) ) : '';
				if ( ! empty( $item['empty'] ) ) {
					$text  = isset( $item['empty_text'] ) && '' !== (string) $item['empty_text'] ? (string) $item['empty_text'] : $none;
					$html .= '<dd class="is-none"' . $val_attrs . '>' . esc_html( $text ) . '</dd>';
				} else {
					$html .= '<dd' . $val_attrs . '>' . esc_html( isset( $item['value'] ) ? (string) $item['value'] : '' );
					if ( isset( $item['unit'] ) && '' !== (string) $item['unit'] ) {
						$html .= ' <small>' . esc_html( (string) $item['unit'] ) . '</small>';
					}
					$html .= '</dd>';
				}
				if ( isset( $item['desc'] ) && '' !== (string) $item['desc'] ) {
					$html .= '<p>' . esc_html( (string) $item['desc'] ) . '</p>';
				}
				$html .= '</div>';
			}
			return $html . '</dl>';
		}

		/**
		 * Echo a meter list (page pattern).
		 *
		 * @param array $args Arguments.
		 */
		public static function meter( $args = array() ) {
			self::emit( self::meter_html( $args ) );
		}

		/**
		 * Return meter list markup: rows of name + proportion bar + value.
		 *
		 * The value text is always printed; the bar is decoration (aria-hidden).
		 * The bar width is data, so it is the one inline style the kit prints.
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type array  $items  array of array(
		 *                         'name'    => row name,
		 *                         'value'   => display text, e.g. 「92 分」,
		 *                         'percent' => bar width 0–100; out-of-range values are clamped,
		 *                         'top'     => bool, highlight the rank badge (ranked lists),
		 *                       ).
		 *     @type bool   $ranked True → ol with rank badges; false → ul without. Default false.
		 *     @type string $class  Extra classes on the list.
		 *     @type array  $attrs  Extra attributes on the list.
		 * }
		 * @return string
		 */
		public static function meter_html( $args = array() ) {
			$args   = self::args( $args, 'Wenpai_Admin_UI::meter' );
			$items  = isset( $args['items'] ) && is_array( $args['items'] ) ? $args['items'] : array();
			$ranked = ! empty( $args['ranked'] );
			$tag    = $ranked ? 'ol' : 'ul';
			$rank   = 0;
			$html   = '<' . $tag . ' class="' . esc_attr( self::classes( array( 'wenpai-meter', self::extra_class( $args ) ) ) ) . '" role="list"' . self::extra_attrs( $args, array( 'role' ) ) . '>';
			foreach ( $items as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				++$rank;
				$percent = isset( $item['percent'] ) && is_numeric( $item['percent'] ) ? (float) $item['percent'] : 0.0;
				$percent = max( 0.0, min( 100.0, $percent ) );
				$width   = rtrim( rtrim( sprintf( '%.2F', $percent ), '0' ), '.' );
				$html   .= '<li class="wenpai-meter__row' . ( $ranked && ! empty( $item['top'] ) ? ' is-top' : '' ) . '">';
				if ( $ranked ) {
					$html .= '<span class="wenpai-meter__rank">' . $rank . '</span>';
				}
				$html .= '<span class="wenpai-meter__name">' . esc_html( isset( $item['name'] ) ? (string) $item['name'] : '' ) . '</span>';
				$html .= '<span class="wenpai-meter__bar" aria-hidden="true"><i style="inline-size:' . esc_attr( $width ) . '%"></i></span>';
				$html .= '<span class="wenpai-meter__value">' . esc_html( isset( $item['value'] ) ? (string) $item['value'] : '' ) . '</span></li>';
			}
			return $html . '</' . $tag . '>';
		}

		/**
		 * Echo a sortable list (page pattern).
		 *
		 * @param array $args Arguments.
		 */
		public static function sortable( $args = array() ) {
			self::emit( self::sortable_html( $args ) );
		}

		/**
		 * Return sortable list markup: a hidden input carrying the order plus
		 * ol.wenpai-sortable with move-up / move-down buttons per item.
		 *
		 * The hidden input holds the item ids joined by commas, in list order.
		 * Kit JS swaps neighbours on click and rewrites the input, the index
		 * numbers and the first/last disabled states. Without JS the buttons do
		 * nothing and the form still submits the rendered order. No drag handle
		 * is printed: dragging is not implemented.
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type string $name    Name of the hidden order input (required).
		 *     @type string $id      Id of the hidden input. Default derived from name.
		 *     @type array  $items   array of array(
		 *                          'id'    => stable item id (no commas),
		 *                          'title' => item title,
		 *                          'desc'  => optional note under the title,
		 *                        ).
		 *     @type array  $strings Override kit strings (sortable_move_up,
		 *                          sortable_move_down, sortable_moved).
		 *     @type string $class   Extra classes on ol.
		 *     @type array  $attrs   Extra attributes on ol.
		 * }
		 * @return string
		 */
		public static function sortable_html( $args = array() ) {
			$args = self::args( $args, 'Wenpai_Admin_UI::sortable' );
			$name = isset( $args['name'] ) ? (string) $args['name'] : '';
			if ( '' === $name ) {
				self::doing_it_wrong( 'sortable needs name.', 'Wenpai_Admin_UI::sortable' );
				return '';
			}
			$items = array();
			foreach ( isset( $args['items'] ) && is_array( $args['items'] ) ? $args['items'] : array() as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$id = isset( $item['id'] ) ? (string) $item['id'] : '';
				if ( '' === $id || false !== strpos( $id, ',' ) ) {
					self::doing_it_wrong( 'sortable item id must be non-empty and contain no comma.', 'Wenpai_Admin_UI::sortable' );
					continue;
				}
				$items[ $id ] = $item;
			}
			$input_id = isset( $args['id'] ) && '' !== (string) $args['id'] ? (string) $args['id'] : 'wenpai-sortable-' . trim( (string) preg_replace( '/[^A-Za-z0-9_-]+/', '-', $name ), '-' );
			$up_label = self::ui_string( $args, 'sortable_move_up', '把 %s 上移' );
			$dn_label = self::ui_string( $args, 'sortable_move_down', '把 %s 下移' );
			$up_icon  = self::icon_svg( 'arrow-up' );
			$dn_icon  = self::icon_svg( 'arrow-down' );
			$last     = count( $items ) - 1;
			$index    = 0;
			$html     = '<input type="hidden" id="' . esc_attr( $input_id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( implode( ',', array_map( 'strval', array_keys( $items ) ) ) ) . '" />';
			$html    .= '<ol class="' . esc_attr( self::classes( array( 'wenpai-sortable', self::extra_class( $args ) ) ) ) . '" role="list" data-wenpai-sortable="' . esc_attr( $input_id ) . '"';
			$html    .= ' data-wenpai-sortable-moved="' . esc_attr( self::ui_string( $args, 'sortable_moved', '%1$s 已移到第 %2$d 位，共 %3$d 项' ) ) . '"';
			$html    .= self::extra_attrs( $args, array( 'role', 'data-wenpai-sortable', 'data-wenpai-sortable-moved' ) ) . '>';
			foreach ( $items as $id => $item ) {
				$title = isset( $item['title'] ) ? (string) $item['title'] : '';
				$html .= '<li class="wenpai-sortable__item" data-wenpai-sortable-item="' . esc_attr( (string) $id ) . '">';
				$html .= '<span class="wenpai-sortable__index">' . ( $index + 1 ) . '</span>';
				$html .= '<span class="wenpai-sortable__text"><span class="wenpai-sortable__title">' . esc_html( $title ) . '</span>';
				if ( isset( $item['desc'] ) && '' !== (string) $item['desc'] ) {
					$html .= '<span class="wenpai-sortable__desc">' . esc_html( (string) $item['desc'] ) . '</span>';
				}
				$html .= '</span><span class="wenpai-sortable__moves">';
				$html .= '<button type="button" class="btn btn-ghost btn-sm" data-wenpai-sortable-up aria-label="' . esc_attr( str_replace( '%s', $title, $up_label ) ) . '"' . ( 0 === $index ? ' disabled' : '' ) . '>' . $up_icon . '</button>';
				$html .= '<button type="button" class="btn btn-ghost btn-sm" data-wenpai-sortable-down aria-label="' . esc_attr( str_replace( '%s', $title, $dn_label ) ) . '"' . ( $last === $index ? ' disabled' : '' ) . '>' . $dn_icon . '</button>';
				$html .= '</span></li>';
				++$index;
			}
			return $html . '</ol>';
		}

		/**
		 * Echo a brand mark (page pattern).
		 *
		 * @param array $args Arguments.
		 */
		public static function logo( $args = array() ) {
			self::emit( self::logo_html( $args ) );
		}

		/**
		 * Return brand mark markup: span.wenpai-logo for a third-party vendor.
		 *
		 * It says who something is. Status belongs to tile().
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type string $text  One or two characters when there is no image.
		 *     @type string $html  svg or img (kses post), wins over text.
		 *     @type string $size  ''|sm|xs → 36 / 24 / 20 px.
		 *     @type string $class Extra classes.
		 *     @type array  $attrs Extra attributes.
		 * }
		 * @return string
		 */
		public static function logo_html( $args = array() ) {
			$args  = self::args( $args, 'Wenpai_Admin_UI::logo' );
			$size  = self::state( isset( $args['size'] ) ? $args['size'] : '', array( '', 'sm', 'xs' ), '', 'Wenpai_Admin_UI::logo' );
			$class = self::classes( array( 'wenpai-logo', '' !== $size ? 'wenpai-logo--' . $size : '', self::extra_class( $args ) ) );
			if ( isset( $args['html'] ) && '' !== (string) $args['html'] ) {
				$inner = self::kses( (string) $args['html'], 'post' );
			} else {
				$inner = esc_html( isset( $args['text'] ) ? (string) $args['text'] : '' );
			}
			return '<span class="' . esc_attr( $class ) . '"' . self::extra_attrs( $args ) . '>' . $inner . '</span>';
		}

		/**
		 * Echo a chart frame (page pattern).
		 *
		 * @param array $args Arguments.
		 */
		public static function chart_frame( $args = array() ) {
			self::emit( self::chart_frame_html( $args ) );
		}

		/**
		 * Return chart frame markup: figure.wenpai-chart with a caption, a
		 * fixed-height plot area, an empty state and an optional data table.
		 *
		 * The kit owns the frame only; the product draws the chart.
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type string $title      Caption (figcaption).
		 *     @type string $plot_html  The chart: inline svg or a canvas (kses chart).
		 *     @type string $empty_text When set, the empty state is printed and
		 *                              plot_html / table_html are not.
		 *     @type string $aria_label Text alternative; puts role="img" on the plot.
		 *     @type string $table_html Same data as a table (kses post), inside
		 *                              details.wenpai-chart__data.
		 *     @type array  $strings    Override kit strings (chart_data).
		 *     @type string $class      Extra classes on figure.
		 *     @type array  $attrs      Extra attributes on figure.
		 * }
		 * @return string
		 */
		public static function chart_frame_html( $args = array() ) {
			$args  = self::args( $args, 'Wenpai_Admin_UI::chart_frame' );
			$empty = isset( $args['empty_text'] ) ? (string) $args['empty_text'] : '';
			$html  = '<figure class="' . esc_attr( self::classes( array( 'wenpai-chart', self::extra_class( $args ) ) ) ) . '"' . self::extra_attrs( $args ) . '>';
			if ( isset( $args['title'] ) && '' !== (string) $args['title'] ) {
				$html .= '<figcaption>' . esc_html( (string) $args['title'] ) . '</figcaption>';
			}
			if ( '' !== $empty ) {
				return $html . '<div class="wenpai-chart__plot is-empty"><p>' . esc_html( $empty ) . '</p></div></figure>';
			}
			$html .= '<div class="wenpai-chart__plot"';
			if ( isset( $args['aria_label'] ) && '' !== (string) $args['aria_label'] ) {
				$html .= ' role="img" aria-label="' . esc_attr( (string) $args['aria_label'] ) . '"';
			}
			$html .= '>' . ( isset( $args['plot_html'] ) ? self::kses( (string) $args['plot_html'], 'chart' ) : '' ) . '</div>';
			if ( isset( $args['table_html'] ) && '' !== (string) $args['table_html'] ) {
				$html .= '<details class="wenpai-chart__data"><summary>' . esc_html( self::ui_string( $args, 'chart_data', '查看数据' ) ) . '</summary>';
				$html .= self::kses( (string) $args['table_html'], 'post' ) . '</details>';
			}
			return $html . '</figure>';
		}

		/**
		 * Echo an equal-height 3-column (or 2-column) module/service hub card grid (kit 0.12.0).
		 *
		 * @param array $args Arguments.
		 */
		public static function module_grid( $args = array() ) {
			self::emit( self::module_grid_html( $args ) );
		}

		/**
		 * Return equal-height module/service hub card grid markup (kit 0.12.0, D-38).
		 *
		 * Pairs with fold_list() below it on multi-module hub tabs (Features / Services / Modules):
		 * up to 3 semantic-tinted featured cards on top, muted single-line list below.
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type array  $items Array of card items: {
		 *         @type string $title          Card heading (14px regular).
		 *         @type string $desc           Concise summary (12px muted).
		 *         @type string $icon           Wenpai_Admin_Icons semantic name.
		 *         @type string $icon_html      Raw inline SVG (kses svg), wins over icon.
		 *         @type string $logo           1–2 char brand fallback.
		 *         @type string $logo_html      Brand SVG/img (kses logo).
		 *         @type string $tone           Tile state: accent|ok|warn|bad|mute|'' (default accent).
		 *         @type string $control_html   Top-right control markup (switch_html / pill_html; kses control).
		 *         @type array  $pill           Alternative top-right pill args (label/text + tone/state).
		 *         @type string $status         Bottom-left status summary text.
		 *         @type string $status_tone    Status dot tone: ok|warn|bad|mute|'' ('' omits dot unless status_dot is true).
		 *         @type bool   $status_dot     Force a neutral dot when status_tone is ''.
		 *         @type array  $status_attrs   Extra attributes on the status text span (e.g. data-* for live latency).
		 *         @type string $action_label   Bottom-right link button label (verb/entry, e.g. 配置 / 查看 / 了解与开通).
		 *         @type string $action_url     Bottom-right link URL.
		 *         @type string $action_variant ghost|secondary|primary (default ghost per D-38).
		 *         @type string $action_target  Optional '_blank' for external links.
		 *         @type string $action_html    Custom bottom-right action markup (kses control); wins over action_label.
		 *         @type string $class          Extra classes on card section.
		 *         @type array  $attrs          Extra attributes on card section.
		 *     }
		 *     @type int    $cols  Column count on wide screens: 3 (default) or 2.
		 *     @type string $class Extra classes on grid wrapper.
		 *     @type array  $attrs Extra attributes on grid wrapper.
		 * }
		 * @return string
		 */
		public static function module_grid_html( $args = array() ) {
			$args  = self::args( $args, 'Wenpai_Admin_UI::module_grid' );
			$items = isset( $args['items'] ) && is_array( $args['items'] ) ? $args['items'] : array();
			if ( array() === $items ) {
				return '';
			}
			$cols = isset( $args['cols'] ) && 2 === (int) $args['cols'] ? 2 : 3;
			$gcls = self::classes( array( 'wenpai-module-grid', 2 === $cols ? 'wenpai-module-grid--2' : '', self::extra_class( $args ) ) );
			$html = '<div class="' . esc_attr( $gcls ) . '"' . self::extra_attrs( $args ) . '>';

			foreach ( $items as $raw_item ) {
				if ( ! is_array( $raw_item ) ) {
					continue;
				}
				$title = isset( $raw_item['title'] ) ? (string) $raw_item['title'] : '';
				$desc  = isset( $raw_item['desc'] ) ? (string) $raw_item['desc'] : '';
				$tone  = isset( $raw_item['tone'] ) ? (string) $raw_item['tone'] : 'accent';
				$mark  = self::logo_slot( $raw_item, '' );
				if ( '' === $mark && ( ! empty( $raw_item['icon'] ) || ! empty( $raw_item['icon_html'] ) ) ) {
					$mark = self::tile_html(
						array(
							'state'     => $tone,
							'icon'      => isset( $raw_item['icon'] ) ? (string) $raw_item['icon'] : '',
							'icon_html' => isset( $raw_item['icon_html'] ) ? (string) $raw_item['icon_html'] : '',
						)
					);
				}

				$ctl = '';
				if ( isset( $raw_item['control_html'] ) && '' !== (string) $raw_item['control_html'] ) {
					$ctl = self::kses( (string) $raw_item['control_html'], 'control' );
				} elseif ( isset( $raw_item['pill'] ) && is_array( $raw_item['pill'] ) ) {
					$ctl = self::pill_html( self::pill_args( $raw_item['pill'] ) );
				}

				$status      = isset( $raw_item['status'] ) ? (string) $raw_item['status'] : '';
				$status_tone = self::state( isset( $raw_item['status_tone'] ) ? $raw_item['status_tone'] : '', array( '', 'ok', 'warn', 'bad', 'mute' ), '', 'Wenpai_Admin_UI::module_grid' );
				$show_dot    = '' !== $status_tone || ! empty( $raw_item['status_dot'] );
				$s_attrs     = isset( $raw_item['status_attrs'] ) && is_array( $raw_item['status_attrs'] ) ? self::extra_attrs( array( 'attrs' => $raw_item['status_attrs'] ) ) : '';

				$act = '';
				if ( isset( $raw_item['action_html'] ) && '' !== (string) $raw_item['action_html'] ) {
					$act = self::kses( (string) $raw_item['action_html'], 'control' );
				} elseif ( isset( $raw_item['action_label'], $raw_item['action_url'] ) && '' !== (string) $raw_item['action_label'] && '' !== (string) $raw_item['action_url'] ) {
					$variant = isset( $raw_item['action_variant'] ) ? (string) $raw_item['action_variant'] : 'ghost';
					$btn_arg = array(
						'label'   => (string) $raw_item['action_label'],
						'href'    => (string) $raw_item['action_url'],
						'variant' => $variant,
					);
					if ( ! empty( $raw_item['action_target'] ) ) {
						$btn_arg['target'] = (string) $raw_item['action_target'];
					}
					$act = self::button_html( $btn_arg );
				}

				$ccls  = self::classes( array( 'card', 'wenpai-module-card', self::extra_class( $raw_item ) ) );
				$html .= '<section class="' . esc_attr( $ccls ) . '"' . self::extra_attrs( $raw_item ) . '>';
				$html .= '<div class="wenpai-module-card__head">';
				$html .= '<div class="wenpai-module-card__lead">' . $mark . '<div class="wenpai-module-card__body">';
				$html .= '<h3 class="wenpai-module-card__title">' . esc_html( $title ) . '</h3>';
				$html .= '</div></div>';
				if ( '' !== $ctl ) {
					$html .= '<div class="wenpai-module-card__ctl">' . $ctl . '</div>';
				}
				$html .= '</div>';
				if ( '' !== $desc ) {
					$html .= '<p class="wenpai-module-card__desc">' . esc_html( $desc ) . '</p>';
				}

				if ( '' !== $status || '' !== $act ) {
					$html .= '<div class="wenpai-module-card__foot">';
					$html .= '<span class="wenpai-module-card__status">';
					if ( $show_dot ) {
						$dot_cls = self::classes( array( 'wenpai-module-card__dot', ( '' !== $status_tone && 'mute' !== $status_tone ) ? 'is-' . $status_tone : '' ) );
						$html   .= '<i class="' . esc_attr( $dot_cls ) . '" aria-hidden="true"></i>';
					}
					$html .= '<span' . $s_attrs . '>' . esc_html( $status ) . '</span></span>';
					$html .= $act;
					$html .= '</div>';
				}
				$html .= '</section>';
			}

			return $html . '</div>';
		}

		/**
		 * Echo a sticky dirty-form save bar for multi-field settings forms (kit 0.12.0).
		 *
		 * @param array $args Arguments.
		 */
		public static function savebar( $args = array() ) {
			self::emit( self::savebar_html( $args ) );
		}

		/**
		 * Return sticky dirty-form save bar markup (kit 0.12.0).
		 *
		 * Hidden until an input inside the enclosing form (or [data-wenpai-savebar-for] target form)
		 * differs from its initial value. Gives multi-field forms one consistent explicit-save contract.
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type string $message      Prompt text (default 「有未保存的设置更改」).
		 *     @type string $reset_label  Reset button label (default 「放弃更改」; '' omits reset button).
		 *     @type string $submit_label Submit button label (default 「保存设置」).
		 *     @type string $busy_label   Optional data-wenpai-busy label on submit button.
		 *     @type string $form         Optional target form id when rendered outside <form>.
		 *     @type string $class        Extra classes.
		 *     @type array  $attrs        Extra attributes.
		 * }
		 * @return string
		 */
		public static function savebar_html( $args = array() ) {
			$args   = self::args( $args, 'Wenpai_Admin_UI::savebar' );
			$msg    = isset( $args['message'] ) && '' !== (string) $args['message'] ? (string) $args['message'] : '有未保存的设置更改';
			$reset  = array_key_exists( 'reset_label', $args ) ? (string) $args['reset_label'] : '放弃更改';
			$submit = isset( $args['submit_label'] ) && '' !== (string) $args['submit_label'] ? (string) $args['submit_label'] : '保存设置';
			$form   = isset( $args['form'] ) ? (string) $args['form'] : '';
			$cls    = self::classes( array( 'wenpai-savebar', self::extra_class( $args ) ) );
			$f_attr = '' !== $form ? ' data-wenpai-savebar-for="' . esc_attr( $form ) . '"' : '';
			$html   = '<div class="' . esc_attr( $cls ) . '" data-wenpai-savebar' . $f_attr . self::extra_attrs( $args, array( 'hidden' ) ) . ' hidden>';
			$html  .= '<span class="wenpai-savebar__msg">' . esc_html( $msg ) . '</span>';
			$html  .= '<div class="wenpai-savebar__actions">';
			if ( '' !== $reset ) {
				$html .= '<button type="reset" class="btn btn-ghost"' . ( '' !== $form ? ' form="' . esc_attr( $form ) . '"' : '' ) . ' data-wenpai-savebar-reset>' . esc_html( $reset ) . '</button>';
			}
			$btn_args = array(
				'type'    => 'submit',
				'variant' => 'primary',
				'label'   => $submit,
			);
			if ( '' !== $form ) {
				$btn_args['form'] = $form;
			}
			if ( ! empty( $args['busy_label'] ) ) {
				$btn_args['busy_label'] = (string) $args['busy_label'];
			}
			$html .= self::button_html( $btn_args );
			$html .= '</div></div>';
			return $html;
		}

		/**
		 * Echo an inline lede drawer trigger button for stat_hub() (kit 0.13.0).
		 *
		 * @param array $args Arguments.
		 */
		public static function drawer_toggle( $args = array() ) {
			self::emit( self::drawer_toggle_html( $args ) );
		}

		/**
		 * Return an inline lede drawer trigger button (e.g. 「通道详情 ▾」) wired to stat_hub() (kit 0.13.0).
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type string $target      Target drawer id (matches stat_hub drawer_id).
		 *     @type string $label       Closed label (default 「通道详情」).
		 *     @type string $open_label  Expanded label (default 「收起通道」).
		 *     @type bool   $open        Initial expanded state. Default false.
		 *     @type string $class       Extra classes.
		 *     @type array  $attrs       Extra attributes.
		 * }
		 * @return string
		 */
		public static function drawer_toggle_html( $args = array() ) {
			$args       = self::args( $args, 'Wenpai_Admin_UI::drawer_toggle' );
			$target     = isset( $args['target'] ) ? sanitize_html_class( (string) $args['target'] ) : '';
			$label      = isset( $args['label'] ) && '' !== (string) $args['label'] ? (string) $args['label'] : '通道详情';
			$open_label = isset( $args['open_label'] ) && '' !== (string) $args['open_label'] ? (string) $args['open_label'] : '收起通道';
			$open       = ! empty( $args['open'] );
			$chevron    = class_exists( 'Wenpai_Admin_Icons', false ) ? Wenpai_Admin_Icons::svg( 'chevron-down' ) : '';
			$cls        = self::classes( array( 'wenpai-lede-toggle', $open ? 'is-open' : '', self::extra_class( $args ) ) );
			$html       = '<button type="button" class="' . esc_attr( $cls ) . '"';
			if ( '' !== $target ) {
				$html .= ' data-wenpai-drawer-toggle="' . esc_attr( $target ) . '" aria-controls="' . esc_attr( $target ) . '"';
			}
			$html .= ' aria-expanded="' . ( $open ? 'true' : 'false' ) . '"';
			$html .= ' data-wenpai-label-closed="' . esc_attr( $label ) . '" data-wenpai-label-open="' . esc_attr( $open_label ) . '"';
			$html .= self::extra_attrs( $args );
			$html .= '><span data-wenpai-toggle-text>' . esc_html( $open ? $open_label : $label ) . '</span>' . $chevron . '</button>';
			return $html;
		}

		/**
		 * Echo a silent-when-healthy statbar hub card with anomaly alert strip and flush channel drawer (kit 0.13.0).
		 *
		 * @param array $args Arguments.
		 */
		public static function stat_hub( $args = array() ) {
			self::emit( self::stat_hub_html( $args ) );
		}

		/**
		 * Return stat_hub markup: 4-col numeric statbar + optional warm fallback strip + flush expandable channel grid (kit 0.13.0).
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type array  $stats          Items passed to statbar_html().
		 *     @type string $drawer_id      DOM id for the expandable drawer (default 'wenpai-stat-drawer').
		 *     @type array  $alert          Optional array{ visible?: bool, text?: string, action_label?: string }.
		 *     @type array  $channels       Channel cells: array of array{ label, value, tone?, attrs?, value_attrs? }.
		 *     @type string $collapse_label Collapse button text in the last channel cell (default 「收起」).
		 *     @type bool   $open           Initial open state for the channel drawer. Default false.
		 *     @type string $class          Extra classes on section.card.
		 *     @type array  $attrs          Extra attributes on section.card.
		 * }
		 * @return string
		 */
		public static function stat_hub_html( $args = array() ) {
			$args           = self::args( $args, 'Wenpai_Admin_UI::stat_hub' );
			$stats          = isset( $args['stats'] ) && is_array( $args['stats'] ) ? $args['stats'] : array();
			$drawer_id      = isset( $args['drawer_id'] ) && '' !== (string) $args['drawer_id'] ? sanitize_html_class( (string) $args['drawer_id'] ) : 'wenpai-stat-drawer';
			$channels       = isset( $args['channels'] ) && is_array( $args['channels'] ) ? array_values( $args['channels'] ) : array();
			$open           = ! empty( $args['open'] );
			$alert          = isset( $args['alert'] ) && is_array( $args['alert'] ) ? $args['alert'] : array();
			$alert_visible  = ! empty( $alert['visible'] );
			$alert_text     = isset( $alert['text'] ) ? (string) $alert['text'] : '';
			$alert_act      = isset( $alert['action_label'] ) && '' !== (string) $alert['action_label'] ? (string) $alert['action_label'] : '展开查看';
			$collapse_label = isset( $args['collapse_label'] ) && '' !== (string) $args['collapse_label'] ? (string) $args['collapse_label'] : '收起';
			$chevron        = class_exists( 'Wenpai_Admin_Icons', false ) ? Wenpai_Admin_Icons::svg( 'chevron-down' ) : '';
			$has_bottom     = $open || ( $alert_visible && '' !== $alert_text );

			$cls   = self::classes( array( 'card', 'wenpai-stat-hub', $has_bottom ? 'has-drawer-visible' : '', self::extra_class( $args ) ) );
			$html  = '<section class="' . esc_attr( $cls ) . '" data-wenpai-stat-hub="' . esc_attr( $drawer_id ) . '"' . self::extra_attrs( $args ) . '>';
			$html .= self::statbar_html( array( 'items' => $stats ) );

			if ( '' !== $alert_text || $alert_visible ) {
				$show_bar = $alert_visible && ! $open;
				$acls     = self::classes( array( 'wenpai-stat-hub__alert', $show_bar ? 'is-visible' : '' ) );
				$html    .= '<button type="button" class="' . esc_attr( $acls ) . '" data-wenpai-drawer-open="' . esc_attr( $drawer_id ) . '" data-wenpai-has-issue="' . ( $alert_visible ? '1' : '0' ) . '">';
				$html    .= '<span class="wenpai-stat-hub__alert-lead"><i class="wenpai-stat-hub__dot" aria-hidden="true"></i><span data-wenpai-alert-text>' . esc_html( $alert_text ) . '</span></span>';
				$html    .= '<span class="wenpai-stat-hub__alert-act">' . esc_html( $alert_act ) . $chevron . '</span>';
				$html    .= '</button>';
			}

			if ( array() !== $channels ) {
				$dcls  = self::classes( array( 'wenpai-stat-hub__drawer', $open ? 'is-open' : '' ) );
				$html .= '<div class="' . esc_attr( $dcls ) . '" id="' . esc_attr( $drawer_id ) . '" data-wenpai-drawer' . ( $open ? '' : ' hidden' ) . '>';
				$last  = count( $channels ) - 1;
				foreach ( $channels as $idx => $ch ) {
					if ( ! is_array( $ch ) ) {
						continue;
					}
					$tone   = isset( $ch['tone'] ) ? (string) $ch['tone'] : '';
					$ccls   = self::classes( array( 'wenpai-stat-hub__cell', ( 'warn' === $tone || 'bad' === $tone ) ? 'is-' . $tone : '' ) );
					$cattrs = isset( $ch['attrs'] ) && is_array( $ch['attrs'] ) ? self::extra_attrs( array( 'attrs' => $ch['attrs'] ) ) : '';
					$vattrs = isset( $ch['value_attrs'] ) && is_array( $ch['value_attrs'] ) ? self::extra_attrs( array( 'attrs' => $ch['value_attrs'] ) ) : '';
					$html  .= '<div class="' . esc_attr( $ccls ) . '"' . $cattrs . '>';
					$html  .= '<div class="wenpai-stat-hub__cell-head">';
					$html  .= '<span class="wenpai-stat-hub__k"><i aria-hidden="true"></i>' . esc_html( isset( $ch['label'] ) ? (string) $ch['label'] : '' ) . '</span>';
					if ( $idx === $last ) {
						$html .= '<button type="button" class="wenpai-stat-hub__collapse" data-wenpai-drawer-close="' . esc_attr( $drawer_id ) . '">' . esc_html( $collapse_label ) . ' &#9652;</button>';
					}
					$html .= '</div>';
					$html .= '<span class="wenpai-stat-hub__v"' . $vattrs . '>' . esc_html( isset( $ch['value'] ) ? (string) $ch['value'] : '' ) . '</span>';
					$html .= '</div>';
				}
				$html .= '</div>';
			}

			return $html . '</section>';
		}

		/**
		 * Echo card-header segmented view tabs wired to view_panes() (kit 0.13.0).
		 *
		 * @param array $args Arguments.
		 */
		public static function view_tabs( $args = array() ) {
			self::emit( self::view_tabs_html( $args ) );
		}

		/**
		 * Return card-header segmented view tabs markup (kit 0.13.0).
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type string $group      Shared group name matching view_panes() group.
		 *     @type string $aria_label Accessible tablist label.
		 *     @type array  $items      Array of array{ id, label, active?: bool, card_title?: string, foot_meta?: string, foot_label?: string, foot_href?: string }.
		 *     @type string $class      Extra classes.
		 *     @type array  $attrs      Extra attributes.
		 * }
		 * @return string
		 */
		public static function view_tabs_html( $args = array() ) {
			$args  = self::args( $args, 'Wenpai_Admin_UI::view_tabs' );
			$group = isset( $args['group'] ) ? sanitize_key( (string) $args['group'] ) : 'default';
			$label = isset( $args['aria_label'] ) ? (string) $args['aria_label'] : '';
			$items = isset( $args['items'] ) && is_array( $args['items'] ) ? $args['items'] : array();
			$cls   = self::classes( array( 'wenpai-seg-tabs', self::extra_class( $args ) ) );
			$html  = '<div class="' . esc_attr( $cls ) . '" role="tablist" data-wenpai-tabs="' . esc_attr( $group ) . '"';
			if ( '' !== $label ) {
				$html .= ' aria-label="' . esc_attr( $label ) . '"';
			}
			$html .= self::extra_attrs( $args ) . '>';
			foreach ( $items as $idx => $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$id     = isset( $item['id'] ) ? sanitize_key( (string) $item['id'] ) : 'tab-' . (int) $idx;
				$active = ! empty( $item['active'] );
				$html  .= '<button type="button" role="tab" class="' . ( $active ? 'is-active' : '' ) . '" aria-selected="' . ( $active ? 'true' : 'false' ) . '" data-wenpai-tab="' . esc_attr( $id ) . '"';
				if ( isset( $item['card_title'] ) && '' !== (string) $item['card_title'] ) {
					$html .= ' data-wenpai-card-title="' . esc_attr( (string) $item['card_title'] ) . '"';
				}
				if ( isset( $item['foot_meta'] ) ) {
					$html .= ' data-wenpai-foot-meta="' . esc_attr( (string) $item['foot_meta'] ) . '"';
				}
				if ( isset( $item['foot_label'] ) ) {
					$html .= ' data-wenpai-foot-label="' . esc_attr( (string) $item['foot_label'] ) . '"';
				}
				if ( isset( $item['foot_href'] ) ) {
					$html .= ' data-wenpai-foot-href="' . esc_url( (string) $item['foot_href'] ) . '"';
				}
				$html .= '>' . esc_html( isset( $item['label'] ) ? (string) $item['label'] : '' ) . '</button>';
			}
			return $html . '</div>';
		}

		/**
		 * Echo zero-jump overlapping view panes (grid-area: 1/1) paired with view_tabs() (kit 0.13.0).
		 *
		 * @param array $args Arguments.
		 */
		public static function view_panes( $args = array() ) {
			self::emit( self::view_panes_html( $args ) );
		}

		/**
		 * Return zero-jump overlapping view panes markup (kit 0.13.0).
		 *
		 * Inactive panes stay in the same CSS Grid cell with visibility:hidden so switching
		 * tabs never alters card height or page scroll position.
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type string $group Shared group name matching view_tabs() group.
		 *     @type array  $panes Array of array{ id, html, active?: bool }.
		 *     @type string $class Extra classes on .wenpai-view-panes.
		 *     @type array  $attrs Extra attributes.
		 * }
		 * @return string
		 */
		public static function view_panes_html( $args = array() ) {
			$args  = self::args( $args, 'Wenpai_Admin_UI::view_panes' );
			$group = isset( $args['group'] ) ? sanitize_key( (string) $args['group'] ) : 'default';
			$panes = isset( $args['panes'] ) && is_array( $args['panes'] ) ? $args['panes'] : array();
			$cls   = self::classes( array( 'wenpai-view-panes', self::extra_class( $args ) ) );
			$html  = '<div class="' . esc_attr( $cls ) . '" data-wenpai-panes="' . esc_attr( $group ) . '"' . self::extra_attrs( $args ) . '>';
			foreach ( $panes as $idx => $pane ) {
				if ( ! is_array( $pane ) ) {
					continue;
				}
				$id     = isset( $pane['id'] ) ? sanitize_key( (string) $pane['id'] ) : 'pane-' . (int) $idx;
				$active = ! empty( $pane['active'] );
				$pcls   = self::classes( array( 'wenpai-view-pane', $active ? '' : 'is-inactive' ) );
				$html  .= '<div class="' . esc_attr( $pcls ) . '" role="tabpanel" data-wenpai-pane="' . esc_attr( $id ) . '"' . ( $active ? '' : ' inert' ) . '>';
				$html  .= isset( $pane['html'] ) ? self::kses( (string) $pane['html'], 'pane' ) : '';
				$html  .= '</div>';
			}
			return $html . '</div>';
		}

		/**
		 * Echo a 2x2 soft-surface metric tile grid (kit 0.13.0).
		 *
		 * @param array $args Arguments.
		 */
		public static function metric_tiles( $args = array() ) {
			self::emit( self::metric_tiles_html( $args ) );
		}

		/**
		 * Return 2x2 soft-surface metric tile grid markup (kit 0.13.0).
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type array  $items Array of array{ label, badge?: string, value, unit?: string, sub?: string, tone?: string, attrs?: array, value_attrs?: array, sub_attrs?: array }.
		 *     @type string $class Extra classes.
		 *     @type array  $attrs Extra attributes.
		 * }
		 * @return string
		 */
		public static function metric_tiles_html( $args = array() ) {
			$args  = self::args( $args, 'Wenpai_Admin_UI::metric_tiles' );
			$items = isset( $args['items'] ) && is_array( $args['items'] ) ? $args['items'] : array();
			$cls   = self::classes( array( 'wenpai-metric-tiles', self::extra_class( $args ) ) );
			$html  = '<div class="' . esc_attr( $cls ) . '"' . self::extra_attrs( $args ) . '>';
			foreach ( $items as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$tone   = isset( $item['tone'] ) ? (string) $item['tone'] : '';
				$tcls   = self::classes( array( 'wenpai-metric-tile', ( 'warn' === $tone || 'bad' === $tone ) ? 'is-' . $tone : '' ) );
				$iattrs = isset( $item['attrs'] ) && is_array( $item['attrs'] ) ? self::extra_attrs( array( 'attrs' => $item['attrs'] ) ) : '';
				$vattrs = isset( $item['value_attrs'] ) && is_array( $item['value_attrs'] ) ? self::extra_attrs( array( 'attrs' => $item['value_attrs'] ) ) : '';
				$sattrs = isset( $item['sub_attrs'] ) && is_array( $item['sub_attrs'] ) ? self::extra_attrs( array( 'attrs' => $item['sub_attrs'] ) ) : '';
				$html  .= '<div class="' . esc_attr( $tcls ) . '"' . $iattrs . '>';
				$html  .= '<div class="wenpai-metric-tile__head">';
				$html  .= '<span class="wenpai-metric-tile__name">' . esc_html( isset( $item['label'] ) ? (string) $item['label'] : '' ) . '</span>';
				if ( isset( $item['badge'] ) && '' !== (string) $item['badge'] ) {
					$html .= '<span class="wenpai-metric-tile__badge">' . esc_html( (string) $item['badge'] ) . '</span>';
				}
				$html .= '</div>';
				$html .= '<div class="wenpai-metric-tile__val"' . $vattrs . '>' . esc_html( isset( $item['value'] ) ? (string) $item['value'] : '' );
				if ( isset( $item['unit'] ) && '' !== (string) $item['unit'] ) {
					$html .= '<small>' . esc_html( (string) $item['unit'] ) . '</small>';
				}
				$html .= '</div>';
				if ( isset( $item['sub'] ) && '' !== (string) $item['sub'] ) {
					$html .= '<span class="wenpai-metric-tile__sub"' . $sattrs . '><i aria-hidden="true"></i><span>' . esc_html( (string) $item['sub'] ) . '</span></span>';
				}
				$html .= '</div>';
			}
			return $html . '</div>';
		}

		/**
		 * Echo an auto-sorted scrollable service quota list (kit 0.13.0).
		 *
		 * @param array $args Arguments.
		 */
		public static function quota_list( $args = array() ) {
			self::emit( self::quota_list_html( $args ) );
		}

		/**
		 * Return auto-sorted scrollable service quota list markup (kit 0.13.0).
		 *
		 * High-consumption services automatically float to the top (when auto_sort is true),
		 * while unlimited / permanently-free items stay at the bottom without progress bars.
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type array  $items     Array of array{ name, usage, percent?: int|null, note?: string, tone?: string, action_label?: string, action_url?: string }.
		 *     @type bool   $auto_sort Sort descending by percent (null/unlimited last). Default true.
		 *     @type string $class     Extra classes.
		 *     @type array  $attrs     Extra attributes.
		 * }
		 * @return string
		 */
		public static function quota_list_html( $args = array() ) {
			$args      = self::args( $args, 'Wenpai_Admin_UI::quota_list' );
			$raw_items = isset( $args['items'] ) && is_array( $args['items'] ) ? array_values( $args['items'] ) : array();
			$auto_sort = ! isset( $args['auto_sort'] ) || (bool) $args['auto_sort'];
			if ( $auto_sort && count( $raw_items ) > 1 ) {
				$indexed = array();
				foreach ( $raw_items as $i => $row ) {
					if ( is_array( $row ) ) {
						$row['_idx'] = $i;
						$indexed[]   = $row;
					}
				}
				usort(
					$indexed,
					function ( $a, $b ) {
						$pa = isset( $a['percent'] ) && null !== $a['percent'] ? (int) $a['percent'] : -1;
						$pb = isset( $b['percent'] ) && null !== $b['percent'] ? (int) $b['percent'] : -1;
						if ( $pa !== $pb ) {
							return $pb <=> $pa;
						}
						return (int) $a['_idx'] <=> (int) $b['_idx'];
					}
				);
				$raw_items = $indexed;
			}

			$cls  = self::classes( array( 'wenpai-quota-scroll', self::extra_class( $args ) ) );
			$html = '<div class="' . esc_attr( $cls ) . '"' . self::extra_attrs( $args ) . '><ul class="wenpai-quota-list">';
			foreach ( $raw_items as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$has_pct = isset( $item['percent'] ) && null !== $item['percent'] && '' !== (string) $item['percent'];
				$pct     = $has_pct ? max( 0, min( 100, (int) $item['percent'] ) ) : 0;
				$tone    = isset( $item['tone'] ) ? (string) $item['tone'] : '';
				if ( '' === $tone && $has_pct && $pct >= 85 ) {
					$tone = 'warn';
				}
				$icls  = self::classes( array( 'wenpai-quota-item', ( 'warn' === $tone || 'bad' === $tone ) ? 'is-' . $tone : '', ( $has_pct && $pct >= 100 ) ? 'is-full' : '' ) );
				$html .= '<li class="' . esc_attr( $icls ) . '">';
				$html .= '<div class="wenpai-quota-item__head">';
				$html .= '<span class="wenpai-quota-item__name">' . esc_html( isset( $item['name'] ) ? (string) $item['name'] : '' ) . '</span>';
				$html .= '<span class="wenpai-quota-item__num">' . esc_html( isset( $item['usage'] ) ? (string) $item['usage'] : '' ) . '</span>';
				$html .= '</div>';
				if ( $has_pct ) {
					$html .= '<div class="wenpai-quota-item__rail" aria-hidden="true"><i style="inline-size:' . (int) $pct . '%"></i></div>';
				}
				$note = isset( $item['note'] ) ? (string) $item['note'] : '';
				$albl = isset( $item['action_label'] ) ? (string) $item['action_label'] : '';
				$aurl = isset( $item['action_url'] ) ? (string) $item['action_url'] : '';
				if ( '' !== $note || ( '' !== $albl && '' !== $aurl ) ) {
					$html .= '<div class="wenpai-quota-item__foot">';
					$html .= '<span>' . esc_html( $note ) . '</span>';
					if ( '' !== $albl && '' !== $aurl ) {
						$html .= '<a class="wenpai-quota-item__act" href="' . esc_url( $aurl ) . '">' . esc_html( $albl ) . '</a>';
					}
					$html .= '</div>';
				}
				$html .= '</li>';
			}
			return $html . '</ul></div>';
		}

		/**
		 * Echo a single-line micro-tag timeline / notice / suggestion list (kit 0.13.0).
		 *
		 * @param array $args Arguments.
		 */
		public static function tag_feed( $args = array() ) {
			self::emit( self::tag_feed_html( $args ) );
		}

		/**
		 * Return single-line micro-tag timeline / feed / suggestion list markup (kit 0.13.0).
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type array  $items Array of array{ tag, tone?: string, title, url?: string, target?: string, note?: string, when?: string, action_html?: string, action_label?: string, action_url?: string, action_variant?: string, attrs?: array }.
		 *     @type array  $empty Optional empty_state() args when items is empty.
		 *     @type string $class Extra classes.
		 *     @type array  $attrs Extra attributes.
		 * }
		 * @return string
		 */
		public static function tag_feed_html( $args = array() ) {
			$args  = self::args( $args, 'Wenpai_Admin_UI::tag_feed' );
			$items = isset( $args['items'] ) && is_array( $args['items'] ) ? $args['items'] : array();
			if ( array() === $items ) {
				return isset( $args['empty'] ) && is_array( $args['empty'] ) ? self::empty_state_html( $args['empty'] ) : '';
			}
			$cls  = self::classes( array( 'wenpai-tag-feed', self::extra_class( $args ) ) );
			$html = '<ul class="' . esc_attr( $cls ) . '"' . self::extra_attrs( $args ) . '>';
			foreach ( $items as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$tone   = isset( $item['tone'] ) ? (string) $item['tone'] : '';
				$tcls   = self::classes( array( 'wenpai-micro-tag', in_array( $tone, array( 'accent', 'warn', 'ok', 'bad' ), true ) ? 'is-' . $tone : '' ) );
				$iattrs = isset( $item['attrs'] ) && is_array( $item['attrs'] ) ? self::extra_attrs( array( 'attrs' => $item['attrs'] ) ) : '';
				$html  .= '<li' . $iattrs . '>';
				$html  .= '<span class="' . esc_attr( $tcls ) . '">' . esc_html( isset( $item['tag'] ) ? (string) $item['tag'] : '' ) . '</span>';
				$html  .= '<span class="wenpai-tag-feed__text">';
				$title  = isset( $item['title'] ) ? (string) $item['title'] : '';
				if ( isset( $item['url'] ) && '' !== (string) $item['url'] ) {
					$tgt   = ! empty( $item['target'] ) ? ' target="' . esc_attr( (string) $item['target'] ) . '" rel="noopener noreferrer"' : '';
					$html .= '<a href="' . esc_url( (string) $item['url'] ) . '"' . $tgt . '>' . esc_html( $title ) . '</a>';
				} else {
					$html .= '<span>' . esc_html( $title ) . '</span>';
				}
				if ( isset( $item['note'] ) && '' !== (string) $item['note'] ) {
					$html .= '<small>' . esc_html( (string) $item['note'] ) . '</small>';
				}
				$html .= '</span>';
				$html .= '<span class="wenpai-tag-feed__side">';
				if ( isset( $item['when'] ) && '' !== (string) $item['when'] ) {
					$html .= '<span>' . esc_html( (string) $item['when'] ) . '</span>';
				}
				if ( isset( $item['action_html'] ) && '' !== (string) $item['action_html'] ) {
					$html .= self::kses( (string) $item['action_html'], 'control' );
				} elseif ( isset( $item['action_label'], $item['action_url'] ) && '' !== (string) $item['action_label'] && '' !== (string) $item['action_url'] ) {
					$html .= self::button_html(
						array(
							'label'   => (string) $item['action_label'],
							'href'    => (string) $item['action_url'],
							'variant' => isset( $item['action_variant'] ) ? (string) $item['action_variant'] : 'secondary',
							'size'    => 'sm',
						)
					);
				}
				$html .= '</span></li>';
			}
			return $html . '</ul>';
		}

		/**
		 * Echo a compact 20rem cloud ad / white-label agency support slot card (kit 0.13.0).
		 *
		 * @param array $args Arguments.
		 */
		public static function ad_slot( $args = array() ) {
			self::emit( self::ad_slot_html( $args ) );
		}

		/**
		 * Return compact cloud ad / white-label agency support slot card markup (kit 0.13.0).
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type string $title         Card header title (default 「精选推荐」).
		 *     @type bool   $agency        True renders neutral white-label support card without pager or close button.
		 *     @type bool   $dismissible   Show × close button (default true when not agency).
		 *     @type string $dismiss_label Accessible title for close button (default 「隐藏推荐位」).
		 *     @type array  $slides        Array of array{ badge?: string, sub?: string, title, desc?: string, pills?: string[], foot_meta?: string, cta_label?: string, cta_url?: string, cta_target?: string }.
		 *     @type string $class         Extra classes on section.card.
		 *     @type array  $attrs         Extra attributes on section.card.
		 * }
		 * @return string
		 */
		public static function ad_slot_html( $args = array() ) {
			$args   = self::args( $args, 'Wenpai_Admin_UI::ad_slot' );
			$slides = isset( $args['slides'] ) && is_array( $args['slides'] ) ? array_values( $args['slides'] ) : array();
			if ( array() === $slides ) {
				return '';
			}
			$agency      = ! empty( $args['agency'] );
			$dismissible = ! $agency && ( ! isset( $args['dismissible'] ) || (bool) $args['dismissible'] );
			$title       = isset( $args['title'] ) && '' !== (string) $args['title'] ? (string) $args['title'] : ( $agency ? '技术支持与维护' : '精选推荐' );
			$close_lbl   = isset( $args['dismiss_label'] ) && '' !== (string) $args['dismiss_label'] ? (string) $args['dismiss_label'] : '隐藏推荐位';
			$count       = count( $slides );
			$first       = is_array( $slides[0] ) ? $slides[0] : array();

			$cls   = self::classes( array( 'card', 'wenpai-card--equal', 'wenpai-ad-slot', self::extra_class( $args ) ) );
			$html  = '<section class="' . esc_attr( $cls ) . '" data-wenpai-ad-slot' . self::extra_attrs( $args ) . '>';
			$html .= '<div class="card-head"><div><h2 class="card-title">' . esc_html( $title ) . '</h2></div>';
			if ( ( ! $agency && $count > 1 ) || $dismissible ) {
				$html .= '<div class="head-actions">';
				if ( ! $agency && $count > 1 ) {
					$html .= '<div class="wenpai-ad-pager">';
					$html .= '<button type="button" data-wenpai-ad-step="-1" aria-label="上一条">&#8249;</button>';
					$html .= '<span data-wenpai-ad-index>1 / ' . (int) $count . '</span>';
					$html .= '<button type="button" data-wenpai-ad-step="1" aria-label="下一条">&#8250;</button>';
					$html .= '</div>';
				}
				if ( $dismissible ) {
					$html .= '<button type="button" class="wenpai-ad-close" data-wenpai-ad-dismiss aria-label="' . esc_attr( $close_lbl ) . '" title="' . esc_attr( $close_lbl ) . '">&times;</button>';
				}
				$html .= '</div>';
			}
			$html .= '</div>';

			$html .= '<div class="wenpai-card__body"><div class="wenpai-view-panes">';
			foreach ( $slides as $idx => $slide ) {
				if ( ! is_array( $slide ) ) {
					continue;
				}
				$active = 0 === $idx;
				$pcls   = self::classes( array( 'wenpai-view-pane', $active ? '' : 'is-inactive' ) );
				$scls   = self::classes( array( 'wenpai-ad-stage', $agency ? 'is-neutral' : '' ) );
				$fmeta  = isset( $slide['foot_meta'] ) ? (string) $slide['foot_meta'] : '';
				$clbl   = isset( $slide['cta_label'] ) ? (string) $slide['cta_label'] : '';
				$curl   = isset( $slide['cta_url'] ) ? (string) $slide['cta_url'] : '';
				$html  .= '<div class="' . esc_attr( $pcls ) . '" data-wenpai-ad-slide="' . (int) $idx . '" data-wenpai-foot-meta="' . esc_attr( $fmeta ) . '" data-wenpai-cta-label="' . esc_attr( $clbl ) . '" data-wenpai-cta-url="' . esc_url( $curl ) . '"' . ( $active ? '' : ' inert' ) . '>';
				$html  .= '<div class="' . esc_attr( $scls ) . '">';
				$html  .= '<div class="wenpai-ad-stage__top">';
				if ( isset( $slide['badge'] ) && '' !== (string) $slide['badge'] ) {
					$bcls  = self::classes( array( 'wenpai-micro-tag', $agency ? '' : 'is-accent' ) );
					$html .= '<span class="' . esc_attr( $bcls ) . '">' . esc_html( (string) $slide['badge'] ) . '</span>';
				}
				if ( isset( $slide['sub'] ) && '' !== (string) $slide['sub'] ) {
					$html .= '<span class="wenpai-ad-stage__sub">' . esc_html( (string) $slide['sub'] ) . '</span>';
				}
				$html .= '</div>';
				$html .= '<div class="wenpai-ad-stage__body">';
				$html .= '<h3 class="wenpai-ad-stage__title">' . esc_html( isset( $slide['title'] ) ? (string) $slide['title'] : '' ) . '</h3>';
				if ( isset( $slide['desc'] ) && '' !== (string) $slide['desc'] ) {
					$html .= '<p class="wenpai-ad-stage__desc">' . esc_html( (string) $slide['desc'] ) . '</p>';
				}
				$html .= '</div>';
				if ( isset( $slide['pills'] ) && is_array( $slide['pills'] ) && array() !== $slide['pills'] ) {
					$html .= '<div class="wenpai-ad-stage__pills">';
					foreach ( $slide['pills'] as $pill ) {
						if ( is_scalar( $pill ) && '' !== (string) $pill ) {
							$html .= '<span class="wenpai-ad-pill">' . esc_html( (string) $pill ) . '</span>';
						}
					}
					$html .= '</div>';
				}
				$html .= '</div></div>';
			}
			$html .= '</div></div>';

			$first_meta = isset( $first['foot_meta'] ) ? (string) $first['foot_meta'] : '';
			$first_lbl  = isset( $first['cta_label'] ) ? (string) $first['cta_label'] : '';
			$first_url  = isset( $first['cta_url'] ) ? (string) $first['cta_url'] : '';
			$first_tgt  = ! empty( $first['cta_target'] ) ? ' target="' . esc_attr( (string) $first['cta_target'] ) . '" rel="noopener noreferrer"' : '';
			$html      .= '<div class="card-foot start">';
			$html      .= '<span class="meta" data-wenpai-ad-foot-meta>' . esc_html( $first_meta ) . '</span>';
			if ( '' !== $first_lbl && '' !== $first_url ) {
				$html .= '<a class="btn btn-ghost btn-sm" href="' . esc_url( $first_url ) . '"' . $first_tgt . ' data-wenpai-ad-foot-cta>' . esc_html( $first_lbl ) . '</a>';
			}
			$html .= '</div></section>';
			return $html;
		}

		/**
		 * Echo an icon-box launcher grid for lightweight tools and ecosystem mini-apps (kit 0.13.0).
		 *
		 * @param array $args Arguments.
		 */
		public static function icon_grid( $args = array() ) {
			self::emit( self::icon_grid_html( $args ) );
		}

		/**
		 * Return icon-box launcher grid markup (kit 0.13.0).
		 *
		 * @param array $args {
		 *     Helper arguments.
		 *
		 *     @type array  $items   Array of array{ label, icon?: string, icon_html?: string, href?: string, target?: string, dashed?: bool, attrs?: array }.
		 *     @type int    $columns Grid columns on desktop (2, 3, or 4; default 4).
		 *     @type string $class   Extra classes.
		 *     @type array  $attrs   Extra attributes.
		 * }
		 * @return string
		 */
		public static function icon_grid_html( $args = array() ) {
			$args  = self::args( $args, 'Wenpai_Admin_UI::icon_grid' );
			$items = isset( $args['items'] ) && is_array( $args['items'] ) ? $args['items'] : array();
			$cols  = isset( $args['columns'] ) ? (int) $args['columns'] : 4;
			if ( ! in_array( $cols, array( 2, 3, 4 ), true ) ) {
				$cols = 4;
			}
			$cls  = self::classes( array( 'wenpai-icon-grid', 4 !== $cols ? 'wenpai-icon-grid--' . $cols : '', self::extra_class( $args ) ) );
			$html = '<div class="' . esc_attr( $cls ) . '"' . self::extra_attrs( $args ) . '>';
			foreach ( $items as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$glyph = '';
				if ( isset( $item['icon_html'] ) && '' !== (string) $item['icon_html'] ) {
					$glyph = self::kses( (string) $item['icon_html'], 'svg' );
				} elseif ( isset( $item['icon'] ) && '' !== (string) $item['icon'] && class_exists( 'Wenpai_Admin_Icons', false ) ) {
					$glyph = Wenpai_Admin_Icons::svg( (string) $item['icon'] );
				}
				$bcls   = self::classes( array( 'wenpai-icon-box', ! empty( $item['dashed'] ) ? 'is-more' : '' ) );
				$iattrs = isset( $item['attrs'] ) && is_array( $item['attrs'] ) ? self::extra_attrs( array( 'attrs' => $item['attrs'] ) ) : '';
				$label  = isset( $item['label'] ) ? (string) $item['label'] : '';
				$inner  = '<span class="wenpai-icon-box__glyph" aria-hidden="true">' . $glyph . '</span><span class="wenpai-icon-box__label">' . esc_html( $label ) . '</span>';
				if ( isset( $item['href'] ) && '' !== (string) $item['href'] ) {
					$tgt   = ! empty( $item['target'] ) ? ' target="' . esc_attr( (string) $item['target'] ) . '" rel="noopener noreferrer"' : '';
					$html .= '<a class="' . esc_attr( $bcls ) . '" href="' . esc_url( (string) $item['href'] ) . '"' . $tgt . $iattrs . '>' . $inner . '</a>';
				} else {
					$html .= '<button type="button" class="' . esc_attr( $bcls ) . '"' . $iattrs . '>' . $inner . '</button>';
				}
			}
			return $html . '</div>';
		}

		/**
		 * Brand mark slot shared by row() and log_list(): 'logo' (one or two
		 * characters) or 'logo_html' (svg / img, kses logo; wins), printed as
		 * span.wenpai-logo. '' when neither key is set.
		 *
		 * @param array  $args Helper args or item.
		 * @param string $size ''|sm|xs.
		 * @return string
		 */
		private static function logo_slot( array $args, $size ) {
			$inner = isset( $args['logo_html'] ) ? self::kses( (string) $args['logo_html'], 'logo' ) : '';
			if ( '' === $inner && isset( $args['logo'] ) ) {
				$inner = esc_html( (string) $args['logo'] );
			}
			if ( '' === $inner ) {
				return '';
			}
			return '<span class="' . esc_attr( self::classes( array( 'wenpai-logo', '' !== $size ? 'wenpai-logo--' . $size : '' ) ) ) . '">' . $inner . '</span>';
		}

		/**
		 * Pill arguments from a pattern item: label/tone, or pill()'s own text/state.
		 *
		 * @param array $pill Caller pill.
		 * @return array
		 */
		private static function pill_args( array $pill ) {
			$text  = isset( $pill['label'] ) ? $pill['label'] : ( isset( $pill['text'] ) ? $pill['text'] : ( isset( $pill[0] ) ? $pill[0] : '' ) );
			$state = isset( $pill['tone'] ) ? $pill['tone'] : ( isset( $pill['state'] ) ? $pill['state'] : ( isset( $pill[1] ) ? $pill[1] : '' ) );
			return array(
				'text'  => (string) $text,
				'state' => (string) $state,
			);
		}

		/**
		 * One kit string: $args['strings'][ $key ], then the Settings default, then $fallback.
		 *
		 * The UI helpers have no instance to carry strings, so a caller passes its
		 * translated copy per call; the Settings defaults keep the wording in one place.
		 *
		 * @param array  $args     Helper args with optional strings.
		 * @param string $key      String key (strings.json).
		 * @param string $fallback Used when Settings is not loaded.
		 * @return string
		 */
		private static function ui_string( array $args, $key, $fallback ) {
			if ( isset( $args['strings'] ) && is_array( $args['strings'] ) && isset( $args['strings'][ $key ] ) && '' !== (string) $args['strings'][ $key ] ) {
				return (string) $args['strings'][ $key ];
			}
			if ( class_exists( 'Wenpai_Admin_Settings', false ) ) {
				$defaults = Wenpai_Admin_Settings::default_strings();
				if ( isset( $defaults[ $key ] ) ) {
					return (string) $defaults[ $key ];
				}
			}
			return $fallback;
		}

		/**
		 * Coerce $args to an array.
		 *
		 * @param mixed  $args  Caller args.
		 * @param string $where Function name for _doing_it_wrong.
		 * @return array
		 */
		private static function args( $args, $where ) {
			if ( is_array( $args ) ) {
				return $args;
			}
			self::doing_it_wrong( 'Arguments must be an array.', $where );
			return array();
		}

		/**
		 * Normalize a state token.
		 *
		 * @param mixed    $value         Caller value.
		 * @param string[] $allowed       Allowed tokens.
		 * @param string   $fallback      Fallback.
		 * @param string   $where         Function name.
		 * @param bool     $mute_to_empty tile(): mute becomes ''.
		 * @return string
		 */
		private static function state( $value, array $allowed, $fallback, $where, $mute_to_empty = false ) {
			$value = is_scalar( $value ) ? (string) $value : '';
			if ( $mute_to_empty && 'mute' === $value ) {
				return '';
			}
			if ( in_array( $value, $allowed, true ) ) {
				return $value;
			}
			self::doing_it_wrong( sprintf( 'Invalid state 「%s」.', $value ), $where );
			return $fallback;
		}

		/**
		 * Heading level 1–6, default 2.
		 *
		 * @param mixed  $heading Caller value.
		 * @param string $where   Function name.
		 * @return int
		 */
		private static function heading( $heading, $where ) {
			$level = (int) $heading;
			if ( $level < 1 || $level > 6 ) {
				self::doing_it_wrong( 'heading must be 1–6.', $where );
				return 2;
			}
			return $level;
		}

		/**
		 * Extra class string from args['class'].
		 *
		 * @param array $args Args.
		 * @return string
		 */
		private static function extra_class( array $args ) {
			if ( ! isset( $args['class'] ) ) {
				return '';
			}
			if ( is_array( $args['class'] ) ) {
				return implode( ' ', $args['class'] );
			}
			return (string) $args['class'];
		}

		/**
		 * Join sanitized class names, dropping empties.
		 *
		 * @param array $parts Class fragments (strings or arrays).
		 * @return string
		 */
		private static function classes( array $parts ) {
			$out = array();
			foreach ( $parts as $p ) {
				$list = is_array( $p ) ? $p : preg_split( '/\s+/', trim( (string) $p ) );
				foreach ( (array) $list as $c ) {
					$c = sanitize_html_class( (string) $c );
					if ( '' !== $c && ! in_array( $c, $out, true ) ) {
						$out[] = $c;
					}
				}
			}
			return implode( ' ', $out );
		}

		/**
		 * Extra attributes: id, data-*, aria-*, role, hidden.
		 *
		 * @param array    $args Args with optional attrs.
		 * @param string[] $skip Names already printed.
		 * @return string
		 */
		private static function extra_attrs( array $args, array $skip = array() ) {
			if ( ! isset( $args['attrs'] ) || ! is_array( $args['attrs'] ) ) {
				return '';
			}
			$html = '';
			foreach ( $args['attrs'] as $name => $value ) {
				$name = strtolower( (string) $name );
				if ( in_array( $name, $skip, true ) ) {
					continue;
				}
				$ok = ( 'id' === $name || 'role' === $name || 'hidden' === $name
					|| 0 === strpos( $name, 'data-' ) || 0 === strpos( $name, 'aria-' ) );
				if ( ! $ok ) {
					continue;
				}
				if ( false === $value || null === $value ) {
					continue;
				}
				$bool = ( true === $value || '' === $value || ( 'hidden' === $name && $value ) );
				if ( $bool ) {
					$html .= ' ' . $name;
					continue;
				}
				$html .= ' ' . $name . '="' . esc_attr( is_scalar( $value ) ? (string) $value : '' ) . '"';
			}
			return $html;
		}

		/**
		 * ' id="…"' from $args[ $key ], or ''.
		 *
		 * @param array  $args Args.
		 * @param string $key  Arg holding the id.
		 * @return string
		 */
		private static function id_attr( array $args, $key ) {
			if ( ! isset( $args[ $key ] ) || '' === (string) $args[ $key ] ) {
				return '';
			}
			return ' id="' . esc_attr( (string) $args[ $key ] ) . '"';
		}

		/**
		 * Read keyed or positional item fields.
		 *
		 * @param array    $item Caller item.
		 * @param string[] $keys Field names in positional order.
		 * @return array
		 */
		private static function item_kv( array $item, array $keys ) {
			$out = array();
			foreach ( $keys as $i => $k ) {
				if ( array_key_exists( $k, $item ) ) {
					$out[ $k ] = $item[ $k ];
				} elseif ( array_key_exists( $i, $item ) ) {
					$out[ $k ] = $item[ $i ];
				} else {
					$out[ $k ] = '';
				}
			}
			return $out;
		}

		/**
		 * Inline SVG for a known icon name. Unknown names return ''.
		 *
		 * Icons::svg() falls back to info; helpers must not.
		 *
		 * @param string $name Icon name.
		 * @return string
		 */
		private static function icon_svg( $name ) {
			if ( ! class_exists( 'Wenpai_Admin_Icons', false ) || ! Wenpai_Admin_Icons::has( $name ) ) {
				return '';
			}
			return Wenpai_Admin_Icons::svg( (string) $name );
		}

		/**
		 * Developer notice, only with WP_DEBUG on.
		 *
		 * @param string $message Message.
		 * @param string $where   Function to name.
		 */
		private static function doing_it_wrong( $message, $where ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && function_exists( '_doing_it_wrong' ) ) {
				_doing_it_wrong( $where, $message, '0.3.4' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plain developer text; core escapes it when displaying.
			}
		}
	}
}
