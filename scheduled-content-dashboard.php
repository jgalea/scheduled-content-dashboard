<?php
/**
 * Scheduled Content Dashboard
 *
 * @package           Scheduled_Content_Dashboard
 * @author            jeangalea
 * @copyright         2026 Jean Galea
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Scheduled Content Dashboard
 * Plugin URI:        https://wordpress.org/plugins/scheduled-content-dashboard/
 * Description:       Displays all scheduled posts, pages, and custom post types on the WordPress dashboard, with missed-schedule detection, auto-fix, admin bar counter, and quick edit links.
 * Version:           1.1.0
 * Requires at least: 5.0
 * Requires PHP:      7.4
 * Author:            jeangalea
 * Author URI:        https://jeangalea.com
 * Text Domain:       scheduled-content-dashboard
 * Domain Path:       /languages
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Scheduled_Content_Dashboard {

    const VERSION                = '1.1.0';
    const MINE_ONLY_META_KEY     = '_scd_mine_only';
    const AUTO_FIX_TRANSIENT     = 'scd_last_auto_fix';
    const AUTO_FIX_INTERVAL      = 600; // 10 minutes.
    const PUBLISH_NOW_ACTION     = 'scd_publish_now';
    const TOGGLE_MINE_ACTION     = 'scd_toggle_mine';

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'wp_dashboard_setup', array( $this, 'register_dashboard_widget' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
        add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_counter' ), 90 );
        add_action( 'admin_post_' . self::PUBLISH_NOW_ACTION, array( $this, 'handle_publish_now' ) );
        add_action( 'admin_post_' . self::TOGGLE_MINE_ACTION, array( $this, 'handle_mine_toggle' ) );
        add_action( 'admin_init', array( $this, 'maybe_auto_fix_missed' ) );
    }

    public function register_dashboard_widget() {
        wp_add_dashboard_widget(
            'scheduled_content_widget',
            __( 'Scheduled Content', 'scheduled-content-dashboard' ),
            array( $this, 'render_widget' )
        );
    }

    public function enqueue_styles( $hook ) {
        if ( 'index.php' !== $hook ) {
            return;
        }

        wp_add_inline_style( 'dashboard', $this->get_widget_styles() );
    }

    private function get_widget_styles() {
        return '
            .scheduled-content-list { margin: 0; padding: 0; }
            .scheduled-content-header {
                display: flex;
                justify-content: flex-end;
                margin-bottom: 8px;
            }
            .scheduled-content-header form { margin: 0; }
            .scheduled-content-toggle {
                background: none;
                border: none;
                color: #2271b1;
                cursor: pointer;
                font-size: 12px;
                padding: 2px 6px;
                text-decoration: underline;
            }
            .scheduled-content-toggle:hover { color: #135e96; }
            .scheduled-content-item {
                display: flex;
                align-items: flex-start;
                padding: 12px 0;
                border-bottom: 1px solid #f0f0f1;
            }
            .scheduled-content-item:last-child { border-bottom: none; }
            .scheduled-content-date {
                flex-shrink: 0;
                width: 90px;
                padding-right: 12px;
                color: #50575e;
                font-size: 12px;
                line-height: 1.4;
            }
            .scheduled-content-date .date { font-weight: 600; color: #1d2327; }
            .scheduled-content-date .time { color: #787c82; }
            .scheduled-content-details { flex-grow: 1; min-width: 0; }
            .scheduled-content-title { margin: 0 0 4px 0; font-size: 13px; line-height: 1.4; }
            .scheduled-content-title a { text-decoration: none; color: #2271b1; }
            .scheduled-content-title a:hover { color: #135e96; text-decoration: underline; }
            .scheduled-content-meta { font-size: 12px; color: #787c82; }
            .scheduled-content-type {
                display: inline-block;
                padding: 2px 6px;
                background: #f0f0f1;
                border-radius: 3px;
                font-size: 11px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            .scheduled-content-empty {
                padding: 20px;
                text-align: center;
                color: #787c82;
            }
            .scheduled-content-group { margin-bottom: 15px; }
            .scheduled-content-group:last-child { margin-bottom: 0; }
            .scheduled-content-group-title {
                font-size: 11px;
                font-weight: 600;
                text-transform: uppercase;
                color: #50575e;
                padding: 8px 0;
                border-bottom: 2px solid #2271b1;
                margin-bottom: 0;
            }
            .scheduled-content-group--missed .scheduled-content-group-title {
                color: #b32d2e;
                border-bottom-color: #b32d2e;
            }
            .scheduled-content-item--missed {
                background: #fcf0f1;
                margin: 0 -12px;
                padding-left: 12px;
                padding-right: 12px;
            }
            .scheduled-content-missed-date { color: #b32d2e; font-weight: 600; }
            .scheduled-content-publish-now {
                margin-top: 4px;
                display: inline-block;
            }
            .scheduled-content-publish-now button {
                background: #b32d2e;
                color: #fff;
                border: none;
                border-radius: 3px;
                padding: 3px 10px;
                font-size: 11px;
                cursor: pointer;
            }
            .scheduled-content-publish-now button:hover { background: #8a2324; }
            .scheduled-content-notice {
                background: #fcf0f1;
                border-left: 4px solid #b32d2e;
                padding: 8px 12px;
                margin-bottom: 12px;
                font-size: 12px;
            }
            .scheduled-content-notice--success {
                background: #edfaef;
                border-left-color: #00a32a;
            }
            #wp-admin-bar-scheduled-content .ab-icon::before {
                content: "\f145";
                top: 3px;
            }
            #wp-admin-bar-scheduled-content.scd-has-missed .ab-label { color: #ff8787; }
        ';
    }

    private function is_mine_only() {
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return false;
        }
        return '1' === get_user_meta( $user_id, self::MINE_ONLY_META_KEY, true );
    }

    private function build_query_args( $args_override = array() ) {
        $post_types = array_keys( get_post_types( array( 'public' => true ), 'objects' ) );

        $args = array(
            'post_type'      => $post_types,
            'post_status'    => 'future',
            'posts_per_page' => 50,
            'orderby'        => 'date',
            'order'          => 'ASC',
            'no_found_rows'  => true,
        );

        if ( $this->is_mine_only() ) {
            $args['author'] = get_current_user_id();
        }

        $args = array_merge( $args, $args_override );

        /**
         * Filters the query arguments for retrieving scheduled content.
         *
         * @since 1.0.0
         * @param array $args WP_Query arguments.
         */
        return apply_filters( 'scheduled_content_dashboard_query_args', $args );
    }

    private function get_scheduled_content() {
        $query     = new WP_Query( $this->build_query_args() );
        $scheduled = array();
        $now_gmt   = time();

        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $post_id       = get_the_ID();
                $post_type_obj = get_post_type_object( get_post_type() );
                $post_date_gmt = (int) get_post_time( 'U', true );

                // Skip missed posts — they are rendered in a separate group.
                if ( $post_date_gmt <= $now_gmt ) {
                    continue;
                }

                $scheduled[] = array(
                    'id'              => $post_id,
                    'title'           => get_the_title(),
                    'edit_link'       => get_edit_post_link( $post_id, 'raw' ),
                    'scheduled_date'  => get_the_date( 'Y-m-d H:i:s' ),
                    'date_formatted'  => get_the_date( 'M j, Y' ),
                    'time_formatted'  => get_the_date( 'g:i a' ),
                    'post_type'       => get_post_type(),
                    'post_type_label' => $post_type_obj ? $post_type_obj->labels->singular_name : get_post_type(),
                    'author'          => get_the_author(),
                );
            }
            wp_reset_postdata();
        }

        return $scheduled;
    }

    private function get_missed_content() {
        $args  = $this->build_query_args(
            array(
                'date_query' => array(
                    array(
                        'column' => 'post_date_gmt',
                        'before' => gmdate( 'Y-m-d H:i:s' ),
                    ),
                ),
            )
        );
        $query = new WP_Query( $args );
        $items = array();

        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $post_id       = get_the_ID();
                $post_type_obj = get_post_type_object( get_post_type() );

                $items[] = array(
                    'id'              => $post_id,
                    'title'           => get_the_title(),
                    'edit_link'       => get_edit_post_link( $post_id, 'raw' ),
                    'scheduled_date'  => get_the_date( 'Y-m-d H:i:s' ),
                    'date_formatted'  => get_the_date( 'M j, Y' ),
                    'time_formatted'  => get_the_date( 'g:i a' ),
                    'post_type'       => get_post_type(),
                    'post_type_label' => $post_type_obj ? $post_type_obj->labels->singular_name : get_post_type(),
                    'author'          => get_the_author(),
                    'publish_nonce'   => wp_create_nonce( self::PUBLISH_NOW_ACTION . '_' . $post_id ),
                );
            }
            wp_reset_postdata();
        }

        return $items;
    }

    private function get_counts() {
        $user_scope_args = $this->is_mine_only() ? array( 'author' => get_current_user_id() ) : array();

        $scheduled = new WP_Query(
            array_merge(
                array(
                    'post_type'              => array_keys( get_post_types( array( 'public' => true ) ) ),
                    'post_status'            => 'future',
                    'posts_per_page'         => 1,
                    'fields'                 => 'ids',
                    'no_found_rows'          => false,
                    'update_post_meta_cache' => false,
                    'update_post_term_cache' => false,
                ),
                $user_scope_args
            )
        );

        $missed = new WP_Query(
            array_merge(
                array(
                    'post_type'              => array_keys( get_post_types( array( 'public' => true ) ) ),
                    'post_status'            => 'future',
                    'posts_per_page'         => 1,
                    'fields'                 => 'ids',
                    'no_found_rows'          => false,
                    'update_post_meta_cache' => false,
                    'update_post_term_cache' => false,
                    'date_query'             => array(
                        array(
                            'column' => 'post_date',
                            'before' => current_time( 'mysql' ),
                        ),
                    ),
                ),
                $user_scope_args
            )
        );

        return array(
            'total'  => (int) $scheduled->found_posts,
            'missed' => (int) $missed->found_posts,
        );
    }

    private function group_content_by_time( $content ) {
        $groups = array(
            'today'     => array( 'label' => __( 'Today', 'scheduled-content-dashboard' ), 'items' => array() ),
            'tomorrow'  => array( 'label' => __( 'Tomorrow', 'scheduled-content-dashboard' ), 'items' => array() ),
            'this_week' => array( 'label' => __( 'This Week', 'scheduled-content-dashboard' ), 'items' => array() ),
            'next_week' => array( 'label' => __( 'Next Week', 'scheduled-content-dashboard' ), 'items' => array() ),
            'later'     => array( 'label' => __( 'Later', 'scheduled-content-dashboard' ), 'items' => array() ),
        );

        $today            = wp_date( 'Y-m-d' );
        $tomorrow         = wp_date( 'Y-m-d', strtotime( '+1 day' ) );
        $end_of_week      = wp_date( 'Y-m-d', strtotime( 'sunday this week' ) );
        $end_of_next_week = wp_date( 'Y-m-d', strtotime( 'sunday next week' ) );

        foreach ( $content as $item ) {
            $item_date = wp_date( 'Y-m-d', strtotime( $item['scheduled_date'] ) );

            if ( $item_date === $today ) {
                $groups['today']['items'][] = $item;
            } elseif ( $item_date === $tomorrow ) {
                $groups['tomorrow']['items'][] = $item;
            } elseif ( $item_date <= $end_of_week ) {
                $groups['this_week']['items'][] = $item;
            } elseif ( $item_date <= $end_of_next_week ) {
                $groups['next_week']['items'][] = $item;
            } else {
                $groups['later']['items'][] = $item;
            }
        }

        return array_filter(
            $groups,
            function ( $group ) {
                return ! empty( $group['items'] );
            }
        );
    }

    public function render_widget() {
        $this->maybe_render_notice();
        $this->render_header();

        $missed    = $this->get_missed_content();
        $scheduled = $this->get_scheduled_content();

        if ( empty( $missed ) && empty( $scheduled ) ) {
            echo '<div class="scheduled-content-empty">';
            echo '<p>' . esc_html__( 'No scheduled content found.', 'scheduled-content-dashboard' ) . '</p>';
            echo '</div>';
            return;
        }

        echo '<div class="scheduled-content-list">';

        if ( ! empty( $missed ) ) {
            $this->render_missed_group( $missed );
        }

        foreach ( $this->group_content_by_time( $scheduled ) as $group ) {
            echo '<div class="scheduled-content-group">';
            echo '<h4 class="scheduled-content-group-title">' . esc_html( $group['label'] ) . '</h4>';
            foreach ( $group['items'] as $item ) {
                $this->render_item( $item );
            }
            echo '</div>';
        }

        echo '</div>';
    }

    private function render_header() {
        $mine_only  = $this->is_mine_only();
        $toggle_url = admin_url( 'admin-post.php' );
        $label      = $mine_only
            ? __( 'Show all authors', 'scheduled-content-dashboard' )
            : __( 'Mine only', 'scheduled-content-dashboard' );
        ?>
        <div class="scheduled-content-header">
            <form method="post" action="<?php echo esc_url( $toggle_url ); ?>">
                <?php wp_nonce_field( self::TOGGLE_MINE_ACTION ); ?>
                <input type="hidden" name="action" value="<?php echo esc_attr( self::TOGGLE_MINE_ACTION ); ?>">
                <button type="submit" class="scheduled-content-toggle">
                    <?php echo esc_html( $label ); ?>
                </button>
            </form>
        </div>
        <?php
    }

    private function maybe_render_notice() {
        if ( empty( $_GET['scd_notice'] ) ) {
            return;
        }

        $notices = array(
            'published' => array(
                'class'   => 'scheduled-content-notice--success',
                'message' => __( 'Post published.', 'scheduled-content-dashboard' ),
            ),
            'publish_failed' => array(
                'class'   => '',
                'message' => __( 'Could not publish post.', 'scheduled-content-dashboard' ),
            ),
        );

        $key = sanitize_key( wp_unslash( $_GET['scd_notice'] ) );
        if ( ! isset( $notices[ $key ] ) ) {
            return;
        }

        printf(
            '<div class="scheduled-content-notice %1$s">%2$s</div>',
            esc_attr( $notices[ $key ]['class'] ),
            esc_html( $notices[ $key ]['message'] )
        );
    }

    private function render_missed_group( $items ) {
        ?>
        <div class="scheduled-content-group scheduled-content-group--missed">
            <h4 class="scheduled-content-group-title">
                <?php
                printf(
                    /* translators: %d: Number of missed scheduled posts. */
                    esc_html( _n( 'Missed schedule (%d)', 'Missed schedule (%d)', count( $items ), 'scheduled-content-dashboard' ) ),
                    count( $items )
                );
                ?>
            </h4>
            <?php foreach ( $items as $item ) : ?>
                <?php $this->render_missed_item( $item ); ?>
            <?php endforeach; ?>
        </div>
        <?php
    }

    private function render_missed_item( $item ) {
        $title         = $item['title'] ? $item['title'] : __( '(no title)', 'scheduled-content-dashboard' );
        $can_publish   = current_user_can( 'edit_post', $item['id'] );
        $action_url    = admin_url( 'admin-post.php' );
        ?>
        <div class="scheduled-content-item scheduled-content-item--missed">
            <div class="scheduled-content-date scheduled-content-missed-date">
                <div class="date"><?php echo esc_html( $item['date_formatted'] ); ?></div>
                <div class="time"><?php echo esc_html( $item['time_formatted'] ); ?></div>
            </div>
            <div class="scheduled-content-details">
                <h5 class="scheduled-content-title">
                    <a href="<?php echo esc_url( $item['edit_link'] ); ?>">
                        <?php echo esc_html( $title ); ?>
                    </a>
                </h5>
                <div class="scheduled-content-meta">
                    <span class="scheduled-content-type"><?php echo esc_html( $item['post_type_label'] ); ?></span>
                    <?php
                    printf(
                        /* translators: %s: Author display name. */
                        esc_html__( ' — by %s', 'scheduled-content-dashboard' ),
                        esc_html( $item['author'] )
                    );
                    ?>
                </div>
                <?php if ( $can_publish ) : ?>
                    <form method="post" action="<?php echo esc_url( $action_url ); ?>" class="scheduled-content-publish-now">
                        <input type="hidden" name="action" value="<?php echo esc_attr( self::PUBLISH_NOW_ACTION ); ?>">
                        <input type="hidden" name="post_id" value="<?php echo esc_attr( $item['id'] ); ?>">
                        <input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $item['publish_nonce'] ); ?>">
                        <button type="submit">
                            <?php esc_html_e( 'Publish now', 'scheduled-content-dashboard' ); ?>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    private function render_item( $item ) {
        $title = $item['title'] ? $item['title'] : __( '(no title)', 'scheduled-content-dashboard' );
        ?>
        <div class="scheduled-content-item">
            <div class="scheduled-content-date">
                <div class="date"><?php echo esc_html( $item['date_formatted'] ); ?></div>
                <div class="time"><?php echo esc_html( $item['time_formatted'] ); ?></div>
            </div>
            <div class="scheduled-content-details">
                <h5 class="scheduled-content-title">
                    <a href="<?php echo esc_url( $item['edit_link'] ); ?>">
                        <?php echo esc_html( $title ); ?>
                    </a>
                </h5>
                <div class="scheduled-content-meta">
                    <span class="scheduled-content-type"><?php echo esc_html( $item['post_type_label'] ); ?></span>
                    <?php
                    printf(
                        /* translators: %s: Author display name. */
                        esc_html__( ' — by %s', 'scheduled-content-dashboard' ),
                        esc_html( $item['author'] )
                    );
                    ?>
                </div>
            </div>
        </div>
        <?php
    }

    public function add_admin_bar_counter( $wp_admin_bar ) {
        if ( ! is_admin_bar_showing() || ! current_user_can( 'edit_posts' ) ) {
            return;
        }

        $counts = $this->get_counts();
        if ( 0 === $counts['total'] ) {
            return;
        }

        $label = sprintf(
            /* translators: %d: Number of scheduled posts. */
            _n( '%d scheduled', '%d scheduled', $counts['total'], 'scheduled-content-dashboard' ),
            $counts['total']
        );

        if ( $counts['missed'] > 0 ) {
            $label .= ' ' . sprintf(
                /* translators: %d: Number of missed scheduled posts. */
                _n( '(%d missed)', '(%d missed)', $counts['missed'], 'scheduled-content-dashboard' ),
                $counts['missed']
            );
        }

        $wp_admin_bar->add_node(
            array(
                'id'    => 'scheduled-content',
                'title' => '<span class="ab-icon" aria-hidden="true"></span><span class="ab-label">' . esc_html( $label ) . '</span>',
                'href'  => admin_url( 'index.php#scheduled_content_widget' ),
                'meta'  => array(
                    'class' => $counts['missed'] > 0 ? 'scd-has-missed' : '',
                    'title' => __( 'Scheduled Content', 'scheduled-content-dashboard' ),
                ),
            )
        );
    }

    public function handle_publish_now() {
        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        check_admin_referer( self::PUBLISH_NOW_ACTION . '_' . $post_id );

        $redirect = admin_url( 'index.php' );

        if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
            wp_safe_redirect( add_query_arg( 'scd_notice', 'publish_failed', $redirect ) );
            exit;
        }

        $post = get_post( $post_id );
        if ( ! $post || 'future' !== $post->post_status ) {
            wp_safe_redirect( add_query_arg( 'scd_notice', 'publish_failed', $redirect ) );
            exit;
        }

        $type = get_post_type_object( $post->post_type );
        if ( ! $type || ! current_user_can( $type->cap->publish_posts ) ) {
            wp_safe_redirect( add_query_arg( 'scd_notice', 'publish_failed', $redirect ) );
            exit;
        }

        wp_publish_post( $post_id );

        wp_safe_redirect( add_query_arg( 'scd_notice', 'published', $redirect ) );
        exit;
    }

    public function handle_mine_toggle() {
        check_admin_referer( self::TOGGLE_MINE_ACTION );

        $user_id = get_current_user_id();
        if ( $user_id ) {
            $current = '1' === get_user_meta( $user_id, self::MINE_ONLY_META_KEY, true );
            update_user_meta( $user_id, self::MINE_ONLY_META_KEY, $current ? '0' : '1' );
        }

        $redirect = wp_get_referer() ? wp_get_referer() : admin_url( 'index.php' );
        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * Auto-fix missed scheduled posts by publishing them.
     *
     * Disabled by setting the `scheduled_content_dashboard_auto_fix_missed` filter
     * to false. Runs at most once per AUTO_FIX_INTERVAL seconds.
     */
    public function maybe_auto_fix_missed() {
        /**
         * Filters whether to auto-publish missed scheduled posts.
         *
         * @since 1.1.0
         * @param bool $enabled Whether auto-fix is enabled. Default true.
         */
        if ( ! apply_filters( 'scheduled_content_dashboard_auto_fix_missed', true ) ) {
            return;
        }

        if ( get_transient( self::AUTO_FIX_TRANSIENT ) ) {
            return;
        }

        set_transient( self::AUTO_FIX_TRANSIENT, time(), self::AUTO_FIX_INTERVAL );

        $query = new WP_Query(
            array(
                'post_type'              => array_keys( get_post_types( array( 'public' => true ) ) ),
                'post_status'            => 'future',
                'posts_per_page'         => 20,
                'fields'                 => 'ids',
                'no_found_rows'          => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
                'date_query'             => array(
                    array(
                        'column' => 'post_date_gmt',
                        'before' => gmdate( 'Y-m-d H:i:s' ),
                    ),
                ),
            )
        );

        if ( empty( $query->posts ) ) {
            return;
        }

        foreach ( $query->posts as $post_id ) {
            wp_publish_post( $post_id );
        }
    }
}

add_action(
    'plugins_loaded',
    function () {
        Scheduled_Content_Dashboard::get_instance();
    },
    0
);
