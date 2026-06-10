<?php

namespace Zaplane\Framework\Console;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Kernel {

	protected static ?self $instance = null;
	protected array $commands = [];

	public static function getInstance(): self {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->registerCommands();
	}

	protected function registerCommands(): void {

		$this->commands = [
			Commands\MigrateCommand::class,
			Commands\MigrateFreshCommand::class,
			Commands\MigrateRollbackCommand::class,
			Commands\MigrateStatusCommand::class,
			Commands\MakeMigrationCommand::class,
			Commands\MakeModelCommand::class,
			Commands\MakeIntegrationCommand::class,
		];

		$this->registerCustomCommands();
	}

	protected function registerCustomCommands(): void {
		$commandsPath = ZAPLANE_ROOT_DIR_PATH . 'includes/commands/';

		if ( ! is_dir( $commandsPath ) ) {
			return;
		}

		$files = glob( $commandsPath . '*-command.php' );

		foreach ( $files as $file ) {
			$filename = basename( $file, '.php' );
			$className = str_replace( '-', '', ucwords( $filename, '-' ) );
			$fullClassName = "Zaplane\\Commands\\{$className}";

			if ( class_exists( $fullClassName ) ) {
				$this->commands[] = $fullClassName;
			}
		}
	}

	public function boot(): void {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			return;
		}

		foreach ( $this->commands as $commandClass ) {
			$command = new $commandClass();
			$this->registerWPCLI( $command );
		}
	}

	protected function registerWPCLI( Command $command ): void {
		$signature = $command->getSignature();
		$parts = explode( ' ', $signature );
		$name = array_shift( $parts );

		\WP_CLI::add_command(
			"zaplane {$name}",
			function ( $args, $assoc_args ) use ( $command ) {
				$command->handle( $args, $assoc_args );
			},
			[
				'shortdesc' => $command->getDescription(),
			]
		);
	}

	public function getCommands(): array {
		return $this->commands;
	}
}
