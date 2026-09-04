<?php
/**
 * 4.0 kernel bootstrap: config → registry → scene register().
 *
 * @package WenPai\ChinaYes
 */

declare(strict_types=1);

namespace WenPai\ChinaYes\Core;

/**
 * New kernel. Does not instantiate 3.x Plugin or Service\Base.
 */
final class Plugin {

	/**
	 * Service locator.
	 *
	 * @var Container
	 */
	private $container;

	/**
	 * Module host.
	 *
	 * @var ModuleRegistry
	 */
	private $registry;

	/**
	 * Current request scene.
	 *
	 * @var Environment
	 */
	private $environment;

	/**
	 * Wire a kernel instance.
	 *
	 * @param Container      $container   Service locator.
	 * @param ModuleRegistry $registry    Module host.
	 * @param Environment    $environment Request scene.
	 */
	public function __construct( Container $container, ModuleRegistry $registry, Environment $environment ) {
		$this->container   = $container;
		$this->registry    = $registry;
		$this->environment = $environment;
	}

	/**
	 * Default wiring and scene boot. Called from wp-china-yes.php when WPCY_KERNEL=v4.
	 *
	 * No modules are registered in M1-02; later tasks add them to the registry.
	 */
	public static function boot(): void {
		self::create()->run();
	}

	/**
	 * Build a kernel with empty config (M1-03 Repository not in this task).
	 */
	public static function create(): self {
		$container   = new Container();
		$config      = self::null_config();
		$environment = Environment::detect();
		$scope       = Scope::detect();
		$logger      = new Logger();
		$registry    = new ModuleRegistry( $config, $environment, $logger );

		$container->set( 'config', $config );
		$container->set( 'environment', $environment );
		$container->set( 'scope', $scope );
		$container->set( 'logger', $logger );
		$container->set( 'registry', $registry );

		return new self( $container, $registry, $environment );
	}

	/**
	 * Register modules for the current scene.
	 */
	public function run(): void {
		$this->registry->boot( $this->environment->context() );
	}

	/**
	 * Service locator.
	 */
	public function container(): Container {
		return $this->container;
	}

	/**
	 * Module host.
	 */
	public function registry(): ModuleRegistry {
		return $this->registry;
	}

	/**
	 * Placeholder config until M1-03 Repository implements Core\Config.
	 */
	private static function null_config(): Config {
		return new class() implements Config {
			/**
			 * Read a dotted path. Always returns the fallback.
			 *
			 * @param string $path     Dotted key.
			 * @param mixed  $fallback Value when absent.
			 * @return mixed
			 */
			public function get( string $path, $fallback = null ) {
				unset( $path );
				return $fallback;
			}

			/**
			 * Whether a dotted path exists. Always false.
			 *
			 * @param string $path Dotted key.
			 */
			public function has( string $path ): bool {
				unset( $path );
				return false;
			}
		};
	}
}
