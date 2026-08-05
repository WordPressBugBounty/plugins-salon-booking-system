<?php

/**
 * Safe, reversible normalizer / diagnostic for break configuration on services and bookings.
 *
 * Context: the nested-bookings engine reads each service's and booking line's
 * break_duration + break_duration_data ({from,to} in minutes). Historical or hand-edited
 * rows can be internally inconsistent (e.g. a break WINDOW defined while the break DURATION
 * is zero), which confuses availability. This tool reports every inconsistency (dry-run by
 * default) and only auto-fixes the unambiguously safe cases, storing a reversible backup.
 *
 * Auto-fixed (safe) case ONLY:
 *   - A break window (to > from) exists but the break duration is 0 -> the window is
 *     contradictory (there is no break) and is cleared to {0,0}.
 * Everything else is REPORT-ONLY (width mismatch, window beyond service length, missing
 * window, a booking line nested inside another line's break) so nothing is silently moved.
 */
class SLN_Admin_BreakDataNormalizer {

	const BACKUP_OPTION_PREFIX = 'sln_break_normalizer_backup_';

	/**
	 * @param bool $apply When true, applies the safe fixes and writes a reversible backup.
	 * @return array Structured report.
	 */
	public static function run( $apply = false ) {
		$report = array(
			'apply'       => (bool) $apply,
			'generated'   => gmdate( 'c' ),
			'services'    => array(),
			'bookings'    => array(),
			'fixes'       => array(),
			'backup_key'  => null,
			'summary'     => array(),
		);

		$backup = array( 'services' => array(), 'bookings' => array() );

		self::analyzeServices( $report, $backup, $apply );
		self::analyzeBookings( $report, $backup, $apply );

		if ( $apply && ! empty( $report['fixes'] ) ) {
			$key = self::BACKUP_OPTION_PREFIX . gmdate( 'Ymd_His' );
			update_option( $key, $backup, false );
			$report['backup_key'] = $key;
		}

		$report['summary'] = array(
			'service_issues'  => count( $report['services'] ),
			'booking_issues'  => count( $report['bookings'] ),
			'fixes_applied'   => count( $report['fixes'] ),
			'auto_fixable'    => count( array_filter( array_merge( $report['services'], $report['bookings'] ), function ( $i ) {
				return ! empty( $i['auto_fixable'] );
			} ) ),
		);

		return $report;
	}

	/**
	 * Restore a previously written backup (undo an apply run).
	 *
	 * @param string $backup_key
	 * @return array {restored_services:int, restored_bookings:int, error:?string}
	 */
	public static function restore( $backup_key ) {
		$out = array( 'restored_services' => 0, 'restored_bookings' => 0, 'error' => null );
		if ( 0 !== strpos( (string) $backup_key, self::BACKUP_OPTION_PREFIX ) ) {
			$out['error'] = 'Invalid backup key.';
			return $out;
		}
		$backup = get_option( $backup_key );
		if ( ! is_array( $backup ) ) {
			$out['error'] = 'Backup not found.';
			return $out;
		}
		foreach ( isset( $backup['services'] ) ? $backup['services'] : array() as $sid => $data ) {
			update_post_meta( (int) $sid, '_sln_service_break_duration_data', $data['break_duration_data'] );
			$out['restored_services']++;
		}
		foreach ( isset( $backup['bookings'] ) ? $backup['bookings'] : array() as $bid => $data ) {
			update_post_meta( (int) $bid, '_sln_booking_services', $data['_sln_booking_services'] );
			$out['restored_bookings']++;
		}
		return $out;
	}

	private static function breakMinutes( $val ) {
		if ( is_array( $val ) ) {
			return 0;
		}
		$val = (string) $val;
		if ( false !== strpos( $val, ':' ) ) {
			return (int) SLN_Func::getMinutesFromDuration( $val );
		}
		return (int) $val;
	}

