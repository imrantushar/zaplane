<?php
namespace Zaplane\Modules\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Workflows { 

    public function register(){
        add_action('wp_ajax_zaplane/update_workflow_status', [$this, 'update_workflow_status']);
    }

    public function update_workflow_status(){
        $security = isset($_POST['security']) ? sanitize_text_field($_POST['security']) : '';
        if ( ! wp_verify_nonce($security, 'zaplane_nonce') ) {
            wp_send_json_error(['message' => 'Invalid nonce']);
        }

        if ( ! current_user_can('manage_options') ) {
            wp_send_json_error(['message' => 'Insufficient access'], 403);
        }

        global $wpdb; 
        $id = absint(isset($_POST['id']) ? $_POST['id'] : 0);
        $status = sanitize_text_field(isset($_POST['status']) ? $_POST['status'] : '');
        if(empty($id) || empty($status)){
            wp_send_json_error(__('id and status is required', 'zaplane'));
        }
        $is_update = $wpdb->update(
            $wpdb->prefix . ZAPLANE_PLUGIN_SLUG . '_workflows',
            array(
                'status' => $status
            ),
            array(
                'id' => $id
            )
        );
        wp_send_json_success($is_update);
    }

}