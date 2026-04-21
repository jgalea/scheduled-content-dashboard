<?php
/**
 * Email digest of scheduled content.
 *
 * @package Scheduled_Content_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SCD_Digest {

    const HOOK = 'scd_send_digest';

    public static function init() {
        $self = new self();
        add_action( self::HOOK, array( $self, 'send' ) );
        add_action( 'init', array( $self, 'ensure_scheduled' ) );
    }

    public function ensure_scheduled() {
        $enabled   = (int) SCD_Settings::get( 'digest_enabled', 0 ) === 1;
        $frequency = (string) SCD_Settings::get( 'digest_frequency', 'weekly' );

        if ( $enabled ) {
            $scheduled = wp_next_scheduled( self::HOOK );
            if ( ! $scheduled ) {
                self::reschedule( true, $frequency );
            }
        } elseif ( wp_next_scheduled( self::HOOK ) ) {
            self::reschedule( false, $frequency );
        }
    }

    public static function reschedule( $enabled, $frequency ) {
        wp_clear_scheduled_hook( self::HOOK );
        if ( $enabled ) {
            $interval = 'daily' === $frequency ? 'daily' : 'weekly';
            wp_schedule_event( self::next_run_timestamp(), $interval, self::HOOK );
        }
    }

    private static function next_run_timestamp() {
        $tz = wp_timezone();
        $now = new DateTime( 'now', $tz );
        $nine_am = new DateTime( 'today 09:00', $tz );
        if ( $now > $nine_am ) {
            $nine_am->modify( '+1 day' );
        }
        return $nine_am->getTimestamp();
    }

    public function send() {
        $recipients = $this->get_recipients();
        if ( empty( $recipients ) ) {
            return;
        }

        $frequency = (string) SCD_Settings::get( 'digest_frequency', 'weekly' );
        $window    = 'daily' === $frequency ? '+1 day' : '+7 days';

        $upcoming = $this->get_upcoming( $window );
        $missed   = $this->get_missed();

        if ( empty( $upcoming ) && empty( $missed ) ) {
            return;
        }

        $site_name = get_bloginfo( 'name' );
        $subject   = 'daily' === $frequency
            ? sprintf( __( '[%s] Scheduled content for today', 'scheduled-content-dashboard' ), $site_name )
            : sprintf( __( '[%s] Scheduled content for the week', 'scheduled-content-dashboard' ), $site_name );

        $body = $this->render_body( $upcoming, $missed );

        wp_mail(
            $recipients,
            $subject,
            $body,
            array( 'Content-Type: text/html; charset=UTF-8' )
        );
    }

    private function get_recipients() {
        $raw = (string) SCD_Settings::get( 'digest_recipients', '' );
        if ( '' === $raw ) {
            return array( get_option( 'admin_email' ) );
        }
        $emails = array();
        foreach ( preg_split( '/[,\n]+/', $raw ) as $email ) {
            $email = trim( $email );
            if ( is_email( $email ) ) {
                $emails[] = $email;
            }
        }
        return $emails;
    }

    private function get_upcoming( $window ) {
        $end = gmdate( 'Y-m-d H:i:s', strtotime( $window ) );

        $query = new WP_Query(
            array(
                'post_type'              => array_keys( get_post_types( array( 'public' => true ) ) ),
                'post_status'            => 'future',
                'posts_per_page'         => 100,
                'orderby'                => 'date',
                'order'                  => 'ASC',
                'no_found_rows'          => true,
                'update_post_term_cache' => false,
                'date_query'             => array(
                    array(
                        'column'    => 'post_date_gmt',
                        'after'     => gmdate( 'Y-m-d H:i:s' ),
                        'before'    => $end,
                        'inclusive' => true,
                    ),
                ),
            )
        );

        return $query->posts;
    }

    private function get_missed() {
        $query = new WP_Query(
            array(
                'post_type'              => array_keys( get_post_types( array( 'public' => true ) ) ),
                'post_status'            => 'future',
                'posts_per_page'         => 50,
                'orderby'                => 'date',
                'order'                  => 'ASC',
                'no_found_rows'          => true,
                'update_post_term_cache' => false,
                'date_query'             => array(
                    array(
                        'column' => 'post_date_gmt',
                        'before' => gmdate( 'Y-m-d H:i:s' ),
                    ),
                ),
            )
        );
        return $query->posts;
    }

    private function render_body( $upcoming, $missed ) {
        $site_name = get_bloginfo( 'name' );
        $site_url  = get_site_url();

        ob_start();
        ?>
        <div style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;max-width:600px;margin:0 auto;color:#1d2327;">
            <h2 style="color:#1d2327;border-bottom:2px solid #2271b1;padding-bottom:8px;">
                <?php echo esc_html( $site_name ); ?> — <?php esc_html_e( 'Scheduled Content', 'scheduled-content-dashboard' ); ?>
            </h2>

            <?php if ( ! empty( $missed ) ) : ?>
                <h3 style="color:#b32d2e;margin-top:24px;">
                    <?php
                    printf(
                        /* translators: %d: number of missed posts */
                        esc_html( _n( '%d missed post', '%d missed posts', count( $missed ), 'scheduled-content-dashboard' ) ),
                        count( $missed )
                    );
                    ?>
                </h3>
                <ul style="padding-left:18px;">
                    <?php foreach ( $missed as $post ) : ?>
                        <li style="margin-bottom:6px;">
                            <a href="<?php echo esc_url( get_edit_post_link( $post->ID, 'raw' ) ); ?>"><?php echo esc_html( $post->post_title ? $post->post_title : __( '(no title)', 'scheduled-content-dashboard' ) ); ?></a>
                            — <?php echo esc_html( get_the_date( '', $post ) . ' ' . get_the_time( '', $post ) ); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php if ( ! empty( $upcoming ) ) : ?>
                <h3 style="margin-top:24px;">
                    <?php
                    printf(
                        /* translators: %d: number of upcoming posts */
                        esc_html( _n( '%d scheduled post', '%d scheduled posts', count( $upcoming ), 'scheduled-content-dashboard' ) ),
                        count( $upcoming )
                    );
                    ?>
                </h3>
                <ul style="padding-left:18px;">
                    <?php foreach ( $upcoming as $post ) : ?>
                        <li style="margin-bottom:6px;">
                            <a href="<?php echo esc_url( get_edit_post_link( $post->ID, 'raw' ) ); ?>"><?php echo esc_html( $post->post_title ? $post->post_title : __( '(no title)', 'scheduled-content-dashboard' ) ); ?></a>
                            — <?php echo esc_html( get_the_date( '', $post ) . ' ' . get_the_time( '', $post ) ); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <p style="color:#787c82;font-size:12px;margin-top:32px;">
                <?php
                printf(
                    /* translators: %s: site URL */
                    esc_html__( 'Sent by Scheduled Content Dashboard on %s', 'scheduled-content-dashboard' ),
                    esc_html( $site_url )
                );
                ?>
            </p>
        </div>
        <?php
        return ob_get_clean();
    }
}
