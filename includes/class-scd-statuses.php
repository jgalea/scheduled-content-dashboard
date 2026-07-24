<?php
/**
 * Post status registry.
 *
 * Reads whatever statuses are registered on the site, including custom ones
 * added by editorial workflow plugins, so the calendar and overview can show
 * them without registering statuses of our own.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SCD_Statuses {

    /** Statuses shipped by WordPress that we treat as pre-publication. */
    const CORE_PIPELINE = array( 'future', 'draft', 'pending' );

    /** Published or otherwise terminal states the plugin never lists. */
    const EXCLUDED = array( 'publish', 'private', 'trash', 'auto-draft', 'inherit' );

    /**
     * Statuses this site can show: every non-internal registered status minus
     * the terminal ones. Custom statuses land here automatically.
     *
     * @return array status name => WP_Post_Status object
     */
    public static function available() {
        $stati = get_post_stati( array( 'internal' => false ), 'objects' );
        $out   = array();

        foreach ( self::CORE_PIPELINE as $name ) {
            if ( isset( $stati[ $name ] ) ) {
                $out[ $name ] = $stati[ $name ];
            }
        }

        foreach ( $stati as $name => $object ) {
            if ( isset( $out[ $name ] ) || in_array( $name, self::EXCLUDED, true ) ) {
                continue;
            }
            $out[ $name ] = $object;
        }

        /**
         * Filters the statuses the plugin can display.
         *
         * @since 3.1.0
         * @param array $out Status name => status object.
         */
        return apply_filters( 'scheduled_content_dashboard_statuses', $out );
    }

    /** Status names the site owner has switched on, always including future. */
    public static function selected() {
        $available = array_keys( self::available() );
        $chosen    = (array) SCD_Settings::get( 'included_statuses', array( 'future' ) );
        $chosen    = array_values( array_intersect( $chosen, $available ) );

        if ( empty( $chosen ) ) {
            $chosen = array( 'future' );
        }
        return $chosen;
    }

    /** Validate a single status name against what's selected. */
    public static function is_selected( $status ) {
        return in_array( $status, self::selected(), true );
    }

    public static function label( $status ) {
        $available = self::available();
        if ( isset( $available[ $status ] ) ) {
            $object = $available[ $status ];
            if ( ! empty( $object->label ) ) {
                return $object->label;
            }
        }
        if ( 'publish' === $status ) {
            return __( 'Published', 'scheduled-content-dashboard' );
        }
        return ucfirst( str_replace( array( '-', '_' ), ' ', (string) $status ) );
    }

    /** A stable colour per status, so chips and columns stay recognisable. */
    public static function color( $status ) {
        $map = array(
            'future'  => 'var(--scd-accent)',
            'draft'   => '#6b7280',
            'pending' => '#d97706',
            'publish' => '#15803d',
        );
        if ( isset( $map[ $status ] ) ) {
            return $map[ $status ];
        }
        $palette = array( '#0d9488', '#db2777', '#7c3aed', '#0284c7', '#65a30d', '#b45309' );
        return $palette[ abs( crc32( (string) $status ) ) % count( $palette ) ];
    }

    /** True when the status came from another plugin rather than core. */
    public static function is_custom( $status ) {
        $core = array_merge( self::CORE_PIPELINE, self::EXCLUDED );
        return ! in_array( $status, $core, true );
    }

    /**
     * Whether a status still counts as unpublished, so dating it in the past
     * is a missed schedule rather than a normal published post.
     */
    public static function can_miss( $status ) {
        return 'future' === $status;
    }
}
