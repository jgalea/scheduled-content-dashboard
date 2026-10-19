<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SCD_Dates {

    /**
     * Midnight on the first of a YYYY-MM month in the site timezone, or the
     * current month when the input isn't a valid month.
     *
     * Month math has to stay in the site timezone. WordPress pins PHP's default
     * timezone to UTC, so strtotime( '2026-08-01' ) is UTC midnight, and wp_date()
     * turns that into 31 July anywhere west of Greenwich.
     *
     * @param string $month Month in YYYY-MM format.
     * @return DateTimeImmutable
     */
    public static function month_start( $month ) {
        $tz    = wp_timezone();
        $start = is_string( $month ) && preg_match( '/^\d{4}-(0[1-9]|1[0-2])$/', $month )
            ? DateTimeImmutable::createFromFormat( '!Y-m-d', $month . '-01', $tz )
            : false;

        return $start ? $start : new DateTimeImmutable( 'first day of this month midnight', $tz );
    }

    /**
     * The YYYY-MM month $delta months away from $month.
     *
     * @param string $month Month in YYYY-MM format.
     * @param int    $delta Months to move, negative for earlier.
     * @return string
     */
    public static function shift_month( $month, $delta ) {
        return self::month_start( $month )->modify( sprintf( '%+d months', (int) $delta ) )->format( 'Y-m' );
    }
}
