<?php
namespace Zaplane\Framework\Core;

use Zaplane\Framework\Classes\Container;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface ModuleInterface {




	public static function init( Container $container): self;



	public function register_hooks(): void;
}
