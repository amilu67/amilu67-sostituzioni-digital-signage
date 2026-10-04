<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AMILU67_SDS_Importer {
    private AMILU67_SDS_DB $db;

    public function __construct( AMILU67_SDS_DB $db ) {
        $this->db = $db;
    }

    public function import_bulk_csv( string $path, bool $replace_all = false ): array {
        $parsed = $this->read_csv( $path );
        if ( isset( $parsed['error'] ) ) {
            return array( 'imported' => 0, 'errors' => array( $parsed['error'] ) );
        }
        if ( $replace_all ) {
            $this->db->clear_schedule();
        }

        $imported = 0;
        $errors   = array();
        foreach ( $parsed['rows'] as $i => $row ) {
            $line = $i + 2;
            $code = trim( (string) $this->value( $row, array( 'codice_docente', 'codice', 'teacher_code', 'docente_id' ) ) );
            $first = trim( (string) $this->value( $row, array( 'nome', 'first_name' ) ) );
            $last  = trim( (string) $this->value( $row, array( 'cognome', 'last_name' ) ) );
            if ( '' === $code ) {
                if ( '' === $first && '' === $last ) {
                    $errors[] = "Riga $line: docente non identificato.";
                    continue;
                }
                $code = strtoupper( sanitize_title( $last . '-' . $first ) );
            }
            $teacher = $this->db->get_teacher_by_code( $code );
            if ( ! $teacher ) {
                $teacher_id = $this->db->upsert_teacher(
                    array(
                        'code'       => $code,
                        'first_name' => $first,
                        'last_name'  => $last,
                        'email'      => (string) $this->value( $row, array( 'email' ) ),
                        'active'     => 1,
                    )
                );
            } else {
                $teacher_id = (int) $teacher['id'];
                if ( ( $first || $last ) && ( $first !== $teacher['first_name'] || $last !== $teacher['last_name'] ) ) {
                    $teacher_id = $this->db->upsert_teacher(
                        array(
                            'code'       => $code,
                            'first_name' => $first ?: $teacher['first_name'],
                            'last_name'  => $last ?: $teacher['last_name'],
                            'email'      => $teacher['email'],
                            'active'     => 1,
                        )
                    );
                }
            }
            $result = $this->import_schedule_row( $row, $teacher_id, $line );
            if ( true === $result ) {
                ++$imported;
            } else {
                $errors[] = $result;
            }
        }
        return array( 'imported' => $imported, 'errors' => $errors );
    }

    public function import_teacher_csv( string $path, int $teacher_id, bool $replace_teacher = false ): array {
        $parsed = $this->read_csv( $path );
        if ( isset( $parsed['error'] ) ) {
            return array( 'imported' => 0, 'errors' => array( $parsed['error'] ) );
        }
        if ( $replace_teacher ) {
            $this->db->clear_schedule( $teacher_id );
        }
        $imported = 0;
        $errors   = array();
        foreach ( $parsed['rows'] as $i => $row ) {
            $result = $this->import_schedule_row( $row, $teacher_id, $i + 2 );
            if ( true === $result ) {
                ++$imported;
            } else {
                $errors[] = $result;
            }
        }
        return array( 'imported' => $imported, 'errors' => $errors );
    }

    private function import_schedule_row( array $row, int $teacher_id, int $line ) {
        $weekday = $this->parse_weekday( (string) $this->value( $row, array( 'giorno', 'weekday', 'giorno_settimana' ) ) );
        $period  = absint( $this->value( $row, array( 'ora', 'periodo', 'period', 'hour' ) ) );
        if ( ! $weekday || $period < 1 || $period > 12 ) {
            return "Riga $line: giorno/ora non validi.";
        }
        $activity_raw = strtolower( remove_accents( trim( (string) $this->value( $row, array( 'tipo', 'activity', 'attivita' ) ) ) ) );
        $activity_map = array(
            'lezione' => 'lesson', 'lesson' => 'lesson',
            'disponibilita' => 'disposition', 'availability' => 'disposition', 'disp' => 'disposition', 'disposizione' => 'disposition', 'disposizione_contrattuale' => 'disposition',
            'potenziamento' => 'potenziamento', 'potenziamento_programmato' => 'potenziamento',
            'potenziamento_disponibile' => 'potenziamento_disponibile', 'potenziamento_utilizzabile' => 'potenziamento_disponibile',
            'recupero' => 'recovery', 'recupero_permesso' => 'recovery',
            'ora_eccedente' => 'extra', 'eccedente' => 'extra', 'extra' => 'extra',
            'compresenza' => 'compresenza', 'altra_sede' => 'other_service', 'servizio_altra_sede' => 'other_service', 'non_disponibile' => 'not_available'
        );
        $activity = $activity_map[ $activity_raw ] ?? 'lesson';
        $class = trim( (string) $this->value( $row, array( 'classe', 'class', 'class_name' ) ) );
        if ( in_array( $activity, array( 'lesson', 'compresenza', 'potenziamento' ), true ) && '' === $class ) {
            return "Riga $line: per una lezione è necessaria la classe.";
        }
        $ok = $this->db->replace_schedule_slot(
            array(
                'teacher_id' => $teacher_id,
                'weekday'    => $weekday,
                'period'     => $period,
                'start_time' => $this->normalize_time( (string) $this->value( $row, array( 'inizio', 'start', 'start_time' ) ) ),
                'end_time'   => $this->normalize_time( (string) $this->value( $row, array( 'fine', 'end', 'end_time' ) ) ),
                'class_name' => $class,
                'subject'    => (string) $this->value( $row, array( 'materia', 'subject' ) ),
                'room'       => (string) $this->value( $row, array( 'aula', 'room' ) ),
                'activity'   => $activity,
            )
        );
        return $ok ? true : "Riga $line: impossibile salvare la fascia oraria.";
    }

    private function read_csv( string $path ): array {
        require_once ABSPATH . 'wp-admin/includes/file.php';

        if ( ! WP_Filesystem() ) {
            return array( 'error' => 'Impossibile inizializzare il filesystem di WordPress.' );
        }

        global $wp_filesystem;
        if ( ! $wp_filesystem || ! $wp_filesystem->exists( $path ) || ! $wp_filesystem->is_readable( $path ) ) {
            return array( 'error' => 'File non leggibile.' );
        }

        $contents = $wp_filesystem->get_contents( $path );
        if ( false === $contents || '' === trim( $contents ) ) {
            return array( 'error' => 'Il file è vuoto.' );
        }

        $contents = preg_replace( '/^\xEF\xBB\xBF/', '', $contents );
        $lines = preg_split( '/\r\n|\n|\r/', $contents );
        if ( ! is_array( $lines ) || empty( $lines ) ) {
            return array( 'error' => 'Contenuto CSV non valido.' );
        }

        $first = array_shift( $lines );
        $delimiter = $this->detect_delimiter( (string) $first );
        $headers = str_getcsv( (string) $first, $delimiter );
        if ( empty( $headers ) ) {
            return array( 'error' => 'Intestazione CSV non valida.' );
        }

        $headers = array_map( array( $this, 'normalize_header' ), $headers );
        $rows = array();

        foreach ( $lines as $line ) {
            if ( '' === trim( (string) $line ) ) {
                continue;
            }

            $values = str_getcsv( (string) $line, $delimiter );
            $values = array_pad( $values, count( $headers ), '' );
            $combined = array_combine( $headers, array_slice( $values, 0, count( $headers ) ) );
            if ( is_array( $combined ) ) {
                $rows[] = $combined;
            }
        }

        return array( 'rows' => $rows );
    }

    private function detect_delimiter( string $line ): string {
        $candidates = array( ';', ',', "\t" );
        $best = ';';
        $best_count = -1;
        foreach ( $candidates as $delimiter ) {
            $count = substr_count( $line, $delimiter );
            if ( $count > $best_count ) {
                $best = $delimiter;
                $best_count = $count;
            }
        }
        return $best;
    }

    private function normalize_header( string $header ): string {
        $header = preg_replace( '/^\xEF\xBB\xBF/', '', trim( $header ) );
        $header = strtolower( remove_accents( $header ) );
        $header = preg_replace( '/[^a-z0-9]+/', '_', $header );
        return trim( $header, '_' );
    }

    private function value( array $row, array $keys ) {
        foreach ( $keys as $key ) {
            $key = $this->normalize_header( $key );
            if ( array_key_exists( $key, $row ) ) {
                return $row[ $key ];
            }
        }
        return '';
    }

    private function parse_weekday( string $value ): int {
        $value = strtolower( remove_accents( trim( $value ) ) );
        if ( is_numeric( $value ) ) {
            $n = (int) $value;
            return ( $n >= 1 && $n <= 7 ) ? $n : 0;
        }
        $map = array(
            'lunedi' => 1, 'lun' => 1, 'monday' => 1,
            'martedi' => 2, 'mar' => 2, 'tuesday' => 2,
            'mercoledi' => 3, 'mer' => 3, 'wednesday' => 3,
            'giovedi' => 4, 'gio' => 4, 'thursday' => 4,
            'venerdi' => 5, 'ven' => 5, 'friday' => 5,
            'sabato' => 6, 'sab' => 6, 'saturday' => 6,
            'domenica' => 7, 'dom' => 7, 'sunday' => 7,
        );
        return $map[ $value ] ?? 0;
    }

    private function normalize_time( string $value ): ?string {
        $value = trim( $value );
        if ( '' === $value ) {
            return null;
        }
        if ( preg_match( '/^(\d{1,2})[:\.](\d{2})$/', $value, $m ) ) {
            $h = min( 23, (int) $m[1] );
            $i = min( 59, (int) $m[2] );
            return sprintf( '%02d:%02d:00', $h, $i );
        }
        return null;
    }
}
