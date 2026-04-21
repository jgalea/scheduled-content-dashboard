<?php
/**
 * REST API endpoints for scheduled content.
 *
 * @package Scheduled_Content_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SCD_Rest {

    const NAMESPACE_V1 = 'scheduled-content-dashboard/v1';

    public static function init() {
        $self = new self();
        add_action( 'rest_api_init', array( $self, 'register_routes' ) );
    }

    public function register_routes() {
        register_rest_route(
            self::NAMESPACE_V1,
            '/scheduled',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_scheduled' ),
                'permission_callback' => array( $this, 'permissions_read' ),
                'args'                => array(
                    'post_type' => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_key' ),
                    'author'    => array( 'type' => 'integer' ),
                    'limit'     => array( 'type' => 'integer', 'default' => 50 ),
                ),
            )
        );

        register_rest_route(
            self::NAMESPACE_V1,
            '/missed',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_missed' ),
                'permission_callback' => array( $this, 'permissions_read' ),
            )
        );

        register_rest_route(
            self::NAMESPACE_V1,
            '/counts',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_counts' ),
                'permission_callback' => array( $this, 'permissions_read' ),
            )
        );

        register_rest_route(
            self::NAMESPACE_V1,
            '/publish/(?P<id>\d+)',
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( $this, 'publish' ),
                'permission_callback' => array( $this, 'permissions_edit' ),
                'args'                => array(
                    'id' => array( 'type' => 'integer', 'required' => true ),
                ),
            )
        );

        register_rest_route(
            self::NAMESPACE_V1,
            '/reschedule/(?P<id>\d+)',
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( $this, 'reschedule' ),
                'permission_callback' => array( $this, 'permissions_edit' ),
                'args'                => array(
                    'id'   => array( 'type' => 'integer', 'required' => true ),
                    'date' => array( 'type' => 'string', 'required' => true ),
                ),
            )
        );
    }

    public function permissions_read() {
        return current_user_can( 'edit_posts' );
    }

    public function permissions_edit( WP_REST_Request $request ) {
        $id = (int) $request['id'];
        return $id && current_user_can( 'edit_post', $id );
    }

    public function get_scheduled( WP_REST_Request $request ) {
        $args = array(
            'post_type'      => $request['post_type'] ? array( $request['post_type'] ) : array_keys( get_post_types( array( 'public' => true ) ) ),
            'post_status'    => 'future',
            'posts_per_page' => max( 1, min( 200, (int) $request['limit'] ) ),
            'orderby'        => 'date',
            'order'          => 'ASC',
            'no_found_rows'  => true,
            'date_query'     => array(
                array(
                    'column'    => 'post_date_gmt',
                    'after'     => gmdate( 'Y-m-d H:i:s' ),
                    'inclusive' => false,
                ),
            ),
        );
        if ( $request['author'] ) {
            $args['author'] = (int) $request['author'];
        }

        return rest_ensure_response( $this->posts_to_items( get_posts( $args ) ) );
    }

    public function get_missed() {
        $posts = get_posts(
            array(
                'post_type'      => array_keys( get_post_types( array( 'public' => true ) ) ),
                'post_status'    => 'future',
                'posts_per_page' => 200,
                'orderby'        => 'date',
                'order'          => 'ASC',
                'no_found_rows'  => true,
                'date_query'     => array(
                    array(
                        'column' => 'post_date_gmt',
                        'before' => gmdate( 'Y-m-d H:i:s' ),
                    ),
                ),
            )
        );
        return rest_ensure_response( $this->posts_to_items( $posts ) );
    }

    public function get_counts() {
        $types  = array_keys( get_post_types( array( 'public' => true ) ) );
        $base   = array(
            'post_type'      => $types,
            'post_status'    => 'future',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => false,
        );
        $total  = new WP_Query( $base );
        $missed = new WP_Query(
            array_merge(
                $base,
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
        return rest_ensure_response(
            array(
                'total'     => (int) $total->found_posts,
                'missed'    => (int) $missed->found_posts,
                'scheduled' => max( 0, (int) $total->found_posts - (int) $missed->found_posts ),
            )
        );
    }

    public function publish( WP_REST_Request $request ) {
        $id   = (int) $request['id'];
        $post = get_post( $id );
        if ( ! $post || 'future' !== $post->post_status ) {
            return new WP_Error( 'scd_not_scheduled', __( 'Post is not scheduled.', 'scheduled-content-dashboard' ), array( 'status' => 400 ) );
        }
        $type = get_post_type_object( $post->post_type );
        if ( ! $type || ! current_user_can( $type->cap->publish_posts ) ) {
            return new WP_Error( 'scd_cannot_publish', __( 'Not allowed to publish this post type.', 'scheduled-content-dashboard' ), array( 'status' => 403 ) );
        }
        wp_publish_post( $id );
        return rest_ensure_response(
            array(
                'id'     => $id,
                'status' => get_post_status( $id ),
            )
        );
    }

    public function reschedule( WP_REST_Request $request ) {
        $id   = (int) $request['id'];
        $date = (string) $request['date'];
        $post = get_post( $id );

        if ( ! $post ) {
            return new WP_Error( 'scd_not_found', __( 'Post not found.', 'scheduled-content-dashboard' ), array( 'status' => 404 ) );
        }

        $ts = strtotime( $date );
        if ( false === $ts || $ts <= time() ) {
            return new WP_Error( 'scd_bad_date', __( 'Reschedule date must be in the future.', 'scheduled-content-dashboard' ), array( 'status' => 400 ) );
        }

        $new_local = wp_date( 'Y-m-d H:i:s', $ts );
        $new_gmt   = gmdate( 'Y-m-d H:i:s', $ts );

        $result = wp_update_post(
            array(
                'ID'            => $id,
                'post_date'     => $new_local,
                'post_date_gmt' => $new_gmt,
                'post_status'   => 'future',
                'edit_date'     => true,
            ),
            true
        );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return rest_ensure_response(
            array(
                'id'             => $id,
                'post_date'      => $new_local,
                'post_date_gmt'  => $new_gmt,
            )
        );
    }

    private function posts_to_items( array $posts ) {
        $items = array();
        foreach ( $posts as $post ) {
            $type = get_post_type_object( $post->post_type );
            $items[] = array(
                'id'        => (int) $post->ID,
                'title'     => get_the_title( $post ),
                'author'    => get_the_author_meta( 'display_name', $post->post_author ),
                'author_id' => (int) $post->post_author,
                'post_type' => $post->post_type,
                'type_label' => $type ? $type->labels->singular_name : $post->post_type,
                'date'      => mysql_to_rfc3339( $post->post_date_gmt ),
                'edit_link' => get_edit_post_link( $post->ID, 'raw' ),
                'permalink' => get_permalink( $post ),
            );
        }
        return $items;
    }
}
