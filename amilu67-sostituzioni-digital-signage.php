<?php
/**
 * Plugin Name: amilu67 Sostituzioni Digital Signage
 * Description: Gestione orari, assenze, classi assenti, disponibilità e sostituzioni docenti con visualizzazione digital signage per istituti scolastici.
 * Version: 1.2.0
 * Author: amilu67
 * License: GPL-2.0-or-later
 * Text Domain: amilu67-sostituzioni-digital-signage
 * Requires at least: 6.5
 * Requires PHP: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'AMILU67_SDS_VERSION', '1.2.0' );
define( 'AMILU67_SDS_PLUGIN_SLUG', 'amilu67-sostituzioni-digital-signage' );
define( 'AMILU67_SDS_FILE', __FILE__ );
define( 'AMILU67_SDS_DIR', plugin_dir_path( __FILE__ ) );
define( 'AMILU67_SDS_URL', plugin_dir_url( __FILE__ ) );

require_once AMILU67_SDS_DIR . 'includes/class-amilu67-sds-db.php';
require_once AMILU67_SDS_DIR . 'includes/class-amilu67-sds-recommender.php';
require_once AMILU67_SDS_DIR . 'includes/class-amilu67-sds-importer.php';
require_once AMILU67_SDS_DIR . 'includes/class-amilu67-sds-display.php';
require_once AMILU67_SDS_DIR . 'includes/class-amilu67-sds-rest.php';
require_once AMILU67_SDS_DIR . 'includes/class-amilu67-sds-admin.php';

final class AMILU67_SDS_Plugin {
    private static ?AMILU67_SDS_Plugin $instance = null;

    public AMILU67_SDS_DB $db;
    public AMILU67_SDS_Recommender $recommender;
    public AMILU67_SDS_Importer $importer;
    public AMILU67_SDS_Display $display;
    public AMILU67_SDS_REST $rest;
    public AMILU67_SDS_Admin $admin;

    public static function instance(): AMILU67_SDS_Plugin {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->db          = new AMILU67_SDS_DB();
        $this->recommender = new AMILU67_SDS_Recommender( $this->db );
        $this->importer    = new AMILU67_SDS_Importer( $this->db );
        $this->display     = new AMILU67_SDS_Display( $this->db );
        $this->rest        = new AMILU67_SDS_REST( $this->db, $this->recommender );
        $this->admin       = new AMILU67_SDS_Admin( $this->db, $this->recommender, $this->importer );

        add_action( 'init', array( $this->display, 'register_rewrite' ) );
        add_filter( 'query_vars', array( $this->display, 'query_vars' ) );
        add_action( 'template_redirect', array( $this->display, 'maybe_render_signage' ) );
        add_shortcode( 'amilu67_sds_sostituzioni', array( $this->display, 'shortcode' ) );
        add_action( 'rest_api_init', array( $this->rest, 'register_routes' ) );
        add_action( 'admin_init', array( $this, 'maybe_upgrade' ) );
    }

    public function maybe_upgrade(): void {
        $installed = get_option( 'amilu67_sds_db_version' );
        if ( AMILU67_SDS_VERSION !== $installed ) {
            $this->db->install();
            update_option( 'amilu67_sds_db_version', AMILU67_SDS_VERSION );
        }
    }

    public static function activate(): void {
        $db = new AMILU67_SDS_DB();
        $db->install();

        $admin = get_role( 'administrator' );
        if ( $admin ) {
            $admin->add_cap( 'amilu67_sds_manage_school_signage' );
        }

        if ( false === get_option( 'amilu67_sds_settings', false ) ) {
            add_option(
                'amilu67_sds_settings',
                array(
                    'school_name'       => get_bloginfo( 'name' ),
                    'screen_title'      => 'Sostituzioni docenti',
                    'refresh_seconds'   => 30,
                    'name_format'       => 'surname_initial',
                    'show_pending'      => 0,
                    'screen_note'       => '',
                    'consider_free'     => 0,
                    'accent'            => '#0066cc',
                )
            );
        }

        $display = new AMILU67_SDS_Display( $db );
        $display->register_rewrite();
        flush_rewrite_rules();
        update_option( 'amilu67_sds_db_version', AMILU67_SDS_VERSION );
    }

    public static function deactivate(): void {
        flush_rewrite_rules();
    }
}

register_activation_hook( __FILE__, array( 'AMILU67_SDS_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'AMILU67_SDS_Plugin', 'deactivate' ) );

add_action(
    'plugins_loaded',
    static function (): void {
        AMILU67_SDS_Plugin::instance();
    }
);
