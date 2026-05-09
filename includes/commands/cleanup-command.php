<?php

namespace Zaplane\Commands;

use Zaplane\Framework\Console\Command;
use Zaplane\Framework\Maintenance\RunRetention;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manually prune old workflow run history. Same logic as the daily
 * scheduled cleanup; useful for one-off runs after enabling retention
 * or for migrations.
 *
 * Usage:
 *   wp zaplane cleanup                  # use configured retention
 *   wp zaplane cleanup --days=14        # override
 *   wp zaplane cleanup --days=7 --dry-run
 */
class CleanupCommand extends Command {

	protected string $signature   = 'cleanup';
	protected string $description = 'Prune workflow run history older than the configured retention window.';

	public function handle( array $args, array $assoc_args ): void {
		$override = $assoc_args['days'] ?? null;
		$dry_run  = isset( $assoc_args['dry-run'] );

		$days = null === $override
			? RunRetention::get_retention_days()
			: max( 0, (int) $override );

		if ( $days <= 0 ) {
			$this->warning( 'Retention is disabled (days=0). Nothing to clean.' );
			return;
		}

		$this->info( sprintf( 'Pruning runs older than %d day(s)%s…', $days, $dry_run ? ' (DRY RUN)' : '' ) );

		if ( $dry_run ) {
			global $wpdb;
			$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
			$count  = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}zaplane_runs WHERE finished_at IS NOT NULL AND finished_at < %s",
					$cutoff
				)
			);
			$this->success( sprintf( '%d run(s) would be deleted (cutoff %s).', $count, $cutoff ) );
			return;
		}

		$result = RunRetention::cleanup_older_than( $days );

		$this->success( sprintf(
			'Deleted %d run(s) and %d node-run(s) (cutoff %s, took %dms).',
			$result['runs_deleted'],
			$result['node_runs_deleted'],
			$result['cutoff'],
			$result['duration_ms']
		) );
	}
}
