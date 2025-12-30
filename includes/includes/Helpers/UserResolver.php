<?php

namespace AmeliaTutor\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UserResolver {

    /**
     * Resolve WordPress user ID from Amelia customer ID
     */
    public static function by_customer_id( $customer_id ) {

        global $wpdb;

        // Try stored mapping first
        $user_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT user_id FROM {$wpdb->prefix}amelia_customer_users
                 WHERE customer_id = %d",
                $customer_id
            )
        );

        if ( $user_id ) {
            return intval( $user_id );
        }

        // Fallback: resolve via email
        $email = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT email FROM {$wpdb->prefix}amelia_customers
                 WHERE id = %d",
                $customer_id
            )
        );

        if ( ! $email ) {
            return 0;
        }

        $user = get_user_by( 'email', $email );

        if ( ! $user ) {
            return 0;
        }

        // Store mapping for future use
        $wpdb->insert(
            $wpdb->prefix . 'amelia_customer_users',
            [
                'customer_id' => $customer_id,
                'user_id'     => $user->ID,
            ],
            [ '%d', '%d' ]
        );

        return intval( $user->ID );
    }
}
