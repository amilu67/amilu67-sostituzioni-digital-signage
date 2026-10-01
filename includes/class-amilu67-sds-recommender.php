<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AMILU67_SDS_Recommender {
    private AMILU67_SDS_DB $db;

    public function __construct( AMILU67_SDS_DB $db ) {
        $this->db = $db;
    }

    public function candidates_for_substitution( array $substitution ): array {
        $date          = (string) $substitution['substitution_date'];
        $period        = (int) $substitution['period'];
        $absent_id     = (int) ( $substitution['absent_teacher_id'] ?? 0 );
        $substitution_id = (int) ( $substitution['id'] ?? 0 );
        $subject       = trim( (string) ( $substitution['subject'] ?? '' ) );
        $settings      = $this->db->settings();
        $weekday       = (int) wp_date( 'N', strtotime( $date . ' 12:00:00' ) );
        $results       = array();

        foreach ( $this->db->list_teachers( true ) as $teacher ) {
            $teacher_id = (int) $teacher['id'];
            if ( $teacher_id === $absent_id ) {
                continue;
            }
            if ( $this->db->teacher_is_absent( $teacher_id, $date, $period ) ) {
                continue;
            }
            if ( $this->db->teacher_is_already_substitute( $teacher_id, $date, $period, $substitution_id ) ) {
                continue;
            }

            $slot   = $this->db->get_teacher_slot( $teacher_id, $date, $period );
            $score  = 0;
            $reason = '';
            $type   = '';

            if ( $slot && 'availability' === $slot['activity'] ) {
                $score  = 100;
                $reason = 'Disponibilità dichiarata';
                $type   = 'availability';
            } elseif ( $slot && 'lesson' === $slot['activity'] && $this->db->class_is_absent( $date, $slot['class_name'], $period ) ) {
                $score  = 90;
                $reason = sprintf( 'Liberato: classe %s assente', $slot['class_name'] );
                $type   = 'freed';
            } elseif ( ! $slot && ! empty( $settings['consider_free'] ) && $this->db->teacher_has_any_schedule_on_weekday( $teacher_id, $weekday ) ) {
                $score  = 40;
                $reason = 'Nessuna lezione prevista in questa ora';
                $type   = 'free';
            } else {
                continue;
            }

            if ( $subject && $this->teacher_teaches_subject( $teacher_id, $subject ) ) {
                $score += 10;
                $reason .= ' · stessa materia';
            }

            $results[] = array(
                'id'         => $teacher_id,
                'code'       => $teacher['code'],
                'first_name' => $teacher['first_name'],
                'last_name'  => $teacher['last_name'],
                'score'      => $score,
                'reason'     => $reason,
                'type'       => $type,
            );
        }

        usort(
            $results,
            static function ( array $a, array $b ): int {
                if ( $a['score'] === $b['score'] ) {
                    return strcasecmp( $a['last_name'] . ' ' . $a['first_name'], $b['last_name'] . ' ' . $b['first_name'] );
                }
                return $b['score'] <=> $a['score'];
            }
        );

        return $results;
    }

    private function teacher_teaches_subject( int $teacher_id, string $subject ): bool {
        $normalized = strtolower( remove_accents( trim( $subject ) ) );
        if ( '' === $normalized ) {
            return false;
        }
        $subjects = $this->db->get_teacher_subjects( $teacher_id );
        foreach ( $subjects as $candidate ) {
            if ( strtolower( remove_accents( trim( (string) $candidate ) ) ) === $normalized ) {
                return true;
            }
        }
        return false;
    }
}
