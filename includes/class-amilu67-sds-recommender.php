<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class AMILU67_SDS_Recommender {
    private AMILU67_SDS_DB $db;
    public function __construct( AMILU67_SDS_DB $db ) { $this->db = $db; }

    public function candidates_for_substitution( array $substitution ): array {
        $date = (string) $substitution['substitution_date'];
        $period = (int) $substitution['period'];
        $absent_id = (int) ( $substitution['absent_teacher_id'] ?? 0 );
        $substitution_id = (int) ( $substitution['id'] ?? 0 );
        $subject = trim( (string) ( $substitution['subject'] ?? '' ) );
        $settings = $this->db->settings();
        $priority = is_array( $settings['priority_order'] ?? null ) ? $settings['priority_order'] : array( 'recovery','disposition','potenziamento','freed','extra' );
        $rank = array_flip( $priority );
        $results = array();

        foreach ( $this->db->list_teachers( true ) as $teacher ) {
            $teacher_id = (int) $teacher['id'];
            if ( $teacher_id === $absent_id || $this->db->teacher_is_absent( $teacher_id, $date, $period ) || $this->db->teacher_is_already_substitute( $teacher_id, $date, $period, $substitution_id ) ) {
                continue;
            }
            $slot = $this->db->get_teacher_slot( $teacher_id, $date, $period );
            if ( ! $slot ) {
                continue; // Dalla 1.3.0 una semplice ora buca non equivale a disponibilità.
            }

            $activity = (string) $slot['activity'];
            $type = '';
            $reason = '';
            $warning = '';
            if ( in_array( $activity, array( 'availability', 'disposition' ), true ) ) {
                $type = 'disposition';
                $reason = 'Disposizione contrattuale';
            } elseif ( 'potenziamento_disponibile' === $activity ) {
                $type = 'potenziamento';
                $reason = 'Potenziamento utilizzabile';
            } elseif ( 'recovery' === $activity ) {
                $recovery = $this->db->recovery_status( $teacher_id, $date );
                if ( $recovery['remaining'] <= 0 ) { continue; }
                $type = 'recovery';
                $reason = sprintf( 'Recupero permesso breve · %.1f h residue%s', $recovery['remaining'], ! empty( $recovery['overdue'] ) ? ' · scaduto' : '' );
                if ( ! empty( $recovery['overdue'] ) ) { $warning = 'Recupero oltre la scadenza registrata: verificare la gestione amministrativa.'; }
            } elseif ( 'extra' === $activity ) {
                if ( empty( $teacher['extra_hours_opt_in'] ) ) { continue; }
                $used = $this->db->weekly_extra_hours( $teacher_id, $date );
                $max = max( 0, (float) ( $teacher['max_extra_weekly'] ?? 6 ) );
                if ( $used >= $max ) { continue; }
                $type = 'extra';
                $reason = sprintf( 'Ora eccedente volontaria · %.0f/%.0f h settimanali', $used, $max );
                if ( $used + 1 > 6 ) { $warning = 'Superamento della soglia prudenziale di 6 ore aggiuntive settimanali.'; }
            } elseif ( 'lesson' === $activity && ! empty( $settings['allow_freed_class'] ) && $this->db->class_is_absent( $date, (string) $slot['class_name'], $period ) ) {
                $type = 'freed';
                $reason = sprintf( 'Classe %s assente: docente liberato', $slot['class_name'] );
            } else {
                continue;
            }

            if ( 'support' === ( $teacher['post_type'] ?? 'common' ) ) {
                if ( 'exclude' === ( $settings['support_policy'] ?? 'warn' ) ) { continue; }
                $warning = trim( $warning . ' Docente di sostegno: utilizzo da valutare e motivare secondo l’organizzazione dell’istituto.' );
            }
            if ( in_array( ( $teacher['employment_type'] ?? 'full_time' ), array( 'part_time', 'coe' ), true ) ) {
                $warning = trim( $warning . ' Verificare compatibilità con part-time/COE e servizio presso altre sedi.' );
            }

            $position = array_key_exists( $type, $rank ) ? (int) $rank[ $type ] : 99;
            $score = 1000 - ( $position * 100 );
            if ( $subject && $this->teacher_teaches_subject( $teacher_id, $subject ) ) {
                $score += 10;
                $reason .= ' · stessa materia';
            }
            if ( 'support' === ( $teacher['post_type'] ?? 'common' ) ) { $score -= 50; }
            $results[] = array(
                'id'=>$teacher_id,'code'=>$teacher['code'],'first_name'=>$teacher['first_name'],'last_name'=>$teacher['last_name'],
                'score'=>$score,'reason'=>$reason,'type'=>$type,'warning'=>$warning,
            );
        }
        usort( $results, static function( array $a, array $b ): int {
            return $a['score'] === $b['score'] ? strcasecmp( $a['last_name'].' '.$a['first_name'], $b['last_name'].' '.$b['first_name'] ) : $b['score'] <=> $a['score'];
        } );
        return $results;
    }

    public function candidate_by_teacher( array $substitution, int $teacher_id ): ?array {
        foreach ( $this->candidates_for_substitution( $substitution ) as $candidate ) {
            if ( (int) $candidate['id'] === $teacher_id ) { return $candidate; }
        }
        return null;
    }

    private function teacher_teaches_subject( int $teacher_id, string $subject ): bool {
        $normalized = strtolower( remove_accents( trim( $subject ) ) );
        if ( '' === $normalized ) { return false; }
        foreach ( $this->db->get_teacher_subjects( $teacher_id ) as $candidate ) {
            if ( strtolower( remove_accents( trim( (string) $candidate ) ) ) === $normalized ) { return true; }
        }
        return false;
    }
}
