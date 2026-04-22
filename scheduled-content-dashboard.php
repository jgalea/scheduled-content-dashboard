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
 * Description:       Editorial calendar with drag-and-drop rescheduling, dashboard widget, missed-schedule auto-fix, admin bar counter, REST API, and optional email digest.
 * Version:           2.0.6
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

require_once plugin_dir_path( __FILE__ ) . 'includes/class-scd-settings.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-scd-calendar.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-scd-digest.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-scd-rest.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-scd-admin-page.php';

class Scheduled_Content_Dashboard {

    const VERSION             = '2.0.6';
    const MINE_ONLY_META_KEY  = '_scd_mine_only';
    const VIEW_META_KEY       = '_scd_view';
    const AUTO_FIX_TRANSIENT  = 'scd_last_auto_fix';
    const AUTO_FIX_INTERVAL   = 600;
    const PUBLISH_NOW_ACTION  = 'scd_publish_now';
    const TOGGLE_MINE_ACTION  = 'scd_toggle_mine';
    const TOGGLE_VIEW_ACTION  = 'scd_toggle_view';

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        SCD_Settings::init();
        SCD_Digest::init();
        SCD_Rest::init();
        SCD_Admin_Page::init();

        register_deactivation_hook( __FILE__, array( __CLASS__, 'deactivate' ) );

