<?php

namespace Zaplane\Framework\Database\ORM;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class Migration {

	abstract public function up(): void;

	public function down(): void {
	}
}
