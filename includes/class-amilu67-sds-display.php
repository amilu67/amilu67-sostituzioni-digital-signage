<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AMILU67_SDS_Display {
    private AMILU67_SDS_DB $db;

    public function __construct( AMILU67_SDS_DB $db ) {
        $this->db = $db;
    }

    public function register_rewrite(): void {
        add_rewrite_rule( '^amilu67-sostituzioni-schermo/?$', 'index.php?amilu67_sds_signage=1', 'top' );
    }

    public function query_vars( array $vars ): array {
        $vars[] = 'amilu67_sds_signage';
        return $vars;
    }

    public function maybe_render_signage(): void {
        if ( ! get_query_var( 'amilu67_sds_signage' ) ) {
            return;
        }

        status_header( 200 );
        nocache_headers();
        $settings = $this->db->settings();
        $date = wp_date( 'Y-m-d' );
        $accent = sanitize_hex_color( $settings['accent'] ) ?: '#0066cc';

        wp_enqueue_style( 'amilu67-sds-signage', AMILU67_SDS_URL . 'assets/signage.css', array(), AMILU67_SDS_VERSION );
        wp_add_inline_style( 'amilu67-sds-signage', ':root{--amilu67-sds-accent:' . $accent . ';}' );
        wp_enqueue_script( 'amilu67-sds-signage', AMILU67_SDS_URL . 'assets/signage.js', array(), AMILU67_SDS_VERSION, true );
        ?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="robots" content="noindex,nofollow">
<title><?php echo esc_html( $settings['screen_title'] . ' – ' . $settings['school_name'] ); ?></title>
<?php wp_print_styles( array( 'amilu67-sds-signage' ) ); ?>
</head>
<body class="amilu67-sds-standalone amilu67-sds-kiosk-view">
<?php echo $this->board_markup( $date, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
<?php wp_print_scripts( array( 'amilu67-sds-signage' ) ); ?>
</body>
</html><?php
        exit;
    }

    public function shortcode( array $atts = array() ): string {
        $atts = shortcode_atts( array( 'date' => '' ), $atts, 'amilu67_sds_sostituzioni' );
        $date = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $atts['date'] ) ? $atts['date'] : wp_date( 'Y-m-d' );
        wp_enqueue_style( 'amilu67-sds-signage', AMILU67_SDS_URL . 'assets/signage.css', array(), AMILU67_SDS_VERSION );
        wp_enqueue_script( 'amilu67-sds-signage', AMILU67_SDS_URL . 'assets/signage.js', array(), AMILU67_SDS_VERSION, true );
        return $this->board_markup( $date, false );
    }

    private function board_markup( string $date, bool $standalone ): string {
        $settings = $this->db->settings();
        $rows = $this->db->list_substitutions( $date, false );
        $classes = $this->db->get_absent_classes( $date );
        $endpoint = rest_url( 'amilu67-sds/v1/screen' );
        $refresh = max( 10, min( 300, (int) $settings['refresh_seconds'] ) );

        ob_start();
        ?>
<main class="amilu67-sds-screen-root<?php echo $standalone ? ' amilu67-sds-screen-root--standalone' : ''; ?>" data-endpoint="<?php echo esc_url( $endpoint ); ?>" data-refresh="<?php echo esc_attr( $refresh ); ?>" data-fixed-date="<?php echo $standalone ? '' : esc_attr( $date ); ?>">
    <header class="amilu67-sds-screen-header">
        <div class="amilu67-sds-screen-title">
            <p class="amilu67-sds-kicker"><span class="amilu67-sds-ui-icon" aria-hidden="true"><?php echo $this->icon( 'school' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><?php echo esc_html( $settings['school_name'] ); ?></p>
            <h1><?php echo esc_html( $settings['screen_title'] ); ?></h1>
        </div>
        <div class="amilu67-sds-header-tools">
            <?php if ( $standalone ) : ?>
                <button class="amilu67-sds-fullscreen-toggle" type="button" aria-label="Attiva schermo intero" title="Attiva schermo intero">
                    <span class="amilu67-sds-fullscreen-icon is-enter" aria-hidden="true"><?php echo $this->icon( 'fullscreen' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                    <span class="amilu67-sds-fullscreen-icon is-exit" aria-hidden="true"><?php echo $this->icon( 'fullscreen-exit' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                    <span class="amilu67-sds-fullscreen-text">Schermo intero</span>
                </button>
            <?php endif; ?>
            <div class="amilu67-sds-clock" aria-live="off">
                <span class="amilu67-sds-clock-time"><span class="amilu67-sds-ui-icon amilu67-sds-clock-icon" aria-hidden="true"><?php echo $this->icon( 'clock' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><?php echo esc_html( wp_date( 'H:i' ) ); ?></span>
                <span class="amilu67-sds-date-label"><span class="amilu67-sds-ui-icon" aria-hidden="true"><?php echo $this->icon( 'calendar' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><?php echo esc_html( wp_date( 'l j F Y', strtotime( $date . ' 12:00:00' ) ) ); ?></span>
            </div>
        </div>
    </header>

    <section class="amilu67-sds-absent-classes" aria-label="Classi assenti"<?php echo empty( $classes ) ? ' hidden' : ''; ?>>
        <span class="amilu67-sds-section-label"><span class="amilu67-sds-ui-icon" aria-hidden="true"><?php echo $this->icon( 'warning' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>Classi assenti</span>
        <div class="amilu67-sds-class-chips">
            <?php foreach ( $classes as $class ) : ?>
                <span class="amilu67-sds-chip"><?php echo esc_html( $class['class_name'] ); ?> · <?php echo esc_html( $this->range_label( (int) $class['from_period'], (int) $class['to_period'] ) ); ?></span>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="amilu67-sds-board" aria-live="polite" aria-atomic="false">
        <div class="amilu67-sds-board-head" aria-hidden="true">
            <span><span class="amilu67-sds-ui-icon"><?php echo $this->icon( 'clock' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>Ora</span>
            <span><span class="amilu67-sds-ui-icon"><?php echo $this->icon( 'classroom' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>Classe</span>
            <span><span class="amilu67-sds-ui-icon"><?php echo $this->icon( 'person-off' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>Docente assente</span>
            <span><span class="amilu67-sds-ui-icon"><?php echo $this->icon( 'swap' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>Sostituisce</span>
            <span><span class="amilu67-sds-ui-icon"><?php echo $this->icon( 'pin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>Aula / Note</span>
        </div>
        <div class="amilu67-sds-board-rows">
            <?php echo $this->render_rows( $rows, $settings ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>
    </section>

    <footer class="amilu67-sds-screen-footer">
        <span class="amilu67-sds-screen-note"><?php echo esc_html( $settings['screen_note'] ); ?></span>
        <span class="amilu67-sds-sync"><span class="amilu67-sds-ui-icon amilu67-sds-sync-icon" aria-hidden="true"><?php echo $this->icon( 'sync' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>Aggiornato alle <strong class="amilu67-sds-generated-at"><?php echo esc_html( wp_date( 'H:i:s' ) ); ?></strong></span>
    </footer>
</main>
        <?php
        return (string) ob_get_clean();
    }

    private function render_rows( array $rows, array $settings ): string {
        if ( empty( $rows ) ) {
            return '<div class="amilu67-sds-empty"><strong>Nessuna sostituzione da visualizzare.</strong><span>Il prospetto si aggiorna automaticamente.</span></div>';
        }
        ob_start();
        foreach ( $rows as $row ) {
            $absent = $this->name( $row['absent_first_name'], $row['absent_last_name'], $settings['name_format'] );
            $sub = $row['substitute_teacher_id'] ? $this->name( $row['substitute_first_name'], $row['substitute_last_name'], $settings['name_format'] ) : 'Da assegnare';
            $meta = trim( implode( ' · ', array_filter( array( $row['room'] ? 'Aula ' . $row['room'] : '', $row['note'] ) ) ) );
            ?>
            <article class="amilu67-sds-board-row<?php echo 'pending' === $row['status'] ? ' is-pending' : ''; ?>">
                <div class="amilu67-sds-period"><strong><?php echo esc_html( (string) $row['period'] ); ?>ª</strong><?php if ( $row['start_time'] ) : ?><small><?php echo esc_html( substr( $row['start_time'], 0, 5 ) ); ?></small><?php endif; ?></div>
                <div class="amilu67-sds-class"><strong><?php echo esc_html( $row['class_name'] ); ?></strong><?php if ( $row['subject'] ) : ?><small><?php echo esc_html( $row['subject'] ); ?></small><?php endif; ?></div>
                <div class="amilu67-sds-person"><?php echo esc_html( $absent ); ?></div>
                <div class="amilu67-sds-person amilu67-sds-substitute"><?php echo esc_html( $sub ); ?></div>
                <div class="amilu67-sds-meta"><?php echo esc_html( $meta ?: '—' ); ?></div>
            </article>
            <?php
        }
        return (string) ob_get_clean();
    }


    private function icon( string $name ): string {
        $icons = array(
            'school'          => '<svg viewBox="0 0 24 24" focusable="false"><path d="M3 21h18M5 21V9l7-4 7 4v12M9 21v-6h6v6M8 11h1M15 11h1"/></svg>',
            'clock'           => '<svg viewBox="0 0 24 24" focusable="false"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
            'calendar'        => '<svg viewBox="0 0 24 24" focusable="false"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4M17 3v4M3 10h18"/></svg>',
            'warning'         => '<svg viewBox="0 0 24 24" focusable="false"><path d="M12 3 2.8 20h18.4L12 3Z"/><path d="M12 9v5M12 17.3v.2"/></svg>',
            'classroom'       => '<svg viewBox="0 0 24 24" focusable="false"><path d="M4 5h16v11H4zM8 20h8M12 16v4"/><path d="M8 9h8M8 12h5"/></svg>',
            'person-off'      => '<svg viewBox="0 0 24 24" focusable="false"><circle cx="10" cy="8" r="3"/><path d="M4.5 19c.8-3.2 2.7-5 5.5-5 1.1 0 2.1.3 2.9.8M16 10l5 5M21 10l-5 5"/></svg>',
            'swap'            => '<svg viewBox="0 0 24 24" focusable="false"><path d="M4 8h13l-3-3M20 16H7l3 3"/></svg>',
            'pin'             => '<svg viewBox="0 0 24 24" focusable="false"><path d="M12 21s6-5.2 6-11a6 6 0 1 0-12 0c0 5.8 6 11 6 11Z"/><circle cx="12" cy="10" r="2"/></svg>',
            'sync'            => '<svg viewBox="0 0 24 24" focusable="false"><path d="M20 7v5h-5M4 17v-5h5"/><path d="M6.2 8.2A7 7 0 0 1 18.5 7M17.8 15.8A7 7 0 0 1 5.5 17"/></svg>',
            'fullscreen'      => '<svg viewBox="0 0 24 24" focusable="false"><path d="M8 3H3v5M16 3h5v5M8 21H3v-5M16 21h5v-5"/></svg>',
            'fullscreen-exit' => '<svg viewBox="0 0 24 24" focusable="false"><path d="M8 8H3V3M16 8h5V3M8 16H3v5M16 16h5v5"/></svg>',
        );
        return $icons[ $name ] ?? '';
    }

    private function name( ?string $first, ?string $last, string $mode ): string {
        $first = trim( (string) $first );
        $last = trim( (string) $last );
        if ( 'full' === $mode ) {
            return trim( $first . ' ' . $last );
        }
        if ( 'surname_only' === $mode ) {
            return $last;
        }
        return trim( $last . ( $first ? ' ' . ( function_exists( 'mb_substr' ) ? mb_substr( $first, 0, 1 ) : substr( $first, 0, 1 ) ) . '.' : '' ) );
    }

    private function range_label( int $from, int $to ): string {
        return $from === $to ? $from . 'ª ora' : $from . 'ª–' . $to . 'ª ora';
    }
}
