<?php
/**
 * Content Overview: everything in the pipeline, grouped and printable.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SCD_Overview {

    const PAGE_SLUG = 'scd-overview';

    /** Hard ceiling per request. Anything above this is reported, never dropped silently. */
    const MAX_ITEMS = 200;

    private $hook = '';

    public static function init() {
        $self = new self();
        add_action( 'admin_menu', array( $self, 'add_menu' ), 6 );
        add_action( 'admin_enqueue_scripts', array( $self, 'enqueue' ) );
    }

    public function add_menu() {
        $this->hook = (string) add_submenu_page(
            SCD_Admin_Page::PAGE_SLUG,
            __( 'Content Overview', 'scheduled-content-dashboard' ),
            __( 'Overview', 'scheduled-content-dashboard' ),
            'edit_posts',
            self::PAGE_SLUG,
            array( $this, 'render_page' )
        );
    }

    public function enqueue( $hook ) {
        if ( ! $this->hook || $hook !== $this->hook ) {
            return;
        }
        wp_add_inline_style( 'common', $this->styles() );
    }

    private function styles() {
        return '
            .scd-ov { --scd-panel:#ffffff; --scd-ink:#1d2230; --scd-soft:#5b6472; --scd-line:#e6e8ee;
                --scd-line2:#d6dae3; --scd-accent:#4f46e5; --scd-miss:#dc2626; --scd-miss-weak:#fdecec; --scd-zebra:#fafbfd;
                font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",system-ui,sans-serif; color:var(--scd-ink); }
            @media (prefers-color-scheme: dark) {
                .scd-ov { --scd-panel:#161922; --scd-ink:#e8eaf0; --scd-soft:#9aa3b2; --scd-line:#252a36;
                    --scd-line2:#313747; --scd-accent:#8b85f5; --scd-miss:#f87171; --scd-miss-weak:#2a1a1d; --scd-zebra:#12141c; }
            }

            .scd-ov-toolbar { display:flex; align-items:center; flex-wrap:wrap; gap:10px; margin:14px 0; }
            .scd-ov-title { font-size:20px; font-weight:700; letter-spacing:-.02em; margin:0; }
            .scd-ov-toolbar form { display:inline-flex; gap:8px; align-items:center; flex-wrap:wrap; }
            .scd-ov-toolbar select { font-size:13px; padding:4px 8px; border:1px solid var(--scd-line2);
                border-radius:8px; background:var(--scd-panel); color:var(--scd-ink); max-width:180px; }
            .scd-ov-spacer { flex:1; }
            .scd-ov-btn { text-decoration:none; font-size:13px; font-weight:600; border-radius:8px; padding:6px 13px;
                border:1px solid var(--scd-line2); color:var(--scd-ink); background:var(--scd-panel); cursor:pointer; }
            .scd-ov-btn:hover { border-color:var(--scd-accent); color:var(--scd-ink); }

            .scd-ov-summary { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:16px; }
            .scd-ov-stat { display:inline-flex; align-items:center; gap:7px; border:1px solid var(--scd-line);
                border-radius:9px; padding:6px 11px; background:var(--scd-panel); font-size:12.5px; }
            .scd-ov-stat b { font-size:15px; font-weight:700; }
            .scd-ov-stat .scd-d { width:9px; height:9px; border-radius:50%; }
            .scd-ov-stat--miss { border-color:var(--scd-miss); color:var(--scd-miss); }

            .scd-ov-group { margin-bottom:22px; break-inside:avoid; }
            .scd-ov-group h2 { font-size:13px; font-weight:700; text-transform:uppercase; letter-spacing:.06em;
                color:var(--scd-soft); margin:0 0 7px; display:flex; align-items:center; gap:8px; }
            .scd-ov-group h2 .scd-count { font-weight:600; color:var(--scd-soft); opacity:.75; text-transform:none; letter-spacing:0; }
            .scd-ov-table { width:100%; border-collapse:collapse; border:1px solid var(--scd-line);
                border-radius:10px; overflow:hidden; background:var(--scd-panel); }
            .scd-ov-table th { text-align:left; font-size:10.5px; font-weight:600; letter-spacing:.07em;
                text-transform:uppercase; color:var(--scd-soft); padding:8px 12px; border-bottom:1px solid var(--scd-line); }
            .scd-ov-table td { padding:9px 12px; font-size:13px; border-bottom:1px solid var(--scd-line); vertical-align:top; }
            .scd-ov-table tr:last-child td { border-bottom:0; }
            .scd-ov-table tbody tr:nth-child(even) { background:var(--scd-zebra); }
            .scd-ov-table tr.is-miss { background:var(--scd-miss-weak); }
            .scd-ov-table a.scd-ov-link { text-decoration:none; font-weight:600; color:var(--scd-ink); }
            .scd-ov-table a.scd-ov-link:hover { color:var(--scd-accent); }
            .scd-ov-when { font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace; font-size:12px; white-space:nowrap; }
            .scd-ov-when.is-miss { color:var(--scd-miss); font-weight:600; }
            .scd-ov-pill { display:inline-flex; align-items:center; gap:5px; font-size:11px; font-weight:600;
                padding:2px 8px; border-radius:999px; border:1px solid var(--scd-line2); white-space:nowrap; }
            .scd-ov-pill .scd-d { width:7px; height:7px; border-radius:50%; }
            .scd-ov-terms { color:var(--scd-soft); font-size:12px; }
            .scd-ov-empty { border:1px dashed var(--scd-line2); border-radius:10px; padding:28px;
                text-align:center; color:var(--scd-soft); font-size:13px; }
            .scd-ov-truncated { border-left:3px solid var(--scd-accent); background:var(--scd-panel);
                padding:9px 12px; margin-bottom:14px; font-size:12.5px; }
            .scd-ov-publish { background:none; border:0; padding:0; color:var(--scd-miss); font-weight:600;
                font-size:12px; cursor:pointer; text-decoration:underline; }

            @media print {
                #adminmenumain, #wpadminbar, #wpfooter, .scd-ov-toolbar, .notice, .update-nag, .scd-ov-publish { display:none !important; }
                #wpcontent, #wpbody-content { margin:0 !important; padding:0 !important; }
                .scd-ov { color:#000; }
                .scd-ov-table { border-color:#999; }
                .scd-ov-table td, .scd-ov-table th { border-color:#ccc; }
                .scd-ov-group { page-break-inside:avoid; }
            }
        ';
    }

    private function params() {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters, no state change.
        $group = isset( $_GET['scd_group'] ) ? sanitize_key( wp_unslash( $_GET['scd_group'] ) ) : '';
        if ( ! array_key_exists( $group, $this->group_choices() ) ) {
            $group = (string) SCD_Settings::get( 'overview_group_by', 'status' );
        }
        if ( ! array_key_exists( $group, $this->group_choices() ) ) {
            $group = 'status';
        }

        $window = isset( $_GET['scd_window'] ) ? sanitize_key( wp_unslash( $_GET['scd_window'] ) ) : 'all';
        if ( ! in_array( $window, array( 'all', 'upcoming', 'overdue' ), true ) ) {
            $window = 'all';
        }

        $status = isset( $_GET['scd_status'] ) ? sanitize_key( wp_unslash( $_GET['scd_status'] ) ) : '';
        if ( '' !== $status && ! SCD_Statuses::is_selected( $status ) ) {
            $status = '';
        }

        $type = isset( $_GET['scd_type'] ) ? sanitize_key( wp_unslash( $_GET['scd_type'] ) ) : '';
        if ( '' !== $type && ! in_array( $type, $this->base_types(), true ) ) {
            $type = '';
        }

        $author = isset( $_GET['scd_author'] ) ? absint( $_GET['scd_author'] ) : 0;
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        return compact( 'group', 'window', 'status', 'type', 'author' );
    }

    private function group_choices() {
        return array(
            'status'   => __( 'Status', 'scheduled-content-dashboard' ),
            'type'     => __( 'Post type', 'scheduled-content-dashboard' ),
            'author'   => __( 'Author', 'scheduled-content-dashboard' ),
            'category' => __( 'Category', 'scheduled-content-dashboard' ),
        );
    }

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
        $found = 0;
        $items = $this->query_items( $p, $found );
        ?>
        <div class="wrap scd-ov">
            <?php $this->render_toolbar( $p ); ?>
            <?php $this->maybe_render_notice(); ?>
            <?php $this->render_summary( $items ); ?>

            <?php if ( $found > count( $items ) ) : ?>
                <div class="scd-ov-truncated">
                    <?php
                    printf(
                        /* translators: 1: number of items listed, 2: total number of matching items. */
                        esc_html__( 'Showing the first %1$d of %2$d matching items. Narrow the filters to see the rest.', 'scheduled-content-dashboard' ),
                        (int) count( $items ),
                        (int) $found
                    );
                    ?>
                </div>
            <?php endif; ?>

            <?php if ( empty( $items ) ) : ?>
                <div class="scd-ov-empty"><?php esc_html_e( 'Nothing in the pipeline for these filters.', 'scheduled-content-dashboard' ); ?></div>
            <?php else : ?>
                <?php foreach ( $this->group_items( $items, $p['group'] ) as $group ) : ?>
                    <div class="scd-ov-group">
                        <h2>
                            <?php if ( ! empty( $group['color'] ) ) : ?>
                                <span class="scd-d" style="width:9px;height:9px;border-radius:50%;display:inline-block;background:<?php echo esc_attr( $group['color'] ); ?>"></span>
                            <?php endif; ?>
                            <?php echo esc_html( $group['label'] ); ?>
                            <span class="scd-count"><?php echo (int) count( $group['items'] ); ?></span>
                        </h2>
                        <?php $this->render_table( $group['items'], $p['group'] ); ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php
    }

    private function render_toolbar( $p ) {
        $base = menu_page_url( self::PAGE_SLUG, false );
        ?>
        <div class="scd-ov-toolbar">
            <h1 class="scd-ov-title"><?php esc_html_e( 'Content Overview', 'scheduled-content-dashboard' ); ?></h1>

            <form method="get">
                <input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>" />

                <select name="scd_group" onchange="this.form.submit()" aria-label="<?php esc_attr_e( 'Group by', 'scheduled-content-dashboard' ); ?>">
                    <?php foreach ( $this->group_choices() as $key => $label ) : ?>
                        <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $p['group'], $key ); ?>>
                            <?php
                            printf(
                                /* translators: %s: grouping dimension, e.g. Status. */
                                esc_html__( 'Group by %s', 'scheduled-content-dashboard' ),
                                esc_html( $label )
                            );
                            ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="scd_status" onchange="this.form.submit()" aria-label="<?php esc_attr_e( 'Filter by status', 'scheduled-content-dashboard' ); ?>">
                    <option value=""><?php esc_html_e( 'All statuses', 'scheduled-content-dashboard' ); ?></option>
                    <?php foreach ( SCD_Statuses::selected() as $status ) : ?>
                        <option value="<?php echo esc_attr( $status ); ?>" <?php selected( $p['status'], $status ); ?>>
                            <?php echo esc_html( SCD_Statuses::label( $status ) ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="scd_type" onchange="this.form.submit()" aria-label="<?php esc_attr_e( 'Filter by type', 'scheduled-content-dashboard' ); ?>">
                    <option value=""><?php esc_html_e( 'All types', 'scheduled-content-dashboard' ); ?></option>
                    <?php foreach ( $this->base_types() as $type ) :
                        $object = get_post_type_object( $type ); ?>
                        <option value="<?php echo esc_attr( $type ); ?>" <?php selected( $p['type'], $type ); ?>>
                            <?php echo esc_html( $object ? $object->labels->name : $type ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="scd_author" onchange="this.form.submit()" aria-label="<?php esc_attr_e( 'Filter by author', 'scheduled-content-dashboard' ); ?>">
                    <option value="0"><?php esc_html_e( 'All authors', 'scheduled-content-dashboard' ); ?></option>
                    <?php foreach ( $this->author_choices() as $id => $name ) : ?>
                        <option value="<?php echo (int) $id; ?>" <?php selected( $p['author'], $id ); ?>><?php echo esc_html( $name ); ?></option>
                    <?php endforeach; ?>
                </select>

                <select name="scd_window" onchange="this.form.submit()" aria-label="<?php esc_attr_e( 'Filter by date', 'scheduled-content-dashboard' ); ?>">
                    <option value="all" <?php selected( $p['window'], 'all' ); ?>><?php esc_html_e( 'Any date', 'scheduled-content-dashboard' ); ?></option>
                    <option value="upcoming" <?php selected( $p['window'], 'upcoming' ); ?>><?php esc_html_e( 'Upcoming', 'scheduled-content-dashboard' ); ?></option>
                    <option value="overdue" <?php selected( $p['window'], 'overdue' ); ?>><?php esc_html_e( 'Past due', 'scheduled-content-dashboard' ); ?></option>
                </select>
            </form>

            <span class="scd-ov-spacer"></span>

            <a class="scd-ov-btn" href="<?php echo esc_url( $base ); ?>"><?php esc_html_e( 'Reset', 'scheduled-content-dashboard' ); ?></a>
            <button type="button" class="scd-ov-btn" onclick="window.print()"><?php esc_html_e( 'Print', 'scheduled-content-dashboard' ); ?></button>
            <a class="scd-ov-btn" href="<?php echo esc_url( menu_page_url( SCD_Admin_Page::PAGE_SLUG, false ) ); ?>"><?php esc_html_e( 'Calendar', 'scheduled-content-dashboard' ); ?></a>
        </div>
        <?php
    }

    private function maybe_render_notice() {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters, no state change.
        if ( empty( $_GET['scd_notice'] ) ) {
            return;
        }
        $key = sanitize_key( wp_unslash( $_GET['scd_notice'] ) );
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        if ( 'published' === $key ) {
            printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html__( 'Post published.', 'scheduled-content-dashboard' ) );
        } elseif ( 'publish_failed' === $key ) {
            printf( '<div class="notice notice-error is-dismissible"><p>%s</p></div>', esc_html__( 'Could not publish post.', 'scheduled-content-dashboard' ) );
        }
    }

    private function render_summary( $items ) {
        $by_status = array();
        $missed    = 0;
        foreach ( $items as $item ) {
            $by_status[ $item['status'] ] = ( $by_status[ $item['status'] ] ?? 0 ) + 1;
            if ( $item['is_missed'] ) {
                $missed++;
            }
        }
        ?>
        <div class="scd-ov-summary">
            <span class="scd-ov-stat">
                <b><?php echo (int) count( $items ); ?></b> <?php esc_html_e( 'items', 'scheduled-content-dashboard' ); ?>
            </span>
            <?php foreach ( $by_status as $status => $count ) : ?>
                <span class="scd-ov-stat">
                    <span class="scd-d" style="background:<?php echo esc_attr( SCD_Statuses::color( $status ) ); ?>"></span>
                    <b><?php echo (int) $count; ?></b> <?php echo esc_html( SCD_Statuses::label( $status ) ); ?>
                </span>
            <?php endforeach; ?>
            <?php if ( $missed > 0 ) : ?>
                <span class="scd-ov-stat scd-ov-stat--miss">
                    <b><?php echo (int) $missed; ?></b> <?php esc_html_e( 'past due', 'scheduled-content-dashboard' ); ?>
                </span>
            <?php endif; ?>
        </div>
        <?php
    }

    private function render_table( $items, $group_by ) {
        ?>
        <table class="scd-ov-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Title', 'scheduled-content-dashboard' ); ?></th>
                    <?php if ( 'status' !== $group_by ) : ?>
                        <th><?php esc_html_e( 'Status', 'scheduled-content-dashboard' ); ?></th>
                    <?php endif; ?>
                    <?php if ( 'type' !== $group_by ) : ?>
                        <th><?php esc_html_e( 'Type', 'scheduled-content-dashboard' ); ?></th>
                    <?php endif; ?>
                    <?php if ( 'author' !== $group_by ) : ?>
                        <th><?php esc_html_e( 'Author', 'scheduled-content-dashboard' ); ?></th>
                    <?php endif; ?>
                    <?php if ( 'category' !== $group_by ) : ?>
                        <th><?php esc_html_e( 'Categories', 'scheduled-content-dashboard' ); ?></th>
                    <?php endif; ?>
                    <th><?php esc_html_e( 'Publish date', 'scheduled-content-dashboard' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $items as $item ) : ?>
                    <tr class="<?php echo $item['is_missed'] ? 'is-miss' : ''; ?>">
                        <td>
                            <?php if ( $item['edit_link'] ) : ?>
                                <a class="scd-ov-link" href="<?php echo esc_url( $item['edit_link'] ); ?>"><?php echo esc_html( $item['title'] ); ?></a>
                            <?php else : ?>
                                <?php echo esc_html( $item['title'] ); ?>
                            <?php endif; ?>
                            <?php if ( $item['is_missed'] && $item['can_publish'] ) : ?>
                                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
                                    <input type="hidden" name="action" value="<?php echo esc_attr( Scheduled_Content_Dashboard::PUBLISH_NOW_ACTION ); ?>">
                                    <input type="hidden" name="post_id" value="<?php echo (int) $item['id']; ?>">
                                    <input type="hidden" name="scd_return" value="overview">
                                    <?php wp_nonce_field( Scheduled_Content_Dashboard::PUBLISH_NOW_ACTION . '_' . $item['id'] ); ?>
                                    <button type="submit" class="scd-ov-publish"><?php esc_html_e( 'Publish now', 'scheduled-content-dashboard' ); ?></button>
                                </form>
                            <?php endif; ?>
                        </td>
                        <?php if ( 'status' !== $group_by ) : ?>
                            <td>
                                <span class="scd-ov-pill">
                                    <span class="scd-d" style="background:<?php echo esc_attr( SCD_Statuses::color( $item['status'] ) ); ?>"></span>
                                    <?php echo esc_html( $item['status_label'] ); ?>
                                </span>
                            </td>
                        <?php endif; ?>
                        <?php if ( 'type' !== $group_by ) : ?>
                            <td><?php echo esc_html( $item['type_label'] ); ?></td>
                        <?php endif; ?>
                        <?php if ( 'author' !== $group_by ) : ?>
                            <td><?php echo esc_html( $item['author'] ); ?></td>
                        <?php endif; ?>
                        <?php if ( 'category' !== $group_by ) : ?>
                            <td class="scd-ov-terms"><?php echo esc_html( $item['terms'] ? implode( ', ', $item['terms'] ) : '—' ); ?></td>
                        <?php endif; ?>
                        <td class="scd-ov-when <?php echo $item['is_missed'] ? 'is-miss' : ''; ?>">
                            <?php echo esc_html( $item['when'] ); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * @param array $p     Request params.
     * @param int   $found Set to the total number of matching posts.
     */
    private function query_items( $p, &$found ) {
        $statuses = $p['status'] ? array( $p['status'] ) : SCD_Statuses::selected();
        $types    = $p['type'] ? array( $p['type'] ) : $this->base_types();

        $args = array(
            'post_type'      => $types,
            'post_status'    => $statuses,
            'posts_per_page' => self::MAX_ITEMS,
            'orderby'        => 'date',
            'order'          => 'ASC',
            'no_found_rows'  => false,
        );

        // Drafts can carry a zeroed post_date_gmt on older content, so the
        // local post_date column is the only one safe to filter across statuses.
        $now_local = current_time( 'mysql' );
        if ( 'upcoming' === $p['window'] ) {
            $args['date_query'] = array( array( 'column' => 'post_date', 'after' => $now_local ) );
        } elseif ( 'overdue' === $p['window'] ) {
            $args['date_query'] = array( array( 'column' => 'post_date', 'before' => $now_local ) );
            $args['order']      = 'DESC';
        }

        if ( $p['author'] > 0 ) {
            $args['author'] = $p['author'];
        }
        if ( ! current_user_can( 'edit_others_posts' ) ) {
            $args['author'] = get_current_user_id();
        }

        $query = new WP_Query( $args );
        $found = (int) $query->found_posts;
        $now   = time();
        $items = array();

        foreach ( $query->posts as $post ) {
            $type_object = get_post_type_object( $post->post_type );

            $items[] = array(
                'id'           => (int) $post->ID,
                'title'        => $post->post_title ? $post->post_title : __( '(no title)', 'scheduled-content-dashboard' ),
                'edit_link'    => get_edit_post_link( $post->ID, 'raw' ),
                'status'       => $post->post_status,
                'status_label' => SCD_Statuses::label( $post->post_status ),
                'type'         => $post->post_type,
                'type_label'   => $type_object ? $type_object->labels->singular_name : $post->post_type,
                'author'       => get_the_author_meta( 'display_name', $post->post_author ),
                'author_id'    => (int) $post->post_author,
                'when'         => mysql2date( get_option( 'date_format' ) . ' · ' . get_option( 'time_format' ), $post->post_date ),
                'terms'        => $this->term_names( $post ),
                'is_missed'    => SCD_Statuses::can_miss( $post->post_status )
                    && strtotime( get_gmt_from_date( $post->post_date ) . ' UTC' ) <= $now,
                'can_publish'  => current_user_can( 'edit_post', $post->ID )
                    && $type_object && current_user_can( $type_object->cap->publish_posts ),
            );
        }

        wp_reset_postdata();
        return $items;
    }

    /** Category names for a post, falling back to its first hierarchical taxonomy. */
    private function term_names( $post ) {
        $taxonomy = 'category';
        if ( ! is_object_in_taxonomy( $post->post_type, $taxonomy ) ) {
            $hierarchical = get_object_taxonomies( $post->post_type, 'objects' );
            $taxonomy     = '';
            foreach ( $hierarchical as $tax ) {
                if ( $tax->hierarchical && $tax->public ) {
                    $taxonomy = $tax->name;
                    break;
                }
            }
        }
        if ( ! $taxonomy ) {
            return array();
        }

        $terms = get_the_terms( $post, $taxonomy );
        if ( ! $terms || is_wp_error( $terms ) ) {
            return array();
        }
        return wp_list_pluck( $terms, 'name' );
    }

    private function group_items( $items, $group_by ) {
        $groups = array();

        foreach ( $items as $item ) {
            foreach ( $this->group_keys( $item, $group_by ) as $key => $label ) {
                if ( ! isset( $groups[ $key ] ) ) {
                    $groups[ $key ] = array(
                        'label' => $label,
                        'color' => 'status' === $group_by ? SCD_Statuses::color( $item['status'] ) : '',
                        'items' => array(),
                    );
                }
                $groups[ $key ]['items'][] = $item;
            }
        }

        if ( 'status' === $group_by ) {
            $order  = SCD_Statuses::selected();
            $sorted = array();
            foreach ( $order as $status ) {
                if ( isset( $groups[ $status ] ) ) {
                    $sorted[ $status ] = $groups[ $status ];
                }
            }
            return $sorted + $groups;
        }

        uasort(
            $groups,
            function ( $a, $b ) {
                return strcasecmp( $a['label'], $b['label'] );
            }
        );
        return $groups;
    }

    /** One item can land in several groups when grouping by category. */
    private function group_keys( $item, $group_by ) {
        if ( 'type' === $group_by ) {
            return array( $item['type'] => $item['type_label'] );
        }
        if ( 'author' === $group_by ) {
            return array( 'a' . $item['author_id'] => $item['author'] );
        }
        if ( 'category' === $group_by ) {
            if ( empty( $item['terms'] ) ) {
                return array( '__none__' => __( 'Uncategorized', 'scheduled-content-dashboard' ) );
            }
            $keys = array();
            foreach ( $item['terms'] as $name ) {
                $keys[ 't' . md5( $name ) ] = $name;
            }
            return $keys;
        }
        return array( $item['status'] => $item['status_label'] );
    }

    private function author_choices() {
        $args = array(
            'post_type'      => $this->base_types(),
            'post_status'    => SCD_Statuses::selected(),
            'posts_per_page' => 500,
            'no_found_rows'  => true,
        );
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
}
