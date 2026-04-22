<?php
/**
 * Settings page for Scheduled Content Dashboard.
 *
 * @package Scheduled_Content_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SCD_Settings {

    const OPTION_NAME = 'scheduled_content_dashboard_settings';
    const PAGE_SLUG   = 'scheduled-content-dashboard';

    public static function init() {
        $self = new self();
        add_action( 'admin_init', array( $self, 'register_settings' ) );
        add_action( 'admin_menu', array( $self, 'add_menu' ) );
    }

    public static function get( $key, $default = null ) {
        $settings = wp_parse_args( get_option( self::OPTION_NAME, array() ), self::defaults() );
        return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
    }

    public static function defaults() {
        return array(
            'item_limit'          => 7,
            'included_post_types' => array( 'post' ),
            'show_drafts'         => 0,
            'default_view'        => 'list',
            'auto_fix_enabled'    => 1,
            'digest_enabled'      => 0,
            'digest_frequency'    => 'weekly',
            'digest_recipients'   => '',
        );
    }

    public function register_settings() {
        register_setting(
            self::OPTION_NAME,
            self::OPTION_NAME,
            array(
                'type'              => 'array',
                'sanitize_callback' => array( $this, 'sanitize' ),
                'default'           => self::defaults(),
            )
        );

        add_settings_section(
            'scd_main',
            __( 'Widget settings', 'scheduled-content-dashboard' ),
            '__return_false',
            self::PAGE_SLUG
        );

        add_settings_field(
            'item_limit',
            __( 'Items per group', 'scheduled-content-dashboard' ),
            array( $this, 'render_item_limit' ),
            self::PAGE_SLUG,
            'scd_main'
        );

        add_settings_field(
            'included_post_types',
            __( 'Post types', 'scheduled-content-dashboard' ),
            array( $this, 'render_post_types' ),
            self::PAGE_SLUG,
            'scd_main'
        );

        add_settings_field(
            'show_drafts',
            __( 'Include drafts', 'scheduled-content-dashboard' ),
            array( $this, 'render_show_drafts' ),
            self::PAGE_SLUG,
            'scd_main'
        );

        add_settings_field(
            'default_view',
            __( 'Default view', 'scheduled-content-dashboard' ),
            array( $this, 'render_default_view' ),
            self::PAGE_SLUG,
            'scd_main'
        );

        add_settings_field(
            'auto_fix_enabled',
            __( 'Auto-fix missed schedules', 'scheduled-content-dashboard' ),
            array( $this, 'render_auto_fix' ),
            self::PAGE_SLUG,
            'scd_main'
        );

        add_settings_section(
            'scd_digest',
            __( 'Email digest', 'scheduled-content-dashboard' ),
            array( $this, 'render_digest_intro' ),
            self::PAGE_SLUG
        );

        add_settings_field(
            'digest_enabled',
            __( 'Send digest', 'scheduled-content-dashboard' ),
            array( $this, 'render_digest_enabled' ),
            self::PAGE_SLUG,
            'scd_digest'
        );

        add_settings_field(
            'digest_frequency',
            __( 'Frequency', 'scheduled-content-dashboard' ),
            array( $this, 'render_digest_frequency' ),
            self::PAGE_SLUG,
            'scd_digest'
        );

        add_settings_field(
            'digest_recipients',
            __( 'Recipients', 'scheduled-content-dashboard' ),
            array( $this, 'render_digest_recipients' ),
            self::PAGE_SLUG,
            'scd_digest'
        );
    }

    public function render_digest_intro() {
        echo '<p>' . esc_html__( 'Optional email summary of scheduled, missed, and upcoming posts.', 'scheduled-content-dashboard' ) . '</p>';
    }

    public function sanitize( $input ) {
        $defaults = self::defaults();
        $clean    = array();

        $clean['item_limit'] = isset( $input['item_limit'] )
            ? max( 1, min( 500, (int) $input['item_limit'] ) )
            : $defaults['item_limit'];

        $clean['included_post_types'] = array();
        if ( isset( $input['included_post_types'] ) && is_array( $input['included_post_types'] ) ) {
            $available = array_keys( get_post_types( array( 'public' => true ) ) );
            foreach ( $input['included_post_types'] as $type ) {
                $type = sanitize_key( $type );
                if ( in_array( $type, $available, true ) ) {
                    $clean['included_post_types'][] = $type;
                }
            }
        }

        $clean['show_drafts']      = ! empty( $input['show_drafts'] ) ? 1 : 0;
        $clean['auto_fix_enabled'] = ! empty( $input['auto_fix_enabled'] ) ? 1 : 0;
        $clean['digest_enabled']   = ! empty( $input['digest_enabled'] ) ? 1 : 0;

        $view = isset( $input['default_view'] ) ? (string) $input['default_view'] : 'list';
        $clean['default_view'] = in_array( $view, array( 'list', 'calendar' ), true ) ? $view : 'list';

        $freq = isset( $input['digest_frequency'] ) ? (string) $input['digest_frequency'] : 'weekly';
        $clean['digest_frequency'] = in_array( $freq, array( 'daily', 'weekly' ), true ) ? $freq : 'weekly';

        $recipients = isset( $input['digest_recipients'] ) ? (string) $input['digest_recipients'] : '';
        $emails     = array();
        foreach ( preg_split( '/[,\n]+/', $recipients ) as $email ) {
            $email = trim( $email );
            if ( is_email( $email ) ) {
                $emails[] = $email;
            }
        }
        $clean['digest_recipients'] = implode( ', ', $emails );

        if ( (bool) $clean['digest_enabled'] !== (bool) self::get( 'digest_enabled' )
            || $clean['digest_frequency'] !== self::get( 'digest_frequency' ) ) {
            SCD_Digest::reschedule( (bool) $clean['digest_enabled'], $clean['digest_frequency'] );
        }

        return $clean;
    }

    public function add_menu() {
        add_submenu_page(
            SCD_Admin_Page::PAGE_SLUG,
            __( 'Scheduled Content Settings', 'scheduled-content-dashboard' ),
            __( 'Settings', 'scheduled-content-dashboard' ),
            'manage_options',
            self::PAGE_SLUG,
            array( $this, 'render_page' )
        );
    }

    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( self::OPTION_NAME );
                do_settings_sections( self::PAGE_SLUG );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function render_item_limit() {
        $value = (int) self::get( 'item_limit' );
        printf(
            '<input type="number" name="%1$s[item_limit]" value="%2$d" min="1" max="500" class="small-text"> <p class="description">%3$s</p>',
            esc_attr( self::OPTION_NAME ),
            esc_attr( $value ),
            esc_html__( 'Maximum number of scheduled items shown in the dashboard widget. A link to the full editorial calendar is shown when more items exist.', 'scheduled-content-dashboard' )
        );
    }

    public function render_post_types() {
        $selected   = (array) self::get( 'included_post_types' );
        $post_types = get_post_types( array( 'public' => true ), 'objects' );

        echo '<fieldset>';
        foreach ( $post_types as $pt ) {
            printf(
                '<label style="display:block; margin-bottom:4px"><input type="checkbox" name="%1$s[included_post_types][]" value="%2$s" %3$s> %4$s</label>',
                esc_attr( self::OPTION_NAME ),
                esc_attr( $pt->name ),
                checked( in_array( $pt->name, $selected, true ), true, false ),
                esc_html( $pt->labels->singular_name )
            );
        }
        echo '<p class="description">' . esc_html__( 'Select which post types to include in the widget.', 'scheduled-content-dashboard' ) . '</p>';
        echo '</fieldset>';
    }

    public function render_show_drafts() {
        $value = (int) self::get( 'show_drafts' );
        printf(
            '<label><input type="checkbox" name="%1$s[show_drafts]" value="1" %2$s> %3$s</label>',
            esc_attr( self::OPTION_NAME ),
            checked( $value, 1, false ),
            esc_html__( 'Show drafts alongside scheduled posts in their own group.', 'scheduled-content-dashboard' )
        );
    }

    public function render_default_view() {
        $value = (string) self::get( 'default_view' );
        ?>
        <label style="margin-right:16px">
            <input type="radio" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[default_view]" value="list" <?php checked( $value, 'list' ); ?>>
            <?php esc_html_e( 'List', 'scheduled-content-dashboard' ); ?>
        </label>
        <label>
            <input type="radio" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[default_view]" value="calendar" <?php checked( $value, 'calendar' ); ?>>
            <?php esc_html_e( 'Calendar', 'scheduled-content-dashboard' ); ?>
        </label>
        <p class="description"><?php esc_html_e( 'Each user can override their view from the widget.', 'scheduled-content-dashboard' ); ?></p>
        <?php
    }

    public function render_auto_fix() {
        $value = (int) self::get( 'auto_fix_enabled' );
        printf(
            '<label><input type="checkbox" name="%1$s[auto_fix_enabled]" value="1" %2$s> %3$s</label>',
            esc_attr( self::OPTION_NAME ),
            checked( $value, 1, false ),
            esc_html__( 'Automatically publish scheduled posts WordPress failed to publish on time.', 'scheduled-content-dashboard' )
        );
    }

    public function render_digest_enabled() {
        $value = (int) self::get( 'digest_enabled' );
        printf(
            '<label><input type="checkbox" name="%1$s[digest_enabled]" value="1" %2$s> %3$s</label>',
            esc_attr( self::OPTION_NAME ),
            checked( $value, 1, false ),
            esc_html__( 'Send an email summary on a schedule.', 'scheduled-content-dashboard' )
        );
    }

    public function render_digest_frequency() {
        $value = (string) self::get( 'digest_frequency' );
        ?>
        <label style="margin-right:16px">
            <input type="radio" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[digest_frequency]" value="daily" <?php checked( $value, 'daily' ); ?>>
            <?php esc_html_e( 'Daily', 'scheduled-content-dashboard' ); ?>
        </label>
        <label>
            <input type="radio" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[digest_frequency]" value="weekly" <?php checked( $value, 'weekly' ); ?>>
            <?php esc_html_e( 'Weekly', 'scheduled-content-dashboard' ); ?>
        </label>
        <?php
    }

    public function render_digest_recipients() {
        $value = (string) self::get( 'digest_recipients' );
        $admin = get_option( 'admin_email' );
        printf(
            '<textarea name="%1$s[digest_recipients]" rows="2" cols="40" placeholder="%2$s">%3$s</textarea><p class="description">%4$s</p>',
            esc_attr( self::OPTION_NAME ),
            esc_attr( $admin ),
            esc_textarea( $value ),
            esc_html__( 'One or more emails, comma or newline separated. Defaults to the site admin email if empty.', 'scheduled-content-dashboard' )
        );
    }
}
