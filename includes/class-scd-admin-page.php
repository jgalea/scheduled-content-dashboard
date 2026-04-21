<?php
/**
 * Full editorial calendar admin page.
 *
 * @package Scheduled_Content_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SCD_Admin_Page {

    const PAGE_SLUG     = 'scd-calendar';
    const AJAX_ACTION   = 'scd_reschedule';
    const NONCE_ACTION  = 'scd_calendar_nonce';

    public static function init() {
        $self = new self();
        add_action( 'admin_menu', array( $self, 'add_menu' ), 5 );
        add_action( 'admin_enqueue_scripts', array( $self, 'enqueue' ) );
        add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $self, 'handle_reschedule' ) );
    }

    public function add_menu() {
        add_menu_page(
            __( 'Scheduled Content', 'scheduled-content-dashboard' ),
            __( 'Scheduled', 'scheduled-content-dashboard' ),
            'edit_posts',
            self::PAGE_SLUG,
            array( $this, 'render_page' ),
            'dashicons-calendar-alt',
            22
        );

        add_submenu_page(
            self::PAGE_SLUG,
            __( 'Editorial Calendar', 'scheduled-content-dashboard' ),
            __( 'Calendar', 'scheduled-content-dashboard' ),
            'edit_posts',
            self::PAGE_SLUG,
            array( $this, 'render_page' )
        );

        add_submenu_page(
            self::PAGE_SLUG,
            __( 'Settings', 'scheduled-content-dashboard' ),
            __( 'Settings', 'scheduled-content-dashboard' ),
            'manage_options',
            'options-general.php?page=' . SCD_Settings::PAGE_SLUG
        );
    }

    public function enqueue( $hook ) {
        if ( 'toplevel_page_' . self::PAGE_SLUG !== $hook ) {
            return;
        }

        wp_enqueue_script( 'jquery-ui-draggable' );
        wp_enqueue_script( 'jquery-ui-droppable' );

        wp_enqueue_script(
            'scd-editorial-calendar',
            plugins_url( 'assets/js/editorial-calendar.js', dirname( __FILE__ ) ),
            array( 'jquery', 'jquery-ui-draggable', 'jquery-ui-droppable' ),
            Scheduled_Content_Dashboard::VERSION,
            true
        );

        wp_localize_script(
            'scd-editorial-calendar',
            'scdCalendar',
            array(
                'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
                'nonce'        => wp_create_nonce( self::NONCE_ACTION ),
                'rescheduling' => __( 'Rescheduling…', 'scheduled-content-dashboard' ),
                'failed'       => __( 'Reschedule failed.', 'scheduled-content-dashboard' ),
                'success'      => __( 'Rescheduled.', 'scheduled-content-dashboard' ),
            )
        );

        wp_add_inline_style( 'common', $this->styles() );
    }

    private function styles() {
        return '
            .scd-cal-toolbar {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin: 16px 0;
            }
            .scd-cal-toolbar h2 { margin: 0; }
            .scd-cal-nav a {
                text-decoration: none;
                padding: 4px 10px;
                background: #fff;
                border: 1px solid #c3c4c7;
                border-radius: 3px;
                margin-left: 4px;
            }
            .scd-cal-nav a:hover { background: #f0f0f1; }
            .scd-cal-grid {
                display: grid;
                grid-template-columns: repeat(7, minmax(0, 1fr));
                grid-auto-rows: 140px;
                gap: 1px;
                background: #c3c4c7;
                border: 1px solid #c3c4c7;
            }
            .scd-cal-dow {
                background: #f6f7f7;
                padding: 8px;
                text-align: center;
                font-size: 12px;
                font-weight: 600;
                color: #50575e;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                grid-row: auto;
                height: auto;
            }
            .scd-cal-grid > .scd-cal-dow { grid-row: 1; height: auto; }
            .scd-cal-day {
                background: #fff;
                height: 140px;
                padding: 6px 8px;
                position: relative;
                font-size: 12px;
                color: #50575e;
                overflow: hidden;
                display: flex;
                flex-direction: column;
                min-width: 0;
            }
            .scd-cal-day-items {
                flex: 1 1 auto;
                overflow: hidden;
                min-height: 0;
            }
            .scd-cal-day-more {
                flex: 0 0 auto;
                font-size: 11px;
                color: #787c82;
                padding-top: 2px;
            }
            .scd-cal-day-more a { color: #2271b1; text-decoration: none; }
            .scd-cal-day--other-month { background: #fafafa; color: #c3c4c7; }
            .scd-cal-day--today { background: #f0f6fc; }
            .scd-cal-day--today .scd-cal-day-number { color: #2271b1; font-weight: 600; }
            .scd-cal-day--missed { background: #fcf0f1; }
            .scd-cal-day-number {
                font-weight: 600;
                font-size: 13px;
                color: #1d2327;
                margin-bottom: 4px;
            }
            .scd-cal-day-over { outline: 3px solid #2271b1; outline-offset: -3px; }
            .scd-cal-item {
                background: #2271b1;
                color: #fff;
                padding: 3px 6px;
                margin-bottom: 2px;
                border-radius: 3px;
                font-size: 11px;
                cursor: move;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
            .scd-cal-item a { color: #fff; text-decoration: none; }
            .scd-cal-item--missed { background: #b32d2e; }
            .scd-cal-item.ui-draggable-dragging { opacity: 0.7; }
            .scd-cal-feedback {
                position: fixed;
                bottom: 20px;
                right: 20px;
                background: #1d2327;
                color: #fff;
                padding: 8px 14px;
                border-radius: 3px;
                font-size: 13px;
                z-index: 9999;
                display: none;
            }
            .scd-cal-feedback--success { background: #00a32a; }
            .scd-cal-feedback--error { background: #b32d2e; }
            .scd-cal-empty-hint { color: #c3c4c7; font-size: 11px; margin-top: 4px; }
        ';
    }

    public function render_page() {
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_die( esc_html__( 'You do not have permission to view this page.', 'scheduled-content-dashboard' ) );
        }

        $month = isset( $_GET['scd_month'] ) ? sanitize_text_field( wp_unslash( $_GET['scd_month'] ) ) : wp_date( 'Y-m' );
        if ( ! preg_match( '/^\d{4}-\d{2}$/', $month ) ) {
            $month = wp_date( 'Y-m' );
        }

        $month_ts      = strtotime( $month . '-01' );
        $year          = (int) wp_date( 'Y', $month_ts );
        $month_num     = (int) wp_date( 'n', $month_ts );
        $days_in       = (int) wp_date( 't', $month_ts );
        $first_weekday = (int) wp_date( 'w', $month_ts );
        $start_of_week = (int) get_option( 'start_of_week', 0 );
        $offset        = ( $first_weekday - $start_of_week + 7 ) % 7;

        $items_by_day = $this->build_items_by_day( $month );
        $today        = wp_date( 'Y-m-d' );
        $prev_month   = wp_date( 'Y-m', strtotime( $month . '-01 -1 month' ) );
        $next_month   = wp_date( 'Y-m', strtotime( $month . '-01 +1 month' ) );
        $this_month   = wp_date( 'Y-m' );
        ?>
        <div class="wrap">
            <div class="scd-cal-toolbar">
                <h1><?php echo esc_html( wp_date( 'F Y', $month_ts ) ); ?></h1>
                <div class="scd-cal-nav">
                    <a href="<?php echo esc_url( add_query_arg( 'scd_month', $prev_month ) ); ?>">&larr; <?php esc_html_e( 'Previous', 'scheduled-content-dashboard' ); ?></a>
                    <a href="<?php echo esc_url( remove_query_arg( array( 'scd_month', 'scd_day' ) ) ); ?>"><?php esc_html_e( 'Today', 'scheduled-content-dashboard' ); ?></a>
                    <a href="<?php echo esc_url( add_query_arg( 'scd_month', $next_month ) ); ?>"><?php esc_html_e( 'Next', 'scheduled-content-dashboard' ); ?> &rarr;</a>
                </div>
            </div>

            <p class="description">
                <?php esc_html_e( 'Drag scheduled posts to a different day to reschedule them. Time of day is preserved.', 'scheduled-content-dashboard' ); ?>
            </p>

            <div class="scd-cal-grid">
                <?php
                for ( $i = 0; $i < 7; $i++ ) {
                    $dow = ( $start_of_week + $i ) % 7;
                    echo '<div class="scd-cal-dow">' . esc_html( $this->dow_name( $dow ) ) . '</div>';
                }

                // Leading blanks.
                for ( $i = 0; $i < $offset; $i++ ) {
                    echo '<div class="scd-cal-day scd-cal-day--other-month"></div>';
                }

                $per_day_cap = 3;
                for ( $day = 1; $day <= $days_in; $day++ ) {
                    $date_key = sprintf( '%04d-%02d-%02d', $year, $month_num, $day );
                    $items    = $items_by_day[ $date_key ] ?? array();
                    $classes  = array( 'scd-cal-day' );
                    if ( $date_key === $today ) {
                        $classes[] = 'scd-cal-day--today';
                    }
                    if ( $this->has_missed( $items ) ) {
                        $classes[] = 'scd-cal-day--missed';
                    }

                    $visible_items  = array_slice( $items, 0, $per_day_cap );
                    $overflow_count = max( 0, count( $items ) - $per_day_cap );
                    ?>
                    <div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" data-date="<?php echo esc_attr( $date_key ); ?>">
                        <div class="scd-cal-day-number"><?php echo (int) $day; ?></div>
                        <div class="scd-cal-day-items">
                            <?php foreach ( $visible_items as $item ) : ?>
                                <div class="scd-cal-item <?php echo $item['is_missed'] ? 'scd-cal-item--missed' : ''; ?>"
                                    data-post-id="<?php echo (int) $item['id']; ?>"
                                    data-time="<?php echo esc_attr( $item['time'] ); ?>"
                                    title="<?php echo esc_attr( $item['time_formatted'] . ' · ' . $item['type_label'] . ' · ' . $item['author'] ); ?>">
                                    <a href="<?php echo esc_url( $item['edit_link'] ); ?>"><?php echo esc_html( $item['title'] ); ?></a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if ( $overflow_count > 0 ) : ?>
                            <div class="scd-cal-day-more">
                                <?php
                                printf(
                                    /* translators: %d: number of additional items on the day. */
                                    esc_html( _n( '+%d more', '+%d more', $overflow_count, 'scheduled-content-dashboard' ) ),
                                    $overflow_count
                                );
                                ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php
                }

                $trailing = ( 7 - ( ( $offset + $days_in ) % 7 ) ) % 7;
                for ( $i = 0; $i < $trailing; $i++ ) {
                    echo '<div class="scd-cal-day scd-cal-day--other-month"></div>';
                }
                ?>
            </div>

            <div id="scd-cal-feedback" class="scd-cal-feedback" role="status" aria-live="polite"></div>
        </div>
        <?php
    }

    private function build_items_by_day( $month ) {
        $start = get_gmt_from_date( $month . '-01 00:00:00' );
        $end   = get_gmt_from_date( wp_date( 'Y-m-t 23:59:59', strtotime( $month . '-01' ) ) );

        $posts = get_posts(
            array(
                'post_type'      => array_keys( get_post_types( array( 'public' => true ) ) ),
                'post_status'    => 'future',
                'posts_per_page' => 500,
                'orderby'        => 'date',
                'order'          => 'ASC',
                'no_found_rows'  => true,
                'date_query'     => array(
                    array(
                        'column'    => 'post_date_gmt',
                        'after'     => $start,
                        'before'    => $end,
                        'inclusive' => true,
                    ),
                ),
            )
        );

        $now_gmt = time();
        $grouped = array();

        foreach ( $posts as $post ) {
            $local_date = wp_date( 'Y-m-d', strtotime( $post->post_date ) );
            $time       = wp_date( 'H:i:s', strtotime( $post->post_date ) );
            $is_missed  = strtotime( $post->post_date_gmt . ' UTC' ) <= $now_gmt;
            $type       = get_post_type_object( $post->post_type );

            if ( ! isset( $grouped[ $local_date ] ) ) {
                $grouped[ $local_date ] = array();
            }

            $grouped[ $local_date ][] = array(
                'id'             => (int) $post->ID,
                'title'          => $post->post_title ? $post->post_title : __( '(no title)', 'scheduled-content-dashboard' ),
                'time'           => $time,
                'time_formatted' => wp_date( get_option( 'time_format' ), strtotime( $post->post_date ) ),
                'edit_link'      => get_edit_post_link( $post->ID, 'raw' ),
                'type_label'     => $type ? $type->labels->singular_name : $post->post_type,
                'author'         => get_the_author_meta( 'display_name', $post->post_author ),
                'is_missed'      => $is_missed,
            );
        }

        return $grouped;
    }

    private function has_missed( $items ) {
        foreach ( $items as $item ) {
            if ( ! empty( $item['is_missed'] ) ) {
                return true;
            }
        }
        return false;
    }

    private function dow_name( $dow ) {
        $names = array(
            __( 'Sunday', 'scheduled-content-dashboard' ),
            __( 'Monday', 'scheduled-content-dashboard' ),
            __( 'Tuesday', 'scheduled-content-dashboard' ),
            __( 'Wednesday', 'scheduled-content-dashboard' ),
            __( 'Thursday', 'scheduled-content-dashboard' ),
            __( 'Friday', 'scheduled-content-dashboard' ),
            __( 'Saturday', 'scheduled-content-dashboard' ),
        );
        return $names[ $dow ] ?? '';
    }

    public function handle_reschedule() {
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );

        $post_id  = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        $new_date = isset( $_POST['new_date'] ) ? sanitize_text_field( wp_unslash( $_POST['new_date'] ) ) : '';
        $time     = isset( $_POST['time'] ) ? sanitize_text_field( wp_unslash( $_POST['time'] ) ) : '';

        if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'scheduled-content-dashboard' ) ), 403 );
        }

        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $new_date ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid date.', 'scheduled-content-dashboard' ) ), 400 );
        }

        if ( ! preg_match( '/^\d{2}:\d{2}(:\d{2})?$/', $time ) ) {
            $time = '09:00:00';
        }
        if ( 5 === strlen( $time ) ) {
            $time .= ':00';
        }

        $local    = $new_date . ' ' . $time;
        $local_ts = strtotime( $local );
        if ( false === $local_ts ) {
            wp_send_json_error( array( 'message' => __( 'Invalid date.', 'scheduled-content-dashboard' ) ), 400 );
        }

        $post = get_post( $post_id );
        if ( ! $post || 'future' !== $post->post_status ) {
            wp_send_json_error( array( 'message' => __( 'Post is not scheduled.', 'scheduled-content-dashboard' ) ), 400 );
        }

        $result = wp_update_post(
            array(
                'ID'            => $post_id,
                'post_date'     => $local,
                'post_date_gmt' => get_gmt_from_date( $local ),
                'post_status'   => 'future',
                'edit_date'     => true,
            ),
            true
        );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ), 500 );
        }

        wp_send_json_success(
            array(
                'id'   => $post_id,
                'date' => $local,
            )
        );
    }
}
