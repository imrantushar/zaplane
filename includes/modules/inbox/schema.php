<?php

namespace Zaplane\Modules\Inbox;

use Zaplane\Framework\Database\ORM\Migrator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates the inbox tables the first time the module is switched on, and
 * applies new inbox migrations after an update.
 *
 * The inbox keeps its migrations out of the main directory so a site that
 * never turns the module on never gets its tables. They still record into the
 * shared migrations table, so each one runs once.
 */
class Schema extends Migrator {

	/** Bump when a migration is added to modules/inbox/migrations. */
	public const VERSION = '1';

	public const OPTION = 'zaplane_inbox_schema';

	public function __construct() {
		parent::__construct();
		$this->migrationsPath = __DIR__ . '/migrations/';
	}

	public static function ensure(): void {
		if ( self::VERSION === get_option( self::OPTION ) ) {
			return;
		}

		( new self() )->run();
		update_option( self::OPTION, self::VERSION );
	}
}