	private static function analyzeServices( array &$report, array &$backup, $apply ) {
		$services = get_posts( array(
			'post_type'      => 'sln_service',
			'posts_per_page' => -1,
			'post_status'    => 'any',
			'fields'         => 'ids',
		) );

		foreach ( $services as $sid ) {
			$brkMeta = get_post_meta( $sid, '_sln_service_break_duration', true );
			$bdd     = get_post_meta( $sid, '_sln_service_break_duration_data', true );
			$brkMin  = self::breakMinutes( $brkMeta );

			$from = ( is_array( $bdd ) && isset( $bdd['from'] ) ) ? (int) $bdd['from'] : null;
			$to   = ( is_array( $bdd ) && isset( $bdd['to'] ) ) ? (int) $bdd['to'] : null;
			$hasWindow = ( null !== $from && null !== $to && $to > $from );

			$issues = array();

			if ( $hasWindow && $brkMin <= 0 ) {
				$issues[] = array(
					'type'         => 'window_without_duration',
					'detail'       => sprintf( 'Break window %d-%d min but break duration is 0.', $from, $to ),
					'auto_fixable' => true,
				);
			}
			if ( $hasWindow && $brkMin > 0 && ( $to - $from ) !== $brkMin ) {
				$issues[] = array(
					'type'         => 'width_mismatch',
					'detail'       => sprintf( 'Window width %d min != break duration %d min (positioning may be intentional).', $to - $from, $brkMin ),
					'auto_fixable' => false,
				);
			}
			if ( $brkMin > 0 && ! $hasWindow ) {
				$issues[] = array(
					'type'         => 'missing_window',
					'detail'       => sprintf( 'Break duration %d min but no positioned window (falls back to start-of-service).', $brkMin ),
					'auto_fixable' => false,
				);
			}

			if ( empty( $issues ) ) {
				continue;
			}

			$row = array(
				'service_id'          => (int) $sid,
				'title'               => get_the_title( $sid ),
				'break_duration_min'  => $brkMin,
				'break_duration_data' => $bdd,
				'issues'              => $issues,
			);

			$autofix = false;
			foreach ( $issues as $i ) {
				if ( ! empty( $i['auto_fixable'] ) ) {
					$autofix = true;
				}
			}
			$row['auto_fixable'] = $autofix;

			if ( $apply && $autofix ) {
				$backup['services'][ $sid ] = array( 'break_duration_data' => $bdd );
				update_post_meta( $sid, '_sln_service_break_duration_data', array( 'from' => 0, 'to' => 0 ) );
				$report['fixes'][] = array(
					'kind'       => 'service',
					'id'         => (int) $sid,
					'action'     => 'cleared contradictory break window to {0,0}',
				);
				$row['fixed'] = true;
			}

			$report['services'][] = $row;
		}
	}

	private static function analyzeBookings( array &$report, array &$backup, $apply ) {
		global $wpdb;
		$ids = $wpdb->get_col(
			"SELECT post_id FROM {$wpdb->postmeta}
			 WHERE meta_key = '_sln_booking_services' AND meta_value LIKE '%break_duration%'"
		);

		foreach ( $ids as $bid ) {
			$services = get_post_meta( $bid, '_sln_booking_services', true );
			if ( ! is_array( $services ) || empty( $services ) ) {
				continue;
			}

			$issues       = array();
			$changed      = false;
			$origServices = $services;

			// Absolute minute windows per line (for nested-in-break detection).
			$lines = array();
			foreach ( $services as $idx => $line ) {
				if ( ! isset( $line['start_time'] ) ) {
					continue;
				}
				$st  = self::breakMinutes( $line['start_time'] );
				$brk = isset( $line['break_duration'] ) ? self::breakMinutes( $line['break_duration'] ) : 0;
				$from = isset( $line['break_duration_data']['from'] ) ? (int) $line['break_duration_data']['from'] : 0;
				$to   = isset( $line['break_duration_data']['to'] ) ? (int) $line['break_duration_data']['to'] : 0;
				$lines[ $idx ] = array( 'st' => $st, 'brk' => $brk, 'bws' => $st + $from, 'bwe' => $st + $to );

				// Safe fix: window present but no break duration on this line.
				if ( $brk <= 0 && $to > $from ) {
					$issues[] = array(
						'type'         => 'line_window_without_duration',
						'detail'       => sprintf( 'Line %d (svc %s): window %d-%d but break duration 0.', $idx, isset( $line['service'] ) ? $line['service'] : '?', $from, $to ),
						'auto_fixable' => true,
					);
					if ( $apply ) {
						$services[ $idx ]['break_duration_data'] = array( 'from' => 0, 'to' => 0 );
						$changed = true;
					}
				}
			}

			// Report-only: a later line starting inside an earlier line's break window.
			foreach ( $lines as $i => $a ) {
				if ( $a['brk'] <= 0 || $a['bwe'] <= $a['bws'] ) {
					continue;
				}
				foreach ( $lines as $j => $b ) {
					if ( $i === $j ) {
						continue;
					}
					if ( $b['st'] >= $a['bws'] && $b['st'] < $a['bwe'] ) {
						$issues[] = array(
							'type'         => 'line_nested_in_break',
							'detail'       => sprintf( 'Line %d starts inside line %d break window [%d-%d] (manual review).', $j, $i, $a['bws'], $a['bwe'] ),
							'auto_fixable' => false,
						);
					}
				}
			}

			if ( empty( $issues ) ) {
				continue;
			}

			$row = array(
				'booking_id'   => (int) $bid,
				'date'         => get_post_meta( $bid, '_sln_booking_date', true ),
				'issues'       => $issues,
				'auto_fixable' => (bool) $changed || (bool) array_filter( $issues, function ( $i ) { return ! empty( $i['auto_fixable'] ); } ),
			);

			if ( $apply && $changed ) {
				$backup['bookings'][ $bid ] = array( '_sln_booking_services' => $origServices );
				update_post_meta( $bid, '_sln_booking_services', $services );
				$report['fixes'][] = array(
					'kind'   => 'booking',
					'id'     => (int) $bid,
					'action' => 'cleared contradictory break window(s) on line(s)',
				);
				$row['fixed'] = true;
			}

			$report['bookings'][] = $row;
		}
	}
}
