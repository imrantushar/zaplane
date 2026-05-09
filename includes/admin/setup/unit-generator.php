<?php

namespace Zaplane\Admin\Setup;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds systemd / supervisor unit files for `wp zaplane work`.
 *
 * Detects the local environment (WP path, WP-CLI binary, www-data user)
 * and renders ready-to-paste configuration. Does NOT install anything —
 * www-data plugin processes don't have sudo. The admin page shows the
 * generated file plus the exact `sudo` commands the customer must run.
 */
class UnitGenerator {

	public static function detect_environment(): array {
		$wp_path        = self::detect_wp_path();
		$wp_cli_bin     = self::detect_wp_cli();
		$run_user       = self::detect_run_user();
		$has_systemd    = self::path_exists_in_path( 'systemctl' );
		$has_supervisor = self::path_exists_in_path( 'supervisorctl' );
		$has_pcntl      = function_exists( 'pcntl_async_signals' );

		return [
			'wp_path'        => $wp_path,
			'wp_cli_bin'     => $wp_cli_bin,
			'run_user'       => $run_user,
			'has_systemd'    => $has_systemd,
			'has_supervisor' => $has_supervisor,
			'has_pcntl'      => $has_pcntl,
			'php_binary'     => PHP_BINARY,
			'os'             => PHP_OS_FAMILY,
			'memory_limit'   => ini_get( 'memory_limit' ),
		];
	}

	public static function recommended_runner( array $env ): string {
		if ( 'Linux' !== $env['os'] ) {
			return 'unsupported';
		}
		if ( $env['has_systemd'] ) {
			return 'systemd';
		}
		if ( $env['has_supervisor'] ) {
			return 'supervisor';
		}
		return 'cron_fallback';
	}

	public static function systemd_unit( array $env, array $opts = [] ): string {
		$wp_cli   = $opts['wp_cli_bin'] ?? $env['wp_cli_bin'];
		$wp_path  = $opts['wp_path'] ?? $env['wp_path'];
		$user     = $opts['run_user'] ?? $env['run_user'];
		$memory   = (int) ( $opts['memory'] ?? 256 );
		$max_jobs = (int) ( $opts['max_jobs'] ?? 1000 );
		$max_time = (int) ( $opts['max_time'] ?? 3600 );

		$exec = sprintf(
			'%s zaplane work --path=%s --memory=%d --max-jobs=%d --max-time=%d',
			escapeshellcmd( $wp_cli ),
			escapeshellarg( $wp_path ),
			$memory,
			$max_jobs,
			$max_time
		);

		return <<<UNIT
[Unit]
Description=Zaplane Worker Daemon
After=network.target mysql.service

[Service]
Type=simple
User={$user}
Group={$user}
WorkingDirectory={$wp_path}
ExecStart={$exec}
Restart=always
RestartSec=5
StartLimitInterval=0
KillSignal=SIGTERM
TimeoutStopSec=30

[Install]
WantedBy=multi-user.target
UNIT;
	}

	public static function supervisor_conf( array $env, array $opts = [] ): string {
		$wp_cli   = $opts['wp_cli_bin'] ?? $env['wp_cli_bin'];
		$wp_path  = $opts['wp_path'] ?? $env['wp_path'];
		$user     = $opts['run_user'] ?? $env['run_user'];
		$memory   = (int) ( $opts['memory'] ?? 256 );
		$max_jobs = (int) ( $opts['max_jobs'] ?? 1000 );
		$max_time = (int) ( $opts['max_time'] ?? 3600 );

		$exec = sprintf(
			'%s zaplane work --path=%s --memory=%d --max-jobs=%d --max-time=%d',
			$wp_cli,
			$wp_path,
			$memory,
			$max_jobs,
			$max_time
		);

		return <<<CONF
[program:zaplane-worker]
process_name=%(program_name)s
command={$exec}
autostart=true
autorestart=true
user={$user}
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/zaplane-worker.log
stopsignal=TERM
stopwaitsecs=30
CONF;
	}

	public static function systemd_install_steps( string $unit_path = '/etc/systemd/system/zaplane-worker.service' ): array {
		return [
			"sudo tee {$unit_path} > /dev/null <<'EOF'\n[paste the unit file above]\nEOF",
			'sudo systemctl daemon-reload',
			'sudo systemctl enable zaplane-worker',
			'sudo systemctl start zaplane-worker',
			'sudo systemctl status zaplane-worker',
		];
	}

	public static function supervisor_install_steps( string $conf_path = '/etc/supervisor/conf.d/zaplane-worker.conf' ): array {
		return [
			"sudo tee {$conf_path} > /dev/null <<'EOF'\n[paste the config above]\nEOF",
			'sudo supervisorctl reread',
			'sudo supervisorctl update',
			'sudo supervisorctl status zaplane-worker',
		];
	}

	private static function detect_wp_path(): string {
		if ( defined( 'ABSPATH' ) ) {
			return rtrim( ABSPATH, '/' );
		}
		return '';
	}

	private static function detect_wp_cli(): string {
		if ( defined( 'WP_CLI' ) && WP_CLI && defined( 'WP_CLI_ROOT' ) ) {
			$candidate = WP_CLI_ROOT . '/bin/wp';
			if ( file_exists( $candidate ) ) {
				return $candidate;
			}
		}
		foreach ( [ '/usr/local/bin/wp', '/usr/bin/wp', '/opt/bin/wp' ] as $candidate ) {
			if ( file_exists( $candidate ) ) {
				return $candidate;
			}
		}
		return 'wp';
	}

	private static function detect_run_user(): string {
		if ( function_exists( 'posix_getpwuid' ) && function_exists( 'posix_geteuid' ) ) {
			$info = posix_getpwuid( posix_geteuid() );
			if ( ! empty( $info['name'] ) ) {
				return (string) $info['name'];
			}
		}
		return 'www-data';
	}

	private static function path_exists_in_path( string $bin ): bool {
		$paths = explode( PATH_SEPARATOR, (string) getenv( 'PATH' ) );
		foreach ( $paths as $dir ) {
			if ( '' === $dir ) {
				continue;
			}
			if ( is_executable( rtrim( $dir, '/' ) . '/' . $bin ) ) {
				return true;
			}
		}
		return false;
	}
}
