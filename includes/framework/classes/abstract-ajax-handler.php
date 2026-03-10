<?php

namespace Zaplane\Framework\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class AbstractAjaxHandler extends AbstractRequestHandler {

	protected bool $is_ajax = true;



	final public function dispatch_actions() {
		foreach ( $this->actions as $action => $details ) {
			add_action( 'wp_ajax_' . $this->namespace . '/' . $action, [ $this, 'handle_request' ] );

			if ( isset( $details['allow_visitor_action'] ) && $details['allow_visitor_action'] === true ) {
				add_action( 'wp_ajax_nopriv_' . $this->namespace . '/' . $action, [ $this, 'handle_request' ] );
			}
		}
	}
}
