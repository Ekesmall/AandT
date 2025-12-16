<?php

namespace AmeliaTutor\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ZoomExtractor {

    /**
     * Extract Zoom URLs from Amelia appointment
     */
    public static function from_appointment( array $appointment ) {

        $join_url = '';
        $host_url = '';

        // Native Amelia Zoom integration
        if ( ! empty( $appointment['zoomMeeting'] ) ) {

            $join_url = $appointment['zoomMeeting']['joinUrl'] ?? '';
            $host_url = $appointment['zoomMeeting']['startUrl'] ?? '';
        }

        // Custom fields fallback
        if ( empty( $join_url ) && ! empty( $appointment['customFields'] ) ) {

            foreach ( $appointment['customFields'] as $field ) {

                if ( empty( $field['value'] ) ) {
                    continue;
                }

                if ( stripos( $field['label'], 'zoom join' ) !== false ) {
                    $join_url = $field['value'];
                }

                if ( stripos( $field['label'], 'zoom host' ) !== false ) {
                    $host_url = $field['value'];
                }
            }
        }

        return [
            'join' => $join_url,
            'host' => $host_url,
        ];
    }
}
