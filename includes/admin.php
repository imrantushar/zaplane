<?php
namespace Zaplane;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin { 
    public static function init(){
        Admin\Menu::init();
    }
}