<?php
namespace Zaplane;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Installer { 
    public $zaplane_version;
    public static function init(){
        $self = new self();
		$self->zaplane_version = get_option( 'zaplane_version' );
        Database::create_initial_custom_table();
    }
}