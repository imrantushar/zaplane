<?php

namespace Zaplane\Modules\AbandonedCart;

use Zaplane\Framework\Core\ModuleInterface;
use Zaplane\Framework\Classes\Container;
use Zaplane\Models\Workflow;
use Zaplane\Models\Run;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AbandonedCartModule implements ModuleInterface {

	protected static ?self $instance = null;
	protected Container $container;

	public static function init( Container $container ): self {
		if ( ! self::$instance ) {
			self::$instance = new self( $container );
		}
		return self::$instance;
	}

	private function __construct( Container $container ) {
		$this->container = $container;
	}

	public function register_hooks(): void {
		// Tracking, scheduling, and the cart data/API are owned by the GemCRM
		// Abandoned Cart addon. Zaplane only runs the recovery automation — and
		// surfaces it inside GemCRM's report via this seam (no data duplication).
		add_filter( 'gemcrm/abandoned_cart/report', [ self::class, 'inject_report_automation' ] );
		add_filter( 'gemcrm/abandoned_cart/carts', [ self::class, 'annotate_carts' ] );
	}

	/** Active + draft workflows that use the Abandoned Cart trigger. */
	private static function abandoned_cart_workflows() {
		// `integration_icons` is a JSON array of the slugs a workflow uses,
		// e.g. ["abandoned-cart","gemcrm"].
		return Workflow::where( 'integration_icons', 'LIKE', '%"abandoned-cart"%' )->get();
	}

	/**
	 * Tell GemCRM's Abandoned Cart report that recovery automation is live here,
	 * and where to manage it. GemCRM renders an "automation" panel from this.
	 * `cards` is reserved for per-period automation stats (added later).
	 *
	 * @param mixed $report The report payload from GemCRM.
	 * @return mixed
	 */
	public static function inject_report_automation( $report ) {
		if ( ! is_array( $report ) ) {
			return $report;
		}

		$slug = defined( 'ZAPLANE_PLUGIN_SLUG' ) ? ZAPLANE_PLUGIN_SLUG : 'zaplane';

		$report['automation'] = [
			'provider'   => 'Zaplane',
			'active'     => true,
			'manage_url' => admin_url( 'admin.php?page=' . $slug ),
			'cards'      => self::automation_cards( $report['date_range'] ?? [] ),
		];

		return $report;
	}

	/**
	 * Aggregate stats for the GemCRM report panel: how many active workflows use
	 * the Abandoned Cart integration, and how many automation runs fired in the
	 * report's date range. Best-effort — any failure yields no cards.
	 *
	 * @param array $range { from, to } date strings (Y-m-d) or empty.
	 * @return array<int, array{label:string,value:int}>
	 */
	private static function automation_cards( array $range ): array {
		try {
			$workflows = self::abandoned_cart_workflows();

			$workflow_ids = [];
			$active       = 0;
			foreach ( $workflows as $w ) {
				$workflow_ids[] = (int) $w->id;
				if ( 'active' === $w->status ) {
					$active++;
				}
			}

			if ( empty( $workflow_ids ) ) {
				return [];
			}

			$runs_query = Run::whereIn( 'workflow_id', $workflow_ids )->where( 'is_test', 0 );

			$from = $range['from'] ?? '';
			$to   = $range['to'] ?? '';
			if ( $from ) {
				$runs_query->where( 'started_at', '>=', $from . ' 00:00:00' );
			}
			if ( $to ) {
				$runs_query->where( 'started_at', '<=', $to . ' 23:59:59' );
			}
			$runs = $runs_query->count();

			return [
				[ 'label' => __( 'Active workflows', 'zaplane' ), 'value' => $active ],
				[ 'label' => __( 'Automation runs', 'zaplane' ), 'value' => $runs ],
			];
		} catch ( \Throwable $e ) {
			return [];
		}
	}

	/**
	 * Annotate each GemCRM cart row with `recovery` = { status } — the latest
	 * recovery-workflow run that fired for that cart (matched by cart id inside
	 * the run's trigger_data). Best-effort + bounded to recent runs.
	 *
	 * @param mixed $carts Array of cart rows from GemCRM.
	 * @return mixed
	 */
	public static function annotate_carts( $carts ) {
		if ( ! is_array( $carts ) || empty( $carts ) ) {
			return $carts;
		}

		try {
			$workflow_ids = [];
			foreach ( self::abandoned_cart_workflows() as $w ) {
				$workflow_ids[] = (int) $w->id;
			}
			if ( empty( $workflow_ids ) ) {
				return $carts;
			}

			// Latest run status per cart id, from recent (non-test) runs.
			$runs = Run::whereIn( 'workflow_id', $workflow_ids )
				->where( 'is_test', 0 )
				->orderBy( 'id', 'desc' )
				->limit( 1000 )
				->get();

			$status_by_cart = [];
			foreach ( $runs as $run ) {
				$data    = json_decode( (string) $run->trigger_data, true );
				$cart_id = is_array( $data ) && isset( $data['id'] ) ? (int) $data['id'] : 0;
				if ( $cart_id && ! isset( $status_by_cart[ $cart_id ] ) ) {
					$status_by_cart[ $cart_id ] = $run->status; // desc → first seen is latest
				}
			}

			foreach ( $carts as &$cart ) {
				$cart_id = (int) ( $cart['id'] ?? 0 );
				if ( $cart_id && isset( $status_by_cart[ $cart_id ] ) ) {
					$cart['recovery'] = [ 'status' => $status_by_cart[ $cart_id ] ];
				}
			}
			unset( $cart );
		} catch ( \Throwable $e ) {
			// best-effort — leave carts unannotated on any failure
		}

		return $carts;
	}
}
