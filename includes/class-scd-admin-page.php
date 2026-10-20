<?php
/**
 * Full editorial calendar admin page.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SCD_Admin_Page {

    const PAGE_SLUG    = 'scd-calendar';
    const AJAX_ACTION  = 'scd_reschedule';
    const AJAX_CREATE  = 'scd_quick_create';
    const AJAX_EDIT    = 'scd_quick_edit';
    const AJAX_TRASH   = 'scd_quick_trash';
    const ICS_ACTION   = 'scd_export_ics';
    const NONCE_ACTION = 'scd_calendar_nonce';

    /** Per-day visible chip cap in month view before "+N more". */
    const MONTH_CAP = 4;

    public static function init() {
        $self = new self();
        add_action( 'admin_menu', array( $self, 'add_menu' ), 5 );
        add_action( 'admin_enqueue_scripts', array( $self, 'enqueue' ) );
        add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $self, 'handle_reschedule' ) );
        add_action( 'wp_ajax_' . self::AJAX_CREATE, array( $self, 'handle_create' ) );
        add_action( 'wp_ajax_' . self::AJAX_EDIT, array( $self, 'handle_edit' ) );
        add_action( 'wp_ajax_' . self::AJAX_TRASH, array( $self, 'handle_trash' ) );
        add_action( 'admin_post_' . self::ICS_ACTION, array( $self, 'handle_ics' ) );
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
    }

    public function enqueue( $hook ) {
        if ( 'toplevel_page_' . self::PAGE_SLUG !== $hook ) {
            return;
        }

        wp_enqueue_script(
            'scd-editorial-calendar',
            plugins_url( 'assets/js/editorial-calendar.js', dirname( __FILE__ ) ),
            array(),
            Scheduled_Content_Dashboard::VERSION,
            true
        );

        wp_localize_script(
            'scd-editorial-calendar',
            'scdCalendar',
            array(
                'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
                'nonce'        => wp_create_nonce( self::NONCE_ACTION ),
                'rescheduleAction' => self::AJAX_ACTION,
                'createAction'     => self::AJAX_CREATE,
                'editAction'       => self::AJAX_EDIT,
                'trashAction'      => self::AJAX_TRASH,
                'rescheduling' => __( 'Rescheduling…', 'scheduled-content-dashboard' ),
                'failed'       => __( 'Reschedule failed.', 'scheduled-content-dashboard' ),
                'success'      => __( 'Rescheduled.', 'scheduled-content-dashboard' ),
                'undo'         => __( 'Undo', 'scheduled-content-dashboard' ),
                'saving'       => __( 'Saving…', 'scheduled-content-dashboard' ),
                'saveFailed'   => __( 'Could not save.', 'scheduled-content-dashboard' ),
                'createFailed' => __( 'Could not create the post.', 'scheduled-content-dashboard' ),
                'newHeading'   => __( 'New post', 'scheduled-content-dashboard' ),
                'editHeading'  => __( 'Edit post', 'scheduled-content-dashboard' ),
                'titleLabel'   => __( 'Title', 'scheduled-content-dashboard' ),
                'whenLabel'    => __( 'Publish at', 'scheduled-content-dashboard' ),
                'save'         => __( 'Save', 'scheduled-content-dashboard' ),
                'cancel'       => __( 'Cancel', 'scheduled-content-dashboard' ),
                'del'          => __( 'Delete', 'scheduled-content-dashboard' ),
                'openEditor'   => __( 'Open editor', 'scheduled-content-dashboard' ),
                'confirmDel'   => __( 'Move this scheduled post to the trash?', 'scheduled-content-dashboard' ),
                'deleted'      => __( 'Moved to trash.', 'scheduled-content-dashboard' ),
                /* translators: %d: number of additional items hidden in a calendar day. */
                'moreLabel'    => __( '+%d more', 'scheduled-content-dashboard' ),
                'lessLabel'    => __( 'Show less', 'scheduled-content-dashboard' ),
                /* translators: %d: number of selected posts. */
                'selected'     => __( '%d selected', 'scheduled-content-dashboard' ),
                'moveHint'     => __( 'Drag any selected post to move them together', 'scheduled-content-dashboard' ),
                'clear'        => __( 'Clear', 'scheduled-content-dashboard' ),
            )
        );

        wp_add_inline_style( 'common', $this->styles() );
    }

    private function styles() {
        return '
            .scd-cal, .scd-pop-backdrop { --scd-bg:#ffffff; --scd-panel:#ffffff; --scd-ink:#1d2230; --scd-soft:#5b6472;
                --scd-line:#e6e8ee; --scd-line2:#d6dae3; --scd-accent:#4f46e5; --scd-accent-weak:#eef0fe;
                --scd-today:#f3f1ff; --scd-miss:#dc2626; --scd-miss-weak:#fdecec; --scd-out:#f7f8fa; --scd-wknd:#fbfbfd;
                --scd-radius:10px;
                font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",system-ui,sans-serif; color:var(--scd-ink); }
            @media (prefers-color-scheme: dark) {
                .scd-cal, .scd-pop-backdrop { --scd-bg:#0e1016; --scd-panel:#161922; --scd-ink:#e8eaf0; --scd-soft:#9aa3b2;
                    --scd-line:#252a36; --scd-line2:#313747; --scd-accent:#8b85f5; --scd-accent-weak:#20223a;
                    --scd-today:#1b1c30; --scd-miss:#f87171; --scd-miss-weak:#2a1a1d; --scd-out:#12141c; --scd-wknd:#13151d; }
                .scd-cal .scd-chip .scd-ti { color:var(--scd-ink); }
            }

            .scd-cal-toolbar { display:flex; align-items:center; flex-wrap:wrap; gap:10px; margin:14px 0 14px; }
            .scd-cal-title { font-size:20px; font-weight:700; letter-spacing:-.02em; margin:0; }
            .scd-seg { display:inline-flex; border:1px solid var(--scd-line2); border-radius:8px; overflow:hidden; }
            .scd-seg a { text-decoration:none; font-size:13px; padding:5px 12px; color:var(--scd-soft); background:var(--scd-panel); }
            .scd-seg a.is-on { background:var(--scd-accent); color:#fff; }
            .scd-cal-filters { display:inline-flex; gap:8px; align-items:center; }
            .scd-cal-filters select { font-size:13px; padding:4px 8px; border:1px solid var(--scd-line2); border-radius:8px;
                background:var(--scd-panel); color:var(--scd-ink); max-width:170px; }
            .scd-nav { display:inline-flex; gap:4px; }
            .scd-nav a { text-decoration:none; font-size:13px; padding:5px 9px; border:1px solid var(--scd-line2);
                border-radius:8px; color:var(--scd-ink); background:var(--scd-panel); }
            .scd-nav a:hover { border-color:var(--scd-accent); }
            .scd-spacer { flex:1; }
            .scd-btn { text-decoration:none; font-size:13px; font-weight:600; border-radius:8px; padding:6px 13px; display:inline-flex; align-items:center; gap:6px; }
            .scd-btn-primary { background:var(--scd-accent); color:#fff; }
            .scd-btn-ghost { border:1px solid var(--scd-line2); color:var(--scd-ink); background:var(--scd-panel); }

            .scd-grid { display:grid; grid-template-columns:repeat(7,minmax(0,1fr)); gap:1px;
                background:var(--scd-line); border:1px solid var(--scd-line); border-radius:var(--scd-radius); overflow:hidden; }
            .scd-grid * { box-sizing:border-box; }
            .scd-dow { background:var(--scd-panel); color:var(--scd-soft); font-size:10.5px; font-weight:600;
                letter-spacing:.07em; text-transform:uppercase; padding:8px 10px; }
            .scd-day { background:var(--scd-panel); min-height:108px; padding:6px 7px 8px; display:flex; flex-direction:column; gap:4px; position:relative; }
            .scd-day--week { min-height:340px; }
            .scd-day--out { background:var(--scd-out); }
            .scd-day--wknd { background:var(--scd-wknd); }
            .scd-day--today { background:var(--scd-today); box-shadow:inset 0 2px 0 0 var(--scd-accent); }
            .scd-day--dragover { outline:2px dashed var(--scd-accent); outline-offset:-2px; background:var(--scd-accent-weak); }
            .scd-daynum { font-size:12.5px; font-weight:600; color:var(--scd-soft); }
            .scd-day--today .scd-daynum { color:var(--scd-accent); }
            .scd-day--out .scd-daynum { opacity:.5; }

            .scd-chip { display:flex; align-items:center; gap:6px; background:var(--scd-panel); border:1px solid var(--scd-line);
                border-radius:7px; padding:4px 7px; cursor:grab; text-decoration:none; }
            .scd-chip:hover { border-color:var(--scd-line2); }
            .scd-chip[hidden] { display:none; }
            .scd-chip.is-dragging { opacity:.5; }
            .scd-chip .scd-dot { width:7px; height:7px; border-radius:50%; flex:0 0 auto; }
            .scd-chip .scd-time { font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace; font-size:10px; color:var(--scd-soft); flex:0 0 auto; }
            .scd-chip .scd-ti { font-size:12px; font-weight:500; color:var(--scd-ink); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
            .scd-chip .scd-who { margin-left:auto; flex:0 0 auto; width:16px; height:16px; border-radius:50%; font-size:9px;
                font-weight:700; color:#fff; display:grid; place-items:center; }
            .scd-chip.is-miss { background:var(--scd-miss-weak); }
            .scd-chip.is-miss .scd-ti { color:var(--scd-miss); }
            .scd-chip.is-unscheduled { border-style:dashed; }
            .scd-chip .scd-st { flex:0 0 auto; font-size:9.5px; font-weight:600; text-transform:uppercase;
                letter-spacing:.04em; border:1px solid; border-radius:999px; padding:0 5px; line-height:14px; }

            .scd-more { font-size:11px; font-weight:600; color:var(--scd-accent); cursor:pointer; background:none; border:0; padding:1px 2px; text-align:left; }
            .scd-add { margin-top:auto; border:1px dashed var(--scd-line2); border-radius:7px; color:var(--scd-soft);
                font-size:11.5px; text-align:center; padding:5px 0; cursor:pointer; opacity:0; transition:opacity .12s; background:none; width:100%; }
            .scd-day:hover .scd-add { opacity:1; }

            .scd-legend { display:flex; flex-wrap:wrap; gap:16px; margin-top:14px; font-size:12px; color:var(--scd-soft); align-items:center; }
            .scd-legend span { display:inline-flex; align-items:center; gap:6px; }
            .scd-legend .scd-d { width:9px; height:9px; border-radius:50%; }

            .scd-feedback { position:fixed; bottom:24px; right:24px; background:var(--scd-ink); color:var(--scd-bg);
                padding:10px 14px; border-radius:9px; font-size:13px; z-index:9999; display:none; align-items:center; gap:12px;
                box-shadow:0 6px 20px rgba(0,0,0,.25); }
            .scd-feedback button { background:none; border:0; color:var(--scd-accent); font-weight:700; cursor:pointer; font-size:13px; }
            .scd-feedback.is-error { background:var(--scd-miss); color:#fff; }

            .scd-day:focus { outline:2px solid var(--scd-accent); outline-offset:-2px; }
            .scd-day:focus-visible { outline:2px solid var(--scd-accent); }
            .scd-chip:focus { outline:2px solid var(--scd-accent); outline-offset:1px; }
            .scd-chip.is-selected { box-shadow:0 0 0 2px var(--scd-accent); }

            .scd-selbar { position:fixed; bottom:24px; left:50%; transform:translateX(-50%); background:var(--scd-ink);
                color:var(--scd-bg); padding:8px 14px; border-radius:10px; font-size:13px; z-index:9998; display:none;
                align-items:center; gap:14px; box-shadow:0 6px 20px rgba(0,0,0,.25); }
            .scd-selbar.is-on { display:flex; }
            .scd-selbar button { background:none; border:0; color:var(--scd-accent); font-weight:700; cursor:pointer; font-size:13px; }
            .scd-selbar .scd-selhint { color:var(--scd-soft); font-size:11.5px; }

            .scd-pop-backdrop { position:fixed; inset:0; z-index:9999; display:flex; align-items:center; justify-content:center;
                background:rgba(0,0,0,.55); padding:16px; }
            .scd-pop { position:relative; z-index:10000; width:320px; max-width:100%; background:var(--scd-panel); color:var(--scd-ink);
                border:1px solid var(--scd-line2); border-radius:12px; box-shadow:0 12px 32px rgba(0,0,0,.28); padding:14px; }
            .scd-pop h3 { margin:0 0 6px; color:inherit; font-size:13px; font-weight:700; }
            .scd-pop label { display:block; font-size:11px; color:var(--scd-soft); margin:8px 0 3px; }
            .scd-pop input { width:100%; box-sizing:border-box; padding:7px 9px; font-size:13px; border:1px solid var(--scd-line2);
                border-radius:8px; background:var(--scd-bg); color:var(--scd-ink); }
            .scd-pop-actions { display:flex; align-items:center; gap:8px; margin-top:12px; }
            .scd-pop-actions .scd-spacer { flex:1; }
            .scd-pop-actions button, .scd-pop-actions a { font-size:13px; border-radius:8px; padding:6px 11px; cursor:pointer;
                text-decoration:none; border:1px solid var(--scd-line2); background:var(--scd-bg); color:var(--scd-ink); }
            .scd-pop-actions .scd-save { background:var(--scd-accent); border-color:var(--scd-accent); color:#fff; font-weight:600; }
            .scd-pop-actions .scd-del { color:var(--scd-miss); }
        ';
    }

    /** Resolve current request params. */
    private function params() {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters, no state change.
        $view = isset( $_GET['scd_view'] ) ? sanitize_key( wp_unslash( $_GET['scd_view'] ) ) : 'month';
        if ( ! in_array( $view, array( 'month', 'week' ), true ) ) {
            $view = 'month';
        }
        $month = isset( $_GET['scd_month'] ) ? sanitize_text_field( wp_unslash( $_GET['scd_month'] ) ) : wp_date( 'Y-m' );
        if ( ! preg_match( '/^\d{4}-\d{2}$/', $month ) ) {
            $month = wp_date( 'Y-m' );
        }
        $week = isset( $_GET['scd_week'] ) ? sanitize_text_field( wp_unslash( $_GET['scd_week'] ) ) : wp_date( 'Y-m-d' );
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $week ) ) {
            $week = wp_date( 'Y-m-d' );
        }
        $type   = isset( $_GET['scd_type'] ) ? sanitize_key( wp_unslash( $_GET['scd_type'] ) ) : '';
        $author = isset( $_GET['scd_author'] ) ? absint( $_GET['scd_author'] ) : 0;

        $status = isset( $_GET['scd_status'] ) ? sanitize_key( wp_unslash( $_GET['scd_status'] ) ) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        if ( '' !== $status && ! SCD_Statuses::is_selected( $status ) ) {
            $status = '';
        }

        return compact( 'view', 'month', 'week', 'type', 'author', 'status' );
    }

    /** Post types the calendar considers, from settings (fallback to post). */
    private function base_types() {
        $types = (array) SCD_Settings::get( 'included_post_types', array( 'post' ) );
        $types = array_values( array_filter( $types, 'post_type_exists' ) );
        return $types ? $types : array( 'post' );
    }

    public function render_page() {
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_die( esc_html__( 'You do not have permission to view this page.', 'scheduled-content-dashboard' ) );
        }

        $p     = $this->params();
        $today = wp_date( 'Y-m-d' );

        if ( 'week' === $p['view'] ) {
            $start = $this->week_start( $p['week'] );
            $end   = gmdate( 'Y-m-d', strtotime( $start . ' +6 days' ) );
        } else {
            $month_start = SCD_Dates::month_start( $p['month'] );
            $start       = $month_start->format( 'Y-m-d' );
            $end         = $month_start->format( 'Y-m-t' );
        }

        $items_by_day = $this->build_items_by_day( $start, $end, $p['type'], $p['author'], $p['status'] );
        $authors      = $this->author_choices();
        $base_url     = menu_page_url( self::PAGE_SLUG, false );

        ?>
        <div class="wrap scd-cal">
            <?php $this->render_toolbar( $p, $base_url, $start ); ?>

            <?php
            if ( 'week' === $p['view'] ) {
                $this->render_week( $start, $items_by_day, $today );
            } else {
                $this->render_month( $p['month'], $items_by_day, $today );
            }
            ?>

            <div class="scd-legend">
                <?php $legend_statuses = array_diff( SCD_Statuses::selected(), array( 'future' ) ); ?>
                <span><span class="scd-d" style="background:<?php echo esc_attr( $this->type_color( 'post' ) ); ?>"></span><?php esc_html_e( 'Post', 'scheduled-content-dashboard' ); ?></span>
                <span><span class="scd-d" style="background:<?php echo esc_attr( $this->type_color( 'page' ) ); ?>"></span><?php esc_html_e( 'Page', 'scheduled-content-dashboard' ); ?></span>
                <?php foreach ( $legend_statuses as $legend_status ) : ?>
                    <span><span class="scd-d" style="background:<?php echo esc_attr( SCD_Statuses::color( $legend_status ) ); ?>"></span><?php echo esc_html( SCD_Statuses::label( $legend_status ) ); ?></span>
                <?php endforeach; ?>
                <span><span class="scd-d" style="background:var(--scd-miss)"></span><?php esc_html_e( 'Missed schedule', 'scheduled-content-dashboard' ); ?></span>
                <span class="scd-spacer"></span>
                <span style="font-size:11px"><?php esc_html_e( 'Drag to reschedule · click an empty day to create', 'scheduled-content-dashboard' ); ?></span>
            </div>

            <div id="scd-cal-feedback" class="scd-feedback" role="status" aria-live="polite"></div>

            <div id="scd-selbar" class="scd-selbar" role="status" aria-live="polite">
                <span class="scd-selcount"></span>
                <span class="scd-selhint"></span>
                <button type="button" class="scd-selclear"></button>
            </div>
        </div>
        <?php
    }

    private function render_toolbar( $p, $base_url, $start ) {
        $is_week  = ( 'week' === $p['view'] );
        $title    = $is_week
            ? $this->week_label( $start )
            : wp_date( 'F Y', SCD_Dates::month_start( $p['month'] )->getTimestamp() );

        // Prev / today / next targets.
        if ( $is_week ) {
            $prev = add_query_arg( array( 'scd_view' => 'week', 'scd_week' => gmdate( 'Y-m-d', strtotime( $start . ' -7 days' ) ) ), $base_url );
            $next = add_query_arg( array( 'scd_view' => 'week', 'scd_week' => gmdate( 'Y-m-d', strtotime( $start . ' +7 days' ) ) ), $base_url );
            $now  = add_query_arg( array( 'scd_view' => 'week', 'scd_week' => wp_date( 'Y-m-d' ) ), $base_url );
        } else {
            $prev = add_query_arg( array( 'scd_view' => 'month', 'scd_month' => SCD_Dates::shift_month( $p['month'], -1 ) ), $base_url );
            $next = add_query_arg( array( 'scd_view' => 'month', 'scd_month' => SCD_Dates::shift_month( $p['month'], 1 ) ), $base_url );
            $now  = add_query_arg( array( 'scd_view' => 'month', 'scd_month' => wp_date( 'Y-m' ) ), $base_url );
        }

        $month_view = add_query_arg( array( 'scd_view' => 'month', 'scd_type' => $p['type'], 'scd_author' => $p['author'] ?: false, 'scd_status' => $p['status'] ), $base_url );
        $week_view  = add_query_arg( array( 'scd_view' => 'week', 'scd_week' => $is_week ? $start : wp_date( 'Y-m-d' ), 'scd_type' => $p['type'], 'scd_author' => $p['author'] ?: false, 'scd_status' => $p['status'] ), $base_url );

        $ics_url = wp_nonce_url(
            admin_url(
                'admin-post.php?action=' . self::ICS_ACTION
                . '&scd_month=' . rawurlencode( $is_week ? substr( $start, 0, 7 ) : $p['month'] )
                . '&scd_status=' . rawurlencode( $p['status'] )
            ),
            self::ICS_ACTION
        );
        ?>
        <div class="scd-cal-toolbar">
            <h1 class="scd-cal-title"><?php echo esc_html( $title ); ?></h1>

            <span class="scd-seg">
                <a href="<?php echo esc_url( $month_view ); ?>" class="<?php echo $is_week ? '' : 'is-on'; ?>"><?php esc_html_e( 'Month', 'scheduled-content-dashboard' ); ?></a>
                <a href="<?php echo esc_url( $week_view ); ?>" class="<?php echo $is_week ? 'is-on' : ''; ?>"><?php esc_html_e( 'Week', 'scheduled-content-dashboard' ); ?></a>
            </span>

            <form class="scd-cal-filters" method="get">
                <input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>" />
                <input type="hidden" name="scd_view" value="<?php echo esc_attr( $p['view'] ); ?>" />
                <input type="hidden" name="scd_month" value="<?php echo esc_attr( $p['month'] ); ?>" />
                <input type="hidden" name="scd_week" value="<?php echo esc_attr( $is_week ? $start : $p['week'] ); ?>" />
                <select name="scd_type" onchange="this.form.submit()" aria-label="<?php esc_attr_e( 'Filter by type', 'scheduled-content-dashboard' ); ?>">
                    <option value=""><?php esc_html_e( 'All types', 'scheduled-content-dashboard' ); ?></option>
                    <?php foreach ( $this->base_types() as $t ) :
                        $obj = get_post_type_object( $t ); ?>
                        <option value="<?php echo esc_attr( $t ); ?>" <?php selected( $p['type'], $t ); ?>><?php echo esc_html( $obj ? $obj->labels->name : $t ); ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="scd_author" onchange="this.form.submit()" aria-label="<?php esc_attr_e( 'Filter by author', 'scheduled-content-dashboard' ); ?>">
                    <option value="0"><?php esc_html_e( 'All authors', 'scheduled-content-dashboard' ); ?></option>
                    <?php foreach ( $this->author_choices() as $id => $name ) : ?>
                        <option value="<?php echo (int) $id; ?>" <?php selected( $p['author'], $id ); ?>><?php echo esc_html( $name ); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php $statuses = SCD_Statuses::selected(); ?>
                <?php if ( count( $statuses ) > 1 ) : ?>
                    <select name="scd_status" onchange="this.form.submit()" aria-label="<?php esc_attr_e( 'Filter by status', 'scheduled-content-dashboard' ); ?>">
                        <option value=""><?php esc_html_e( 'All statuses', 'scheduled-content-dashboard' ); ?></option>
                        <?php foreach ( $statuses as $status ) : ?>
                            <option value="<?php echo esc_attr( $status ); ?>" <?php selected( $p['status'], $status ); ?>>
                                <?php echo esc_html( SCD_Statuses::label( $status ) ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </form>

            <span class="scd-nav">
                <a href="<?php echo esc_url( $prev ); ?>" aria-label="<?php esc_attr_e( 'Previous', 'scheduled-content-dashboard' ); ?>">&larr;</a>
                <a href="<?php echo esc_url( $now ); ?>"><?php esc_html_e( 'Today', 'scheduled-content-dashboard' ); ?></a>
                <a href="<?php echo esc_url( $next ); ?>" aria-label="<?php esc_attr_e( 'Next', 'scheduled-content-dashboard' ); ?>">&rarr;</a>
            </span>

            <span class="scd-spacer"></span>

            <a class="scd-btn scd-btn-ghost" href="<?php echo esc_url( $ics_url ); ?>"><?php esc_html_e( 'Export .ics', 'scheduled-content-dashboard' ); ?></a>
            <a class="scd-btn scd-btn-primary" href="<?php echo esc_url( admin_url( 'post-new.php' ) ); ?>">+ <?php esc_html_e( 'New post', 'scheduled-content-dashboard' ); ?></a>
        </div>
        <?php
    }

    private function render_month( $month, $items_by_day, $today ) {
        $month_start   = SCD_Dates::month_start( $month );
        $year          = (int) $month_start->format( 'Y' );
        $month_num     = (int) $month_start->format( 'n' );
        $days_in       = (int) $month_start->format( 't' );
        $first_weekday = (int) $month_start->format( 'w' );
        $start_of_week = (int) get_option( 'start_of_week', 0 );
        $offset        = ( $first_weekday - $start_of_week + 7 ) % 7;
        ?>
        <div class="scd-grid">
            <?php
            for ( $i = 0; $i < 7; $i++ ) {
                $dow = ( $start_of_week + $i ) % 7;
                echo '<div class="scd-dow">' . esc_html( $this->dow_abbr( $dow ) ) . '</div>';
            }

            for ( $i = 0; $i < $offset; $i++ ) {
                echo '<div class="scd-day scd-day--out"></div>';
            }

            for ( $day = 1; $day <= $days_in; $day++ ) {
                $date_key = sprintf( '%04d-%02d-%02d', $year, $month_num, $day );
                $items    = $items_by_day[ $date_key ] ?? array();
                $this->render_day_cell( $date_key, $day, $items, $today, false );
            }

            $trailing = ( 7 - ( ( $offset + $days_in ) % 7 ) ) % 7;
            for ( $i = 0; $i < $trailing; $i++ ) {
                echo '<div class="scd-day scd-day--out"></div>';
            }
            ?>
        </div>
        <?php
    }

    private function render_week( $start, $items_by_day, $today ) {
        $start_of_week = (int) get_option( 'start_of_week', 0 );
        ?>
        <div class="scd-grid">
            <?php
            for ( $i = 0; $i < 7; $i++ ) {
                $dow = ( $start_of_week + $i ) % 7;
                echo '<div class="scd-dow">' . esc_html( $this->dow_abbr( $dow ) ) . '</div>';
            }
            for ( $i = 0; $i < 7; $i++ ) {
                $date_key = gmdate( 'Y-m-d', strtotime( $start . ' +' . $i . ' days' ) );
                $day_num  = (int) gmdate( 'j', strtotime( $date_key ) );
                $items    = $items_by_day[ $date_key ] ?? array();
                $this->render_day_cell( $date_key, $day_num, $items, $today, true );
            }
            ?>
        </div>
        <?php
    }

    private function render_day_cell( $date_key, $day_num, $items, $today, $is_week ) {
        $classes = array( 'scd-day' );
        if ( $is_week ) {
            $classes[] = 'scd-day--week';
        }
        $dow = (int) gmdate( 'w', strtotime( $date_key ) );
        if ( 0 === $dow || 6 === $dow ) {
            $classes[] = 'scd-day--wknd';
        }
        if ( $date_key === $today ) {
            $classes[] = 'scd-day--today';
        }

        $cap       = $is_week ? 999 : self::MONTH_CAP;
        $overflow  = max( 0, count( $items ) - $cap );

        echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '" data-date="' . esc_attr( $date_key ) . '"'
            . ' tabindex="0" role="gridcell" aria-label="' . esc_attr( mysql2date( 'l, F j', $date_key ) ) . '">';
        echo '<div class="scd-daynum">' . (int) $day_num . '</div>';

        $idx = 0;
        foreach ( $items as $item ) {
            $this->render_chip( $item, $idx >= $cap );
            $idx++;
        }

        if ( $overflow > 0 ) {
            printf(
                '<button type="button" class="scd-more" data-more="%1$d" data-less="%2$s">%3$s</button>',
                (int) $overflow,
                esc_attr__( 'Show less', 'scheduled-content-dashboard' ),
                esc_html( sprintf(
                    /* translators: %d: number of additional items. */
                    _n( '+%d more', '+%d more', $overflow, 'scheduled-content-dashboard' ),
                    $overflow
                ) )
            );
        }

        echo '<button type="button" class="scd-add" aria-label="' . esc_attr__( 'Add a post on this day', 'scheduled-content-dashboard' ) . '">+ ' . esc_html__( 'Add', 'scheduled-content-dashboard' ) . '</button>';
        echo '</div>';
    }

    private function render_chip( $item, $hidden ) {
        $status    = isset( $item['status'] ) ? $item['status'] : 'future';
        $scheduled = ( 'future' === $status );

        $classes = array( 'scd-chip' );
        if ( $item['is_missed'] ) {
            $classes[] = 'is-miss';
        }
        if ( ! $scheduled ) {
            $classes[] = 'is-unscheduled';
        }

        if ( $item['is_missed'] ) {
            $dot = 'var(--scd-miss)';
        } elseif ( $scheduled ) {
            $dot = $this->type_color( $item['type'] );
        } else {
            $dot = SCD_Statuses::color( $status );
        }

        $tooltip = $item['time_formatted'] . ' · ' . $item['type_label'] . ' · ' . $item['author'];
        if ( ! $scheduled ) {
            $tooltip .= ' · ' . $item['status_label'];
        }

        // Scheduled chips lead with the time. Anything else leads with its status,
        // which matters more than the hour and leaves the title its width.
        $lead = $scheduled
            ? sprintf( '<span class="scd-time">%s</span>', esc_html( substr( $item['time'], 0, 5 ) ) )
            : sprintf(
                '<span class="scd-st" style="color:%1$s;border-color:%1$s">%2$s</span>',
                esc_attr( SCD_Statuses::color( $status ) ),
                esc_html( $item['status_label'] )
            );

        printf(
            '<a class="%1$s" href="%2$s" %3$s draggable="true" data-post-id="%4$d" data-time="%5$s" data-title="%6$s" data-status="%7$s" title="%8$s">'
            . '<span class="scd-dot" style="background:%9$s"></span>'
            . '%10$s'
            . '<span class="scd-ti">%11$s</span>'
            . '<span class="scd-who" style="background:%12$s">%13$s</span>'
            . '</a>',
            esc_attr( implode( ' ', $classes ) ),
            esc_url( $item['edit_link'] ),
            $hidden ? 'hidden' : '',
            (int) $item['id'],
            esc_attr( $item['time'] ),
            esc_attr( $item['title'] ),
            esc_attr( $status ),
            esc_attr( $tooltip ),
            esc_attr( $dot ),
            wp_kses_post( $lead ),
            esc_html( $item['title'] ),
            esc_attr( $this->author_color( $item['author'] ) ),
            esc_html( $this->initial( $item['author'] ) )
        );
    }

    private function build_items_by_day( $start, $end, $type, $author, $status = '' ) {
        $base     = $type && in_array( $type, $this->base_types(), true ) ? array( $type ) : $this->base_types();
        $statuses = $status && SCD_Statuses::is_selected( $status ) ? array( $status ) : SCD_Statuses::selected();

        // Filter on the local post_date column: drafts can carry a zeroed
        // post_date_gmt, so a GMT window would drop them.
        $args = array(
            'post_type'      => $base,
            'post_status'    => $statuses,
            'posts_per_page' => 500,
            'orderby'        => 'date',
            'order'          => 'ASC',
            'no_found_rows'  => true,
            'date_query'     => array(
                array(
                    'column'    => 'post_date',
                    'after'     => $start . ' 00:00:00',
                    'before'    => $end . ' 23:59:59',
                    'inclusive' => true,
                ),
            ),
        );
        if ( $author > 0 ) {
            $args['author'] = $author;
        }
        // Users who can't edit others' posts only see their own scheduled content.
        if ( ! current_user_can( 'edit_others_posts' ) ) {
            $args['author'] = get_current_user_id();
        }

        $posts   = get_posts( $args );
        $now_gmt = time();
        $grouped = array();

        foreach ( $posts as $post ) {
            $local_date = mysql2date( 'Y-m-d', $post->post_date, false );
            $type_obj   = get_post_type_object( $post->post_type );

            $grouped[ $local_date ][] = array(
                'id'             => (int) $post->ID,
                'title'          => $post->post_title ? $post->post_title : __( '(no title)', 'scheduled-content-dashboard' ),
                'time'           => mysql2date( 'H:i:s', $post->post_date, false ),
                'time_formatted' => mysql2date( get_option( 'time_format' ), $post->post_date ),
                'edit_link'      => get_edit_post_link( $post->ID, 'raw' ),
                'type'           => $post->post_type,
                'type_label'     => $type_obj ? $type_obj->labels->singular_name : $post->post_type,
                'author'         => get_the_author_meta( 'display_name', $post->post_author ),
                'status'         => $post->post_status,
                'status_label'   => SCD_Statuses::label( $post->post_status ),
                'is_missed'      => SCD_Statuses::can_miss( $post->post_status )
                    && strtotime( get_gmt_from_date( $post->post_date ) . ' UTC' ) <= $now_gmt,
            );
        }

        return $grouped;
    }

    /** Authors who currently have pipeline content in the base types. */
    private function author_choices() {
        $args = array(
            'post_type'      => $this->base_types(),
            'post_status'    => SCD_Statuses::selected(),
            'posts_per_page' => 500,
            'no_found_rows'  => true,
        );
        // Don't reveal other authors to users who can't edit others' posts.
        if ( ! current_user_can( 'edit_others_posts' ) ) {
            $args['author'] = get_current_user_id();
        }
        $authors = array();
        foreach ( get_posts( $args ) as $post ) {
            $authors[ (int) $post->post_author ] = get_the_author_meta( 'display_name', $post->post_author );
        }
        asort( $authors );
        return $authors;
    }

    /** Stable color for a post type. */
    private function type_color( $type ) {
        $map = array(
            'post' => 'var(--scd-accent)',
            'page' => '#d97706',
        );
        if ( isset( $map[ $type ] ) ) {
            return $map[ $type ];
        }
        $palette = array( '#0d9488', '#db2777', '#7c3aed', '#0284c7', '#65a30d' );
        $i       = abs( crc32( $type ) ) % count( $palette );
        return $palette[ $i ];
    }

    private function author_color( $name ) {
        $palette = array( '#475569', '#7c3aed', '#0d9488', '#b45309', '#be123c', '#1d4ed8' );
        $i       = abs( crc32( (string) $name ) ) % count( $palette );
        return $palette[ $i ];
    }

    private function initial( $name ) {
        $name = trim( (string) $name );
        return $name ? mb_strtoupper( mb_substr( $name, 0, 1 ) ) : '?';
    }

    private function week_start( $date ) {
        $start_of_week = (int) get_option( 'start_of_week', 0 );
        $dow           = (int) gmdate( 'w', strtotime( $date ) );
        $diff          = ( $dow - $start_of_week + 7 ) % 7;
        return gmdate( 'Y-m-d', strtotime( $date . ' -' . $diff . ' days' ) );
    }

    private function week_label( $start ) {
        $end = gmdate( 'Y-m-d', strtotime( $start . ' +6 days' ) );
        return mysql2date( 'M j', $start ) . ' – ' . mysql2date( 'M j, Y', $end );
    }

    private function dow_abbr( $dow ) {
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

        $local = $new_date . ' ' . $time;
        if ( false === strtotime( $local ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid date.', 'scheduled-content-dashboard' ) ), 400 );
        }

        $post = get_post( $post_id );
        if ( ! $post || ! SCD_Statuses::is_selected( $post->post_status ) ) {
            wp_send_json_error( array( 'message' => __( 'This post is not in the content pipeline.', 'scheduled-content-dashboard' ) ), 400 );
        }

        // Keep whatever status the post already has: moving a draft changes its
        // intended date, it doesn't schedule or publish it.
        $result = wp_update_post(
            array(
                'ID'            => $post_id,
                'post_date'     => $local,
                'post_date_gmt' => get_gmt_from_date( $local ),
                'post_status'   => $post->post_status,
                'edit_date'     => true,
            ),
            true
        );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ), 500 );
        }

        wp_send_json_success( array( 'id' => $post_id, 'date' => $local ) );
    }

    public function handle_create() {
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );

        $date  = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';
        $time  = isset( $_POST['time'] ) ? sanitize_text_field( wp_unslash( $_POST['time'] ) ) : '09:00';
        $title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';

        $types = $this->base_types();
        $type  = $types[0];

        if ( ! current_user_can( get_post_type_object( $type )->cap->create_posts ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'scheduled-content-dashboard' ) ), 403 );
        }
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid date.', 'scheduled-content-dashboard' ) ), 400 );
        }
        if ( ! preg_match( '/^\d{2}:\d{2}$/', $time ) ) {
            $time = '09:00';
        }
        if ( '' === $title ) {
            $title = __( '(no title)', 'scheduled-content-dashboard' );
        }

        $local = $date . ' ' . $time . ':00';
        // Ensure the date is in the future so the post stays 'future'.
        if ( strtotime( get_gmt_from_date( $local ) . ' UTC' ) <= time() ) {
            $local = wp_date( 'Y-m-d H:i:s', time() + 3600 );
        }

        $id = wp_insert_post(
            array(
                'post_title'    => $title,
                'post_type'     => $type,
                'post_status'   => 'future',
                'post_date'     => $local,
                'post_date_gmt' => get_gmt_from_date( $local ),
                'edit_date'     => true,
            ),
            true
        );

        if ( is_wp_error( $id ) ) {
            wp_send_json_error( array( 'message' => $id->get_error_message() ), 500 );
        }

        wp_send_json_success(
            array(
                'id'        => (int) $id,
                'edit_link' => get_edit_post_link( $id, 'raw' ),
                'title'     => $title,
            )
        );
    }

    public function handle_edit() {
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );

        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        $title   = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
        $when    = isset( $_POST['when'] ) ? sanitize_text_field( wp_unslash( $_POST['when'] ) ) : '';

        if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'scheduled-content-dashboard' ) ), 403 );
        }

        $post = get_post( $post_id );
        if ( ! $post || ! SCD_Statuses::is_selected( $post->post_status ) ) {
            wp_send_json_error( array( 'message' => __( 'This post is not in the content pipeline.', 'scheduled-content-dashboard' ) ), 400 );
        }
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $when ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid date.', 'scheduled-content-dashboard' ) ), 400 );
        }
        if ( '' === $title ) {
            $title = __( '(no title)', 'scheduled-content-dashboard' );
        }

        $local = str_replace( 'T', ' ', $when ) . ':00';
        // A scheduled post dated in the past publishes itself, so push it out.
        // Drafts and custom statuses can sit on any date.
        if ( 'future' === $post->post_status && strtotime( get_gmt_from_date( $local ) . ' UTC' ) <= time() ) {
            $local = wp_date( 'Y-m-d H:i:s', time() + 3600 );
        }

        $result = wp_update_post(
            array(
                'ID'            => $post_id,
                'post_title'    => $title,
                'post_date'     => $local,
                'post_date_gmt' => get_gmt_from_date( $local ),
                'post_status'   => $post->post_status,
                'edit_date'     => true,
            ),
            true
        );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ), 500 );
        }

        wp_send_json_success(
            array(
                'id'    => $post_id,
                'title' => $title,
                'date'  => mysql2date( 'Y-m-d', $local, false ),
                'time'  => mysql2date( 'H:i:s', $local, false ),
            )
        );
    }

    public function handle_trash() {
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );

        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        if ( ! $post_id || ! current_user_can( 'delete_post', $post_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'scheduled-content-dashboard' ) ), 403 );
        }
        if ( ! wp_trash_post( $post_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Could not move to trash.', 'scheduled-content-dashboard' ) ), 500 );
        }
        wp_send_json_success( array( 'id' => $post_id ) );
    }

    public function handle_ics() {
        if ( ! current_user_can( 'edit_posts' ) || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), self::ICS_ACTION ) ) {
            wp_die( esc_html__( 'Permission denied.', 'scheduled-content-dashboard' ) );
        }

        $month = isset( $_GET['scd_month'] ) ? sanitize_text_field( wp_unslash( $_GET['scd_month'] ) ) : wp_date( 'Y-m' );
        if ( ! preg_match( '/^\d{4}-\d{2}$/', $month ) ) {
            $month = wp_date( 'Y-m' );
        }
        $status = isset( $_GET['scd_status'] ) ? sanitize_key( wp_unslash( $_GET['scd_status'] ) ) : '';
        if ( '' !== $status && ! SCD_Statuses::is_selected( $status ) ) {
            $status = '';
        }

        $month_start = SCD_Dates::month_start( $month );
        $start       = $month_start->format( 'Y-m-d' );
        $end         = $month_start->format( 'Y-m-t' );

        $by_day = $this->build_items_by_day( $start, $end, '', 0, $status );

        $lines   = array( 'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Scheduled Content Dashboard//EN', 'CALSCALE:GREGORIAN' );
        $dtstamp = gmdate( 'Ymd\THis\Z' );
        foreach ( $by_day as $date => $items ) {
            foreach ( $items as $item ) {
                $dt = gmdate( 'Ymd\THis\Z', strtotime( get_gmt_from_date( $date . ' ' . $item['time'] ) . ' UTC' ) );
                $lines[] = 'BEGIN:VEVENT';
                $lines[] = 'UID:scd-' . $item['id'] . '@' . wp_parse_url( home_url(), PHP_URL_HOST );
                $lines[] = 'DTSTAMP:' . $dtstamp;
                $lines[] = 'DTSTART:' . $dt;
                $meta    = 'future' === $item['status'] ? $item['type_label'] : $item['type_label'] . ', ' . $item['status_label'];
                $lines[] = 'SUMMARY:' . $this->ics_escape( $item['title'] . ' (' . $meta . ')' );
                $lines[] = 'END:VEVENT';
            }
        }
        $lines[] = 'END:VCALENDAR';

        nocache_headers();
        header( 'Content-Type: text/calendar; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="scheduled-' . $month . '.ics"' );
        echo implode( "\r\n", $lines ) . "\r\n"; // phpcs:ignore WordPress.Security.EscapeOutput
        exit;
    }

    private function ics_escape( $text ) {
        // RFC 5545 TEXT escaping, in order: backslash, then ; and , then newlines.
        $text = str_replace( '\\', '\\\\', $text );
        $text = str_replace( array( ';', ',' ), array( '\\;', '\\,' ), $text );
        $text = str_replace( array( "\r\n", "\r", "\n" ), '\\n', $text );
        return $text;
    }
}
