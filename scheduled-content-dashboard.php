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
 * Description:       Displays all scheduled posts, pages, and custom post types on the WordPress dashboard with quick edit links.
 * Version:           1.0.0
 * Requires at least: 5.0
 * Requires PHP:      7.4
 * Author:            jeangalea
 * Author URI:        https://jeangalea.com
 * Text Domain:       scheduled-content-dashboard
 * Domain Path:       /languages
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

// Prevent direct access to this file.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Main plugin class.
 *
 * Handles the display of scheduled content in a dashboard widget.
 *
 * @since 1.0.0
 */
class Scheduled_Content_Dashboard {

    /**
     * Plugin version.
     *
     * @since 1.0.0
     * @var string
     */
    const VERSION = '1.0.0';

    /**
     * Singleton instance of this class.
     *
     * @since 1.0.0
     * @var Scheduled_Content_Dashboard|null
     */
    private static $instance = null;

    /**
     * Get the singleton instance.
     *
     * @since 1.0.0
     * @return Scheduled_Content_Dashboard
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     *
     * Sets up hooks for the dashboard widget.
     *
     * @since 1.0.0
     */
    private function __construct() {
        add_action( 'wp_dashboard_setup', array( $this, 'register_dashboard_widget' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
    }

    /**
     * Register the dashboard widget.
     *
     * @since 1.0.0
     * @return void
     */
    public function register_dashboard_widget() {
        wp_add_dashboard_widget(
            'scheduled_content_widget',
            __( 'Scheduled Content', 'scheduled-content-dashboard' ),
            array( $this, 'render_widget' )
        );
    }

    /**
     * Enqueue admin styles on the dashboard page.
     *
     * @since 1.0.0
     * @param string $hook The current admin page hook.
     * @return void
     */
    public function enqueue_styles( $hook ) {
        if ( 'index.php' !== $hook ) {
            return;
        }

        wp_add_inline_style( 'dashboard', $this->get_widget_styles() );
    }

    /**
     * Get the CSS styles for the widget.
     *
     * @since 1.0.0
     * @return string CSS styles.
     */
    private function get_widget_styles() {
        return '
            .scheduled-content-list {
                margin: 0;
                padding: 0;
            }
            .scheduled-content-item {
                display: flex;
                align-items: flex-start;
                padding: 12px 0;
                border-bottom: 1px solid #f0f0f1;
            }
            .scheduled-content-item:last-child {
                border-bottom: none;
            }
            .scheduled-content-date {
                flex-shrink: 0;
                width: 90px;
                padding-right: 12px;
                color: #50575e;
                font-size: 12px;
                line-height: 1.4;
            }
            .scheduled-content-date .date {
                font-weight: 600;
                color: #1d2327;
            }
            .scheduled-content-date .time {
                color: #787c82;
            }
            .scheduled-content-details {
                flex-grow: 1;
                min-width: 0;
            }
            .scheduled-content-title {
                margin: 0 0 4px 0;
                font-size: 13px;
                line-height: 1.4;
            }
            .scheduled-content-title a {
                text-decoration: none;
                color: #2271b1;
            }
            .scheduled-content-title a:hover {
                color: #135e96;
                text-decoration: underline;
            }
            .scheduled-content-meta {
                font-size: 12px;
                color: #787c82;
            }
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
            .scheduled-content-group {
                margin-bottom: 15px;
            }
            .scheduled-content-group:last-child {
                margin-bottom: 0;
            }
            .scheduled-content-group-title {
                font-size: 11px;
                font-weight: 600;
                text-transform: uppercase;
                color: #50575e;
                padding: 8px 0;
                border-bottom: 2px solid #2271b1;
                margin-bottom: 0;
            }
        ';
    }

    /**
     * Get all scheduled content from the database.
     *
     * Retrieves posts, pages, and custom post types with 'future' status.
     *
     * @since 1.0.0
     * @return array Array of scheduled content items.
     */
    private function get_scheduled_content() {
        $post_types      = get_post_types( array( 'public' => true ), 'objects' );
        $post_type_names = array_keys( $post_types );

        $args = array(
            'post_type'      => $post_type_names,
            'post_status'    => 'future',
            'posts_per_page' => 50,
            'orderby'        => 'date',
            'order'          => 'ASC',
            'no_found_rows'  => true,
        );

        /**
         * Filters the query arguments for retrieving scheduled content.
         *
         * @since 1.0.0
         * @param array $args WP_Query arguments.
         */
        $args = apply_filters( 'scheduled_content_dashboard_query_args', $args );

        $query     = new WP_Query( $args );
        $scheduled = array();

        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $post_id       = get_the_ID();
                $post_type_obj = get_post_type_object( get_post_type() );

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

    /**
     * Group scheduled content by relative time periods.
     *
     * Organizes content into Today, Tomorrow, This Week, Next Week, and Later.
     *
     * @since 1.0.0
     * @param array $content Array of scheduled content items.
     * @return array Grouped content with labels.
     */
    private function group_content_by_time( $content ) {
        $groups = array(
            'today'     => array(
                'label' => __( 'Today', 'scheduled-content-dashboard' ),
                'items' => array(),
            ),
            'tomorrow'  => array(
                'label' => __( 'Tomorrow', 'scheduled-content-dashboard' ),
                'items' => array(),
            ),
            'this_week' => array(
                'label' => __( 'This Week', 'scheduled-content-dashboard' ),
                'items' => array(),
            ),
            'next_week' => array(
                'label' => __( 'Next Week', 'scheduled-content-dashboard' ),
                'items' => array(),
            ),
            'later'     => array(
                'label' => __( 'Later', 'scheduled-content-dashboard' ),
                'items' => array(),
            ),
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

        // Remove empty groups.
        return array_filter(
            $groups,
            function ( $group ) {
                return ! empty( $group['items'] );
            }
        );
    }

    /**
     * Render the dashboard widget content.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_widget() {
        $content = $this->get_scheduled_content();

        if ( empty( $content ) ) {
            echo '<div class="scheduled-content-empty">';
            echo '<p>' . esc_html__( 'No scheduled content found.', 'scheduled-content-dashboard' ) . '</p>';
            echo '</div>';
            return;
        }

        $grouped = $this->group_content_by_time( $content );

        echo '<div class="scheduled-content-list">';

        foreach ( $grouped as $group_key => $group ) {
            echo '<div class="scheduled-content-group">';
            echo '<h4 class="scheduled-content-group-title">' . esc_html( $group['label'] ) . '</h4>';

            foreach ( $group['items'] as $item ) {
                $this->render_item( $item );
            }

            echo '</div>';
        }

        echo '</div>';
    }

    /**
     * Render a single scheduled content item.
     *
     * @since 1.0.0
     * @param array $item The scheduled content item data.
     * @return void
     */
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
}

// Initialize the plugin.
add_action(
    'plugins_loaded',
    function () {
        Scheduled_Content_Dashboard::get_instance();
    },
    0
);
