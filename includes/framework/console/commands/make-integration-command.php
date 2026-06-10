<?php

namespace Zaplane\Framework\Console\Commands;

use Zaplane\Framework\Console\Command;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Laravel-style generator that scaffolds everything needed for a new
 * integration in one shot: the integration class, the registry entry, a
 * PHPUnit stub, and a starter live recipe.
 */
class MakeIntegrationCommand extends Command {

	protected string $signature = 'make:integration <slug>';
	protected string $description = 'Scaffold a new integration (class, registry, test, recipe)';

	public function handle( array $args, array $assoc_args ): void {
		if ( empty( $args[0] ) ) {
			$this->error( 'Please provide an integration slug, e.g. make:integration mailchimp' );
			return;
		}

		$slug = strtolower( preg_replace( '/[^a-z0-9]+/i', '', $args[0] ) );
		if ( '' === $slug ) {
			$this->error( 'Slug must contain letters or numbers.' );
			return;
		}

		$class   = ucfirst( $slug );
		$label   = $assoc_args['name'] ?? ucwords( str_replace( [ '-', '_' ], ' ', $slug ) );
		$plugin  = $assoc_args['plugin'] ?? '';
		$trigger = $assoc_args['trigger'] ?? 'item_created';
		$action  = $assoc_args['action'] ?? 'create_item';

		$this->createIntegration( $slug, $class, $label, $trigger, $action );
		$this->registerInConfig( $slug, $class );
		if ( '' !== $plugin ) {
			$this->registerPluginDependency( $slug, $plugin );
		}
		$this->createTest( $class );
		$this->createRecipe( $slug, $trigger, $plugin );

		$this->success( "Integration '{$slug}' scaffolded. Fill in the stubs, then: wp zaplane recipe run {$slug}" );
	}

	protected function createIntegration( string $slug, string $class, string $label, string $trigger, string $action ): void {
		$path = ZAPLANE_ROOT_DIR_PATH . "integrations/{$slug}.php";
		if ( file_exists( $path ) ) {
			$this->warning( "integrations/{$slug}.php already exists — skipped." );
			return;
		}

		$content = <<<PHP
<?php

namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class {$class} extends IntegrationBase {

	public static function get_slug(): string {
		return '{$slug}';
	}

	public static function get_name(): string {
		return '{$label}';
	}

	public static function get_triggers(): array {
		return [
			'{$trigger}' => [
				'label' => '{$label} item created',
				'hook'  => '{$slug}_item_created',
			],
		];
	}

	public static function get_actions(): array {
		return [
			'{$action}' => [
				'label' => 'Create {$label} item',
			],
		];
	}

	public static function resolve_trigger( array \$node, array \$hook_args ) {
		\$event = \$node['event'] ?? '';

		switch ( \$event ) {
			case '{$trigger}':
				\$item_id = \$hook_args[0] ?? null;
				if ( ! \$item_id ) {
					return false;
				}
				return [
					'item_id' => (int) \$item_id,
				];
		}

		return false;
	}

	// Self-seeding: list the triggers you can create sample data for, then create
	// it in seed_trigger_args() and return the real hook arguments. This lets a
	// bare recipe (no factory/input) just run. Leave empty if you'll seed recipes
	// with a factory/setup.action instead.
	public static function get_seedable_triggers(): array {
		return [
			// '{$trigger}',
		];
	}

	public static function seed_trigger_args( string \$event ): ?array {
		switch ( \$event ) {
			case '{$trigger}':
				// TODO: create real data and return the positional hook args, e.g.
				// \$id = wp_insert_post( [...] );
				// return [ \$id ];
				return null;
		}

		return null;
	}

	public static function execute_node( array \$node, array \$input ): array {
		\$config = \$node['data']['config'] ?? [];
		\$event  = \$node['data']['event'] ?? '';

		switch ( \$event ) {
			case '{$action}':
				// TODO: perform the action against the live plugin.
				return [
					'port' => 'main',
					'data' => array_merge( \$input, [ 'created' => true ] ),
				];
		}

		return [
			'port' => 'main',
			'data' => \$input,
		];
	}
}
PHP;

		file_put_contents( $path, $content . "\n" );
		$this->success( "Created integrations/{$slug}.php" );
	}

