<?php

/**
 * Read-only diagnostics for booking discount meta vs admin UI / discount extension.
 */
class SLN_Admin_BookingDiscountDiagnostics {

	/**
	 * @param SLN_Plugin $plugin
	 * @param int        $booking_id
	 * @return array
	 */
	public static function run( SLN_Plugin $plugin, $booking_id ) {
		$booking_id = absint( $booking_id );
		$result     = array(
			'booking_id'   => $booking_id,
			'error'        => null,
			'post'         => null,
			'booking_edit' => null,
			'environment'  => array(),
			'related_meta' => array(),
			'discount_ids' => array(),
			'catalog'      => array(),
			'per_discount' => array(),
			'insights'     => array(),
		);

		$post = get_post( $booking_id );
		if ( ! $post ) {
			$result['error'] = __( 'No post exists with that ID.', 'salon-booking-system' );
			return $result;
		}
		$result['post'] = array(
			'post_type'    => $post->post_type,
			'post_status'  => $post->post_status,
			'post_title'   => $post->post_title,
			'post_modified'=> $post->post_modified,
		);

		if ( SLN_Plugin::POST_TYPE_BOOKING !== $post->post_type ) {
			$result['error'] = sprintf(
				/* translators: %s: WordPress post type slug */
				__( 'This post is not a salon booking (post type: %s).', 'salon-booking-system' ),
				$post->post_type
			);
			return $result;
		}

		$result['booking_edit'] = admin_url( 'post.php?post=' . $booking_id . '&action=edit' );

		$settings              = $plugin->getSettings();
		$enable_discount       = (bool) $settings->get( 'enable_discount_system' );
		$result['environment'] = array(
			'enable_discount_system' => $enable_discount ? __( 'Yes', 'salon-booking-system' ) : __( 'No', 'salon-booking-system' ),
		);

		$catalog_ids = array();
		if ( $enable_discount && class_exists( 'SLB_Discount_Plugin' ) ) {
			$repo = $plugin->getRepository( SLB_Discount_Plugin::POST_TYPE_DISCOUNT );
			if ( $repo ) {
				$coupons = $repo->getAll();
				foreach ( $coupons as $coupon ) {
					$catalog_ids[ (int) $coupon->getId() ] = $coupon->getTitle();
				}
			}
		}
		$result['catalog']                      = $catalog_ids;
		$result['environment']['catalog_count'] = count( $catalog_ids );
		$result['environment']['admin_discount_select_visible'] = ( $enable_discount && ! empty( $catalog_ids ) )
			? __( 'Yes (discount dropdown is rendered on booking edit screen)', 'salon-booking-system' )
			: __( 'No (dropdown hidden — saves may omit discount field from POST)', 'salon-booking-system' );

		$custom = get_post_custom( $booking_id );
		if ( ! is_array( $custom ) ) {
			$custom = array();
		}
		$prefix = '_' . SLN_Plugin::POST_TYPE_BOOKING . '_discount';
		foreach ( $custom as $meta_key => $values ) {
			if ( strpos( $meta_key, $prefix ) !== 0 ) {
				continue;
			}
			$display = self::format_meta_values( $values );
			$result['related_meta'][ $meta_key ] = $display;
		}
		$result['related_meta']['_' . SLN_Plugin::POST_TYPE_BOOKING . '_amount'] = self::format_meta_values(
			isset( $custom[ '_' . SLN_Plugin::POST_TYPE_BOOKING . '_amount' ] ) ? $custom[ '_' . SLN_Plugin::POST_TYPE_BOOKING . '_amount' ] : array( '' )
		);

		$booking = $plugin->getRepository( SLN_Plugin::POST_TYPE_BOOKING )->create( $booking_id );

		$discount_ids = array();
		if ( class_exists( 'SLB_Discount_Helper_Booking' ) ) {
			$discount_ids = SLB_Discount_Helper_Booking::getBookingDiscountIds( $booking );
		} else {
			$raw = $booking->getMeta( 'discounts' );
			if ( is_array( $raw ) ) {
				$discount_ids = array_map( 'intval', $raw );
			}
		}
		$result['discount_ids'] = $discount_ids;

		$booking_ts = 0;
		if ( $booking->getDate() ) {
			$booking_ts = $booking->getDate()->getTimestamp();
		}

		foreach ( $discount_ids as $did ) {
			$row = array(
				'id'            => $did,
				'coupon_post'   => null,
				'in_catalog'    => isset( $catalog_ids[ $did ] ),
				'validate_msgs' => array(),
			);
			$coupon_post = get_post( $did );
			if ( ! $coupon_post ) {
				$row['coupon_post'] = __( 'Missing post (discount was deleted or wrong ID)', 'salon-booking-system' );
			} elseif ( class_exists( 'SLB_Discount_Plugin' ) && SLB_Discount_Plugin::POST_TYPE_DISCOUNT !== $coupon_post->post_type ) {
				$row['coupon_post'] = sprintf(
					/* translators: %s: post type */
					__( 'Post exists but is not a discount coupon (type: %s)', 'salon-booking-system' ),
					$coupon_post->post_type
				);
			} else {
				$row['coupon_post'] = get_the_title( $did ) . ' — ' . $coupon_post->post_status;
			}
			if ( class_exists( 'SLB_Discount_Wrapper_Discount' ) && $coupon_post && SLB_Discount_Plugin::POST_TYPE_DISCOUNT === $coupon_post->post_type ) {
				$discount_obj = new SLB_Discount_Wrapper_Discount( $did );
				$when         = $booking_ts ? $booking_ts : time();
				$errs         = $discount_obj->validateDiscount( $when );
				$row['validate_msgs'] = is_array( $errs ) ? $errs : array();
			}
			$result['per_discount'][] = $row;
		}

		$amount_meta   = $booking->getMeta( 'discount_amount' );
		$has_amount    = ! empty( $amount_meta );
		$has_ids       = ! empty( $discount_ids );
		$orphans       = array();
		foreach ( $discount_ids as $did ) {
			if ( ! isset( $catalog_ids[ $did ] ) ) {
				$orphans[] = $did;
			}
		}

		$booking_pt   = SLN_Plugin::POST_TYPE_BOOKING;
		$orphan_flags = array();
		foreach ( array_keys( $result['related_meta'] ) as $meta_key ) {
			if ( preg_match( '/^_' . preg_quote( $booking_pt, '/' ) . '_discount_(\d+)$/', $meta_key, $m ) ) {
				$orphan_flags[] = (int) $m[1];
			}
		}

		if ( $has_amount && ! $has_ids ) {
			$result['insights'][] = __( 'Inconsistency: discount_amount meta is set but the discounts list is empty. The Totals tab may show “No Discounts” while old amount data still exists.', 'salon-booking-system' );
			$result['insights'][] = __( 'Note: keys inside discount_amount are salon service post IDs (per-line reductions), not discount coupon IDs.', 'salon-booking-system' );
		}
		if ( ! $has_ids && ! empty( $orphan_flags ) ) {
			$result['insights'][] = sprintf(
				/* translators: %s: comma-separated numeric discount (coupon) post IDs */
				__( 'Stale per-coupon meta: _sln_booking_discount_{couponId} is still set for ID(s) %s while _sln_booking_discounts is empty. That usually means the canonical list was cleared or overwritten without removing all per-coupon flags.', 'salon-booking-system' ),
				implode( ', ', array_map( 'strval', array_unique( $orphan_flags ) ) )
			);
		}
		if ( ! empty( $orphans ) ) {
			$result['insights'][] = sprintf(
				/* translators: %s: comma-separated discount post IDs */
				__( 'Orphan discount IDs (stored on booking but not returned by current discount catalog getAll()): %s. Common causes: coupon trashed/deleted, or repository filters.', 'salon-booking-system' ),
				implode( ', ', array_map( 'strval', $orphans ) )
			);
		}
		if ( $enable_discount && empty( $catalog_ids ) ) {
			$result['insights'][] = __( 'Discount system is enabled but there are no coupons in the catalog, so the booking edit screen does not render the discount field. A booking update that does not include discount fields in POST can clear previously stored discount IDs.', 'salon-booking-system' );
		}
		if ( ! $enable_discount && $has_ids ) {
			$result['insights'][] = __( 'Discount IDs exist in meta while the discount system is disabled in settings; discount logic may not run until re-enabled.', 'salon-booking-system' );
		}
		if ( $has_ids && empty( $result['insights'] ) ) {
			$result['insights'][] = __( 'Stored discount IDs look consistent with the catalog. If the UI still shows “No Discounts”, check for JavaScript/Select2 issues, user role restrictions, or compare post_modified with when staff last saved the booking.', 'salon-booking-system' );
		}

		return $result;
	}

	/**
	 * @param array $values Meta values as returned by get_post_custom.
	 * @return string
	 */
	private static function format_meta_values( $values ) {
		if ( ! is_array( $values ) || ! isset( $values[0] ) ) {
			return '';
		}
		$v = maybe_unserialize( $values[0] );
		if ( is_scalar( $v ) ) {
			return (string) $v;
		}
		return wp_json_encode( $v, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
	}
}
