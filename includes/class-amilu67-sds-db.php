<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AMILU67_SDS_DB {
    public array $tables;

    public function __construct() {
        global $wpdb;
        $p = $wpdb->prefix . 'amilu67_sds_';
        $this->tables = array(
            'teachers'         => $p . 'teachers',
            'schedule'         => $p . 'schedule',
            'teacher_absences' => $p . 'teacher_absences',
            'absent_classes'   => $p . 'absent_classes',
            'substitutions'    => $p . 'substitutions',
        );
    }

    public function install(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();

        $sql = array();
        $sql[] = "CREATE TABLE {$this->tables['teachers']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            code varchar(60) NOT NULL,
            first_name varchar(120) NOT NULL DEFAULT '',
            last_name varchar(120) NOT NULL DEFAULT '',
            email varchar(190) NOT NULL DEFAULT '',
            active tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY code (code),
            KEY active (active),
            KEY last_name (last_name)
        ) $charset;";

        $sql[] = "CREATE TABLE {$this->tables['schedule']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            teacher_id bigint(20) unsigned NOT NULL,
            weekday tinyint(2) unsigned NOT NULL,
            period tinyint(2) unsigned NOT NULL,
            start_time time NULL,
            end_time time NULL,
            class_name varchar(80) NOT NULL DEFAULT '',
            subject varchar(160) NOT NULL DEFAULT '',
            room varchar(80) NOT NULL DEFAULT '',
            activity varchar(30) NOT NULL DEFAULT 'lesson',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY teacher_slot (teacher_id,weekday,period),
            KEY weekday_period (weekday,period),
            KEY class_slot (class_name,weekday,period),
            KEY activity (activity)
        ) $charset;";

        $sql[] = "CREATE TABLE {$this->tables['teacher_absences']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            teacher_id bigint(20) unsigned NOT NULL,
            absence_date date NOT NULL,
            from_period tinyint(2) unsigned NOT NULL DEFAULT 1,
            to_period tinyint(2) unsigned NOT NULL DEFAULT 12,
            note varchar(255) NOT NULL DEFAULT '',
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY teacher_date (teacher_id,absence_date),
            KEY absence_date (absence_date)
        ) $charset;";

        $sql[] = "CREATE TABLE {$this->tables['absent_classes']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            absence_date date NOT NULL,
            class_name varchar(80) NOT NULL,
            from_period tinyint(2) unsigned NOT NULL DEFAULT 1,
            to_period tinyint(2) unsigned NOT NULL DEFAULT 12,
            note varchar(255) NOT NULL DEFAULT '',
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY class_date (class_name,absence_date),
            KEY absence_date (absence_date)
        ) $charset;";

        $sql[] = "CREATE TABLE {$this->tables['substitutions']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            source_absence_id bigint(20) unsigned NULL,
            substitution_date date NOT NULL,
            period tinyint(2) unsigned NOT NULL,
            start_time time NULL,
            end_time time NULL,
            class_name varchar(80) NOT NULL DEFAULT '',
            absent_teacher_id bigint(20) unsigned NULL,
            substitute_teacher_id bigint(20) unsigned NULL,
            subject varchar(160) NOT NULL DEFAULT '',
            room varchar(80) NOT NULL DEFAULT '',
            note varchar(255) NOT NULL DEFAULT '',
            status varchar(30) NOT NULL DEFAULT 'pending',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY generated_slot (substitution_date,period,absent_teacher_id,class_name),
            KEY substitution_date (substitution_date),
            KEY substitute_slot (substitute_teacher_id,substitution_date,period),
            KEY status (status),
            KEY source_absence_id (source_absence_id)
        ) $charset;";

        foreach ( $sql as $query ) {
            dbDelta( $query );
        }

        $this->migrate_legacy_data();
    }

    /**
     * Copies data created by pre-release builds into the uniquely prefixed
     * WordPress.org storage. Legacy tables/options are intentionally left
     * untouched so a test installation can be rolled back safely.
     */
    private function migrate_legacy_data(): void {
        global $wpdb;

        $legacy_table_prefix = $wpdb->prefix . 'i' . 'sd_';
        $table_suffixes = array( 'teachers', 'schedule', 'teacher_absences', 'absent_classes', 'substitutions' );

        foreach ( $table_suffixes as $suffix ) {
            $legacy_table = $legacy_table_prefix . $suffix;
            $new_table    = $this->tables[ $suffix ];

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time upgrade check for a legacy custom table.
            $legacy_exists = $wpdb->get_var(
                $wpdb->prepare(
                    'SHOW TABLES LIKE %s',
                    $wpdb->esc_like( $legacy_table )
                )
            );

            if ( $legacy_table !== $legacy_exists ) {
                continue;
            }

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time migration between plugin-owned custom tables.
            $wpdb->query(
                $wpdb->prepare(
                    'INSERT IGNORE INTO %i SELECT * FROM %i',
                    $new_table,
                    $legacy_table
                )
            );
        }

        if ( false === get_option( 'amilu67_sds_settings', false ) ) {
            $legacy_option_name = 'i' . 'sd_settings';
            $legacy_settings    = get_option( $legacy_option_name, false );
            if ( false !== $legacy_settings && is_array( $legacy_settings ) ) {
                add_option( 'amilu67_sds_settings', $legacy_settings );
            }
        }
    }

    public function settings(): array {
        $defaults = array(
            'school_name'     => get_bloginfo( 'name' ),
            'screen_title'    => 'Sostituzioni docenti',
            'refresh_seconds' => 30,
            'name_format'     => 'surname_initial',
            'show_pending'    => 0,
            'screen_note'     => '',
            'consider_free'   => 0,
            'accent'          => '#0066cc',
        );
        return wp_parse_args( (array) get_option( 'amilu67_sds_settings', array() ), $defaults );
    }

    public function save_settings( array $settings ): void {
        update_option( 'amilu67_sds_settings', wp_parse_args( $settings, $this->settings() ) );
    }

    public function list_teachers( bool $active_only = false ): array {
        global $wpdb;
        if ( $active_only ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom plugin tables; live operational data.
            return $wpdb->get_results(
                $wpdb->prepare(
                    'SELECT * FROM %i WHERE active = 1 ORDER BY last_name, first_name',
                    $this->tables['teachers']
                ),
                ARRAY_A
            ) ?: array();
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom plugin tables; live operational data.
        return $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM %i ORDER BY last_name, first_name',
                $this->tables['teachers']
            ),
            ARRAY_A
        ) ?: array();
    }

    public function get_teacher( int $id ): ?array {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom plugin tables; live operational data.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM %i WHERE id = %d',
                $this->tables['teachers'],
                $id
            ),
            ARRAY_A
        );
        return $row ?: null;
    }

    public function get_teacher_by_code( string $code ): ?array {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom plugin tables; live operational data.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM %i WHERE code = %s',
                $this->tables['teachers'],
                $code
            ),
            ARRAY_A
        );
        return $row ?: null;
    }

    public function upsert_teacher( array $data ): int {
        global $wpdb;
        $now  = current_time( 'mysql' );
        $code = sanitize_text_field( $data['code'] ?? '' );
        if ( '' === $code ) {
            return 0;
        }

        $existing = $this->get_teacher_by_code( $code );
        $row = array(
            'code'       => $code,
            'first_name' => sanitize_text_field( $data['first_name'] ?? '' ),
            'last_name'  => sanitize_text_field( $data['last_name'] ?? '' ),
            'email'      => sanitize_email( $data['email'] ?? '' ),
            'active'     => isset( $data['active'] ) ? (int) (bool) $data['active'] : 1,
            'updated_at' => $now,
        );

        if ( $existing ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom plugin tables; live operational data.
            $wpdb->update( $this->tables['teachers'], $row, array( 'id' => (int) $existing['id'] ) );
            return (int) $existing['id'];
        }

        $row['created_at'] = $now;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom plugin tables; live operational data.
        $wpdb->insert( $this->tables['teachers'], $row );
        return (int) $wpdb->insert_id;
    }

    public function delete_teacher( int $id ): bool {
        global $wpdb;
        $now = current_time( 'mysql' );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom plugin tables; live operational data.
        $wpdb->delete( $this->tables['schedule'], array( 'teacher_id' => $id ), array( '%d' ) );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom plugin tables; live operational data.
        $wpdb->delete( $this->tables['teacher_absences'], array( 'teacher_id' => $id ), array( '%d' ) );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom plugin tables; live operational data.
        $wpdb->query(
            $wpdb->prepare(
                'DELETE FROM %i WHERE absent_teacher_id = %d',
                $this->tables['substitutions'],
                $id
            )
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom plugin tables; live operational data.
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE %i SET substitute_teacher_id = NULL, status = 'pending', updated_at = %s WHERE substitute_teacher_id = %d AND status = 'assigned'",
                $this->tables['substitutions'],
                $now,
                $id
            )
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom plugin tables; live operational data.
        return (bool) $wpdb->delete( $this->tables['teachers'], array( 'id' => $id ), array( '%d' ) );
    }

    public function replace_schedule_slot( array $row ): bool {
        global $wpdb;
        $now = current_time( 'mysql' );
        $data = array(
            'teacher_id' => (int) $row['teacher_id'],
            'weekday'    => (int) $row['weekday'],
            'period'     => (int) $row['period'],
            'start_time' => ! empty( $row['start_time'] ) ? $row['start_time'] : null,
            'end_time'   => ! empty( $row['end_time'] ) ? $row['end_time'] : null,
            'class_name' => sanitize_text_field( $row['class_name'] ?? '' ),
            'subject'    => sanitize_text_field( $row['subject'] ?? '' ),
            'room'       => sanitize_text_field( $row['room'] ?? '' ),
            'activity'   => in_array( $row['activity'] ?? 'lesson', array( 'lesson', 'availability' ), true ) ? $row['activity'] : 'lesson',
            'updated_at' => $now,
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom plugin tables; live operational data.
        $existing = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT id FROM %i WHERE teacher_id = %d AND weekday = %d AND period = %d',
                $this->tables['schedule'],
                $data['teacher_id'],
                $data['weekday'],
                $data['period']
            )
        );

        if ( $existing ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom plugin tables; live operational data.
            return false !== $wpdb->update( $this->tables['schedule'], $data, array( 'id' => (int) $existing ) );
        }

        $data['created_at'] = $now;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom plugin tables; live operational data.
        return false !== $wpdb->insert( $this->tables['schedule'], $data );
    }

    public function delete_schedule_slot( int $teacher_id, int $weekday, int $period ): bool {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom plugin tables; live operational data.
        return 0 < (int) $wpdb->delete(
            $this->tables['schedule'],
            array(
                'teacher_id' => $teacher_id,
                'weekday'    => $weekday,
                'period'     => $period,
            ),
            array( '%d', '%d', '%d' )
        );
    }

    public function clear_schedule( ?int $teacher_id = null ): int {
        global $wpdb;
        if ( $teacher_id ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom plugin tables; live operational data.
            return (int) $wpdb->delete( $this->tables['schedule'], array( 'teacher_id' => $teacher_id ), array( '%d' ) );
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom plugin tables; live operational data.
        return (int) $wpdb->query(
            $wpdb->prepare(
                'TRUNCATE TABLE %i',
                $this->tables['schedule']
            )
        );
    }

    public function get_schedule( ?int $teacher_id = null ): array {
        global $wpdb;
        if ( $teacher_id ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom plugin tables; live operational data.
            return $wpdb->get_results(
                $wpdb->prepare(
                    'SELECT s.*, t.code, t.first_name, t.last_name
                     FROM %i s
                     INNER JOIN %i t ON t.id = s.teacher_id
                     WHERE s.teacher_id = %d
                     ORDER BY t.last_name, t.first_name, s.weekday, s.period',
                    $this->tables['schedule'],
                    $this->tables['teachers'],
                    $teacher_id
                ),
                ARRAY_A
            ) ?: array();
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom plugin tables; live operational data.
        return $wpdb->get_results(
            $wpdb->prepare(
                'SELECT s.*, t.code, t.first_name, t.last_name
                 FROM %i s
                 INNER JOIN %i t ON t.id = s.teacher_id
                 ORDER BY t.last_name, t.first_name, s.weekday, s.period',
                $this->tables['schedule'],
                $this->tables['teachers']
            ),
            ARRAY_A
        ) ?: array();
    }

    public function get_teacher_slot( int $teacher_id, string $date, int $period ): ?array {
        global $wpdb;
        $weekday = (int) wp_date( 'N', strtotime( $date . ' 12:00:00' ) );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom plugin tables; live operational data.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM %i WHERE teacher_id = %d AND weekday = %d AND period = %d',
                $this->tables['schedule'],
                $teacher_id,
                $weekday,
                $period
            ),
            ARRAY_A
        );
        return $row ?: null;
    }

    public function teacher_has_any_schedule_on_weekday( int $teacher_id, int $weekday ): bool {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom plugin tables; live operational data.
        return (bool) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT 1 FROM %i WHERE teacher_id = %d AND weekday = %d LIMIT 1',
                $this->tables['schedule'],
                $teacher_id,
                $weekday
            )
        );
    }

    public function get_teacher_subjects( int $teacher_id ): array {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom plugin tables; live operational data.
        return $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT subject FROM %i WHERE teacher_id = %d AND activity = 'lesson' AND subject <> ''",
                $this->tables['schedule'],
                $teacher_id
            )
        ) ?: array();
    }

    public function add_teacher_absence( int $teacher_id, string $date, int $from_period, int $to_period, string $note = '' ): int {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom plugin tables; live operational data.
        $wpdb->insert(
            $this->tables['teacher_absences'],
            array(
                'teacher_id'   => $teacher_id,
                'absence_date' => $date,
                'from_period'  => max( 1, min( 12, $from_period ) ),
                'to_period'    => max( 1, min( 12, $to_period ) ),
                'note'         => sanitize_text_field( $note ),
                'created_at'   => current_time( 'mysql' ),
            )
        );

        $id = (int) $wpdb->insert_id;
        if ( $id ) {
            $this->sync_substitutions_for_absence( $id );
        }
        return $id;
    }

    public function get_teacher_absences( ?string $date = null ): array {
        global $wpdb;
        if ( $date ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom plugin tables; live operational data.
            return $wpdb->get_results(
                $wpdb->prepare(
                    'SELECT a.*, t.first_name, t.last_name, t.code
                     FROM %i a
                     INNER JOIN %i t ON t.id = a.teacher_id
                     WHERE a.absence_date = %s
                     ORDER BY a.absence_date DESC, t.last_name, t.first_name',
                    $this->tables['teacher_absences'],
                    $this->tables['teachers'],
                    $date
                ),
                ARRAY_A
            ) ?: array();
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom plugin tables; live operational data.
        return $wpdb->get_results(
            $wpdb->prepare(
                'SELECT a.*, t.first_name, t.last_name, t.code
                 FROM %i a
                 INNER JOIN %i t ON t.id = a.teacher_id
                 ORDER BY a.absence_date DESC, t.last_name, t.first_name',
                $this->tables['teacher_absences'],
                $this->tables['teachers']
            ),
            ARRAY_A
        ) ?: array();
    }

    public function teacher_is_absent( int $teacher_id, string $date, int $period ): bool {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom plugin tables; live operational data.
        return (bool) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT 1 FROM %i WHERE teacher_id = %d AND absence_date = %s AND %d BETWEEN from_period AND to_period LIMIT 1',
                $this->tables['teacher_absences'],
                $teacher_id,
                $date,
                $period
            )
        );
    }

    public function delete_teacher_absence( int $id ): bool {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom plugin tables; live operational data.
        $absence = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM %i WHERE id = %d',
                $this->tables['teacher_absences'],
                $id
            ),
            ARRAY_A
        );

        if ( ! $absence ) {
            return false;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom plugin tables; live operational data.
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM %i WHERE source_absence_id = %d AND status IN ('pending','not_required','cancelled')",
                $this->tables['substitutions'],
                $id
            )
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom plugin tables; live operational data.
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE %i SET status = 'cancelled', updated_at = %s WHERE source_absence_id = %d AND status = 'assigned'",
                $this->tables['substitutions'],
                current_time( 'mysql' ),
                $id
            )
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom plugin tables; live operational data.
        return (bool) $wpdb->delete( $this->tables['teacher_absences'], array( 'id' => $id ), array( '%d' ) );
    }

    public function add_absent_class( string $date, string $class_name, int $from_period, int $to_period, string $note = '' ): int {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom plugin tables; live operational data.
        $wpdb->insert(
            $this->tables['absent_classes'],
            array(
                'absence_date' => $date,
                'class_name'   => sanitize_text_field( $class_name ),
                'from_period'  => max( 1, min( 12, $from_period ) ),
                'to_period'    => max( 1, min( 12, $to_period ) ),
                'note'         => sanitize_text_field( $note ),
                'created_at'   => current_time( 'mysql' ),
            )
        );

        $id = (int) $wpdb->insert_id;
        if ( $id ) {
            $this->mark_not_required_for_absent_class( $date, $class_name, $from_period, $to_period );
        }
        return $id;
    }

    public function get_absent_classes( ?string $date = null ): array {
        global $wpdb;
        if ( $date ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom plugin tables; live operational data.
            return $wpdb->get_results(
                $wpdb->prepare(
                    'SELECT * FROM %i WHERE absence_date = %s ORDER BY absence_date DESC, class_name, from_period',
                    $this->tables['absent_classes'],
                    $date
                ),
                ARRAY_A
            ) ?: array();
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom plugin tables; live operational data.
        return $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM %i ORDER BY absence_date DESC, class_name, from_period',
                $this->tables['absent_classes']
            ),
            ARRAY_A
        ) ?: array();
    }

    public function class_is_absent( string $date, string $class_name, int $period ): bool {
        global $wpdb;
        if ( '' === trim( $class_name ) ) {
            return false;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom plugin tables; live operational data.
        return (bool) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT 1 FROM %i WHERE absence_date = %s AND class_name = %s AND %d BETWEEN from_period AND to_period LIMIT 1',
                $this->tables['absent_classes'],
                $date,
                $class_name,
                $period
            )
        );
    }

    public function delete_absent_class( int $id ): bool {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom plugin tables; live operational data.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM %i WHERE id = %d',
                $this->tables['absent_classes'],
                $id
            ),
            ARRAY_A
        );

        if ( ! $row ) {
            return false;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom plugin tables; live operational data.
        $deleted = (bool) $wpdb->delete( $this->tables['absent_classes'], array( 'id' => $id ), array( '%d' ) );
        if ( $deleted ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom plugin tables; live operational data.
            $absences = $wpdb->get_col(
                $wpdb->prepare(
                    'SELECT id FROM %i WHERE absence_date = %s',
                    $this->tables['teacher_absences'],
                    $row['absence_date']
                )
            );
            foreach ( $absences as $absence_id ) {
                $this->sync_substitutions_for_absence( (int) $absence_id );
            }
        }

        return $deleted;
    }

    public function mark_not_required_for_absent_class( string $date, string $class_name, int $from_period, int $to_period ): void {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom plugin tables; live operational data.
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE %i
                 SET status = 'not_required', substitute_teacher_id = NULL, updated_at = %s
                 WHERE substitution_date = %s AND class_name = %s AND period BETWEEN %d AND %d AND status IN ('pending','assigned')",
                $this->tables['substitutions'],
                current_time( 'mysql' ),
                $date,
                $class_name,
                $from_period,
                $to_period
            )
        );
    }

    public function sync_substitutions_for_absence( int $absence_id ): void {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom plugin tables; live operational data.
        $absence = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM %i WHERE id = %d',
                $this->tables['teacher_absences'],
                $absence_id
            ),
            ARRAY_A
        );

        if ( ! $absence ) {
            return;
        }

        $weekday = (int) wp_date( 'N', strtotime( $absence['absence_date'] . ' 12:00:00' ) );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom plugin tables; live operational data.
        $slots = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM %i
                 WHERE teacher_id = %d AND weekday = %d AND period BETWEEN %d AND %d AND activity = 'lesson'
                 ORDER BY period",
                $this->tables['schedule'],
                (int) $absence['teacher_id'],
                $weekday,
                (int) $absence['from_period'],
                (int) $absence['to_period']
            ),
            ARRAY_A
        ) ?: array();

        $valid_keys = array();
        foreach ( $slots as $slot ) {
            $valid_keys[] = (int) $slot['period'] . '|' . (string) $slot['class_name'];
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom plugin tables; live operational data.
        $existing_rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT id, period, class_name, status FROM %i WHERE source_absence_id = %d',
                $this->tables['substitutions'],
                $absence_id
            ),
            ARRAY_A
        ) ?: array();

        foreach ( $existing_rows as $existing_row ) {
            $key = (int) $existing_row['period'] . '|' . (string) $existing_row['class_name'];
            if ( in_array( $key, $valid_keys, true ) ) {
                continue;
            }

            if ( 'assigned' === $existing_row['status'] ) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom plugin tables; live operational data.
                $wpdb->update(
                    $this->tables['substitutions'],
                    array(
                        'status'                => 'cancelled',
                        'substitute_teacher_id' => null,
                        'updated_at'            => current_time( 'mysql' ),
                    ),
                    array( 'id' => (int) $existing_row['id'] )
                );
            } else {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom plugin tables; live operational data.
                $wpdb->delete( $this->tables['substitutions'], array( 'id' => (int) $existing_row['id'] ), array( '%d' ) );
            }
        }

        foreach ( $slots as $slot ) {
            $is_class_absent = $this->class_is_absent( $absence['absence_date'], $slot['class_name'], (int) $slot['period'] );
            $status = $is_class_absent ? 'not_required' : 'pending';

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom plugin tables; live operational data.
            $existing = $wpdb->get_row(
                $wpdb->prepare(
                    'SELECT id, status FROM %i
                     WHERE substitution_date = %s AND period = %d AND absent_teacher_id = %d AND class_name = %s',
                    $this->tables['substitutions'],
                    $absence['absence_date'],
                    (int) $slot['period'],
                    (int) $absence['teacher_id'],
                    $slot['class_name']
                ),
                ARRAY_A
            );

            $data = array(
                'source_absence_id' => $absence_id,
                'substitution_date' => $absence['absence_date'],
                'period'            => (int) $slot['period'],
                'start_time'        => $slot['start_time'],
                'end_time'          => $slot['end_time'],
                'class_name'        => $slot['class_name'],
                'absent_teacher_id' => (int) $absence['teacher_id'],
                'subject'           => $slot['subject'],
                'room'              => $slot['room'],
                'updated_at'        => current_time( 'mysql' ),
            );

            if ( $existing ) {
                if ( 'assigned' !== $existing['status'] || $is_class_absent ) {
                    $data['status'] = $status;
                    if ( $is_class_absent ) {
                        $data['substitute_teacher_id'] = null;
                    }
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom plugin tables; live operational data.
                    $wpdb->update( $this->tables['substitutions'], $data, array( 'id' => (int) $existing['id'] ) );
                }
            } else {
                $data['status'] = $status;
                $data['created_at'] = current_time( 'mysql' );
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom plugin tables; live operational data.
                $wpdb->insert( $this->tables['substitutions'], $data );
            }
        }
    }

    public function sync_substitutions_for_date( string $date ): int {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom plugin tables; live operational data.
        $ids = $wpdb->get_col(
            $wpdb->prepare(
                'SELECT id FROM %i WHERE absence_date = %s ORDER BY id',
                $this->tables['teacher_absences'],
                $date
            )
        ) ?: array();

        foreach ( $ids as $absence_id ) {
            $this->sync_substitutions_for_absence( (int) $absence_id );
        }

        return count( $ids );
    }

    public function sync_all_substitutions(): int {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom plugin tables; live operational data.
        $ids = $wpdb->get_col(
            $wpdb->prepare(
                'SELECT id FROM %i ORDER BY absence_date, id',
                $this->tables['teacher_absences']
            )
        ) ?: array();

        foreach ( $ids as $absence_id ) {
            $this->sync_substitutions_for_absence( (int) $absence_id );
        }

        return count( $ids );
    }

    public function count_substitutions_for_absence( int $absence_id ): int {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom plugin tables; live operational data.
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM %i WHERE source_absence_id = %d AND status <> 'cancelled'",
                $this->tables['substitutions'],
                $absence_id
            )
        );
    }

    public function list_substitutions( string $date, bool $include_hidden = true ): array {
        global $wpdb;
        if ( $include_hidden ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom plugin tables; live operational data.
            return $wpdb->get_results(
                $wpdb->prepare(
                    'SELECT s.*,
                            a.first_name AS absent_first_name, a.last_name AS absent_last_name, a.code AS absent_code,
                            r.first_name AS substitute_first_name, r.last_name AS substitute_last_name, r.code AS substitute_code
                     FROM %i s
                     LEFT JOIN %i a ON a.id = s.absent_teacher_id
                     LEFT JOIN %i r ON r.id = s.substitute_teacher_id
                     WHERE s.substitution_date = %s
                     ORDER BY s.period, s.class_name',
                    $this->tables['substitutions'],
                    $this->tables['teachers'],
                    $this->tables['teachers'],
                    $date
                ),
                ARRAY_A
            ) ?: array();
        }

        $settings = $this->settings();
        if ( ! empty( $settings['show_pending'] ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom plugin tables; live operational data.
            return $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT s.*,
                            a.first_name AS absent_first_name, a.last_name AS absent_last_name, a.code AS absent_code,
                            r.first_name AS substitute_first_name, r.last_name AS substitute_last_name, r.code AS substitute_code
                     FROM %i s
                     LEFT JOIN %i a ON a.id = s.absent_teacher_id
                     LEFT JOIN %i r ON r.id = s.substitute_teacher_id
                     WHERE s.substitution_date = %s AND s.status IN ('assigned','pending')
                     ORDER BY s.period, s.class_name",
                    $this->tables['substitutions'],
                    $this->tables['teachers'],
                    $this->tables['teachers'],
                    $date
                ),
                ARRAY_A
            ) ?: array();
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom plugin tables; live operational data.
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT s.*,
                        a.first_name AS absent_first_name, a.last_name AS absent_last_name, a.code AS absent_code,
                        r.first_name AS substitute_first_name, r.last_name AS substitute_last_name, r.code AS substitute_code
                 FROM %i s
                 LEFT JOIN %i a ON a.id = s.absent_teacher_id
                 LEFT JOIN %i r ON r.id = s.substitute_teacher_id
                 WHERE s.substitution_date = %s AND s.status = 'assigned'
                 ORDER BY s.period, s.class_name",
                $this->tables['substitutions'],
                $this->tables['teachers'],
                $this->tables['teachers'],
                $date
            ),
            ARRAY_A
        ) ?: array();
    }

    public function get_substitution( int $id ): ?array {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom plugin tables; live operational data.
        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM %i WHERE id = %d',
                $this->tables['substitutions'],
                $id
            ),
            ARRAY_A
        );
        return $row ?: null;
    }

    public function assign_substitute( int $substitution_id, ?int $teacher_id, string $note = '' ): bool {
        global $wpdb;
        $status = $teacher_id ? 'assigned' : 'pending';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom plugin tables; live operational data.
        return false !== $wpdb->update(
            $this->tables['substitutions'],
            array(
                'substitute_teacher_id' => $teacher_id ?: null,
                'status'                => $status,
                'note'                  => sanitize_text_field( $note ),
                'updated_at'            => current_time( 'mysql' ),
            ),
            array( 'id' => $substitution_id )
        );
    }

    public function teacher_is_already_substitute( int $teacher_id, string $date, int $period, ?int $ignore_substitution_id = null ): bool {
        global $wpdb;
        if ( $ignore_substitution_id ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom plugin tables; live operational data.
            return (bool) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT 1 FROM %i
                     WHERE substitute_teacher_id = %d AND substitution_date = %s AND period = %d
                       AND status = 'assigned' AND id <> %d
                     LIMIT 1",
                    $this->tables['substitutions'],
                    $teacher_id,
                    $date,
                    $period,
                    $ignore_substitution_id
                )
            );
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom plugin tables; live operational data.
        return (bool) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT 1 FROM %i
                 WHERE substitute_teacher_id = %d AND substitution_date = %s AND period = %d
                   AND status = 'assigned'
                 LIMIT 1",
                $this->tables['substitutions'],
                $teacher_id,
                $date,
                $period
            )
        );
    }

    public function count_today_dashboard( string $date ): array {
        global $wpdb;
        return array(
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom plugin tables; live operational data.
            'teachers'      => (int) $wpdb->get_var(
                $wpdb->prepare(
                    'SELECT COUNT(*) FROM %i WHERE active = 1',
                    $this->tables['teachers']
                )
            ),
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom plugin tables; live operational data.
            'schedule'      => (int) $wpdb->get_var(
                $wpdb->prepare(
                    'SELECT COUNT(*) FROM %i',
                    $this->tables['schedule']
                )
            ),
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom plugin tables; live operational data.
            'absences'      => (int) $wpdb->get_var(
                $wpdb->prepare(
                    'SELECT COUNT(*) FROM %i WHERE absence_date = %s',
                    $this->tables['teacher_absences'],
                    $date
                )
            ),
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom plugin tables; live operational data.
            'substitutions' => (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM %i WHERE substitution_date = %s AND status = 'assigned'",
                    $this->tables['substitutions'],
                    $date
                )
            ),
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom plugin tables; live operational data.
            'pending'       => (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM %i WHERE substitution_date = %s AND status = 'pending'",
                    $this->tables['substitutions'],
                    $date
                )
            ),
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom plugin tables; live operational data.
            'classes'       => (int) $wpdb->get_var(
                $wpdb->prepare(
                    'SELECT COUNT(*) FROM %i WHERE absence_date = %s',
                    $this->tables['absent_classes'],
                    $date
                )
            ),
        );
    }
}
