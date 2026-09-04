<?php
/**
 * Dry-run / execute / rollback for 3.x → 4.0 option migration.
 *
 * @package WenPai\ChinaYes
 * @since   4.0.0
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Migration;

use WenPai\ChinaYes\Config\Repository;
use WenPai\ChinaYes\Config\Schema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Idempotent. Never writes 4.0 values into `wp_china_yes`.
 */
final class Runner {

	/**
	 * 3.x option reader.
	 *
	 * @var LegacyReader
	 */
	private LegacyReader $reader;

	/**
	 * §7.2 mapper.
	 *
	 * @var Mappers
	 */
	private Mappers $mappers;

	/**
	 * Backup writer.
	 *
	 * @var Backup
	 */
	private Backup $backup;

	/**
	 * 4.0 settings store.
	 *
	 * @var Repository
	 */
	private Repository $repository;

	/**
	 * Constructor.
	 *
	 * @since 4.0.0
	 *
	 * @param LegacyReader|null $reader     3.x reader.
	 * @param Mappers|null      $mappers    §7.2 table.
	 * @param Backup|null       $backup     Backup writer.
	 * @param Repository|null   $repository 4.0 store.
	 */
	public function __construct( $reader = null, $mappers = null, $backup = null, $repository = null ) {
		$this->reader     = $reader instanceof LegacyReader ? $reader : new LegacyReader();
		$this->mappers    = $mappers instanceof Mappers ? $mappers : new Mappers();
		$this->backup     = $backup instanceof Backup ? $backup : new Backup();
		$this->repository = $repository instanceof Repository ? $repository : new Repository();
	}

	/**
	 * Map without writing.
	 *
	 * @since 4.0.0
	 */
	public function dry_run(): Report {
		$legacy = $this->reader->read();
		return $this->map_legacy( $legacy );
	}

	/**
	 * Write 4.0 settings + backup. Leaves `wp_china_yes` untouched.
	 *
	 * Repeating with the same 3.x option overwrites 4.0 with the same document.
	 *
	 * @since 4.0.0
	 */
	public function execute(): Report {
		$legacy = $this->reader->read();
		$report = $this->map_legacy( $legacy );

		$this->backup->write(
			$this->from_version(),
			Backup::hash( $legacy ),
			$report->ignored()
		);

		$option = $this->reader->is_multisite() ? Schema::NETWORK_SETTINGS : Schema::SETTINGS;
		$this->repository->save_option( $option, $report->settings() );

		return $report;
	}

	/**
	 * Restore 4.0 options to pre-migration (delete or rewrite defaults).
	 *
	 * Does not write 3.x structure into `wp_china_yes`.
	 *
	 * @since 4.0.0
	 *
	 * @return bool False when no backup exists.
	 */
	public function rollback(): bool {
		$stored = $this->backup->read();
		if ( array() === $stored ) {
			return false;
		}

		if ( $this->reader->is_multisite() ) {
			$this->delete_option( Schema::NETWORK_SETTINGS, true );
		} else {
			$this->delete_option( Schema::SETTINGS, false );
		}

		$this->backup->delete();

		return true;
	}

	/**
	 * Map using site or network defaults.
	 *
	 * @param array<string, mixed> $legacy Raw `wp_china_yes`.
	 */
	private function map_legacy( array $legacy ): Report {
		if ( $this->reader->is_multisite() ) {
			return $this->mappers->map_network( $legacy );
		}
		return $this->mappers->map_site( $legacy );
	}

	/**
	 * Source version recorded on the backup.
	 *
	 * `wp_china_yes` has no version field; fixtures keep version in `_fixture`.
	 */
	private function from_version(): string {
		return '3.x';
	}

	/**
	 * Drop a 4.0 option. Missing delete_* is treated as success in unit tests.
	 *
	 * @param string $option      Option name.
	 * @param bool   $network     Use site_option APIs.
	 */
	private function delete_option( string $option, bool $network ): void {
		if ( $network ) {
			if ( function_exists( 'delete_site_option' ) ) {
				delete_site_option( $option );
			}
			return;
		}
		if ( function_exists( 'delete_option' ) ) {
			delete_option( $option );
		}
	}
}