        add_action( 'init', array( $this, 'load_textdomain' ) );
        add_action( 'wp_dashboard_setup', array( $this, 'register_dashboard_widget' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
        add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_counter' ), 90 );
        add_action( 'admin_post_' . self::PUBLISH_NOW_ACTION, array( $this, 'handle_publish_now' ) );
        add_action( 'admin_post_' . self::TOGGLE_MINE_ACTION, array( $this, 'handle_mine_toggle' ) );
        add_action( 'admin_post_' . self::TOGGLE_VIEW_ACTION, array( $this, 'handle_view_toggle' ) );
        add_action( 'admin_init', array( $this, 'maybe_auto_fix_missed' ) );
    }

    public function register_dashboard_widget() {
        wp_add_dashboard_widget(
            'scheduled_content_widget',
            __( 'Scheduled Content', 'scheduled-content-dashboard' ),
            array( $this, 'render_widget' )
        );
    }

    public function load_textdomain() {
        load_plugin_textdomain(
            'scheduled-content-dashboard',
            false,
            dirname( plugin_basename( __FILE__ ) ) . '/languages'
        );
    }

    public static function deactivate() {
        wp_clear_scheduled_hook( SCD_Digest::HOOK );
    }

    public function enqueue_styles( $hook ) {
        if ( 'index.php' !== $hook && 'settings_page_' . SCD_Settings::PAGE_SLUG !== $hook ) {
            return;
        }
        wp_add_inline_style( 'dashboard', $this->get_widget_styles() );
    }

    private function get_widget_styles() {
        return '
            .scheduled-content-list { margin: 0; padding: 0; }
            .scheduled-content-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 10px;
                flex-wrap: wrap;
                gap: 8px;
                font-size: 12px;
            }
            .scheduled-content-header form { margin: 0; display: inline; }
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
            .scheduled-content-toggle--active { font-weight: 600; text-decoration: none; }
            .scd-view-switch { display: inline-flex; gap: 4px; }
            .scd-view-switch .button-link { color: #50575e; }
            .scd-filters summary {
                cursor: pointer;
                color: #2271b1;
                font-size: 12px;
                padding: 2px 0;
            }
            .scd-filters[open] summary { margin-bottom: 6px; }
            .scd-filters form {
                display: flex;
                gap: 6px;
                flex-wrap: wrap;
                align-items: center;
            }
            .scd-filters select { font-size: 12px; }
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
            .scheduled-content-empty { padding: 20px; text-align: center; color: #787c82; }
            .scheduled-content-more {
                margin-top: 12px;
                padding-top: 10px;
                border-top: 1px solid #f0f0f1;
                text-align: center;
                font-size: 12px;
            }
            .scheduled-content-more a { text-decoration: none; color: #2271b1; font-weight: 500; }
            .scheduled-content-more a:hover { color: #135e96; text-decoration: underline; }
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
            .scheduled-content-group--drafts .scheduled-content-group-title {
                color: #996800;
                border-bottom-color: #dba617;
            }
            .scheduled-content-item--missed {
                background: #fcf0f1;
                margin: 0 -12px;
                padding-left: 12px;
                padding-right: 12px;
            }
            .scheduled-content-missed-date { color: #b32d2e; font-weight: 600; }
            .scheduled-content-publish-now { margin-top: 4px; display: inline-block; }
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
            #wp-admin-bar-scheduled-content .ab-icon::before { content: "\f145"; top: 3px; }
            #wp-admin-bar-scheduled-content.scd-has-missed .ab-label { color: #ff8787; }

            .scd-calendar-nav {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 6px;
                font-size: 12px;
            }
            .scd-calendar-nav-link { text-decoration: none; }
            .scd-calendar-grid { width: 100%; border-collapse: collapse; font-size: 11px; }
            .scd-calendar-grid th {
                color: #787c82;
                font-weight: 400;
                padding: 4px 0;
                border-bottom: 1px solid #f0f0f1;
                text-transform: uppercase;
                font-size: 10px;
                letter-spacing: 0.5px;
            }
            .scd-calendar-day {
                width: 14.28%;
                height: 34px;
                vertical-align: top;
                padding: 3px;
                text-align: right;
                border: 1px solid #f0f0f1;
                color: #787c82;
                position: relative;
            }
            .scd-calendar-day--empty { background: #fafafa; }
            .scd-calendar-day--today { background: #f0f6fc; font-weight: 600; color: #1d2327; }
            .scd-calendar-day--selected { outline: 2px solid #2271b1; }
            .scd-calendar-day--has-items .scd-calendar-day-link {
                display: block;
                height: 100%;
                text-decoration: none;
                color: #1d2327;
                font-weight: 600;
            }
            .scd-calendar-day--has-items:hover { background: #f6f7f7; }
            .scd-calendar-day--missed { background: #fcf0f1; }
            .scd-calendar-day--missed.scd-calendar-day--has-items { background: #fbe7e9; }
            .scd-calendar-dot {
                display: inline-block;
                min-width: 16px;
                padding: 0 4px;
                font-size: 9px;
                line-height: 14px;
                background: #2271b1;
                color: #fff;
                border-radius: 8px;
                position: absolute;
                bottom: 2px;
                left: 3px;
                font-weight: 400;
            }
            .scd-calendar-day--missed .scd-calendar-dot { background: #b32d2e; }
            .scd-calendar-day-detail {
                margin-top: 10px;
                padding: 8px 10px;
                background: #f6f7f7;
                border-left: 3px solid #2271b1;
                font-size: 12px;
            }
            .scd-calendar-day-detail h5 { margin: 0 0 6px; font-size: 12px; }
            .scd-calendar-day-detail ul { margin: 0; list-style: none; padding: 0; }
            .scd-calendar-day-detail li { padding: 3px 0; }
            .scd-calendar-time { color: #787c82; margin-left: 6px; }
            .scd-calendar-missed-badge {
                display: inline-block;
                background: #b32d2e;
                color: #fff;
                padding: 1px 6px;
                border-radius: 3px;
                font-size: 10px;
                margin-left: 6px;
            }
        ';
    }

    // ---------- Preference helpers ----------

    private function is_mine_only() {
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return false;
        }
        return '1' === get_user_meta( $user_id, self::MINE_ONLY_META_KEY, true );
    }

    private function get_user_view() {
        $user_id = get_current_user_id();
        $view    = $user_id ? (string) get_user_meta( $user_id, self::VIEW_META_KEY, true ) : '';
        if ( ! in_array( $view, array( 'list', 'calendar' ), true ) ) {
            $view = (string) SCD_Settings::get( 'default_view', 'list' );
        }
        return $view;
    }

    private function get_active_post_types() {
        $configured = (array) SCD_Settings::get( 'included_post_types', array() );
        $public     = array_keys( get_post_types( array( 'public' => true ) ) );

        // Intersect with currently-registered public types so an old saved CPT
        // that's since been deregistered doesn't leak into queries.
        return array_values( array_intersect( $configured, $public ) );
    }

    private function get_filter_state() {
        $types = $this->get_active_post_types();

        $type = isset( $_GET['scd_type'] ) ? sanitize_key( wp_unslash( $_GET['scd_type'] ) ) : '';
        if ( '' !== $type && ! in_array( $type, $types, true ) ) {
            $type = '';
        }

        $author = isset( $_GET['scd_author'] ) ? absint( $_GET['scd_author'] ) : 0;

        return array(
            'type'   => $type,
            'author' => $author,
        );
    }

    // ---------- Query helpers ----------

    private function build_query_args( $args_override = array() ) {
        $limit = max( 1, (int) SCD_Settings::get( 'item_limit', 50 ) );
        $types = $this->get_active_post_types();
        if ( empty( $types ) ) {
            // Force zero results; WP_Query would otherwise fall back to 'post'.
            $types = array( '__scd_no_types__' );
        }

        $args = array(
            'post_type'      => $types,
            'post_status'    => 'future',
            'posts_per_page' => $limit,
            'orderby'        => 'date',
            'order'          => 'ASC',
            'no_found_rows'  => true,
        );

        if ( $this->is_mine_only() ) {
            $args['author'] = get_current_user_id();
        }

        $filters = $this->get_filter_state();
        if ( $filters['type'] ) {
            $args['post_type'] = array( $filters['type'] );
        }
        if ( $filters['author'] ) {
            $args['author'] = $filters['author'];
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

    private function map_query_to_items( WP_Query $query, $with_nonce = false ) {
        $items = array();
        if ( ! $query->have_posts() ) {
            return $items;
        }
        while ( $query->have_posts() ) {
            $query->the_post();
            $post_id       = get_the_ID();
            $post_type_obj = get_post_type_object( get_post_type() );

            $item = array(
                'id'              => $post_id,
                'title'           => get_the_title(),
                'edit_link'       => get_edit_post_link( $post_id, 'raw' ),
                'scheduled_date'  => get_the_date( 'Y-m-d H:i:s' ),
                'date_formatted'  => get_the_date( 'M j, Y' ),
                'time_formatted'  => get_the_date( 'g:i a' ),
                'post_type'       => get_post_type(),
                'post_type_label' => $post_type_obj ? $post_type_obj->labels->singular_name : get_post_type(),
                'author'          => get_the_author(),
                'status'          => get_post_status(),
            );
            if ( $with_nonce ) {
                $item['publish_nonce'] = wp_create_nonce( self::PUBLISH_NOW_ACTION . '_' . $post_id );
            }
            $items[] = $item;
        }
        wp_reset_postdata();
        return $items;
    }

    private function get_scheduled_content() {
        $args = $this->build_query_args(
            array(
                'date_query' => array(
                    array(
                        'column'    => 'post_date_gmt',
                        'after'     => gmdate( 'Y-m-d H:i:s' ),
                        'inclusive' => false,
                    ),
                ),
            )
        );
        return $this->map_query_to_items( new WP_Query( $args ) );
    }

    private function get_missed_content() {
        $args = $this->build_query_args(
            array(
                'date_query' => array(
                    array(
                        'column' => 'post_date_gmt',
                        'before' => gmdate( 'Y-m-d H:i:s' ),
                    ),
                ),
            )
        );
        return $this->map_query_to_items( new WP_Query( $args ), true );
    }

    private function get_draft_content() {
        if ( ! SCD_Settings::get( 'show_drafts', 0 ) ) {
            return array();
        }
        $args                    = $this->build_query_args();
        $args['post_status']     = 'draft';
        $args['orderby']         = 'modified';
        $args['order']           = 'DESC';
        unset( $args['date_query'] );

        $query = new WP_Query( $args );
        return $this->map_query_to_items( $query );
    }

    private function get_month_items( $month ) {
        $start = $month . '-01 00:00:00';
        $end   = wp_date( 'Y-m-t 23:59:59', strtotime( $month . '-01' ) );

        $args = $this->build_query_args(
            array(
                'posts_per_page' => 200,
                'date_query'     => array(
                    array(
                        'column'    => 'post_date_gmt',
                        'after'     => get_gmt_from_date( $start ),
                        'before'    => get_gmt_from_date( $end ),
                        'inclusive' => true,
                    ),
                ),
            )
        );

        $items   = $this->map_query_to_items( new WP_Query( $args ), true );
        $now_gmt = time();
        foreach ( $items as &$item ) {
            $item['is_missed'] = get_post_time( 'U', true, $item['id'] ) <= $now_gmt;
        }
        unset( $item );
        return $items;
    }

    private function get_counts() {
        $types = $this->get_active_post_types();
        if ( empty( $types ) ) {
            return array( 'total' => 0, 'missed' => 0 );
        }
        $user_scope_args = $this->is_mine_only() ? array( 'author' => get_current_user_id() ) : array();

        $base = array(
            'post_type'              => $types,
            'post_status'            => 'future',
            'posts_per_page'         => 1,
            'fields'                 => 'ids',
            'no_found_rows'          => false,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        );

        $scheduled = new WP_Query( array_merge( $base, $user_scope_args ) );

        $missed = new WP_Query(
            array_merge(
                $base,
                $user_scope_args,
                array(
                    'date_query' => array(
                        array(
                            'column' => 'post_date_gmt',
                            'before' => gmdate( 'Y-m-d H:i:s' ),
                        ),
                    ),
                )
            )
        );

        return array(
            'total'  => (int) $scheduled->found_posts,
            'missed' => (int) $missed->found_posts,
        );
    }

    // ---------- Rendering ----------

    public function render_widget() {
        $this->maybe_render_notice();
        $this->render_header();

        if ( 'calendar' === $this->get_user_view() ) {
            $this->render_calendar_view();
            return;
        }

        $this->render_list_view();
    }

    private function render_list_view() {
        $missed    = $this->get_missed_content();
        $scheduled = $this->get_scheduled_content();
        $drafts    = $this->get_draft_content();

        if ( empty( $missed ) && empty( $scheduled ) && empty( $drafts ) ) {
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

        if ( ! empty( $drafts ) ) {
            $this->render_drafts_group( $drafts );
        }

        $this->render_more_footer( count( $scheduled ) + count( $missed ) );

        echo '</div>';
    }

    private function render_more_footer( $shown_count ) {
        $counts = $this->get_counts();
        $total  = (int) $counts['total'];
        if ( $total <= $shown_count ) {
            return;
        }
        $remaining = $total - $shown_count;
        $calendar_url = admin_url( 'admin.php?page=' . SCD_Admin_Page::PAGE_SLUG );
        ?>
        <div class="scheduled-content-more">
            <a href="<?php echo esc_url( $calendar_url ); ?>">
                <?php
                printf(
                    /* translators: %d: number of additional scheduled posts not shown in the widget. */
                    esc_html( _n( '+%d more scheduled — open full calendar', '+%d more scheduled — open full calendar', $remaining, 'scheduled-content-dashboard' ) ),
                    $remaining
                );
                ?>
            </a>
        </div>
        <?php
    }

    private function render_calendar_view() {
        $month = isset( $_GET['scd_month'] ) ? sanitize_text_field( wp_unslash( $_GET['scd_month'] ) ) : wp_date( 'Y-m' );
        if ( ! preg_match( '/^\d{4}-\d{2}$/', $month ) ) {
            $month = wp_date( 'Y-m' );
        }
        $day = isset( $_GET['scd_day'] ) ? sanitize_text_field( wp_unslash( $_GET['scd_day'] ) ) : '';
        if ( '' !== $day && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $day ) ) {
            $day = '';
        }

        $items    = $this->get_month_items( $month );
        $base_url = admin_url( 'index.php' );
        SCD_Calendar::render( $items, $month, $base_url, $day );
    }

    private function render_header() {
        $mine_only  = $this->is_mine_only();
        $view       = $this->get_user_view();
        $filters    = $this->get_filter_state();
        $action_url = admin_url( 'admin-post.php' );
        ?>
        <div class="scheduled-content-header">
            <div>
                <details class="scd-filters" <?php echo ( $filters['type'] || $filters['author'] ) ? 'open' : ''; ?>>
                    <summary><?php esc_html_e( 'Filter', 'scheduled-content-dashboard' ); ?></summary>
                    <form method="get" action="<?php echo esc_url( admin_url( 'index.php' ) ); ?>">
                        <select name="scd_type">
                            <option value=""><?php esc_html_e( 'All post types', 'scheduled-content-dashboard' ); ?></option>
                            <?php foreach ( $this->get_active_post_types() as $type ) :
                                $obj = get_post_type_object( $type ); ?>
                                <option value="<?php echo esc_attr( $type ); ?>" <?php selected( $filters['type'], $type ); ?>>
                                    <?php echo esc_html( $obj ? $obj->labels->singular_name : $type ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <select name="scd_author">
                            <option value="0"><?php esc_html_e( 'All authors', 'scheduled-content-dashboard' ); ?></option>
                            <?php foreach ( $this->get_scheduling_authors() as $author ) : ?>
                                <option value="<?php echo esc_attr( $author->ID ); ?>" <?php selected( $filters['author'], (int) $author->ID ); ?>>
                                    <?php echo esc_html( $author->display_name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="button button-small"><?php esc_html_e( 'Apply', 'scheduled-content-dashboard' ); ?></button>
                        <?php if ( $filters['type'] || $filters['author'] ) : ?>
                            <a href="<?php echo esc_url( admin_url( 'index.php' ) ); ?>"><?php esc_html_e( 'Clear', 'scheduled-content-dashboard' ); ?></a>
                        <?php endif; ?>
                    </form>
                </details>
            </div>
            <div class="scd-header-right">
                <a class="scheduled-content-toggle" href="<?php echo esc_url( admin_url( 'admin.php?page=' . SCD_Admin_Page::PAGE_SLUG ) ); ?>">
                    <?php esc_html_e( 'Open full calendar', 'scheduled-content-dashboard' ); ?>
                </a>
                <span class="scd-view-switch">
                    <?php $this->render_view_toggle_button( 'list', $view ); ?>
                    <?php $this->render_view_toggle_button( 'calendar', $view ); ?>
                </span>
                <form method="post" action="<?php echo esc_url( $action_url ); ?>">
                    <?php wp_nonce_field( self::TOGGLE_MINE_ACTION ); ?>
                    <input type="hidden" name="action" value="<?php echo esc_attr( self::TOGGLE_MINE_ACTION ); ?>">
                    <button type="submit" class="scheduled-content-toggle">
                        <?php echo esc_html( $mine_only ? __( 'Show all authors', 'scheduled-content-dashboard' ) : __( 'Mine only', 'scheduled-content-dashboard' ) ); ?>
                    </button>
                </form>
            </div>
        </div>
        <?php
    }

    private function render_view_toggle_button( $target, $current ) {
        $label = 'list' === $target ? __( 'List', 'scheduled-content-dashboard' ) : __( 'Calendar', 'scheduled-content-dashboard' );
        $class = 'scheduled-content-toggle';
        if ( $current === $target ) {
            $class .= ' scheduled-content-toggle--active';
        }
        ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( self::TOGGLE_VIEW_ACTION ); ?>
            <input type="hidden" name="action" value="<?php echo esc_attr( self::TOGGLE_VIEW_ACTION ); ?>">
            <input type="hidden" name="view" value="<?php echo esc_attr( $target ); ?>">
            <button type="submit" class="<?php echo esc_attr( $class ); ?>">
                <?php echo esc_html( $label ); ?>
            </button>
        </form>
        <?php
    }

    private function get_scheduling_authors() {
        $cache = wp_cache_get( 'scd_scheduling_authors', 'scd' );
        if ( false !== $cache ) {
            return $cache;
        }

        global $wpdb;
        $types  = $this->get_active_post_types();
        if ( empty( $types ) ) {
            return array();
        }
        $in     = implode( ',', array_fill( 0, count( $types ), '%s' ) );
        $author_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT post_author FROM {$wpdb->posts} WHERE post_status = 'future' AND post_type IN ({$in})",
                $types
            )
        );

        $authors = array();
        foreach ( $author_ids as $id ) {
            $u = get_userdata( (int) $id );
            if ( $u ) {
                $authors[] = $u;
            }
        }

        usort(
            $authors,
            function ( $a, $b ) {
                return strcasecmp( $a->display_name, $b->display_name );
            }
        );

        wp_cache_set( 'scd_scheduling_authors', $authors, 'scd', 300 );
        return $authors;
    }

    private function maybe_render_notice() {
        if ( empty( $_GET['scd_notice'] ) ) {
            return;
        }

        $notices = array(
            'published'      => array(
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

    private function render_drafts_group( $items ) {
        ?>
        <div class="scheduled-content-group scheduled-content-group--drafts">
            <h4 class="scheduled-content-group-title">
                <?php
                printf(
                    /* translators: %d: Number of drafts. */
                    esc_html( _n( 'Drafts (%d)', 'Drafts (%d)', count( $items ), 'scheduled-content-dashboard' ) ),
                    count( $items )
                );
                ?>
            </h4>
            <?php foreach ( $items as $item ) : ?>
                <?php $this->render_item( $item ); ?>
            <?php endforeach; ?>
        </div>
        <?php
    }

    private function render_missed_item( $item ) {
        $title       = $item['title'] ? $item['title'] : __( '(no title)', 'scheduled-content-dashboard' );
        $can_publish = current_user_can( 'edit_post', $item['id'] );
        $action_url  = admin_url( 'admin-post.php' );
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

    // ---------- Admin bar ----------

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
                'href'  => admin_url( 'admin.php?page=' . SCD_Admin_Page::PAGE_SLUG ),
                'meta'  => array(
                    'class' => $counts['missed'] > 0 ? 'scd-has-missed' : '',
                    'title' => __( 'Scheduled Content', 'scheduled-content-dashboard' ),
                ),
            )
        );
    }

    // ---------- Action handlers ----------

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

        wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'index.php' ) );
        exit;
    }

    public function handle_view_toggle() {
        check_admin_referer( self::TOGGLE_VIEW_ACTION );

        $view    = isset( $_POST['view'] ) ? sanitize_key( wp_unslash( $_POST['view'] ) ) : 'list';
        $view    = in_array( $view, array( 'list', 'calendar' ), true ) ? $view : 'list';
        $user_id = get_current_user_id();
        if ( $user_id ) {
            update_user_meta( $user_id, self::VIEW_META_KEY, $view );
        }

        wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'index.php' ) );
        exit;
    }

    /**
     * Auto-publish missed scheduled posts on admin page loads.
     *
     * WordPress occasionally fails to fire wp-cron and leaves posts in `future`
     * status past their publish date. This runs once per AUTO_FIX_INTERVAL seconds
     * and publishes anything stuck. Disable via settings UI or the
     * `scheduled_content_dashboard_auto_fix_missed` filter.
     */
    public function maybe_auto_fix_missed() {
        $setting_enabled = (int) SCD_Settings::get( 'auto_fix_enabled', 1 ) === 1;

        /**
         * Filters whether to auto-publish missed scheduled posts.
         *
         * @since 1.1.0
         * @param bool $enabled Whether auto-fix is enabled.
         */
        if ( ! apply_filters( 'scheduled_content_dashboard_auto_fix_missed', $setting_enabled ) ) {
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
