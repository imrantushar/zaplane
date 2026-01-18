<?php

namespace Zaplane\Utils\Traits;

if (!defined('ABSPATH')) exit;

trait Menu
{
    public static function get_admin_menu_list() {
        $menu = zaplane_config('menu.admin', []);

		return apply_filters( 'zaplane/admin_menu_list', $menu );
	}
}