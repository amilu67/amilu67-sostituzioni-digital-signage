<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AMILU67_SDS_Admin {
    private AMILU67_SDS_DB $db;
    private AMILU67_SDS_Recommender $recommender;
    private AMILU67_SDS_Importer $importer;

    public function __construct( AMILU67_SDS_DB $db, AMILU67_SDS_Recommender $recommender, AMILU67_SDS_Importer $importer ) {
        $this->db = $db;
        $this->recommender = $recommender;
        $this->importer = $importer;

        add_action( 'admin_menu', array( $this, 'menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
        add_action( 'admin_post_amilu67_sds_save_teacher', array( $this, 'save_teacher' ) );
        add_action( 'admin_post_amilu67_sds_delete_teacher', array( $this, 'delete_teacher' ) );
        add_action( 'admin_post_amilu67_sds_import_teacher', array( $this, 'import_teacher' ) );
        add_action( 'admin_post_amilu67_sds_import_bulk', array( $this, 'import_bulk' ) );
        add_action( 'admin_post_amilu67_sds_save_schedule_table', array( $this, 'save_schedule_table' ) );
        add_action( 'admin_post_amilu67_sds_add_teacher_absence', array( $this, 'add_teacher_absence' ) );
        add_action( 'admin_post_amilu67_sds_delete_teacher_absence', array( $this, 'delete_teacher_absence' ) );
        add_action( 'admin_post_amilu67_sds_add_absent_class', array( $this, 'add_absent_class' ) );
        add_action( 'admin_post_amilu67_sds_delete_absent_class', array( $this, 'delete_absent_class' ) );
        add_action( 'admin_post_amilu67_sds_assign_substitute', array( $this, 'assign_substitute' ) );
        add_action( 'admin_post_amilu67_sds_save_settings', array( $this, 'save_settings' ) );
    }

    public function menu(): void {
        $cap = $this->capability();
        add_menu_page( 'amilu67 Sostituzioni Digital Signage', 'amilu67 Sostituzioni Digital Signage', $cap, 'amilu67-sds-signage', array( $this, 'dashboard_page' ), 'dashicons-welcome-view-site', 26 );
        add_submenu_page( 'amilu67-sds-signage', 'Dashboard', 'Dashboard', $cap, 'amilu67-sds-signage', array( $this, 'dashboard_page' ) );
        add_submenu_page( 'amilu67-sds-signage', 'Docenti', 'Docenti', $cap, 'amilu67-sds-teachers', array( $this, 'teachers_page' ) );
        add_submenu_page( 'amilu67-sds-signage', 'Orario e disponibilità', 'Orario e disponibilità', $cap, 'amilu67-sds-schedule', array( $this, 'schedule_page' ) );
        add_submenu_page( 'amilu67-sds-signage', 'Assenze', 'Assenze', $cap, 'amilu67-sds-absences', array( $this, 'absences_page' ) );
        add_submenu_page( 'amilu67-sds-signage', 'Sostituzioni', 'Sostituzioni', $cap, 'amilu67-sds-substitutions', array( $this, 'substitutions_page' ) );
        add_submenu_page( 'amilu67-sds-signage', 'Impostazioni schermo', 'Impostazioni schermo', $cap, 'amilu67-sds-settings', array( $this, 'settings_page' ) );
    }

    public function assets( string $hook ): void {
        if ( false === strpos( $hook, 'amilu67-sds-' ) && 'toplevel_page_amilu67-sds-signage' !== $hook ) {
            return;
        }
        wp_enqueue_style( 'amilu67-sds-admin', AMILU67_SDS_URL . 'assets/admin.css', array(), AMILU67_SDS_VERSION );
        wp_enqueue_script( 'amilu67-sds-admin', AMILU67_SDS_URL . 'assets/admin.js', array(), AMILU67_SDS_VERSION, true );
    }

    private function capability(): string {
        return current_user_can( 'amilu67_sds_manage_school_signage' ) ? 'amilu67_sds_manage_school_signage' : 'manage_options';
    }

    private function guard(): void {
        if ( ! current_user_can( 'amilu67_sds_manage_school_signage' ) && ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Non autorizzato.', 'amilu67-sostituzioni-digital-signage' ) );
        }
    }

    private function redirect( string $page, string $message, string $type = 'success', array $extra = array() ): void {
        $args = array_merge( array( 'page' => $page, 'amilu67_sds_message' => $message, 'amilu67_sds_type' => $type ), $extra );
        wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
        exit;
    }

    private function notices(): void {
        // Read-only display parameters created by this plugin after an admin redirect.
        $message = isset( $_GET['amilu67_sds_message'] ) ? sanitize_text_field( wp_unslash( $_GET['amilu67_sds_message'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( '' === $message ) {
            return;
        }
        $notice_type = isset( $_GET['amilu67_sds_type'] ) ? sanitize_key( wp_unslash( $_GET['amilu67_sds_type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $type = 'error' === $notice_type ? 'notice-error' : 'notice-success';
        echo '<div class="notice ' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
    }

    private function page_header( string $title, string $subtitle = '' ): void {
        echo '<div class="wrap amilu67-sds-admin"><div class="amilu67-sds-admin-hero"><div><p class="amilu67-sds-eyebrow">amilu67 Sostituzioni Digital Signage</p><h1>' . esc_html( $title ) . '</h1>';
        if ( $subtitle ) {
            echo '<p>' . esc_html( $subtitle ) . '</p>';
        }
        echo '</div><a class="button button-primary amilu67-sds-screen-button" href="' . esc_url( $this->screen_url() ) . '" target="_blank" rel="noopener"><span class="dashicons dashicons-desktop"></span> Apri schermo</a></div>';
        $this->notices();
    }

    private function page_end(): void {
        echo '</div>';
    }

    public function dashboard_page(): void {
        $this->page_header( 'Dashboard', 'Controllo rapido di orari, assenze e sostituzioni.' );
        $today = wp_date( 'Y-m-d' );
        $counts = $this->db->count_today_dashboard( $today );
        $rows = $this->db->list_substitutions( $today, true );
        ?>
        <div class="amilu67-sds-stat-grid">
            <?php
            $stats = array(
                array( 'Docenti attivi', $counts['teachers'], 'groups' ),
                array( 'Fasce orarie', $counts['schedule'], 'calendar-alt' ),
                array( 'Docenti assenti oggi', $counts['absences'], 'businessperson' ),
                array( 'Sostituzioni oggi', $counts['substitutions'], 'yes-alt' ),
                array( 'Da assegnare', $counts['pending'], 'warning' ),
                array( 'Classi assenti', $counts['classes'], 'location-alt' ),
            );
            foreach ( $stats as $stat ) : ?>
                <div class="amilu67-sds-stat-card"><span class="dashicons dashicons-<?php echo esc_attr( $stat[2] ); ?>"></span><strong><?php echo esc_html( (string) $stat[1] ); ?></strong><span><?php echo esc_html( $stat[0] ); ?></span></div>
            <?php endforeach; ?>
        </div>
        <div class="amilu67-sds-grid-2">
            <section class="amilu67-sds-panel">
                <div class="amilu67-sds-panel-title"><div><h2>Sostituzioni di oggi</h2><p><?php echo esc_html( wp_date( 'l j F Y', strtotime( $today . ' 12:00:00' ) ) ); ?></p></div><a href="<?php echo esc_url( admin_url( 'admin.php?page=amilu67-sds-substitutions' ) ); ?>">Gestisci</a></div>
                <?php if ( $rows ) : ?>
                    <div class="amilu67-sds-mini-list">
                    <?php foreach ( array_slice( $rows, 0, 8 ) as $row ) : ?>
                        <div><span class="amilu67-sds-hour-badge"><?php echo esc_html( $row['period'] . 'ª' ); ?></span><strong><?php echo esc_html( $row['class_name'] ); ?></strong><span><?php echo esc_html( trim( $row['absent_last_name'] . ' → ' . ( $row['substitute_last_name'] ?: 'da assegnare' ) ) ); ?></span><span class="amilu67-sds-status is-<?php echo esc_attr( $row['status'] ); ?>"><?php echo esc_html( $this->status_label( $row['status'] ) ); ?></span></div>
                    <?php endforeach; ?>
                    </div>
                <?php else : ?><div class="amilu67-sds-empty-admin">Nessuna sostituzione registrata per oggi.</div><?php endif; ?>
            </section>
            <section class="amilu67-sds-panel amilu67-sds-quick-panel">
                <div class="amilu67-sds-panel-title"><div><h2>Azioni rapide</h2><p>Flusso consigliato per la segreteria.</p></div></div>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=amilu67-sds-absences' ) ); ?>"><span class="dashicons dashicons-calendar"></span><div><strong>Registra un’assenza</strong><small>Genera automaticamente le ore da coprire.</small></div></a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=amilu67-sds-substitutions' ) ); ?>"><span class="dashicons dashicons-randomize"></span><div><strong>Assegna i sostituti</strong><small>Priorità a disponibilità e docenti liberati.</small></div></a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=amilu67-sds-schedule' ) ); ?>"><span class="dashicons dashicons-upload"></span><div><strong>Importa orario</strong><small>Caricamento singolo o bulk in CSV.</small></div></a>
            </section>
        </div>
        <?php
        $this->page_end();
    }

    public function teachers_page(): void {
        $this->page_header( 'Docenti', 'Anagrafica essenziale usata per orari, disponibilità e sostituzioni.' );
        $teachers = $this->db->list_teachers();
        ?>
        <div class="amilu67-sds-grid-sidebar">
            <section class="amilu67-sds-panel">
                <h2>Aggiungi o aggiorna docente</h2>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="amilu67-sds-form-grid">
                    <input type="hidden" name="action" value="amilu67_sds_save_teacher"><?php wp_nonce_field( 'amilu67_sds_save_teacher' ); ?>
                    <label><span>Codice docente *</span><input name="code" required placeholder="es. ROSSI-M"></label>
                    <label><span>Nome</span><input name="first_name"></label>
                    <label><span>Cognome *</span><input name="last_name" required></label>
                    <label><span>Email</span><input type="email" name="email"></label>
                    <label class="amilu67-sds-check"><input type="checkbox" name="active" value="1" checked> <span>Docente attivo</span></label>
                    <div><button class="button button-primary">Salva docente</button></div>
                </form>
            </section>
            <section class="amilu67-sds-panel">
                <div class="amilu67-sds-panel-title"><div><h2>Elenco docenti</h2><p><?php echo esc_html( count( $teachers ) . ' docenti registrati' ); ?></p></div></div>
                <div class="amilu67-sds-table-wrap"><table class="widefat striped amilu67-sds-table"><thead><tr><th>Codice</th><th>Docente</th><th>Email</th><th>Stato</th><th></th></tr></thead><tbody>
                <?php foreach ( $teachers as $teacher ) : ?>
                    <tr><td><code><?php echo esc_html( $teacher['code'] ); ?></code></td><td><strong><?php echo esc_html( trim( $teacher['last_name'] . ' ' . $teacher['first_name'] ) ); ?></strong></td><td><?php echo esc_html( $teacher['email'] ); ?></td><td><span class="amilu67-sds-status <?php echo $teacher['active'] ? 'is-assigned' : 'is-cancelled'; ?>"><?php echo $teacher['active'] ? 'Attivo' : 'Disattivo'; ?></span></td><td class="amilu67-sds-actions"><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Eliminare il docente e il relativo orario?')"><input type="hidden" name="action" value="amilu67_sds_delete_teacher"><input type="hidden" name="id" value="<?php echo esc_attr( $teacher['id'] ); ?>"><?php wp_nonce_field( 'amilu67_sds_delete_teacher' ); ?><button class="button-link-delete">Elimina</button></form></td></tr>
                <?php endforeach; ?>
                <?php if ( ! $teachers ) : ?><tr><td colspan="5">Nessun docente registrato.</td></tr><?php endif; ?>
                </tbody></table></div>
            </section>
        </div>
        <?php $this->page_end();
    }

    public function schedule_page(): void {
        $this->page_header( 'Orario e disponibilità', 'Gestisci l’orario con editor tabellare oppure importa file CSV singoli o bulk.' );
        $teachers = $this->db->list_teachers( true );
        $filter_teacher = isset( $_GET['teacher_id'] ) ? absint( wp_unslash( $_GET['teacher_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $schedule = $this->db->get_schedule( $filter_teacher ?: null );
        $teacher_schedule = array();
        if ( $filter_teacher ) {
            foreach ( $schedule as $slot ) {
                $teacher_schedule[ (int) $slot['weekday'] ][ (int) $slot['period'] ] = $slot;
            }
        }
        $active_day = (int) wp_date( 'N' );
        if ( $active_day < 1 || $active_day > 6 ) {
            $active_day = 1;
        }
        ?>
        <section class="amilu67-sds-panel amilu67-sds-schedule-editor">
            <div class="amilu67-sds-panel-title amilu67-sds-editor-heading">
                <div>
                    <span class="amilu67-sds-section-kicker">Inserimento manuale</span>
                    <h2>Editor tabellare settimanale</h2>
                    <p>Seleziona un docente e compila le ore da lunedì a sabato. Le righe vuote vengono ignorate; scegli “Disponibilità” per rendere il docente prioritario nelle sostituzioni.</p>
                </div>
                <form method="get" class="amilu67-sds-teacher-picker">
                    <input type="hidden" name="page" value="amilu67-sds-schedule">
                    <label><span>Docente da modificare</span>
                        <select name="teacher_id" onchange="this.form.submit()">
                            <option value="0">Seleziona un docente…</option>
                            <?php foreach ( $teachers as $t ) : ?>
                                <option value="<?php echo esc_attr( $t['id'] ); ?>" <?php selected( $filter_teacher, (int) $t['id'] ); ?>><?php echo esc_html( $t['last_name'] . ' ' . $t['first_name'] ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </form>
            </div>

            <?php if ( $filter_teacher ) : ?>
                <?php $selected_teacher = $this->db->get_teacher( $filter_teacher ); ?>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="amilu67-sds-week-form" data-amilu67-sds-schedule-form>
                    <input type="hidden" name="action" value="amilu67_sds_save_schedule_table">
                    <input type="hidden" name="teacher_id" value="<?php echo esc_attr( $filter_teacher ); ?>">
                    <?php wp_nonce_field( 'amilu67_sds_save_schedule_table' ); ?>

                    <div class="amilu67-sds-editor-toolbar">
                        <div class="amilu67-sds-selected-teacher">
                            <span class="dashicons dashicons-businessperson"></span>
                            <div><small>Orario di</small><strong><?php echo esc_html( trim( ( $selected_teacher['last_name'] ?? '' ) . ' ' . ( $selected_teacher['first_name'] ?? '' ) ) ); ?></strong></div>
                        </div>
                        <div class="amilu67-sds-editor-actions">
                            <button type="button" class="button" data-amilu67-sds-copy-times><span class="dashicons dashicons-admin-page"></span> Copia fasce orarie sugli altri giorni</button>
                            <button type="button" class="button" data-amilu67-sds-clear-day><span class="dashicons dashicons-trash"></span> Pulisci giorno</button>
                        </div>
                    </div>

                    <div class="amilu67-sds-day-tabs" role="tablist" aria-label="Giorni della settimana">
                        <?php for ( $day = 1; $day <= 6; $day++ ) : ?>
                            <button type="button" class="amilu67-sds-day-tab <?php echo $day === $active_day ? 'is-active' : ''; ?>" data-day-tab="<?php echo esc_attr( $day ); ?>" role="tab" aria-selected="<?php echo $day === $active_day ? 'true' : 'false'; ?>">
                                <span><?php echo esc_html( $this->weekday_label( $day ) ); ?></span>
                                <small><?php echo esc_html( $this->schedule_day_summary( $teacher_schedule[ $day ] ?? array() ) ); ?></small>
                            </button>
                        <?php endfor; ?>
                    </div>

                    <?php for ( $day = 1; $day <= 6; $day++ ) : ?>
                        <div class="amilu67-sds-day-panel <?php echo $day === $active_day ? 'is-active' : ''; ?>" data-day-panel="<?php echo esc_attr( $day ); ?>" role="tabpanel">
                            <div class="amilu67-sds-table-wrap amilu67-sds-editor-table-wrap">
                                <table class="widefat amilu67-sds-schedule-table">
                                    <thead><tr><th class="amilu67-sds-col-period">Ora</th><th>Inizio</th><th>Fine</th><th>Tipo</th><th>Classe</th><th>Materia</th><th>Aula</th></tr></thead>
                                    <tbody>
                                    <?php for ( $period = 1; $period <= 12; $period++ ) :
                                        $slot = $teacher_schedule[ $day ][ $period ] ?? array();
                                        $activity = $slot['activity'] ?? '';
                                        $row_class = $activity ? 'is-' . $activity : 'is-empty';
                                        ?>
                                        <tr class="amilu67-sds-schedule-row <?php echo esc_attr( $row_class ); ?>" data-schedule-row>
                                            <td class="amilu67-sds-period-cell"><span><?php echo esc_html( $period . 'ª' ); ?></span></td>
                                            <td><input type="time" name="schedule[<?php echo esc_attr( $day ); ?>][<?php echo esc_attr( $period ); ?>][start_time]" value="<?php echo esc_attr( ! empty( $slot['start_time'] ) ? substr( $slot['start_time'], 0, 5 ) : '' ); ?>" data-detail-field></td>
                                            <td><input type="time" name="schedule[<?php echo esc_attr( $day ); ?>][<?php echo esc_attr( $period ); ?>][end_time]" value="<?php echo esc_attr( ! empty( $slot['end_time'] ) ? substr( $slot['end_time'], 0, 5 ) : '' ); ?>" data-detail-field></td>
                                            <td>
                                                <select name="schedule[<?php echo esc_attr( $day ); ?>][<?php echo esc_attr( $period ); ?>][activity]" data-activity-select>
                                                    <option value="" <?php selected( $activity, '' ); ?>>— Vuota —</option>
                                                    <option value="lesson" <?php selected( $activity, 'lesson' ); ?>>Lezione</option>
                                                    <option value="availability" <?php selected( $activity, 'availability' ); ?>>Disponibilità</option>
                                                </select>
                                            </td>
                                            <td><input type="text" name="schedule[<?php echo esc_attr( $day ); ?>][<?php echo esc_attr( $period ); ?>][class_name]" value="<?php echo esc_attr( $slot['class_name'] ?? '' ); ?>" placeholder="es. 3A" data-lesson-field></td>
                                            <td><input type="text" name="schedule[<?php echo esc_attr( $day ); ?>][<?php echo esc_attr( $period ); ?>][subject]" value="<?php echo esc_attr( $slot['subject'] ?? '' ); ?>" placeholder="Materia" data-lesson-field></td>
                                            <td><input type="text" name="schedule[<?php echo esc_attr( $day ); ?>][<?php echo esc_attr( $period ); ?>][room]" value="<?php echo esc_attr( $slot['room'] ?? '' ); ?>" placeholder="Aula" data-lesson-field></td>
                                        </tr>
                                    <?php endfor; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endfor; ?>

                    <div class="amilu67-sds-editor-footer">
                        <div class="amilu67-sds-editor-legend"><span><i class="is-lesson"></i> Lezione</span><span><i class="is-availability"></i> Disponibilità</span><span><i class="is-empty"></i> Ora non impostata</span></div>
                        <button class="button button-primary button-hero"><span class="dashicons dashicons-saved"></span> Salva orario settimanale</button>
                    </div>
                </form>
            <?php else : ?>
                <div class="amilu67-sds-editor-empty"><span class="dashicons dashicons-calendar-alt"></span><div><strong>Seleziona un docente per iniziare</strong><p>L’editor caricherà automaticamente l’orario già presente e potrai modificarlo direttamente nella griglia.</p></div></div>
            <?php endif; ?>
        </section>

        <div class="amilu67-sds-grid-2 amilu67-sds-import-grid">
            <section class="amilu67-sds-panel amilu67-sds-upload-card">
                <span class="dashicons dashicons-admin-users"></span><h2>Importa per singolo docente</h2><p>CSV con colonne: giorno, ora, inizio, fine, classe, materia, aula, tipo.</p>
                <form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <input type="hidden" name="action" value="amilu67_sds_import_teacher"><?php wp_nonce_field( 'amilu67_sds_import_teacher' ); ?>
                    <label><span>Docente</span><select name="teacher_id" required><option value="">Seleziona…</option><?php foreach ( $teachers as $t ) : ?><option value="<?php echo esc_attr( $t['id'] ); ?>"><?php echo esc_html( $t['last_name'] . ' ' . $t['first_name'] ); ?></option><?php endforeach; ?></select></label>
                    <label><span>File CSV</span><input type="file" name="csv" accept=".csv,text/csv" required></label>
                    <label class="amilu67-sds-check"><input type="checkbox" name="replace" value="1"> <span>Sostituisci tutto l’orario del docente</span></label>
                    <div class="amilu67-sds-form-actions"><button class="button button-primary">Importa orario</button><a href="<?php echo esc_url( AMILU67_SDS_URL . 'samples/orario-docente.csv' ); ?>" download>Scarica esempio</a></div>
                </form>
            </section>
            <section class="amilu67-sds-panel amilu67-sds-upload-card">
                <span class="dashicons dashicons-database-import"></span><h2>Importazione bulk</h2><p>Un unico CSV per tutti i docenti; se il codice non esiste il docente viene creato.</p>
                <form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <input type="hidden" name="action" value="amilu67_sds_import_bulk"><?php wp_nonce_field( 'amilu67_sds_import_bulk' ); ?>
                    <label><span>File CSV</span><input type="file" name="csv" accept=".csv,text/csv" required></label>
                    <label class="amilu67-sds-check"><input type="checkbox" name="replace" value="1"> <span>Svuota prima l’intero orario</span></label>
                    <div class="amilu67-sds-form-actions"><button class="button button-primary">Importa bulk</button><a href="<?php echo esc_url( AMILU67_SDS_URL . 'samples/orario-bulk.csv' ); ?>" download>Scarica esempio</a></div>
                </form>
            </section>
        </div>
        <section class="amilu67-sds-panel">
            <div class="amilu67-sds-panel-title"><div><h2>Orario caricato</h2><p>“Disponibilità” indica un’ora utilizzabile prioritariamente per le sostituzioni.</p></div>
                <form method="get"><input type="hidden" name="page" value="amilu67-sds-schedule"><select name="teacher_id" onchange="this.form.submit()"><option value="0">Tutti i docenti</option><?php foreach ( $teachers as $t ) : ?><option value="<?php echo esc_attr( $t['id'] ); ?>" <?php selected( $filter_teacher, (int) $t['id'] ); ?>><?php echo esc_html( $t['last_name'] . ' ' . $t['first_name'] ); ?></option><?php endforeach; ?></select></form>
            </div>
            <div class="amilu67-sds-table-wrap"><table class="widefat striped amilu67-sds-table"><thead><tr><th>Docente</th><th>Giorno</th><th>Ora</th><th>Fascia</th><th>Classe</th><th>Materia</th><th>Aula</th><th>Tipo</th></tr></thead><tbody>
            <?php foreach ( $schedule as $s ) : ?><tr><td><strong><?php echo esc_html( $s['last_name'] . ' ' . $s['first_name'] ); ?></strong></td><td><?php echo esc_html( $this->weekday_label( (int) $s['weekday'] ) ); ?></td><td><?php echo esc_html( $s['period'] . 'ª' ); ?></td><td><?php echo esc_html( $this->time_range( $s['start_time'], $s['end_time'] ) ); ?></td><td><?php echo esc_html( $s['class_name'] ?: '—' ); ?></td><td><?php echo esc_html( $s['subject'] ?: '—' ); ?></td><td><?php echo esc_html( $s['room'] ?: '—' ); ?></td><td><span class="amilu67-sds-status <?php echo 'availability' === $s['activity'] ? 'is-availability' : 'is-assigned'; ?>"><?php echo 'availability' === $s['activity'] ? 'Disponibilità' : 'Lezione'; ?></span></td></tr><?php endforeach; ?>
            <?php if ( ! $schedule ) : ?><tr><td colspan="8">Nessun orario caricato.</td></tr><?php endif; ?></tbody></table></div>
        </section>
        <?php $this->page_end();
    }

    public function absences_page(): void {
        $this->page_header( 'Assenze', 'Le assenze dei docenti generano automaticamente le ore da coprire; le classi assenti liberano i rispettivi docenti.' );
        $teachers = $this->db->list_teachers( true );
        $raw_date = isset( $_GET['date'] ) && is_string( $_GET['date'] ) ? sanitize_text_field( wp_unslash( $_GET['date'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $date = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw_date ) ? $raw_date : wp_date( 'Y-m-d' );
        $teacher_absences = $this->db->get_teacher_absences( $date );
        $recent_teacher_absences = array_slice( $this->db->get_teacher_absences(), 0, 100 );
        $class_absences = $this->db->get_absent_classes( $date );
        ?>
        <div class="amilu67-sds-grid-2">
            <section class="amilu67-sds-panel">
                <div class="amilu67-sds-panel-title"><div><h2>Docente assente</h2><p>Il sistema legge il suo orario e crea le sostituzioni necessarie.</p></div></div>
                <form class="amilu67-sds-form-grid" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <input type="hidden" name="action" value="amilu67_sds_add_teacher_absence"><?php wp_nonce_field( 'amilu67_sds_add_teacher_absence' ); ?>
                    <label class="amilu67-sds-span-2"><span>Docente</span><select name="teacher_id" required><option value="">Seleziona…</option><?php foreach ( $teachers as $t ) : ?><option value="<?php echo esc_attr( $t['id'] ); ?>"><?php echo esc_html( $t['last_name'] . ' ' . $t['first_name'] ); ?></option><?php endforeach; ?></select></label>
                    <label><span>Data</span><input type="date" name="date" value="<?php echo esc_attr( $date ); ?>" required></label>
                    <label><span>Dalla / alla ora</span><span class="amilu67-sds-inline"><input type="number" name="from_period" min="1" max="12" value="1" required><input type="number" name="to_period" min="1" max="12" value="12" required></span></label>
                    <label class="amilu67-sds-span-2"><span>Nota</span><input name="note" placeholder="es. permesso, uscita anticipata…"></label>
                    <div><button class="button button-primary">Registra assenza</button></div>
                </form>
            </section>
            <section class="amilu67-sds-panel">
                <div class="amilu67-sds-panel-title"><div><h2>Classe assente</h2><p>I docenti in servizio su quella classe diventano candidati disponibili.</p></div></div>
                <form class="amilu67-sds-form-grid" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <input type="hidden" name="action" value="amilu67_sds_add_absent_class"><?php wp_nonce_field( 'amilu67_sds_add_absent_class' ); ?>
                    <label><span>Classe</span><input name="class_name" placeholder="es. 4A" required></label>
                    <label><span>Data</span><input type="date" name="date" value="<?php echo esc_attr( $date ); ?>" required></label>
                    <label><span>Dalla ora</span><input type="number" name="from_period" min="1" max="12" value="1" required></label>
                    <label><span>Alla ora</span><input type="number" name="to_period" min="1" max="12" value="12" required></label>
                    <label class="amilu67-sds-span-2"><span>Nota</span><input name="note" placeholder="es. uscita didattica"></label>
                    <div><button class="button button-primary">Registra classe assente</button></div>
                </form>
            </section>
        </div>
        <section class="amilu67-sds-panel">
            <div class="amilu67-sds-panel-title"><div><h2>Assenze del giorno</h2><p><?php echo esc_html( wp_date( 'l j F Y', strtotime( $date . ' 12:00:00' ) ) ); ?> · <?php echo esc_html( count( $teacher_absences ) . ' docenti assenti' ); ?></p></div><form method="get"><input type="hidden" name="page" value="amilu67-sds-absences"><input type="date" name="date" value="<?php echo esc_attr( $date ); ?>" onchange="this.form.submit()"></form></div>
            <div class="amilu67-sds-split-lists"><div><h3>Docenti</h3><?php if ( $teacher_absences ) : foreach ( $teacher_absences as $a ) : ?><div class="amilu67-sds-list-row"><div><strong><?php echo esc_html( $a['last_name'] . ' ' . $a['first_name'] ); ?></strong><small><?php echo esc_html( $this->period_range( $a['from_period'], $a['to_period'] ) . ( $a['note'] ? ' · ' . $a['note'] : '' ) ); ?></small></div><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="amilu67_sds_delete_teacher_absence"><input type="hidden" name="id" value="<?php echo esc_attr( $a['id'] ); ?>"><input type="hidden" name="date" value="<?php echo esc_attr( $date ); ?>"><?php wp_nonce_field( 'amilu67_sds_delete_teacher_absence' ); ?><button class="button-link-delete">Rimuovi</button></form></div><?php endforeach; else : ?><p>Nessuna assenza docente per la data selezionata.</p><?php endif; ?></div>
            <div><h3>Classi</h3><?php if ( $class_absences ) : foreach ( $class_absences as $a ) : ?><div class="amilu67-sds-list-row"><div><strong><?php echo esc_html( $a['class_name'] ); ?></strong><small><?php echo esc_html( $this->period_range( $a['from_period'], $a['to_period'] ) . ( $a['note'] ? ' · ' . $a['note'] : '' ) ); ?></small></div><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="amilu67_sds_delete_absent_class"><input type="hidden" name="id" value="<?php echo esc_attr( $a['id'] ); ?>"><input type="hidden" name="date" value="<?php echo esc_attr( $date ); ?>"><?php wp_nonce_field( 'amilu67_sds_delete_absent_class' ); ?><button class="button-link-delete">Rimuovi</button></form></div><?php endforeach; else : ?><p>Nessuna classe assente per la data selezionata.</p><?php endif; ?></div></div>
        </section>
        <section class="amilu67-sds-panel">
            <div class="amilu67-sds-panel-title"><div><h2>Elenco assenze docenti registrate</h2><p>Ultimi 100 inserimenti, indipendentemente dalla data selezionata. Utile per verificare subito che una registrazione sia stata salvata.</p></div></div>
            <div class="amilu67-sds-table-wrap"><table class="widefat striped amilu67-sds-table"><thead><tr><th>Data</th><th>Docente</th><th>Fascia</th><th>Nota</th><th></th></tr></thead><tbody>
                <?php foreach ( $recent_teacher_absences as $a ) : ?><tr><td><strong><?php echo esc_html( wp_date( 'd/m/Y', strtotime( $a['absence_date'] . ' 12:00:00' ) ) ); ?></strong></td><td><?php echo esc_html( $a['last_name'] . ' ' . $a['first_name'] ); ?><br><small><?php echo esc_html( $a['code'] ); ?></small></td><td><?php echo esc_html( $this->period_range( $a['from_period'], $a['to_period'] ) ); ?></td><td><?php echo esc_html( $a['note'] ?: '—' ); ?></td><td class="amilu67-sds-actions"><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="amilu67_sds_delete_teacher_absence"><input type="hidden" name="id" value="<?php echo esc_attr( $a['id'] ); ?>"><input type="hidden" name="date" value="<?php echo esc_attr( $date ); ?>"><?php wp_nonce_field( 'amilu67_sds_delete_teacher_absence' ); ?><button class="button-link-delete">Rimuovi</button></form></td></tr><?php endforeach; ?>
                <?php if ( ! $recent_teacher_absences ) : ?><tr><td colspan="5">Nessuna assenza docente registrata.</td></tr><?php endif; ?>
            </tbody></table></div>
        </section>
        <?php $this->page_end();
    }

    public function substitutions_page(): void {
        $this->page_header( 'Sostituzioni', 'I candidati sono ordinati dando priorità alle ore di disponibilità e ai docenti liberati da classi assenti.' );
        $raw_date = isset( $_GET['date'] ) && is_string( $_GET['date'] ) ? sanitize_text_field( wp_unslash( $_GET['date'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $date = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw_date ) ? $raw_date : wp_date( 'Y-m-d' );
        // Ricalcola sempre il prospetto dalla fonte autorevole (assenze + orario).
        // In questo modo anche un orario importato/modificato dopo la registrazione
        // di un'assenza viene recepito senza dover cancellare e reinserire l'assenza.
        $this->db->sync_substitutions_for_date( $date );
        $rows = $this->db->list_substitutions( $date, true );
        $day_absences = $this->db->get_teacher_absences( $date );
        ?>
        <section class="amilu67-sds-panel">
            <div class="amilu67-sds-panel-title"><div><h2>Prospetto del <?php echo esc_html( wp_date( 'j F Y', strtotime( $date . ' 12:00:00' ) ) ); ?></h2><p><?php echo esc_html( count( $day_absences ) . ' assenze registrate · ' . count( $rows ) . ' ore risultanti dal prospetto' ); ?>. Le righe “non necessarie” corrispondono a classi assenti.</p></div><form method="get"><input type="hidden" name="page" value="amilu67-sds-substitutions"><input type="date" name="date" value="<?php echo esc_attr( $date ); ?>" onchange="this.form.submit()"></form></div>
            <div class="amilu67-sds-substitution-list">
                <?php foreach ( $rows as $row ) :
                    $candidates = 'not_required' === $row['status'] ? array() : $this->recommender->candidates_for_substitution( $row );
                    $manual = 'not_required' === $row['status'] ? array() : $this->manual_candidates( $row, $candidates );
                    ?>
                    <article class="amilu67-sds-sub-card is-<?php echo esc_attr( $row['status'] ); ?>">
                        <div class="amilu67-sds-sub-time"><strong><?php echo esc_html( $row['period'] . 'ª' ); ?></strong><span><?php echo esc_html( $this->time_range( $row['start_time'], $row['end_time'] ) ); ?></span></div>
                        <div class="amilu67-sds-sub-main"><div class="amilu67-sds-sub-class"><?php echo esc_html( $row['class_name'] ); ?></div><h3><?php echo esc_html( trim( $row['absent_last_name'] . ' ' . $row['absent_first_name'] ) ); ?></h3><p><?php echo esc_html( implode( ' · ', array_filter( array( $row['subject'], $row['room'] ? 'Aula ' . $row['room'] : '' ) ) ) ); ?></p></div>
                        <div class="amilu67-sds-sub-assignment">
                            <?php if ( 'not_required' === $row['status'] ) : ?>
                                <span class="amilu67-sds-status is-not_required">Non necessaria: classe assente</span>
                            <?php elseif ( 'cancelled' === $row['status'] ) : ?>
                                <span class="amilu67-sds-status is-cancelled">Annullata</span>
                            <?php else : ?>
                                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                    <input type="hidden" name="action" value="amilu67_sds_assign_substitute"><input type="hidden" name="id" value="<?php echo esc_attr( $row['id'] ); ?>"><input type="hidden" name="date" value="<?php echo esc_attr( $date ); ?>"><?php wp_nonce_field( 'amilu67_sds_assign_substitute' ); ?>
                                    <label><span>Sostituto</span><select name="teacher_id"><option value="0">— Da assegnare —</option>
                                        <?php if ( $candidates ) : ?><optgroup label="Consigliati"><?php foreach ( $candidates as $c ) : ?><option value="<?php echo esc_attr( $c['id'] ); ?>" <?php selected( (int) $row['substitute_teacher_id'], (int) $c['id'] ); ?>>★ <?php echo esc_html( $c['last_name'] . ' ' . $c['first_name'] . ' — ' . $c['reason'] ); ?></option><?php endforeach; ?></optgroup><?php endif; ?>
                                        <?php if ( $manual ) : ?><optgroup label="Altri docenti non impegnati nell’orario caricato"><?php foreach ( $manual as $c ) : ?><option value="<?php echo esc_attr( $c['id'] ); ?>" <?php selected( (int) $row['substitute_teacher_id'], (int) $c['id'] ); ?>><?php echo esc_html( $c['last_name'] . ' ' . $c['first_name'] ); ?></option><?php endforeach; ?></optgroup><?php endif; ?>
                                    </select></label>
                                    <label><span>Nota schermo</span><input name="note" value="<?php echo esc_attr( $row['note'] ); ?>" placeholder="opzionale"></label>
                                    <button class="button button-primary">Salva assegnazione</button>
                                </form>
                                <?php if ( $candidates ) : ?><div class="amilu67-sds-candidate-hint"><strong>Prima scelta:</strong> <?php echo esc_html( $candidates[0]['last_name'] . ' ' . $candidates[0]['first_name'] ); ?> <span><?php echo esc_html( $candidates[0]['reason'] ); ?></span></div><?php else : ?><div class="amilu67-sds-candidate-hint is-warning">Nessuna disponibilità prioritaria rilevata.</div><?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
                <?php if ( ! $rows ) : ?>
                    <div class="amilu67-sds-empty-admin amilu67-sds-empty-large">
                        <span class="dashicons dashicons-info-outline"></span>
                        <strong>Nessuna ora di lezione da coprire per questa data.</strong>
                        <?php if ( $day_absences ) : ?>
                            <span>Le assenze sono registrate, ma nell’intervallo indicato i docenti non hanno lezioni nell’orario caricato (potrebbero avere disponibilità o nessuna attività).</span>
                            <small><?php foreach ( $day_absences as $i => $a ) { echo ( $i ? ' · ' : '' ) . esc_html( $a['last_name'] . ' ' . $a['first_name'] . ': ' . $this->period_range( $a['from_period'], $a['to_period'] ) ); } ?></small>
                        <?php else : ?>
                            <span>Non risultano assenze docenti registrate per la data selezionata.</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
        <?php $this->page_end();
    }

    public function settings_page(): void {
        $this->page_header( 'Impostazioni schermo', 'Personalizza il monitor mantenendo leggibilità, contrasto e coerenza con il design system della scuola.' );
        $s = $this->db->settings();
        ?>
        <div class="amilu67-sds-grid-sidebar">
            <section class="amilu67-sds-panel">
                <form class="amilu67-sds-form-grid" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <input type="hidden" name="action" value="amilu67_sds_save_settings"><?php wp_nonce_field( 'amilu67_sds_save_settings' ); ?>
                    <label class="amilu67-sds-span-2"><span>Nome istituto</span><input name="school_name" value="<?php echo esc_attr( $s['school_name'] ); ?>"></label>
                    <label class="amilu67-sds-span-2"><span>Titolo schermo</span><input name="screen_title" value="<?php echo esc_attr( $s['screen_title'] ); ?>"></label>
                    <label><span>Aggiornamento automatico</span><select name="refresh_seconds"><?php foreach ( array( 10,15,30,60,120 ) as $n ) : ?><option value="<?php echo esc_attr( $n ); ?>" <?php selected( (int) $s['refresh_seconds'], $n ); ?>>ogni <?php echo esc_html( $n ); ?> secondi</option><?php endforeach; ?></select></label>
                    <label><span>Visualizzazione nomi</span><select name="name_format"><option value="surname_initial" <?php selected( $s['name_format'], 'surname_initial' ); ?>>Cognome + iniziale</option><option value="full" <?php selected( $s['name_format'], 'full' ); ?>>Nome e cognome</option><option value="surname_only" <?php selected( $s['name_format'], 'surname_only' ); ?>>Solo cognome</option></select></label>
                    <label><span>Colore principale</span><input type="color" name="accent" value="<?php echo esc_attr( sanitize_hex_color( $s['accent'] ) ?: '#0066cc' ); ?>"></label>
                    <label class="amilu67-sds-check"><input type="checkbox" name="show_pending" value="1" <?php checked( ! empty( $s['show_pending'] ) ); ?>> <span>Mostra sul monitor anche le sostituzioni non ancora assegnate</span></label>
                    <label class="amilu67-sds-check amilu67-sds-span-2"><input type="checkbox" name="consider_free" value="1" <?php checked( ! empty( $s['consider_free'] ) ); ?>> <span>Tra i consigli considera anche docenti senza lezione in quella fascia (oltre a disponibilità esplicite e docenti liberati)</span></label>
                    <label class="amilu67-sds-span-2"><span>Messaggio a piè di schermo</span><input name="screen_note" value="<?php echo esc_attr( $s['screen_note'] ); ?>" placeholder="es. Rivolgersi alla vicepresidenza per variazioni"></label>
                    <div><button class="button button-primary">Salva impostazioni</button></div>
                </form>
            </section>
            <section class="amilu67-sds-panel amilu67-sds-url-card"><span class="dashicons dashicons-desktop"></span><h2>URL Digital Signage</h2><p>Apri questo indirizzo sul browser del monitor: la vista occupa automaticamente tutta la finestra disponibile. Per il fullscreen reale del browser usa il pulsante “Schermo intero” presente sul monitor.</p><div class="amilu67-sds-copy-row"><input id="amilu67-sds-screen-url" readonly value="<?php echo esc_attr( $this->screen_url() ); ?>"><button type="button" class="button" data-copy="#amilu67-sds-screen-url">Copia</button></div><p class="description">È disponibile anche lo shortcode <code>[amilu67_sds_sostituzioni]</code> per incorporare il prospetto in una pagina del sito.</p></section>
        </div>
        <?php $this->page_end();
    }

    public function save_teacher(): void {
        $this->guard();
        check_admin_referer( 'amilu67_sds_save_teacher' );
        $id = $this->db->upsert_teacher(
            array(
                'code'       => sanitize_text_field( wp_unslash( $_POST['code'] ?? '' ) ),
                'first_name' => sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) ),
                'last_name'  => sanitize_text_field( wp_unslash( $_POST['last_name'] ?? '' ) ),
                'email'      => sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ),
                'active'     => ! empty( $_POST['active'] ),
            )
        );
        $this->redirect( 'amilu67-sds-teachers', $id ? 'Docente salvato.' : 'Impossibile salvare il docente.', $id ? 'success' : 'error' );
    }

    public function delete_teacher(): void {
        $this->guard();
        check_admin_referer( 'amilu67_sds_delete_teacher' );
        $this->db->delete_teacher( absint( $_POST['id'] ?? 0 ) );
        $this->redirect( 'amilu67-sds-teachers', 'Docente eliminato.' );
    }

    public function save_schedule_table(): void {
        $this->guard();
        check_admin_referer( 'amilu67_sds_save_schedule_table' );
        $teacher_id = absint( $_POST['teacher_id'] ?? 0 );
        $teacher = $this->db->get_teacher( $teacher_id );
        if ( ! $teacher ) {
            $this->redirect( 'amilu67-sds-schedule', 'Docente non trovato.', 'error' );
        }

        $raw_schedule = isset( $_POST['schedule'] ) && is_array( $_POST['schedule'] ) ? map_deep( wp_unslash( $_POST['schedule'] ), 'sanitize_text_field' ) : array();
        $prepared = array();
        $errors = array();

        for ( $day = 1; $day <= 6; $day++ ) {
            for ( $period = 1; $period <= 12; $period++ ) {
                $slot = isset( $raw_schedule[ $day ][ $period ] ) && is_array( $raw_schedule[ $day ][ $period ] ) ? $raw_schedule[ $day ][ $period ] : array();
                $activity = sanitize_key( $slot['activity'] ?? '' );
                if ( ! in_array( $activity, array( '', 'lesson', 'availability' ), true ) ) {
                    $activity = '';
                }
                $class_name = sanitize_text_field( $slot['class_name'] ?? '' );
                $subject = sanitize_text_field( $slot['subject'] ?? '' );
                $room = sanitize_text_field( $slot['room'] ?? '' );
                $start_time = $this->normalize_table_time( $slot['start_time'] ?? '' );
                $end_time = $this->normalize_table_time( $slot['end_time'] ?? '' );

                if ( 'lesson' === $activity && '' === $class_name ) {
                    $errors[] = sprintf( '%s, %dª ora: inserisci la classe oppure lascia la riga vuota.', $this->weekday_label( $day ), $period );
                }
                if ( $start_time && $end_time && $start_time >= $end_time ) {
                    $errors[] = sprintf( '%s, %dª ora: l’orario di fine deve essere successivo all’inizio.', $this->weekday_label( $day ), $period );
                }

                $prepared[ $day ][ $period ] = array(
                    'activity'   => $activity,
                    'start_time' => $start_time,
                    'end_time'   => $end_time,
                    'class_name' => 'availability' === $activity ? '' : $class_name,
                    'subject'    => 'availability' === $activity ? '' : $subject,
                    'room'       => 'availability' === $activity ? '' : $room,
                );
            }
        }

        if ( $errors ) {
            $this->redirect( 'amilu67-sds-schedule', implode( ' ', array_slice( $errors, 0, 3 ) ), 'error', array( 'teacher_id' => $teacher_id ) );
        }

        $saved = 0;
        $cleared = 0;
        foreach ( $prepared as $day => $periods ) {
            foreach ( $periods as $period => $slot ) {
                if ( '' === $slot['activity'] ) {
                    $cleared += $this->db->delete_schedule_slot( $teacher_id, (int) $day, (int) $period ) ? 1 : 0;
                    continue;
                }
                $ok = $this->db->replace_schedule_slot(
                    array(
                        'teacher_id' => $teacher_id,
                        'weekday'    => (int) $day,
                        'period'     => (int) $period,
                        'start_time' => $slot['start_time'],
                        'end_time'   => $slot['end_time'],
                        'class_name' => $slot['class_name'],
                        'subject'    => $slot['subject'],
                        'room'       => $slot['room'],
                        'activity'   => $slot['activity'],
                    )
                );
                if ( $ok ) {
                    ++$saved;
                }
            }
        }

        $this->db->sync_all_substitutions();
        $message = sprintf( 'Orario di %s aggiornato: %d righe salvate', trim( $teacher['last_name'] . ' ' . $teacher['first_name'] ), $saved );
        if ( $cleared ) {
            $message .= sprintf( ', %d righe rimosse', $cleared );
        }
        $message .= '. Il prospetto delle sostituzioni è stato ricalcolato.';
        $this->redirect( 'amilu67-sds-schedule', $message, 'success', array( 'teacher_id' => $teacher_id ) );
    }

    public function import_teacher(): void {
        $this->guard();
        check_admin_referer( 'amilu67_sds_import_teacher' );
        $teacher_id = absint( $_POST['teacher_id'] ?? 0 );
        $tmp_name = isset( $_FILES['csv']['tmp_name'] ) ? sanitize_text_field( wp_unslash( $_FILES['csv']['tmp_name'] ) ) : '';
        if ( ! $teacher_id || '' === $tmp_name || ! is_uploaded_file( $tmp_name ) ) {
            $this->redirect( 'amilu67-sds-schedule', 'Seleziona docente e un file CSV valido.', 'error' );
        }
        $result = $this->importer->import_teacher_csv( $tmp_name, $teacher_id, ! empty( $_POST['replace'] ) );
        if ( $result['imported'] ) {
            $this->db->sync_all_substitutions();
        }
        $msg = sprintf( 'Importate %d righe. Il prospetto delle sostituzioni è stato ricalcolato.', (int) $result['imported'] );
        if ( $result['errors'] ) {
            $msg .= ' Errori: ' . implode( ' | ', array_slice( $result['errors'], 0, 3 ) );
        }
        $this->redirect( 'amilu67-sds-schedule', $msg, $result['imported'] ? 'success' : 'error', array( 'teacher_id' => $teacher_id ) );
    }

    public function import_bulk(): void {
        $this->guard();
        check_admin_referer( 'amilu67_sds_import_bulk' );
        $tmp_name = isset( $_FILES['csv']['tmp_name'] ) ? sanitize_text_field( wp_unslash( $_FILES['csv']['tmp_name'] ) ) : '';
        if ( '' === $tmp_name || ! is_uploaded_file( $tmp_name ) ) {
            $this->redirect( 'amilu67-sds-schedule', 'Seleziona un file CSV valido.', 'error' );
        }
        $result = $this->importer->import_bulk_csv( $tmp_name, ! empty( $_POST['replace'] ) );
        if ( $result['imported'] ) {
            $this->db->sync_all_substitutions();
        }
        $msg = sprintf( 'Importate %d righe. Il prospetto delle sostituzioni è stato ricalcolato.', (int) $result['imported'] );
        if ( $result['errors'] ) {
            $msg .= ' Errori: ' . implode( ' | ', array_slice( $result['errors'], 0, 3 ) );
        }
        $this->redirect( 'amilu67-sds-schedule', $msg, $result['imported'] ? 'success' : 'error' );
    }

    public function add_teacher_absence(): void {
        $this->guard();
        check_admin_referer( 'amilu67_sds_add_teacher_absence' );
        $from = max( 1, absint( $_POST['from_period'] ?? 1 ) );
        $to = max( $from, absint( $_POST['to_period'] ?? 12 ) );
        $date = sanitize_text_field( wp_unslash( $_POST['date'] ?? '' ) );
        $id = $this->db->add_teacher_absence( absint( $_POST['teacher_id'] ?? 0 ), $date, $from, $to, sanitize_text_field( wp_unslash( $_POST['note'] ?? '' ) ) );
        if ( $id ) {
            $generated = $this->db->count_substitutions_for_absence( $id );
            $message = $generated
                ? sprintf( 'Assenza registrata: generate %d ore nel prospetto sostituzioni.', $generated )
                : 'Assenza registrata, ma nell’intervallo selezionato non risultano ore di lezione da coprire nell’orario caricato.';
            $this->redirect( 'amilu67-sds-absences', $message, 'success', array( 'date' => $date ) );
        }
        $this->redirect( 'amilu67-sds-absences', 'Errore nella registrazione.', 'error', array( 'date' => $date ) );
    }

    public function delete_teacher_absence(): void {
        $this->guard();
        check_admin_referer( 'amilu67_sds_delete_teacher_absence' );
        $date = sanitize_text_field( wp_unslash( $_POST['date'] ?? '' ) );
        $this->db->delete_teacher_absence( absint( $_POST['id'] ?? 0 ) );
        $extra = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ? array( 'date' => $date ) : array();
        $this->redirect( 'amilu67-sds-absences', 'Assenza rimossa.', 'success', $extra );
    }

    public function add_absent_class(): void {
        $this->guard();
        check_admin_referer( 'amilu67_sds_add_absent_class' );
        $from = max( 1, absint( $_POST['from_period'] ?? 1 ) );
        $to = max( $from, absint( $_POST['to_period'] ?? 12 ) );
        $date = sanitize_text_field( wp_unslash( $_POST['date'] ?? '' ) );
        $id = $this->db->add_absent_class( $date, sanitize_text_field( wp_unslash( $_POST['class_name'] ?? '' ) ), $from, $to, sanitize_text_field( wp_unslash( $_POST['note'] ?? '' ) ) );
        $this->redirect( 'amilu67-sds-absences', $id ? 'Classe assente registrata; i docenti interessati risultano liberati.' : 'Errore nella registrazione.', $id ? 'success' : 'error', array( 'date' => $date ) );
    }

    public function delete_absent_class(): void {
        $this->guard();
        check_admin_referer( 'amilu67_sds_delete_absent_class' );
        $date = sanitize_text_field( wp_unslash( $_POST['date'] ?? '' ) );
        $this->db->delete_absent_class( absint( $_POST['id'] ?? 0 ) );
        $extra = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ? array( 'date' => $date ) : array();
        $this->redirect( 'amilu67-sds-absences', 'Classe assente rimossa; il prospetto è stato ricalcolato.', 'success', $extra );
    }

    public function assign_substitute(): void {
        $this->guard();
        check_admin_referer( 'amilu67_sds_assign_substitute' );
        $id = absint( $_POST['id'] ?? 0 );
        $teacher_id = absint( $_POST['teacher_id'] ?? 0 );
        $date = sanitize_text_field( wp_unslash( $_POST['date'] ?? '' ) );
        $sub = $this->db->get_substitution( $id );
        if ( ! $sub ) {
            $this->redirect( 'amilu67-sds-substitutions', 'Sostituzione non trovata.', 'error', array( 'date' => $date ) );
        }
        if ( $teacher_id ) {
            if ( $this->db->teacher_is_absent( $teacher_id, $sub['substitution_date'], (int) $sub['period'] ) || $this->db->teacher_is_already_substitute( $teacher_id, $sub['substitution_date'], (int) $sub['period'], $id ) ) {
                $this->redirect( 'amilu67-sds-substitutions', 'Il docente selezionato non è disponibile in questa fascia.', 'error', array( 'date' => $date ) );
            }
            $slot = $this->db->get_teacher_slot( $teacher_id, $sub['substitution_date'], (int) $sub['period'] );
            if ( $slot && 'lesson' === $slot['activity'] && ! $this->db->class_is_absent( $sub['substitution_date'], $slot['class_name'], (int) $sub['period'] ) ) {
                $this->redirect( 'amilu67-sds-substitutions', 'Il docente selezionato è già impegnato in lezione.', 'error', array( 'date' => $date ) );
            }
        }
        $ok = $this->db->assign_substitute( $id, $teacher_id ?: null, sanitize_text_field( wp_unslash( $_POST['note'] ?? '' ) ) );
        $this->redirect( 'amilu67-sds-substitutions', $ok ? 'Assegnazione aggiornata.' : 'Errore nell’assegnazione.', $ok ? 'success' : 'error', array( 'date' => $date ) );
    }

    public function save_settings(): void {
        $this->guard();
        check_admin_referer( 'amilu67_sds_save_settings' );
        $this->db->save_settings(
            array(
                'school_name'     => sanitize_text_field( wp_unslash( $_POST['school_name'] ?? '' ) ),
                'screen_title'    => sanitize_text_field( wp_unslash( $_POST['screen_title'] ?? '' ) ),
                'refresh_seconds' => max( 10, min( 300, absint( $_POST['refresh_seconds'] ?? 30 ) ) ),
                'name_format'     => in_array( sanitize_key( wp_unslash( $_POST['name_format'] ?? '' ) ), array( 'full', 'surname_initial', 'surname_only' ), true ) ? sanitize_key( wp_unslash( $_POST['name_format'] ?? '' ) ) : 'surname_initial',
                'show_pending'    => ! empty( $_POST['show_pending'] ) ? 1 : 0,
                'consider_free'   => ! empty( $_POST['consider_free'] ) ? 1 : 0,
                'screen_note'     => sanitize_text_field( wp_unslash( $_POST['screen_note'] ?? '' ) ),
                'accent'          => sanitize_hex_color( wp_unslash( $_POST['accent'] ?? '#0066cc' ) ) ?: '#0066cc',
            )
        );
        $this->redirect( 'amilu67-sds-settings', 'Impostazioni salvate.' );
    }

    private function manual_candidates( array $substitution, array $recommended ): array {
        $recommended_ids = array_map( 'intval', wp_list_pluck( $recommended, 'id' ) );
        $out = array();
        foreach ( $this->db->list_teachers( true ) as $t ) {
            $id = (int) $t['id'];
            if ( $id === (int) $substitution['absent_teacher_id'] || in_array( $id, $recommended_ids, true ) ) {
                continue;
            }
            if ( $this->db->teacher_is_absent( $id, $substitution['substitution_date'], (int) $substitution['period'] ) || $this->db->teacher_is_already_substitute( $id, $substitution['substitution_date'], (int) $substitution['period'], (int) $substitution['id'] ) ) {
                continue;
            }
            $slot = $this->db->get_teacher_slot( $id, $substitution['substitution_date'], (int) $substitution['period'] );
            if ( $slot && 'lesson' === $slot['activity'] && ! $this->db->class_is_absent( $substitution['substitution_date'], $slot['class_name'], (int) $substitution['period'] ) ) {
                continue;
            }
            $out[] = $t;
        }
        return $out;
    }


    private function normalize_table_time( $value ): ?string {
        $value = trim( sanitize_text_field( (string) $value ) );
        if ( '' === $value ) {
            return null;
        }
        if ( preg_match( '/^(\d{2}):(\d{2})$/', $value, $m ) ) {
            $hour = (int) $m[1];
            $minute = (int) $m[2];
            if ( $hour >= 0 && $hour <= 23 && $minute >= 0 && $minute <= 59 ) {
                return sprintf( '%02d:%02d:00', $hour, $minute );
            }
        }
        return null;
    }

    private function schedule_day_summary( array $slots ): string {
        $lessons = 0;
        $availability = 0;
        foreach ( $slots as $slot ) {
            if ( 'lesson' === ( $slot['activity'] ?? '' ) ) {
                ++$lessons;
            } elseif ( 'availability' === ( $slot['activity'] ?? '' ) ) {
                ++$availability;
            }
        }
        if ( ! $lessons && ! $availability ) {
            return 'Nessuna ora';
        }
        $parts = array();
        if ( $lessons ) {
            $parts[] = $lessons . ' lez.';
        }
        if ( $availability ) {
            $parts[] = $availability . ' disp.';
        }
        return implode( ' · ', $parts );
    }

    private function screen_url(): string {
        if ( get_option( 'permalink_structure' ) ) {
            return home_url( '/amilu67-sostituzioni-schermo/' );
        }
        return add_query_arg( 'amilu67_sds_signage', '1', home_url( '/' ) );
    }

    private function weekday_label( int $n ): string {
        $days = array( 1 => 'Lunedì', 2 => 'Martedì', 3 => 'Mercoledì', 4 => 'Giovedì', 5 => 'Venerdì', 6 => 'Sabato', 7 => 'Domenica' );
        return $days[ $n ] ?? '';
    }

    private function period_range( $from, $to ): string {
        return (int) $from === (int) $to ? $from . 'ª ora' : $from . 'ª–' . $to . 'ª ora';
    }

    private function time_range( $start, $end ): string {
        if ( ! $start && ! $end ) {
            return '—';
        }
        return trim( ( $start ? substr( $start, 0, 5 ) : '' ) . ( $end ? '–' . substr( $end, 0, 5 ) : '' ) );
    }

    private function status_label( string $status ): string {
        $labels = array( 'assigned' => 'Assegnata', 'pending' => 'Da assegnare', 'not_required' => 'Non necessaria', 'cancelled' => 'Annullata' );
        return $labels[ $status ] ?? $status;
    }
}
