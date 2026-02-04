<?php
namespace Zaplane\Integrations;

use Zaplane\Framework\Classes\IntegrationBase;

if (!defined('ABSPATH')) exit;

class Spectra extends IntegrationBase {

    public static function get_slug(): string {
        return 'spectra';
    }

    public static function get_triggers(): array {
        return [
            // Form Triggers
            'form_process' => [
                'label' => 'UAGB Form Process',
                'hook'  => 'wp_ajax_uagb_process_forms'
            ],
            'form_process_nopriv' => [
                'label' => 'UAGB Form Process (No Priv)',
                'hook'  => 'wp_ajax_nopriv_uagb_process_forms'
            ],
            'form_success' => [
                'label' => 'UAGB Form Success',
                'hook'  => 'uagb_form_success'
            ],
            
            // Plugin/Theme Activation
            'plugin_activated' => [
                'label' => 'Plugin Activated',
                'hook'  => 'uagb_plugin_activated'
            ],
            'theme_activated' => [
                'label' => 'Theme Activated', 
                'hook'  => 'uagb_theme_activated'
            ],
            
            // Menu Registration
            'after_menu_register' => [
                'label' => 'After Menu Register',
                'hook'  => 'spectra_after_menu_register'
            ],
            
            // Block Registration
            'block_registered' => [
                'label' => 'Block Registered',
                'hook'  => 'uag_register_block'
            ],
            
            // Asset Management
            'delete_asset_dir' => [
                'label' => 'Delete Asset Directory',
                'hook'  => 'uagb_delete_uag_asset_dir'
            ],
            'delete_page_assets' => [
                'label' => 'Delete Page Assets',
                'hook'  => 'uagb_delete_page_assets'
            ],
            
            // Core Loading
            'core_loaded' => [
                'label' => 'Spectra Core Loaded',
                'hook'  => 'spectra_core_loaded'
            ],
            
            // Updates
            'update_before' => [
                'label' => 'Before Update',
                'hook'  => 'uagb_update_before'
            ],
            'update_after' => [
                'label' => 'After Update',
                'hook'  => 'uagb_update_after'
            ],
            
            // Slider
            'slider_options_loaded' => [
                'label' => 'Slider Options Loaded',
                'hook'  => 'spectra_after_slider_options_loaded'
            ],
            
            // Post Events (Dynamic - fires for any post type)
            'post_before_article' => [
                'label' => 'Before Post Article',
                'hook'  => 'uagb_post_before_article_post'
            ],
            'post_after_article' => [
                'label' => 'After Post Article', 
                'hook'  => 'uagb_post_after_article_post'
            ],
            'post_before_title' => [
                'label' => 'Before Post Title',
                'hook'  => 'uagb_single_post_before_title_post'
            ],
            'post_after_title' => [
                'label' => 'After Post Title',
                'hook'  => 'uagb_single_post_after_title_post'
            ],
            'post_before_meta' => [
                'label' => 'Before Post Meta',
                'hook'  => 'uagb_single_post_before_meta_post'
            ],
            'post_after_meta' => [
                'label' => 'After Post Meta',
                'hook'  => 'uagb_single_post_after_meta_post'
            ],
            'post_before_excerpt' => [
                'label' => 'Before Post Excerpt',
                'hook'  => 'uagb_single_post_before_excerpt_post'
            ],
            'post_after_excerpt' => [
                'label' => 'After Post Excerpt',
                'hook'  => 'uagb_single_post_after_excerpt_post'
            ],
            'post_before_featured_image' => [
                'label' => 'Before Featured Image',
                'hook'  => 'uagb_single_post_before_featured_image_post'
            ],
            'post_after_featured_image' => [
                'label' => 'After Featured Image',
                'hook'  => 'uagb_single_post_after_featured_image_post'
            ],
            
            // Pro Features
            'popup_meta_register' => [
                'label' => 'Popup Meta Register',
                'hook'  => 'register_spectra_pro_popup_meta'
            ],
            'popup_dashboard' => [
                'label' => 'Popup Dashboard',
                'hook'  => 'spectra_pro_popup_dashboard'
            ],
            'localize_pro_block_ajax' => [
                'label' => 'Localize Pro Block AJAX',
                'hook'  => 'spectra_localize_pro_block_ajax'
            ],
        ]; 
    }

    public static function get_trigger_config_schema( string $trigger ): array {
        return [];
    }

    public static function resolve_trigger( array $node, array $args ) {
        switch ( $node['event'] ) {
            case 'form_success':
                $form_data = $_POST['form_data'] ?? '';
                $form_id   = $_POST['id'] ?? 0;
                
                if ( empty( $form_data ) ) {
                    return false;
                }
                
                return [
                    'form_data'    => json_decode($form_data, true),
                    'form_id'      => $form_id,
                    'submitted_at' => current_time('mysql'),
                ];
                
            case 'plugin_activated':
            case 'theme_activated':
                return [
                    'plugin_data' => $args[0] ?? [],
                    'activated_at' => current_time('mysql'),
                ];
                
            case 'block_registered':
                return [
                    'block_instance' => $args[0] ?? null,
                    'registered_at' => current_time('mysql'),
                ];
                
            case 'slider_options_loaded':
                return [
                    'slider_attributes' => $args[0] ?? [],
                    'loaded_at' => current_time('mysql'),
                ];
                
            // Post-related triggers
            case 'post_before_article':
            case 'post_after_article':
            case 'post_before_title':
            case 'post_after_title':
            case 'post_before_meta':
            case 'post_after_meta':
            case 'post_before_excerpt':
            case 'post_after_excerpt':
            case 'post_before_featured_image':
            case 'post_after_featured_image':
                return [
                    'post_id' => $args[0] ?? 0,
                    'attributes' => $args[1] ?? [],
                    'post_data' => get_post($args[0] ?? 0),
                    'triggered_at' => current_time('mysql'),
                ];
                
            default:
                return [
                    'hook_args' => $args,
                    'triggered_at' => current_time('mysql'),
                ];
        }
    }

   public static function get_actions(): array {
        return [];
    }

    public static function get_action_config_schema( string $action ): array {
        return [];
    }

    public static function execute_node( array $node, array $input ): array {
        return ['port' => 'main', 'data' => $input];
    }
}