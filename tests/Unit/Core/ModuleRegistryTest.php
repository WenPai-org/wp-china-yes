<?php
/**
 * ModuleRegistry: order, dependencies, isolation, scene filter.
 *
 * @package WenPai\ChinaYes
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use WenPai\ChinaYes\Core\Environment;
use WenPai\ChinaYes\Core\Logger;
use WenPai\ChinaYes\Core\ModuleRegistry;

/**
 * Kernel registry contract from docs/4.0-rewrite-plan.md §5.3.
 */
class ModuleRegistryTest extends TestCase {

	/**
	 * Independent modules register in the order they were added.
	 */
	public function test_register_order_follows_insertion_when_no_dependencies() {
		$order    = array();
		$registry = $this->registry();
		$registry->add( RecordingModule::everywhere( 'first', $order ) );
		$registry->add( RecordingModule::everywhere( 'second', $order ) );
		$registry->add( RecordingModule::everywhere( 'third', $order ) );

		$registry->boot( Environment::ADMIN );

		$this->assertSame( array( 'first', 'second', 'third' ), $order );
	}

	/**
	 * A dependent module registers after its dependency, regardless of add() order.
	 */
	public function test_dependency_order_puts_dependency_first() {
		$order    = array();
		$registry = $this->registry();
		$registry->add( new RecordingModule( 'leaf', array( 'root' ), Environment::CONTEXTS, $order ) );
		$registry->add( new RecordingModule( 'root', array(), Environment::CONTEXTS, $order ) );

		$registry->boot( Environment::FRONTEND );

		$this->assertSame( array( 'root', 'leaf' ), $order );
	}

	/**
	 * A throwing module is recorded and does not stop later modules.
	 */
	public function test_throwing_module_does_not_block_others() {
		$order    = array();
		$registry = $this->registry();
		$registry->add( RecordingModule::everywhere( 'ok-before', $order ) );
		$registry->add( new ThrowingModule( 'boom' ) );
		$registry->add( RecordingModule::everywhere( 'ok-after', $order ) );

		$registry->boot( Environment::ADMIN );

		$this->assertSame( array( 'ok-before', 'ok-after' ), $order );
		$this->assertArrayHasKey( 'boom', $registry->failures() );
		$this->assertNotEmpty( $registry->failures()['boom'] );
		$this->assertInstanceOf( RuntimeException::class, $registry->failures()['boom'][0] );
	}

	/**
	 * Each request scene only registers modules that list it in contexts().
	 *
	 * @dataProvider scene_provider
	 *
	 * @param string $scene Scene under test.
	 */
	public function test_scene_filter( string $scene ) {
		$order    = array();
		$registry = $this->registry();
		foreach ( Environment::CONTEXTS as $listed ) {
			$registry->add( new RecordingModule( 'only-' . $listed, array(), array( $listed ), $order ) );
		}

		$registry->boot( $scene );

		$this->assertSame( array( 'only-' . $scene ), $order );
	}

	/**
	 * Scene ids used by test_scene_filter.
	 *
	 * @return array<string, array{0: string}>
	 */
	public function scene_provider(): array {
		$cases = array();
		foreach ( Environment::CONTEXTS as $scene ) {
			$cases[ $scene ] = array( $scene );
		}
		return $cases;
	}

	/**
	 * ConditionalModule::enabled() throwing does not stop later modules.
	 */
	public function test_enabled_throw_does_not_block_others() {
		$order    = array();
		$registry = $this->registry();
		$registry->add( RecordingModule::everywhere( 'ok-before', $order ) );
		$registry->add( new ThrowingEnabledModule( 'boom-enabled' ) );
		$registry->add( RecordingModule::everywhere( 'ok-after', $order ) );

		$registry->boot( Environment::ADMIN );

		$this->assertSame( array( 'ok-before', 'ok-after' ), $order );
		$this->assertArrayHasKey( 'boom-enabled', $registry->failures() );
		$this->assertNotEmpty( $registry->failures()['boom-enabled'] );
		$this->assertInstanceOf( RuntimeException::class, $registry->failures()['boom-enabled'][0] );
	}

	/**
	 * ConditionalModule::enabled() false skips register() without a failure.
	 */
	public function test_disabled_conditional_module_is_skipped() {
		$order    = array();
		$registry = $this->registry();
		$registry->add( new GatedModule( 'off', false, $order ) );
		$registry->add( RecordingModule::everywhere( 'on', $order ) );

		$registry->boot( Environment::ADMIN );

		$this->assertSame( array( 'on' ), $order );
		$this->assertSame( array(), $registry->failures() );
	}

	/**
	 * Missing dependency is recorded; other modules still register.
	 */
	public function test_missing_dependency_is_isolated() {
		$order    = array();
		$registry = $this->registry();
		$registry->add( new RecordingModule( 'needs-ghost', array( 'ghost' ), Environment::CONTEXTS, $order ) );
		$registry->add( RecordingModule::everywhere( 'solo', $order ) );

		$registry->boot( Environment::CLI );

		$this->assertSame( array( 'solo' ), $order );
		$this->assertArrayHasKey( 'needs-ghost', $registry->failures() );
	}

	/**
	 * Modules without dependencies()/contexts() still register on admin/frontend.
	 */
	public function test_module_without_optional_methods_uses_defaults() {
		$registered = array();
		$registry   = $this->registry();
		$registry->add( new BareModule( $registered ) );
		$registry->boot( Environment::ADMIN );
		$this->assertSame( array( 'bare' ), $registered );

		$registered = array();
		$registry   = $this->registry();
		$registry->add( new BareModule( $registered ) );
		$registry->boot( Environment::REST );
		$this->assertSame( array(), $registered );
	}

	/**
	 * Registry with empty config and a silent logger.
	 */
	private function registry(): ModuleRegistry {
		return new ModuleRegistry(
			new ArrayConfig(),
			new Environment( Environment::ADMIN ),
			new Logger(
				'error',
				static function (): void {
				}
			)
		);
	}
}
