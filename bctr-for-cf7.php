<?php

/**
Plugin Name: Record For Contact Form 7
Plugin URI:
Description: Record For Contact Form 7 is a plugin for WordPress, you can save all submitted from contact form 7 to database and display in Contact > Record menu
Author: Bool Cool Team
Version: 1.0.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Author URI:
 */

if( ! defined( 'ABSPATH' ) ) {
	die("Silence is golden");
}

define( 'BCTR_CF7_PLUGIN_DIR', dirname( __FILE__ ) );
define( 'BCTR_CF7_VERSION', '1.0.4' );

class BCTR_Cf7r_Plugin {
    private string $plugin;

    function __construct() {
        $this->plugin = plugin_basename( __FILE__ );

        require_once BCTR_CF7_PLUGIN_DIR . '/logic/contact.php';
        require_once BCTR_CF7_PLUGIN_DIR . '/logic/form.php';
        require_once BCTR_CF7_PLUGIN_DIR . '/logic/ajax.php';
    }

    function register() {
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );

        add_action( 'admin_menu', array($this, 'add_admin_pages') );

        //view database link
        add_filter( 'plugin_action_links_' . $this->plugin, array($this, 'plugin_action_database_view'), 10, 2 );
        //other link
        //add_filter( 'plugin_action_links_' . $this->plugin, array($this, 'plugin_action_other') );

        // hook cf7 submit
        add_action( 'wpcf7_before_send_mail',  array($this, 'hook_before_send_email'), 10, 3 );

        // ajax func
        add_action( 'wp_ajax_bctr_cf7_load_page', array('BCTR_Cf7_Ajax', 'GetDataPageLoad') );
        add_action( 'wp_ajax_bctr_cf7_list', array('BCTR_Cf7_Ajax', 'GetContactData') );
        add_action( 'wp_ajax_bctr_cf7_edit', array('BCTR_Cf7_Ajax', 'EditRow') );
        add_action( 'wp_ajax_bctr_cf7_del', array('BCTR_Cf7_Ajax', 'DeleteRow') );
        add_action( 'wp_ajax_bctr_cf7_save_setting', array('BCTR_Cf7_Ajax', 'SaveSetting') );
        add_action( 'wp_ajax_bctr_cf7_followup_add', array('BCTR_Cf7_Ajax', 'AddFollowup') );
        add_action( 'wp_ajax_bctr_cf7_followup_list', array('BCTR_Cf7_Ajax', 'GetFollowupList') );
        add_action( 'wp_ajax_bctr_cf7_followup_del', array('BCTR_Cf7_Ajax', 'DelFollowup') );
    }

    function plugin_action_database_view( $links, $file ) {
        if ( $file != $this->plugin ) {
            return $links;
        }

        if ( ! current_user_can( 'wpcf7_read_contact_forms' ) ) {
            return $links;
        }

        $this->add_link( $links, '<a href="admin.php?page=bctr_cf7_record">View Record</a>', 'before' );

        return $links;
    }

    function add_link( &$links, $newLink, $pos = "after" ) {
        if( $pos == "after" ) {
            array_push($links, $newLink);
        } else {
            array_unshift($links, $newLink);
        }
    }

    function add_admin_pages() {
        $GLOBALS['bctr_cf7_hook_suffix'] =
            add_submenu_page(
                'wpcf7',
                'Record',
                'Record',
                apply_filters( 'bctr_cf7_options_page_capabilities', 'manage_options' ),
                'bctr_cf7_record',
                array($this, 'admin_index')
            );
    }

    function admin_index() {
        echo '<div id="boo-cool-cf7-app" style="padding-right: 1rem;"></div>';
    }

    function activate() {
        BCTR_Cf7_Subscriber::InitTable();
        flush_rewrite_rules();
    }

    function deactivate() {
        flush_rewrite_rules();
    }

    function enqueue( $menuHook ) {
        $isBooCoolPage = is_rtl() == '1' && strrpos( $menuHook, 'page_bctr_cf7_record' );
        if ( $menuHook === $GLOBALS['bctr_cf7_hook_suffix'] || $isBooCoolPage ) {

            wp_enqueue_style( 'bctr_cf7_main_css', plugins_url( 'admin/main.css', __FILE__ ), array(), BCTR_CF7_VERSION );
            wp_enqueue_script( 'bctr_cf7_main_js', plugins_url( 'admin/main.js', __FILE__ ), array(), BCTR_CF7_VERSION, true );
            // js window data
            wp_localize_script(
                'bctr_cf7_main_js',
                'bctr_cf7_data',
                array(
                    'nonce' => wp_create_nonce( 'bctr_cf7_nonce' ),
                    'is_rtl' => apply_filters( 'cf7d_is_rtl', is_rtl() ),
                    'locale' => get_user_locale(),
                    'lang' => get_available_languages()
                )
            );

        }
    }

    function hook_before_send_email($contact_form, &$abort, $submission) {
        BCTR_Cf7_Subscriber::AddContact($contact_form, $abort, $submission);
    }
}

$bctr_cf7r_plugin = new BCTR_Cf7r_Plugin();
$bctr_cf7r_plugin->register();

register_activation_hook( __FILE__, array($bctr_cf7r_plugin, 'activate') );
register_deactivation_hook( __FILE__, array($bctr_cf7r_plugin, 'deactivate') );