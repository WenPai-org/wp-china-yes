<?php
/**
 * Module registration, dependency order, scene filter, failure isolation.
 *
 * @package WenPai\ChinaYes
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Core;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Host for kernel modules. A throwing module does not stop the rest.
 */
final class ModuleRegistry {

	/**
	 * Modules keyed by id(), insertion order.
	 *
	 * @var array<string, Module>
	 */
	private $modules = array();

	/**
	 * Config read model used by ConditionalModule::enabled().
	 *
	 * @var Config
	 */
	private $config;

	/**
	 * Current request scene.
	 *
	 * @var Environment
	 */
	private $environment;

	/**
	 * Kernel logger.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Failures keyed by module id. Never discarded after a log line.
	 *
	 * @var array<string, list<Throwable>>
	 */
	private $failures = array();

	/**
	 * Create a registry.
	 *
	 * @param Config      $config      Config read model (M1-03 Repository later).
	 * @param Environment $environment Current request scene.
	 * @param Logger      $logger      Kernel logger.
	 */
	public function __construct( Config $config, Environment $environment, Logger $logger ) {
		$this->config      = $config;
		$this->environment = $environment;
		$this->logger      = $logger;
	}

	/**
	 * Add a module. Duplicate ids are rejected.
	 *
	 * @param Module $module Module to host.
	 *
	 * @throws InvalidArgumentException If id() is empty or already registered.
	 */
	public function add( Module $module ): void {
		$id = $module->id();
		if ( '' === $id ) {
			throw new InvalidArgumentException( 'Module id must not be empty.' );
		}
		if ( isset( $this->modules[ $id ] ) ) {
			throw new InvalidArgumentException( sprintf( 'Duplicate module id: %s', $id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- exception message, not HTML output.
		}

		$this->modules[ $id ] = $module;
	}

	/**
	 * Register modules for a request scene.
	 *
	 * Order is dependencies() topological. Scene and enabled() filter
	 * before register(). Throwable from register() is logged and recorded.
	 *
	 * @param string $scene One of Environment::CONTEXTS.
	 */
	public function boot( string $scene ): void {
		foreach ( $this->topological_sort() as $module ) {
			if ( ! in_array( $scene, $this->contexts_of( $module ), true ) ) {
				continue;
			}

			if ( $module instanceof ConditionalModule
				&& ! $module->enabled( $this->config, $this->environment )
			) {
				continue;
			}

			try {
				$module->register();
			} catch ( Throwable $error ) {
				$this->logger->error(
					sprintf( 'Module register() failed: %s', $module->id() ),
					array(
						'module'    => $module->id(),
						'exception' => $error->getMessage(),
					)
				);
				$this->record_failure( $module->id(), $error );
			}
		}
	}

	/**
	 * Recorded register() / graph failures, keyed by module id.
	 *
	 * @return array<string, list<Throwable>>
	 */
	public function failures(): array {
		return $this->failures;
	}

	/**
	 * Registered modules in insertion order, before sorting.
	 *
	 * @return array<string, Module>
	 */
	public function modules(): array {
		return $this->modules;
	}

	/**
	 * Kahn topological sort by dependencies(). Missing edges and cycles are recorded.
	 *
	 * @return list<Module>
	 */
	private function topological_sort(): array {
		$incoming = array();
		$outgoing = array();
		$blocked  = array();

		foreach ( $this->modules as $id => $module ) {
			unset( $module );
			$incoming[ $id ] = 0;
			$outgoing[ $id ] = array();
		}

		foreach ( $this->modules as $id => $module ) {
			foreach ( $this->dependencies_of( $module ) as $dependency ) {
				if ( ! isset( $this->modules[ $dependency ] ) ) {
					$blocked[ $id ] = true;
					$error          = new RuntimeException(
						sprintf(
							'Module "%s" depends on missing module "%s".',
							$id,
							$dependency
						)
					);
					$this->logger->error(
						$error->getMessage(),
						array(
							'module'     => $id,
							'dependency' => $dependency,
						)
					);
					$this->record_failure( $id, $error );
					continue;
				}

				$outgoing[ $dependency ][] = $id;
				++$incoming[ $id ];
			}
		}

		$queue = array();
		foreach ( $this->modules as $id => $module ) {
			unset( $module );
			if ( 0 === $incoming[ $id ] && ! isset( $blocked[ $id ] ) ) {
				$queue[] = $id;
			}
		}

		$sorted = array();
		while ( array() !== $queue ) {
			$id       = array_shift( $queue );
			$sorted[] = $this->modules[ $id ];

			foreach ( $outgoing[ $id ] as $dependent ) {
				if ( isset( $blocked[ $dependent ] ) ) {
					continue;
				}
				--$incoming[ $dependent ];
				if ( 0 === $incoming[ $dependent ] ) {
					$queue[] = $dependent;
				}
			}
		}

		$sorted_ids = array();
		foreach ( $sorted as $module ) {
			$sorted_ids[ $module->id() ] = true;
		}

		foreach ( $this->modules as $id => $module ) {
			unset( $module );
			if ( isset( $sorted_ids[ $id ] ) || isset( $blocked[ $id ] ) ) {
				continue;
			}

			$error = new RuntimeException(
				sprintf( 'Module "%s" dependencies did not resolve (missing or cycle).', $id )
			);
			$this->logger->error(
				$error->getMessage(),
				array( 'module' => $id )
			);
			$this->record_failure( $id, $error );
		}

		return $sorted;
	}

	/**
	 * Module ids that must register first. Missing method means none.
	 *
	 * @param Module $module Module under inspection.
	 * @return list<string>
	 */
	private function dependencies_of( Module $module ): array {
		$callable = array( $module, 'dependencies' );
		if ( ! is_callable( $callable ) ) {
			return array();
		}

		$dependencies = $callable();
		return is_array( $dependencies ) ? array_values( $dependencies ) : array();
	}

	/**
	 * Scenes this module may run in. Missing method means admin and frontend.
	 *
	 * @param Module $module Module under inspection.
	 * @return list<string>
	 */
	private function contexts_of( Module $module ): array {
		$callable = array( $module, 'contexts' );
		if ( ! is_callable( $callable ) ) {
			return array( Environment::ADMIN, Environment::FRONTEND );
		}

		$contexts = $callable();
		return is_array( $contexts ) ? array_values( $contexts ) : array();
	}

	/**
	 * Keep a failure visible for diagnostics. Do not treat it as success.
	 *
	 * @param string    $module_id Module id.
	 * @param Throwable $error     Caught error.
	 */
	private function record_failure( string $module_id, Throwable $error ): void {
		if ( ! isset( $this->failures[ $module_id ] ) ) {
			$this->failures[ $module_id ] = array();
		}

		$this->failures[ $module_id ][] = $error;
	}
}
