<?php
/**
 * Settings tools page: export, import, reset.
 *
 * @package WenpaiAdminUI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Wenpai_Admin_Tools', false ) ) {
	/**
	 * Three admin-post handlers and the three cards for one Settings instance.
	 */
	class Wenpai_Admin_Tools {

		const MAX_BYTES      = 262144;
		const FORMAT         = 'wenpai-settings';
		const FORMAT_VERSION = 1;

		/**
		 * Settings this instance serves.
		 *
		 * @var Wenpai_Admin_Settings
		 */
		private $settings;

		/**
		 * Product slug for the envelope and filename.
		 *
		 * @var string
		 */
		private $plugin;

		/**
		 * Product version written into the envelope; may be empty.
		 *
		 * @var string
		 */
		private $plugin_version;

		/**
		 * Capability required to run a handler.
		 *
		 * @var string
		 */
		private $capability;

		/**
		 * Attach the three admin-post handlers for this option.
		 *
		 * @param Wenpai_Admin_Settings $settings Settings instance.
		 * @param array                 $args     tools args from register(): plugin, plugin_version, capability.
		 */
		public function __construct( Wenpai_Admin_Settings $settings, array $args ) {
			$this->settings       = $settings;
			$this->plugin         = isset( $args['plugin'] ) ? sanitize_key( (string) $args['plugin'] ) : '';
			$this->plugin_version = isset( $args['plugin_version'] ) ? (string) $args['plugin_version'] : '';
			$cap                  = isset( $args['capability'] ) ? (string) $args['capability'] : 'manage_options';
			$this->capability     = '' !== $cap ? $cap : 'manage_options';
			if ( '' === $this->plugin || ! function_exists( 'add_action' ) ) {
				return;
			}
			add_action( 'admin_post_' . $this->hook( 'export' ), array( $this, 'handle_export' ) );
			add_action( 'admin_post_' . $this->hook( 'import' ), array( $this, 'handle_import' ) );
			add_action( 'admin_post_' . $this->hook( 'reset' ), array( $this, 'handle_reset' ) );
		}

		/**
		 * Admin-post / nonce action for one verb.
		 *
		 * @param string $which export|import|reset.
		 * @return string
		 */
		public function hook( $which ) {
			return 'wenpai_settings_' . $which . '_' . $this->settings->option();
		}

		/**
		 * Export envelope. values come from export() (no secret, no _schema).
		 *
		 * @return array
		 */
		public function envelope() {
			return array(
				'format'         => self::FORMAT,
				'format_version' => self::FORMAT_VERSION,
				'option'         => $this->settings->option(),
				'schema_version' => $this->settings->schema_version(),
				'plugin'         => $this->plugin,
				'plugin_version' => $this->plugin_version,
				'kit_version'    => $this->kit_version(),
				'exported_at'    => gmdate( 'Y-m-d\TH:i:s\Z' ),
				'values'         => $this->settings->export(),
			);
		}

		/**
		 * Download filename `{plugin}-settings-{Ymd-His}.json` in UTC.
		 *
		 * @return string
		 */
		public function filename() {
			return $this->plugin . '-settings-' . gmdate( 'Ymd-His' ) . '.json';
		}

		/**
		 * Import from a JSON string and byte size. Does not read $_FILES.
		 *
		 * @param string $body JSON text.
		 * @param int    $size Byte length of the upload.
		 * @return array{code: string, n?: int}
		 */
		public function apply_import( $body, $size ) {
			$size = (int) $size;
			if ( $size > self::MAX_BYTES || strlen( (string) $body ) > self::MAX_BYTES ) {
				return array( 'code' => 'import-size' );
			}
			$data = json_decode( (string) $body, true );
			if ( ! is_array( $data )
				|| ! isset( $data['format'] ) || self::FORMAT !== $data['format']
				|| ! isset( $data['format_version'] ) || self::FORMAT_VERSION !== $data['format_version']
				|| ! isset( $data['option'] ) || $this->settings->option() !== $data['option']
				|| ! isset( $data['values'] ) || ! is_array( $data['values'] ) ) {
				return array( 'code' => 'import-invalid' );
			}
			if ( ! isset( $data['schema_version'] ) || ! is_int( $data['schema_version'] ) || $data['schema_version'] < 1 ) {
				return array( 'code' => 'import-invalid' );
			}
			$file_ver = $data['schema_version'];
			$current  = $this->settings->schema_version();
			if ( $file_ver > $current ) {
				return array( 'code' => 'import-newer' );
			}
			$values = $data['values'];
			unset( $values[ Wenpai_Admin_Settings::SCHEMA_KEY ] );
			if ( $file_ver < $current ) {
				$up = $this->settings->run_upgrades( $values, $file_ver, $current );
				if ( ! $up['ok'] ) {
					return array( 'code' => 'import-invalid' );
				}
				$values = $up['values'];
			}
			$this->settings->import( $values );
			$errs = $this->settings->errors();
			if ( array() !== $errs ) {
				if ( function_exists( 'set_transient' ) && function_exists( 'get_settings_errors' ) ) {
					set_transient( 'settings_errors', get_settings_errors(), 30 );
				}
				return array(
					'code' => 'imported-partial',
					'n'    => count( $errs ),
				);
			}
			return array( 'code' => 'imported' );
		}

		/**
		 * Reset stored values to defaults. Does not read $_POST.
		 *
		 * @param bool $confirm       Whether the confirmation gate was checked.
		 * @param bool $clear_secrets Whether to clear saved secrets via CLEAR_KEY.
		 * @return string reset|reset-confirm
		 */
		public function apply_reset( $confirm, $clear_secrets ) {
			if ( ! $confirm ) {
				return 'reset-confirm';
			}
			$input = $this->settings->defaults();
			$clear = array();
			foreach ( $this->settings->fields() as $id => $f ) {
				if ( empty( $f['secret'] ) ) {
					continue;
				}
				unset( $input[ $id ] );
				if ( $clear_secrets ) {
					$input[ $id ] = '';
					$clear[]      = (string) $id;
				}
			}
			if ( array() !== $clear ) {
				$input[ Wenpai_Admin_Settings::CLEAR_KEY ] = $clear;
			}
			$this->settings->commit( $input );
			return 'reset';
		}

		/**
		 * Export handler: nonce, capability, download. No redirect.
		 */
		public function handle_export() {
			$this->guard( 'export' );
			$this->send_download( $this->envelope(), $this->filename() );
		}

		/**
		 * Import handler: nonce, capability, read upload, apply, redirect.
		 */
		public function handle_import() {
			$this->guard( 'import' );
			if ( ! $this->posted_flag( 'wenpai_confirm' ) ) {
				$this->redirect_with( 'import-confirm' );
				return;
			}
			$upload = $this->read_upload();
			if ( empty( $upload['ok'] ) ) {
				$this->redirect_with( 'import-invalid' );
				return;
			}
			$result = $this->apply_import( $upload['body'], $upload['size'] );
			$this->redirect_with( $result['code'], isset( $result['n'] ) ? $result['n'] : null );
		}

		/**
		 * Reset handler: nonce, capability, apply, redirect.
		 */
		public function handle_reset() {
			$this->guard( 'reset' );
			$this->redirect_with( $this->apply_reset( $this->posted_flag( 'wenpai_confirm' ), $this->posted_flag( 'wenpai_clear_secrets' ) ) );
		}

		/**
		 * Whether a checkbox posted value 1. Call after guard().
		 *
		 * @param string $key POST key.
		 * @return bool
		 */
		private function posted_flag( $key ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce checked in guard().
			return isset( $_POST[ $key ] ) && '1' === sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
		}

		/**
		 * Markup for the three cards.
		 *
		 * @param array $args reset_confirm, reset_note.
		 * @return string
		 */
		public function cards_html( array $args = array() ) {
			$s    = $this->settings->strings();
			$args = wp_parse_args(
				$args,
				array(
					'reset_confirm' => isset( $s['reset_confirm'] ) ? $s['reset_confirm'] : '',
					'reset_note'    => '',
				)
			);
			return $this->export_card_html( $s ) . $this->import_card_html( $s ) . $this->reset_card_html( $s, $args );
		}

		/**
		 * Read the uploaded file. Tests replace this.
		 *
		 * @return array{ok: bool, size: int, body: string}
		 */
		protected function read_upload() {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce checked in handle_import().
			if ( ! isset( $_FILES['wenpai_settings_file'] ) || ! is_array( $_FILES['wenpai_settings_file'] ) ) {
				return array(
					'ok'   => false,
					'size' => 0,
					'body' => '',
				);
			}

			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- PHP upload array; only error, tmp_name and size are read. Nonce checked in handle_import().
			$file = $_FILES['wenpai_settings_file'];
			$err  = isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
			if ( UPLOAD_ERR_OK !== $err ) {
				return array(
					'ok'   => false,
					'size' => 0,
					'body' => '',
				);
			}
			$tmp = isset( $file['tmp_name'] ) ? (string) $file['tmp_name'] : '';
			if ( '' === $tmp || ! is_uploaded_file( $tmp ) ) {
				return array(
					'ok'   => false,
					'size' => 0,
					'body' => '',
				);
			}
			$size = filesize( $tmp );
			$size = false === $size ? 0 : (int) $size;
			if ( $size > self::MAX_BYTES ) {
				return array(
					'ok'   => true,
					'size' => $size,
					'body' => '',
				);
			}
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPress.Security.NonceVerification.Missing -- Local PHP upload tmp file; nonce checked in handle_import().
			$body = file_get_contents( $tmp );
			if ( ! is_string( $body ) ) {
				return array(
					'ok'   => false,
					'size' => $size,
					'body' => '',
				);
			}
			return array(
				'ok'   => true,
				'size' => $size,
				'body' => $body,
			);
		}

		/**
		 * Send the JSON download. Tests replace this.
		 *
		 * @param array  $envelope Envelope.
		 * @param string $filename Attachment name.
		 */
		protected function send_download( $envelope, $filename ) {
			if ( function_exists( 'nocache_headers' ) ) {
				nocache_headers();
			}
			header( 'Content-Type: application/json; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="' . str_replace( array( '"', "\r", "\n" ), '', (string) $filename ) . '"' );
			echo wp_json_encode( $envelope, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON download body.
			exit;
		}

		/**
		 * Redirect back with wenpai_tools* query args. Tests replace this.
		 *
		 * @param string   $code Result code.
		 * @param int|null $n    Invalid-field count for imported-partial.
		 */
		protected function redirect_with( $code, $n = null ) {
			$ref = function_exists( 'wp_get_referer' ) ? wp_get_referer() : false;
			if ( ! $ref ) {
				$ref = admin_url();
			}
			$ref  = remove_query_arg( array( 'wenpai_tools', 'wenpai_tools_option', 'wenpai_tools_n' ), $ref );
			$args = array(
				'wenpai_tools'        => $code,
				'wenpai_tools_option' => $this->settings->option(),
			);
			if ( null !== $n ) {
				$args['wenpai_tools_n'] = (int) $n;
			}
			wp_safe_redirect( add_query_arg( $args, $ref ) );
			exit;
		}

		/**
		 * Capability + nonce, or wp_die 403.
		 *
		 * @param string $which export|import|reset.
		 */
		protected function guard( $which ) {
			if ( ! function_exists( 'current_user_can' ) || ! current_user_can( $this->capability ) ) {
				$this->deny();
			}
			$nonce = '';
			if ( isset( $_REQUEST['_wpnonce'] ) && is_scalar( $_REQUEST['_wpnonce'] ) ) {
				$nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) );
			}
			if ( ! function_exists( 'wp_verify_nonce' ) || false === wp_verify_nonce( $nonce, $this->hook( $which ) ) ) {
				$this->deny();
			}
		}

		/**
		 * Refuse the request without writing.
		 */
		protected function deny() {
			$s   = $this->settings->strings();
			$msg = isset( $s['action_denied'] ) ? $s['action_denied'] : '';
			wp_die( $msg, 403 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_die escapes; copy from default_strings.
		}

		/**
		 * Kit VERSION of the winning copy, else this copy's VERSION file.
		 *
		 * @return string
		 */
		private function kit_version() {
			if ( function_exists( 'wenpai_admin_ui_info' ) ) {
				$info = wenpai_admin_ui_info();
				if ( ! empty( $info['version'] ) ) {
					return (string) $info['version'];
				}
			}
			$file = dirname( __DIR__ ) . '/VERSION';
			if ( is_readable( $file ) ) {
				return trim( (string) file_get_contents( $file ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local VERSION file shipped with the kit.
			}
			return '';
		}

		/**
		 * Whether at least one secret field has a stored non-empty value.
		 *
		 * @return bool
		 */
		private function has_saved_secrets() {
			$stored = get_option( $this->settings->option(), array() );
			if ( ! is_array( $stored ) ) {
				return false;
			}
			foreach ( $this->settings->fields() as $id => $f ) {
				if ( empty( $f['secret'] ) ) {
					continue;
				}
				if ( isset( $stored[ $id ] ) && is_scalar( $stored[ $id ] ) && '' !== (string) $stored[ $id ] ) {
					return true;
				}
			}
			return false;
		}

		/**
		 * Load Wenpai_Admin_UI when Tools was required first.
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
		 * Open a POST form to admin-post.php.
		 *
		 * @param string $which      export|import|reset.
		 * @param bool   $multipart  Import needs enctype.
		 * @return string
		 */
		private function form_open( $which, $multipart = false ) {
			$action = $this->hook( $which );
			$html   = '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"';
			if ( $multipart ) {
				$html .= ' enctype="multipart/form-data"';
			}
			$html .= '>';
			$html .= '<input type="hidden" name="action" value="' . esc_attr( $action ) . '">';
			// Three forms share a page: no wp_nonce_field(), whose fixed id="_wpnonce" would repeat.
			$html .= '<input type="hidden" name="_wpnonce" value="' . esc_attr( wp_create_nonce( $action ) ) . '">';
			if ( function_exists( 'wp_referer_field' ) ) {
				$html .= wp_referer_field( false );
			}
			return $html;
		}

		/**
		 * Export card.
		 *
		 * @param array $s Strings.
		 * @return string
		 */
		private function export_card_html( array $s ) {
			self::ensure_ui();
			$html  = Wenpai_Admin_UI::card_open_html(
				array(
					'title' => $s['tools_export_title'],
					'sub'   => $s['tools_export_sub'],
					'icon'  => 'download',
					'attrs' => array( 'id' => 'wenpai-tools-export' ),
				)
			);
			$html .= $this->form_open( 'export' );
			$html .= Wenpai_Admin_UI::card_foot_html(
				array(
					'actions' => array(
						array(
							'label'   => $s['tools_export_button'],
							'type'    => 'submit',
							'variant' => 'secondary',
						),
					),
				)
			);
			$html .= '</form>' . Wenpai_Admin_UI::card_close_html();
			return $html;
		}

		/**
		 * Import card.
		 *
		 * @param array $s Strings.
		 * @return string
		 */
		private function import_card_html( array $s ) {
			self::ensure_ui();
			$fid   = 'wenpai-tools-import-file';
			$gid   = 'wenpai-tools-import-gate';
			$html  = Wenpai_Admin_UI::card_open_html(
				array(
					'title' => $s['tools_import_title'],
					'sub'   => $s['tools_import_sub'],
					'icon'  => 'cloud',
					'attrs' => array( 'id' => 'wenpai-tools-import' ),
				)
			);
			$html .= $this->form_open( 'import', true );
			$html .= '<div class="field"><label class="field-label" for="' . esc_attr( $fid ) . '">' . esc_html( $s['tools_import_file'] ) . '</label>';
			$html .= '<div class="field-ctl"><input id="' . esc_attr( $fid ) . '" type="file" name="wenpai_settings_file" accept=".json,application/json"></div></div>';
			// No disabled attributes here: kit JS locks the gate and button on load; without JS the server checks the gate.
			// Need-file hint is import-only; gate() has no slot for it, so it is inserted next to the label.
			$gate  = Wenpai_Admin_UI::gate_html(
				array(
					'name'    => 'wenpai_confirm',
					'label'   => $s['tools_import_confirm'],
					'text_id' => $gid,
				)
			);
			$hint  = ' <span class="meta" data-wenpai-need-file hidden>' . esc_html( $s['tools_import_need_file'] ) . '</span>';
			$html .= str_replace( '</label>', $hint . '</label>', $gate );
			$html .= Wenpai_Admin_UI::card_foot_html(
				array(
					'actions' => array(
						array(
							'label'      => $s['tools_import_button'],
							'type'       => 'submit',
							'variant'    => 'primary',
							'need_apply' => true,
							'attrs'      => array( 'aria-describedby' => $gid ),
						),
					),
				)
			);
			$html .= '</form>' . Wenpai_Admin_UI::card_close_html();
			return $html;
		}

		/**
		 * Reset card.
		 *
		 * @param array $s    Strings.
		 * @param array $args reset_confirm, reset_note.
		 * @return string
		 */
		private function reset_card_html( array $s, array $args ) {
			self::ensure_ui();
			$gid  = 'wenpai-tools-reset-gate';
			$sub  = isset( $s['tools_reset_sub'] ) ? $s['tools_reset_sub'] : '';
			$link = '<a href="#wenpai-tools-export">' . esc_html( $s['tools_reset_export'] ) . '</a>';
			if ( false !== strpos( $sub, '%s' ) ) {
				$parts    = explode( '%s', $sub, 2 );
				$sub_html = esc_html( $parts[0] ) . $link . esc_html( isset( $parts[1] ) ? $parts[1] : '' );
			} else {
				$sub_html = esc_html( $sub );
			}
			$html  = Wenpai_Admin_UI::card_open_html(
				array(
					'title'    => $s['tools_reset_title'],
					'sub_html' => $sub_html,
					'icon'     => 'info',
					'state'    => 'bad',
					'attrs'    => array( 'id' => 'wenpai-tools-reset' ),
				)
			);
			$html .= $this->form_open( 'reset' );
			$html .= Wenpai_Admin_UI::gate_html(
				array(
					'name'    => 'wenpai_confirm',
					'label'   => $args['reset_confirm'],
					'text_id' => $gid,
					'note'    => (string) $args['reset_note'],
				)
			);
			if ( $this->has_saved_secrets() ) {
				$html .= '<div class="chk"><label><input type="checkbox" name="wenpai_clear_secrets" value="1"> ' . esc_html( $s['tools_reset_clear'] ) . '</label></div>';
			}
			$html .= Wenpai_Admin_UI::card_foot_html(
				array(
					'actions' => array(
						array(
							'label'      => $s['tools_reset_button'],
							'type'       => 'submit',
							'variant'    => 'danger',
							'need_apply' => true,
							'busy_label' => $s['tools_reset_busy'],
							'attrs'      => array( 'aria-describedby' => $gid ),
						),
					),
				)
			);
			$html .= '</form>' . Wenpai_Admin_UI::card_close_html();
			return $html;
		}
	}
}
