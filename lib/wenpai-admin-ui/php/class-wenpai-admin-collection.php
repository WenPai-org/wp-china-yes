<?php
/**
 * List CRUD skeleton (Track D / E08 · 0.4): option-storage items via Action.
 *
 * Full WP_List_Table skin, table/cpt storage, and bulk delete are deferred.
 * Fold / endpoint-like rows: compose Card (UI helpers); never invent EndpointCard.
 *
 * @package Wenpai_Admin_UI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Wenpai_Admin_Collection', false ) ) {

	/**
	 * Register a thin option-backed collection with save/delete Actions.
	 */
	class Wenpai_Admin_Collection {

		/**
		 * Registered collections: id => instance.
		 *
		 * @var array<string, self>
		 */
		private static $registry = array();

		/**
		 * Collection identifier.
		 *
		 * @var string
		 */
		private $id;

		/**
		 * Storage backend; only option storage is implemented in this slice.
		 *
		 * @var string option|table|cpt
		 */
		private $storage;

		/**
		 * Option name used for option-backed storage.
		 *
		 * @var string
		 */
		private $option;

		/**
		 * Capability required to save or delete items.
		 *
		 * @var string
		 */
		private $capability;

		/**
		 * Nested field schema (same shape as Settings field children).
		 *
		 * @var array<string, array>
		 */
		private $fields;

		/**
		 * User-facing labels.
		 *
		 * @var array<string, string>
		 */
		private $labels;

		/**
		 * Register a thin option-backed collection with save/delete Actions.
		 *
		 * @param array $args {
		 *     Collection registration arguments.
		 *     @type string $id         Required.
		 *     @type string $storage    option|table|cpt. Only option is wired.
		 *     @type string $option     Option name when storage=option (default wenpai_col_{id}).
		 *     @type array  $fields     Item field schema.
		 *     @type string $capability Default manage_options.
		 *     @type array  $labels     list_title, add, edit, save, delete, delete_confirm, empty.
		 * }
		 * @return self|null
		 */
		public static function register( array $args ) {
			$id = isset( $args['id'] ) ? sanitize_key( (string) $args['id'] ) : '';
			if ( '' === $id ) {
				if ( function_exists( '_doing_it_wrong' ) ) {
					_doing_it_wrong( __METHOD__, 'id is required.', '0.4.0' );
				}
				return null;
			}
			if ( isset( self::$registry[ $id ] ) ) {
				return self::$registry[ $id ];
			}
			$storage = isset( $args['storage'] ) ? sanitize_key( (string) $args['storage'] ) : 'option';
			if ( ! in_array( $storage, array( 'option', 'table', 'cpt' ), true ) ) {
				$storage = 'option';
			}
			$option                = isset( $args['option'] ) ? sanitize_key( (string) $args['option'] ) : 'wenpai_col_' . $id;
			$fields                = isset( $args['fields'] ) && is_array( $args['fields'] ) ? $args['fields'] : array();
			$cap                   = isset( $args['capability'] ) ? (string) $args['capability'] : 'manage_options';
			$labels                = wp_parse_args(
				isset( $args['labels'] ) && is_array( $args['labels'] ) ? $args['labels'] : array(),
				array(
					'list_title'     => '条目',
					'add'            => '新增',
					'edit'           => '编辑',
					'save'           => '保存',
					'delete'         => '删除',
					'delete_confirm' => '确认删除该条目',
					'empty'          => '还没有条目。',
					'item_fallback'  => '未命名',
				)
			);
			$inst                  = new self( $id, $storage, $option, $fields, $cap, $labels );
			self::$registry[ $id ] = $inst;
			$inst->hook_actions();
			return $inst;
		}

		/**
		 * Get a registered collection by id.
		 *
		 * @param string $id Collection id.
		 * @return self|null
		 */
		public static function get( $id ) {
			$id = sanitize_key( (string) $id );
			return isset( self::$registry[ $id ] ) ? self::$registry[ $id ] : null;
		}

		/**
		 * Initialize a collection definition.
		 *
		 * @param string               $id         Collection id.
		 * @param string               $storage    Storage backend.
		 * @param string               $option     Option name.
		 * @param array<string,array>  $fields     Field schema.
		 * @param string               $capability Required capability.
		 * @param array<string,string> $labels     User-facing labels.
		 */
		private function __construct( $id, $storage, $option, array $fields, $capability, array $labels ) {
			$this->id         = $id;
			$this->storage    = $storage;
			$this->option     = $option;
			$this->fields     = $fields;
			$this->capability = $capability;
			$this->labels     = $labels;
		}

		/**
		 * Return the collection id.
		 *
		 * @return string
		 */
		public function id() {
			return $this->id;
		}

		/**
		 * Return the storage backend.
		 *
		 * @return string
		 */
		public function storage() {
			return $this->storage;
		}

		/**
		 * Return the option name.
		 *
		 * @return string
		 */
		public function option() {
			return $this->option;
		}

		/**
		 * Return the field schema.
		 *
		 * @return array<string, array>
		 */
		public function fields() {
			return $this->fields;
		}

		/**
		 * All items as id => data (option storage only).
		 *
		 * @return array<string, array>
		 */
		public function items() {
			if ( 'option' !== $this->storage ) {
				return array();
			}
			$raw = function_exists( 'get_option' ) ? get_option( $this->option, array() ) : array();
			if ( ! is_array( $raw ) ) {
				return array();
			}
			$out = array();
			foreach ( $raw as $iid => $row ) {
				$iid = sanitize_key( (string) $iid );
				if ( '' === $iid || ! is_array( $row ) ) {
					continue;
				}
				$out[ $iid ] = $row;
			}
			return $out;
		}

		/**
		 * Get one item by id.
		 *
		 * @param string $item_id Item id.
		 * @return array|null
		 */
		public function get_item( $item_id ) {
			$item_id = sanitize_key( (string) $item_id );
			$items   = $this->items();
			return isset( $items[ $item_id ] ) ? $items[ $item_id ] : null;
		}

		/**
		 * Sanitize one item against the collection field schema (text/url/email/toggle/number/select).
		 *
		 * @param mixed $raw Raw posted row.
		 * @return array
		 */
		public function sanitize_item( $raw ) {
			$raw = is_array( $raw ) ? $raw : array();
			$out = array();
			foreach ( $this->fields as $fid => $f ) {
				$f    = is_array( $f ) ? $f : array();
				$type = isset( $f['type'] ) ? (string) $f['type'] : 'text';
				$def  = array_key_exists( 'default', $f ) ? $f['default'] : ( 'toggle' === $type ? false : ( 'number' === $type ? 0 : '' ) );
				$v    = array_key_exists( $fid, $raw ) ? $raw[ $fid ] : $def;
				switch ( $type ) {
					case 'toggle':
						$out[ $fid ] = ! empty( $v );
						break;
					case 'number':
						$n = null === $v || '' === $v ? (int) $def : (int) $v;
						if ( isset( $f['min'] ) ) {
							$n = max( (int) $f['min'], $n );
						}
						if ( isset( $f['max'] ) ) {
							$n = min( (int) $f['max'], $n );
						}
						$out[ $fid ] = $n;
						break;
					case 'email':
						if ( null === $v || '' === $v ) {
							$out[ $fid ] = '';
						} elseif ( ! is_scalar( $v ) ) {
							$out[ $fid ] = $def;
						} else {
							$out[ $fid ] = function_exists( 'sanitize_email' ) ? sanitize_email( (string) $v ) : sanitize_text_field( (string) $v );
						}
						break;
					case 'url':
						if ( null === $v || '' === $v ) {
							$out[ $fid ] = '';
						} elseif ( ! is_scalar( $v ) ) {
							$out[ $fid ] = $def;
						} else {
							$out[ $fid ] = function_exists( 'esc_url_raw' )
								? esc_url_raw( (string) $v, array( 'http', 'https' ) )
								: sanitize_text_field( (string) $v );
						}
						break;
					case 'select':
						$v           = is_scalar( $v ) ? (string) $v : '';
						$choices     = isset( $f['choices'] ) && is_array( $f['choices'] ) ? $f['choices'] : array();
						$out[ $fid ] = array_key_exists( $v, $choices ) ? $v : $def;
						break;
					default:
						$out[ $fid ] = null === $v || ! is_scalar( $v ) ? $def : sanitize_text_field( (string) $v );
				}
			}
			return $out;
		}

		/**
		 * Persist an item (create when $item_id empty). Option storage only.
		 *
		 * @param string      $item_id Existing id or ''.
		 * @param array|mixed $data   Raw data.
		 * @return string|false New/existing id, or false on unsupported storage.
		 */
		public function save_item( $item_id, $data ) {
			if ( 'option' !== $this->storage ) {
				return false;
			}
			$clean   = $this->sanitize_item( $data );
			$items   = $this->items();
			$item_id = sanitize_key( (string) $item_id );
			if ( '' === $item_id ) {
				$item_id = function_exists( 'wp_generate_password' )
					? strtolower( wp_generate_password( 8, false, false ) )
					: substr( md5( wp_json_encode( $clean ) . microtime( true ) ), 0, 8 );
			}
			$items[ $item_id ] = $clean;
			update_option( $this->option, $items, false );
			return $item_id;
		}

		/**
		 * Delete an item from option-backed storage.
		 *
		 * @param string $item_id Item id.
		 * @return bool
		 */
		public function delete_item( $item_id ) {
			if ( 'option' !== $this->storage ) {
				return false;
			}
			$item_id = sanitize_key( (string) $item_id );
			$items   = $this->items();
			if ( ! isset( $items[ $item_id ] ) ) {
				return false;
			}
			unset( $items[ $item_id ] );
			update_option( $this->option, $items, false );
			return true;
		}

		/**
		 * Simple list markup (no WP_List_Table). Card composition for fold rows is caller's job.
		 *
		 * @param array $args Optional. 'edit_url' sprintf with %s = item id; 'new_url'.
		 * @return string
		 */
		public function list_html( array $args = array() ) {
			$edit  = isset( $args['edit_url'] ) ? (string) $args['edit_url'] : '';
			$new   = isset( $args['new_url'] ) ? (string) $args['new_url'] : '';
			$items = $this->items();
			$html  = '<div class="wenpai-collection" data-wenpai-collection="' . esc_attr( $this->id ) . '">';
			$html .= '<div class="wenpai-collection__toolbar">';
			if ( '' !== $new ) {
				$html .= '<a class="btn btn-primary" href="' . esc_url( $new ) . '">' . esc_html( $this->labels['add'] ) . '</a>';
			}
			$html .= '</div>';
			if ( array() === $items ) {
				if ( class_exists( 'Wenpai_Admin_UI', false ) ) {
					$html .= Wenpai_Admin_UI::empty_state_html(
						array(
							'text' => $this->labels['empty'],
						)
					);
				} else {
					$html .= '<p class="empty">' . esc_html( $this->labels['empty'] ) . '</p>';
				}
				return $html . '</div>';
			}
			$html .= '<ul class="wenpai-collection__list">';
			foreach ( $items as $iid => $row ) {
				$title = $this->item_title( $row );
				$html .= '<li class="wenpai-collection__item">';
				$html .= '<span class="t">' . esc_html( $title ) . '</span>';
				if ( '' !== $edit ) {
					$url   = sprintf( $edit, rawurlencode( $iid ) );
					$html .= ' <a class="btn btn-ghost" href="' . esc_url( $url ) . '">' . esc_html( $this->labels['edit'] ) . '</a>';
				}
				$html .= $this->delete_form_html( $iid );
				$html .= '</li>';
			}
			return $html . '</ul></div>';
		}

		/**
		 * Add/edit form from the same schema. Save via Action.
		 *
		 * @param string $item_id '' for new.
		 * @return string
		 */
		public function form_html( $item_id = '' ) {
			$item_id = sanitize_key( (string) $item_id );
			$row     = '' !== $item_id ? $this->get_item( $item_id ) : null;
			$row     = is_array( $row ) ? $row : array();
			$action  = $this->save_action();
			$url     = function_exists( 'admin_url' ) ? admin_url( 'admin-post.php' ) : 'admin-post.php';
			$html    = '<form method="post" action="' . esc_url( $url ) . '" class="wenpai-collection__form" data-wenpai-collection-form="' . esc_attr( $this->id ) . '">';
			if ( class_exists( 'Wenpai_Admin_Action', false ) ) {
				$html .= Wenpai_Admin_Action::fields_html( $action );
			}
			$html .= '<input type="hidden" name="item" value="' . esc_attr( $item_id ) . '">';
			foreach ( $this->fields as $fid => $f ) {
				$f     = is_array( $f ) ? $f : array();
				$type  = isset( $f['type'] ) ? (string) $f['type'] : 'text';
				$label = isset( $f['label'] ) ? (string) $f['label'] : $fid;
				$def   = array_key_exists( 'default', $f ) ? $f['default'] : '';
				$val   = array_key_exists( $fid, $row ) ? $row[ $fid ] : $def;
				$name  = 'wenpai_item[' . $fid . ']';
				$fid_h = 'wenpai-col-' . $this->id . '-' . $fid;
				$html .= '<div class="wenpai-field field wenpai-field--row">';
				$html .= '<div class="field-label"><label for="' . esc_attr( $fid_h ) . '">' . esc_html( $label ) . '</label></div>';
				$html .= '<div class="field-ctl">';
				if ( 'toggle' === $type ) {
					$html .= '<input type="hidden" name="' . esc_attr( $name ) . '" value="0">';
					$html .= '<input type="checkbox" id="' . esc_attr( $fid_h ) . '" name="' . esc_attr( $name ) . '" value="1"' . checked( ! empty( $val ), true, false ) . '>';
				} elseif ( 'textarea' === $type ) {
					$html .= '<textarea id="' . esc_attr( $fid_h ) . '" name="' . esc_attr( $name ) . '" rows="3">' . esc_textarea( is_scalar( $val ) ? (string) $val : '' ) . '</textarea>';
				} elseif ( 'number' === $type ) {
					$html .= '<input type="number" id="' . esc_attr( $fid_h ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( is_scalar( $val ) ? $val : 0 ) . '">';
				} elseif ( 'email' === $type ) {
					$html .= '<input type="email" id="' . esc_attr( $fid_h ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( is_scalar( $val ) ? $val : '' ) . '">';
				} elseif ( 'url' === $type ) {
					$html .= '<input type="url" id="' . esc_attr( $fid_h ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( is_scalar( $val ) ? $val : '' ) . '">';
				} elseif ( 'select' === $type ) {
					$html   .= '<select id="' . esc_attr( $fid_h ) . '" name="' . esc_attr( $name ) . '">';
					$choices = isset( $f['choices'] ) && is_array( $f['choices'] ) ? $f['choices'] : array();
					foreach ( $choices as $k => $lab ) {
						$html .= '<option value="' . esc_attr( $k ) . '"' . selected( (string) $val, (string) $k, false ) . '>' . esc_html( is_array( $lab ) ? (string) $lab['label'] : (string) $lab ) . '</option>';
					}
					$html .= '</select>';
				} else {
					$html .= '<input type="text" id="' . esc_attr( $fid_h ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( is_scalar( $val ) ? $val : '' ) . '">';
				}
				$html .= '</div></div>';
			}
			$html .= '<p><button type="submit" class="btn btn-primary">' . esc_html( $this->labels['save'] ) . '</button></p>';
			return $html . '</form>';
		}

		/**
		 * Delete form with confirm gate (Action).
		 *
		 * @param string $item_id Item id.
		 * @return string
		 */
		public function delete_form_html( $item_id ) {
			$item_id = sanitize_key( (string) $item_id );
			$action  = $this->delete_action();
			$url     = function_exists( 'admin_url' ) ? admin_url( 'admin-post.php' ) : 'admin-post.php';
			$html    = '<form method="post" action="' . esc_url( $url ) . '" class="wenpai-collection__delete">';
			if ( class_exists( 'Wenpai_Admin_Action', false ) ) {
				$html .= Wenpai_Admin_Action::fields_html( $action );
			}
			$html   .= '<input type="hidden" name="item" value="' . esc_attr( $item_id ) . '">';
			$confirm = 'wenpai_confirm_' . $this->id;
			$html   .= '<label><input type="checkbox" name="' . esc_attr( $confirm ) . '" value="1"> ' . esc_html( $this->labels['delete_confirm'] ) . '</label> ';
			$html   .= '<button type="submit" class="btn btn-ghost">' . esc_html( $this->labels['delete'] ) . '</button>';
			return $html . '</form>';
		}

		/**
		 * Return the registered save action name.
		 *
		 * @return string
		 */
		public function save_action() {
			return 'wenpai_col_save_' . $this->id;
		}

		/**
		 * Return the registered delete action name.
		 *
		 * @return string
		 */
		public function delete_action() {
			return 'wenpai_col_delete_' . $this->id;
		}

		/**
		 * Choose a human-readable title from an item row.
		 *
		 * @param array $row Item data.
		 * @return string
		 */
		private function item_title( array $row ) {
			foreach ( array( 'name', 'title', 'label', 'url' ) as $k ) {
				if ( isset( $row[ $k ] ) && is_scalar( $row[ $k ] ) && '' !== (string) $row[ $k ] ) {
					return (string) $row[ $k ];
				}
			}
			return $this->labels['item_fallback'];
		}

		/**
		 * Hook save/delete Actions (option storage only).
		 */
		private function hook_actions() {
			if ( 'option' !== $this->storage || ! class_exists( 'Wenpai_Admin_Action', false ) ) {
				return;
			}
			$self = $this;
			Wenpai_Admin_Action::register(
				array(
					'action'     => $this->save_action(),
					'capability' => $this->capability,
					'item'       => false,
					'callback'   => function () use ( $self ) {
						// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Action envelope verified nonce.
						$item = isset( $_POST['item'] ) ? sanitize_key( wp_unslash( (string) $_POST['item'] ) ) : '';
						// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Action verified nonce; sanitize_item() sanitizes every field.
						$raw  = isset( $_POST['wenpai_item'] ) && is_array( $_POST['wenpai_item'] ) ? wp_unslash( $_POST['wenpai_item'] ) : array();
						$id   = $self->save_item( $item, $raw );
						if ( false === $id ) {
							return array(
								'level'   => 'bad',
								'message' => '暂时无法保存。',
							);
						}
						return array(
							'level'   => 'ok',
							'message' => '已保存。',
						);
					},
				)
			);
			$confirm = 'wenpai_confirm_' . $this->id;
			Wenpai_Admin_Action::register(
				array(
					'action'     => $this->delete_action(),
					'capability' => $this->capability,
					'confirm'    => $confirm,
					'item'       => true,
					'callback'   => function ( $ctx ) use ( $self ) {
						$item = isset( $ctx['item'] ) ? (string) $ctx['item'] : '';
						$ok   = $self->delete_item( $item );
						return $ok
							? array(
								'level'   => 'ok',
								'message' => '已删除。',
							)
							: array(
								'level'   => 'bad',
								'message' => '暂时无法删除。',
							);
					},
				)
			);
		}
	}
}
