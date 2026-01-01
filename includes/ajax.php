<?php
namespace Zaplane;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ajax { 
    public static function init(){ 
        $self = new self();
        $self->dispatch_hook();
    }

    public function dispatch_hook(){
        Ajax\Workflows::init();
    }
}