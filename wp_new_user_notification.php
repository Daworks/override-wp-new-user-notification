<?php
! defined( 'ABSPATH' ) AND exit;
/**
 * Plugin Name: (pluggable) New User Notification
 */

if ( !function_exists('wp_new_user_notification') ) {
    function wp_new_user_notification( $user_id, $deprecated = null, $notify = '' ) {
        if ( $deprecated !== null ) {
            _deprecated_argument( __FUNCTION__, '4.3.1' );
        }

        // Accepts only 'user', 'admin' , 'both' or default '' as $notify
        if ( ! in_array( $notify, array( 'user', 'admin', 'both', '' ), true ) ) {
            return;
        }

        global $wpdb, $wp_hasher;
        $user = get_userdata( $user_id );

        // The blogname option is escaped with esc_html on the way into the database in sanitize_option
        // we want to reverse this for the plain text arena of emails.
        $blogname = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );

        if ( 'user' !== $notify ) {
            $switched_locale = switch_to_locale( get_locale() );

            /* translators: %s: site title */
            $message = sprintf( __( 'New user registration on your site %s:' ), $blogname ) . "\r\n\r\n";
            /* translators: %s: user login */
            $message .= sprintf( __( 'Username: %s' ), $user->user_login ) . "\r\n\r\n";
            /* translators: %s: user email address */
            $message .= sprintf( __( 'Email: %s' ), $user->user_email ) . "\r\n";

            $wp_new_user_notification_email_admin = array(
                'to'      => get_option( 'admin_email' ),
                /* translators: New user registration notification email subject. %s: Site title */
                'subject' => __( '[%s] New User Registration' ),
                'message' => $message,
                'headers' => '',
            );

            /**
             * Filters the contents of the new user notification email sent to the site admin.
             *
             * @since 4.9.0
             *
             * @param array   $wp_new_user_notification_email {
             *     Used to build wp_mail().
             *
             *     @type string $to      The intended recipient - site admin email address.
             *     @type string $subject The subject of the email.
             *     @type string $message The body of the email.
             *     @type string $headers The headers of the email.
             * }
             * @param WP_User $user     User object for new user.
             * @param string  $blogname The site title.
             */
            $wp_new_user_notification_email_admin = apply_filters( 'wp_new_user_notification_email_admin', $wp_new_user_notification_email_admin, $user, $blogname );

            @wp_mail(
                $wp_new_user_notification_email_admin['to'],
                wp_specialchars_decode( sprintf( $wp_new_user_notification_email_admin['subject'], $blogname ) ),
                $wp_new_user_notification_email_admin['message'],
                $wp_new_user_notification_email_admin['headers']
            );

            if ( $switched_locale ) {
                restore_previous_locale();
            }
        }

        // `$deprecated was pre-4.3 `$plaintext_pass`. An empty `$plaintext_pass` didn't sent a user notification.
        if ( 'admin' === $notify || ( empty( $deprecated ) && empty( $notify ) ) ) {
            return;
        }

        // Generate something random for a password reset key.
        $key = wp_generate_password( 20, false );

        /** This action is documented in wp-login.php */
        do_action( 'retrieve_password_key', $user->user_login, $key );

        // Now insert the key, hashed, into the DB.
        if ( empty( $wp_hasher ) ) {
            require_once ABSPATH . WPINC . '/class-phpass.php';
            $wp_hasher = new PasswordHash( 8, true );
        }
        $hashed = time() . ':' . $wp_hasher->HashPassword( $key );
        $wpdb->update( $wpdb->users, array( 'user_activation_key' => $hashed ), array( 'user_login' => $user->user_login ) );

        $switched_locale = switch_to_locale( get_user_locale( $user ) );

        /* translators: %s: user login */
        $message  = sprintf( __( 'Username: %s' ), $user->user_login ) . "\r\n\r\n";
        $message .= __( 'To set your password, visit the following address:' ) . "\r\n\r\n";
        $message .= network_site_url( "wp-login.php?action=rp&key=$key&login=" . rawurlencode( $user->user_login ), 'login' ) . "\r\n\r\n";
        $message .= '로그인을 하려면 다음 링크를 방문하세요.' . "\r\n\r\n";
        $message .= wp_login_url() . "\r\n";

        $wp_new_user_notification_email = array(
            'to'      => $user->user_email,
            /* translators: Login details notification email subject. %s: Site title */
            'subject' => __( '[%s] 가입 확인 메일' ),
            'message' => $message,
            'headers' => '',
        );

        /**
         * Filters the contents of the new user notification email sent to the new user.
         *
         * @since 4.9.0
         *
         * @param array   $wp_new_user_notification_email {
         *     Used to build wp_mail().
         *
         *     @type string $to      The intended recipient - New user email address.
         *     @type string $subject The subject of the email.
         *     @type string $message The body of the email.
         *     @type string $headers The headers of the email.
         * }
         * @param WP_User $user     User object for new user.
         * @param string  $blogname The site title.
         */
        $wp_new_user_notification_email = apply_filters( 'wp_new_user_notification_email', $wp_new_user_notification_email, $user, $blogname );

        wp_mail(
            $wp_new_user_notification_email['to'],
            wp_specialchars_decode( sprintf( $wp_new_user_notification_email['subject'], $blogname ) ),
            $wp_new_user_notification_email['message'],
            $wp_new_user_notification_email['headers']
        );

        if ( $switched_locale ) {
            restore_previous_locale();
        }
    }
}
