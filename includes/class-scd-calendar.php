<?php
/**
 * Mini calendar view for the dashboard widget.
 *
 * @package Scheduled_Content_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SCD_Calendar {

    /**
     * Render the month calendar grid.
     *
     * @param array  $items   Flat list of items with scheduled_date + 'is_missed' flag + title/edit_link.
     * @param string $month   Month to render in YYYY-MM format.
     * @param string $base_url URL to append month/day params to (for nav).
     * @param string $selected_day Currently selected day (YYYY-MM-DD) or empty.
     */
    public static function render( array $items, $month, $base_url, $selected_day = '' ) {
        $month_ts = strtotime( $month . '-01' );
        if ( false === $month_ts ) {
            $month_ts = strtotime( wp_date( 'Y-m-01' ) );
        }

        $year        = (int) wp_date( 'Y', $month_ts );
        $month_num   = (int) wp_date( 'n', $month_ts );
        $days_in     = (int) wp_date( 't', $month_ts );
        $first_weekday = (int) wp_date( 'w', $month_ts ); // 0 = Sunday.
        $start_of_week = (int) get_option( 'start_of_week', 0 );
        $offset        = ( $first_weekday - $start_of_week + 7 ) % 7;

        $items_by_day = array();
        foreach ( $items as $item ) {
            $day_key = wp_date( 'Y-m-d', strtotime( $item['scheduled_date'] ) );
            if ( ! isset( $items_by_day[ $day_key ] ) ) {
                $items_by_day[ $day_key ] = array();
            }
            $items_by_day[ $day_key ][] = $item;
        }

        $prev_month = wp_date( 'Y-m', strtotime( $month . '-01 -1 month' ) );
        $next_month = wp_date( 'Y-m', strtotime( $month . '-01 +1 month' ) );
        $today      = wp_date( 'Y-m-d' );

        ?>
        <div class="scd-calendar">
            <div class="scd-calendar-nav">
                <a class="scd-calendar-nav-link" href="<?php echo esc_url( add_query_arg( 'scd_month', $prev_month, $base_url ) ); ?>#scheduled_content_widget">&lsaquo; <?php esc_html_e( 'Prev', 'scheduled-content-dashboard' ); ?></a>
                <strong><?php echo esc_html( wp_date( 'F Y', $month_ts ) ); ?></strong>
                <a class="scd-calendar-nav-link" href="<?php echo esc_url( add_query_arg( 'scd_month', $next_month, $base_url ) ); ?>#scheduled_content_widget"><?php esc_html_e( 'Next', 'scheduled-content-dashboard' ); ?> &rsaquo;</a>
            </div>
            <table class="scd-calendar-grid">
                <thead>
                    <tr>
                        <?php
                        for ( $i = 0; $i < 7; $i++ ) {
                            $dow = ( $start_of_week + $i ) % 7;
                            echo '<th>' . esc_html( self::dow_abbr( $dow ) ) . '</th>';
                        }
                        ?>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                    <?php
                    for ( $i = 0; $i < $offset; $i++ ) {
                        echo '<td class="scd-calendar-day scd-calendar-day--empty"></td>';
                    }

                    for ( $day = 1; $day <= $days_in; $day++ ) {
                        $date_key = sprintf( '%04d-%02d-%02d', $year, $month_num, $day );
                        $has      = ! empty( $items_by_day[ $date_key ] );
                        $missed   = $has ? self::has_missed( $items_by_day[ $date_key ] ) : false;
                        $is_today = ( $date_key === $today );
                        $is_sel   = ( $date_key === $selected_day );

                        $classes = array( 'scd-calendar-day' );
                        if ( $has ) {
                            $classes[] = 'scd-calendar-day--has-items';
                        }
                        if ( $missed ) {
                            $classes[] = 'scd-calendar-day--missed';
                        }
                        if ( $is_today ) {
                            $classes[] = 'scd-calendar-day--today';
                        }
                        if ( $is_sel ) {
                            $classes[] = 'scd-calendar-day--selected';
                        }

                        echo '<td class="' . esc_attr( implode( ' ', $classes ) ) . '">';
                        if ( $has ) {
                            $url = add_query_arg(
                                array(
                                    'scd_month' => $month,
                                    'scd_day'   => $date_key,
                                ),
                                $base_url
                            );
                            printf(
                                '<a href="%1$s#scheduled_content_widget" class="scd-calendar-day-link" title="%4$s">%2$d<span class="scd-calendar-dot">%3$d</span></a>',
                                esc_url( $url ),
                                (int) $day,
                                count( $items_by_day[ $date_key ] ),
                                esc_attr(
                                    sprintf(
                                        /* translators: %d: number of items */
                                        _n( '%d scheduled', '%d scheduled', count( $items_by_day[ $date_key ] ), 'scheduled-content-dashboard' ),
                                        count( $items_by_day[ $date_key ] )
                                    )
                                )
                            );
                        } else {
                            echo (int) $day;
                        }
                        echo '</td>';

                        if ( 0 === ( ( $offset + $day ) % 7 ) && $day !== $days_in ) {
                            echo '</tr><tr>';
                        }
                    }

                    $trailing = ( 7 - ( ( $offset + $days_in ) % 7 ) ) % 7;
                    for ( $i = 0; $i < $trailing; $i++ ) {
                        echo '<td class="scd-calendar-day scd-calendar-day--empty"></td>';
                    }
                    ?>
                    </tr>
                </tbody>
            </table>
            <?php if ( $selected_day && isset( $items_by_day[ $selected_day ] ) ) : ?>
                <div class="scd-calendar-day-detail">
                    <h5><?php echo esc_html( wp_date( 'l, F j', strtotime( $selected_day ) ) ); ?></h5>
                    <ul>
                        <?php foreach ( $items_by_day[ $selected_day ] as $item ) : ?>
                            <li>
                                <a href="<?php echo esc_url( $item['edit_link'] ); ?>"><?php echo esc_html( $item['title'] ? $item['title'] : __( '(no title)', 'scheduled-content-dashboard' ) ); ?></a>
                                <span class="scd-calendar-time"><?php echo esc_html( $item['time_formatted'] ); ?></span>
                                <?php if ( ! empty( $item['is_missed'] ) ) : ?>
                                    <span class="scd-calendar-missed-badge"><?php esc_html_e( 'Missed', 'scheduled-content-dashboard' ); ?></span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function has_missed( $items ) {
        foreach ( $items as $item ) {
            if ( ! empty( $item['is_missed'] ) ) {
                return true;
            }
        }
        return false;
    }

    private static function dow_abbr( $dow ) {
        $names = array(
            __( 'Sun', 'scheduled-content-dashboard' ),
            __( 'Mon', 'scheduled-content-dashboard' ),
            __( 'Tue', 'scheduled-content-dashboard' ),
            __( 'Wed', 'scheduled-content-dashboard' ),
            __( 'Thu', 'scheduled-content-dashboard' ),
            __( 'Fri', 'scheduled-content-dashboard' ),
            __( 'Sat', 'scheduled-content-dashboard' ),
        );
        return $names[ $dow ] ?? '';
    }
}