	protected function registerInConfig( string $slug, string $class ): void {
		$path = ZAPLANE_ROOT_DIR_PATH . 'includes/config/integrations.php';
		$src  = file_get_contents( $path );

		if ( false !== strpos( $src, "'{$slug}'" ) && preg_match( "/'" . preg_quote( $slug, '/' ) . "'\s*=>/", $src ) ) {
			$this->warning( "Registry already has '{$slug}' — skipped." );
			return;
		}

		$entry  = "\t'{$slug}' => [\n";
		$entry .= "\t\t'file'  => '{$slug}.php',\n";
		$entry .= "\t\t'class' => \\Zaplane\\Integrations\\{$class}::class,\n";
		$entry .= "\t],\n";

		$anchor  = "\$registry = [\n";
		$updated = str_replace( $anchor, $anchor . $entry, $src );

		if ( $updated === $src ) {
			$this->warning( 'Could not locate $registry array — add the entry manually.' );
			return;
		}

		file_put_contents( $path, $updated );
		$this->success( "Registered '{$slug}' in includes/config/integrations.php" );
	}

	protected function registerPluginDependency( string $slug, string $plugin ): void {
		$path = ZAPLANE_ROOT_DIR_PATH . 'includes/config/integration-plugins.php';
		$src  = file_get_contents( $path );

		if ( preg_match( "/'" . preg_quote( $slug, '/' ) . "'\s*=>/", $src ) ) {
			$this->warning( "Plugin map already has '{$slug}' — skipped." );
			return;
		}

		$entry   = "\t'{$slug}' => [ '{$plugin}' ],\n";
		$anchor  = "return [\n";
		$updated = str_replace( $anchor, $anchor . $entry, $src );

		if ( $updated === $src ) {
			$this->warning( 'Could not locate the plugin map — add the dependency manually.' );
			return;
		}

		file_put_contents( $path, $updated );
		$this->success( "Mapped '{$slug}' => '{$plugin}' in includes/config/integration-plugins.php" );
	}

	protected function createTest( string $class ): void {
		$path = ZAPLANE_ROOT_DIR_PATH . "tests/Integrations/{$class}Test.php";
		if ( file_exists( $path ) ) {
			$this->warning( "tests/Integrations/{$class}Test.php already exists — skipped." );
			return;
		}

		$content = <<<PHP
<?php

namespace Zaplane\Tests\Integrations;

use Zaplane\Integrations\\{$class};

class {$class}Test extends IntegrationTestCase {

	protected function getIntegrationClass(): string {
		return {$class}::class;
	}
}
PHP;

		file_put_contents( $path, $content . "\n" );
		$this->success( "Created tests/Integrations/{$class}Test.php" );
	}

	protected function createRecipe( string $slug, string $trigger, string $plugin ): void {
		$dir = ZAPLANE_ROOT_DIR_PATH . "recipes-test/{$slug}/";
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		$path = $dir . "{$slug}-sample.json";
		if ( file_exists( $path ) ) {
			$this->warning( "recipes-test/{$slug}/{$slug}-sample.json already exists — skipped." );
			return;
		}

		$recipe = [
			'name'             => "{$slug}-{$trigger}",
			'integration'      => $slug,
			'required_plugins' => '' !== $plugin ? [ $plugin ] : [],
			'setup'            => [ 'factory' => '', 'args' => new \stdClass() ],
			'node'             => [
				'kind'   => 'trigger',
				'event'  => $trigger,
				'config' => new \stdClass(),
				'input'  => [ 1 ],
			],
			'expect'           => [
				'not_false' => true,
				'has_keys'  => [ 'item_id' ],
			],
		];

		file_put_contents( $path, wp_json_encode( $recipe, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n" );
		$this->success( "Created recipes-test/{$slug}/{$slug}-sample.json" );
	}
}
