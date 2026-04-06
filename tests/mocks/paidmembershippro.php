<?php

namespace Zaplane\Tests\Mocks;

if ( ! function_exists( __NAMESPACE__ . '\\pmpro_getMembershipLevelForUser' ) ) {
    function pmpro_getMembershipLevelForUser( $user_id ) {
        return (object) [
            'ID' => 1,
            'name' => 'Gold',
        ];
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\pmpro_getMembershipLevelsForUser' ) ) {
    function pmpro_getMembershipLevelsForUser( $user_id ) {
        return [
            (object) [ 'ID' => 1, 'name' => 'Gold' ]
        ];
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\pmpro_changeMembershipLevel' ) ) {
    function pmpro_changeMembershipLevel( $level, $user_id ) {
        return true;
    }
}

if ( ! function_exists( __NAMESPACE__ . '\\pmpro_cancelMembershipLevel' ) ) {
    function pmpro_cancelMembershipLevel( $level_id, $user_id ) {
        return true;
    }
}

if ( ! defined( 'ARRAY_A' ) ) {
    define( 'ARRAY_A', 'ARRAY_A' );
}

class WPDBMock {

    public $pmpro_membership_levels;
    public $prefix = 'wp_';
    public $last_error = '';

    public function __construct() {
        $this->pmpro_membership_levels = 'wp_pmpro_membership_levels';
    }

    public function get_results($query) {
        return [
            (object)[ 'id' => 1, 'name' => 'Gold' ],
            (object)[ 'id' => 2, 'name' => 'Silver' ],
        ];
    }

    public function get_row($query, $output = OBJECT) {

        if ($output === ARRAY_A) {
            return [
                'id'   => 1,
                'name' => 'Gold'
            ];
        }

        return (object)[
            'id'   => 1,
            'name' => 'Gold'
        ];
    }

    public function prepare($query, ...$args) {
        return $query;
    }
}