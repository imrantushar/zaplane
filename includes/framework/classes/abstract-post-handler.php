<?php

namespace Zaplane\Framework\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class AbstractPostHandler extends AbstractRequestHandler {



	public function dispatch_actions() {
		foreach ( $this->actions as $action => $details ) {
			add_action( 'admin_post_' . $this->namespace . '/' . $action, [ $this, 'handle_request' ] );

			if ( isset( $details['allow_visitor_action'] ) && true === $details['allow_visitor_action'] ) {
				add_action( 'admin_post_nopriv_' . $this->namespace . '/' . $action, [ $this, 'handle_request' ] );
			}
		}
	}
}
