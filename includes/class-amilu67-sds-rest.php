<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AMILU67_SDS_REST {
    private AMILU67_SDS_DB $db;
    private AMILU67_SDS_Recommender $recommender;

    public function __construct( AMILU67_SDS_DB $db, AMILU67_SDS_Recommender $recommender ) {
        $this->db = $db;
        $this->recommender = $recommender;
    }

    public function register_routes(): void {
        register_rest_route(
            'amilu67-sds/v1',
            '/screen',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'screen_data' ),
                'permission_callback' => '__return_true',
                'args'                => array(
                    'date' => array(
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                ),
            )
        );

        register_rest_route(
            'amilu67-sds/v1',
            '/candidates/(?P<id>\d+)',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'candidates' ),
                'permission_callback' => static function (): bool {
                    return current_user_can( 'amilu67_sds_manage_school_signage' ) || current_user_can( 'manage_options' );
                },
            )
        );
    }

    public function screen_data( WP_REST_Request $request ): WP_REST_Response {
        $date = $request->get_param( 'date' );
        if ( ! $date || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
            $date = wp_date( 'Y-m-d' );
        }
        $settings = $this->db->settings();
        $rows = array_map(
            function ( array $row ) use ( $settings ): array {
                return array(
                    'id'          => (int) $row['id'],
                    'period'      => (int) $row['period'],
                    'start_time'  => $this->format_time( $row['start_time'] ),
                    'end_time'    => $this->format_time( $row['end_time'] ),
                    'class_name'  => $row['class_name'],
                    'subject'     => $row['subject'],
                    'room'        => $row['room'],
                    'absent'      => $this->format_name( $row['absent_first_name'], $row['absent_last_name'], $settings['name_format'] ),
                    'substitute'  => $row['substitute_teacher_id'] ? $this->format_name( $row['substitute_first_name'], $row['substitute_last_name'], $settings['name_format'] ) : '',
                    'status'      => $row['status'],
                    'note'        => $row['note'],
                );
            },
            $this->db->list_substitutions( $date, false )
        );

        $classes = array_map(
            static function ( array $row ): array {
                return array(
                    'class_name'  => $row['class_name'],
                    'from_period' => (int) $row['from_period'],
                    'to_period'   => (int) $row['to_period'],
                    'note'        => $row['note'],
                );
            },
            $this->db->get_absent_classes( $date )
        );

        return new WP_REST_Response(
            array(
                'date'           => $date,
                'date_label'     => wp_date( 'l j F Y', strtotime( $date . ' 12:00:00' ) ),
                'school_name'    => $settings['school_name'],
                'screen_title'   => $settings['screen_title'],
                'screen_note'    => $settings['screen_note'],
                'refresh_seconds'=> (int) $settings['refresh_seconds'],
                'substitutions'  => $rows,
                'absent_classes' => $classes,
                'generated_at'   => wp_date( 'H:i:s' ),
            )
        );
    }

    public function candidates( WP_REST_Request $request ): WP_REST_Response {
        $substitution = $this->db->get_substitution( (int) $request['id'] );
        if ( ! $substitution ) {
            return new WP_REST_Response( array( 'message' => 'Sostituzione non trovata.' ), 404 );
        }
        return new WP_REST_Response( $this->recommender->candidates_for_substitution( $substitution ) );
    }

    private function format_name( ?string $first, ?string $last, string $mode ): string {
        $first = trim( (string) $first );
        $last  = trim( (string) $last );
        if ( 'full' === $mode ) {
            return trim( $first . ' ' . $last );
        }
        if ( 'surname_only' === $mode ) {
            return $last;
        }
        return trim( $last . ( $first ? ' ' . ( function_exists( 'mb_substr' ) ? mb_substr( $first, 0, 1 ) : substr( $first, 0, 1 ) ) . '.' : '' ) );
    }

    private function format_time( ?string $time ): string {
        if ( ! $time ) {
            return '';
        }
        return substr( $time, 0, 5 );
    }
}
