<?php
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch

if (!class_exists('WP_Posts_List_Table')) {
	_get_list_table('WP_Posts_List_Table');
}

class SLB_Discount_Admin_DiscountsHistoryList extends WP_Posts_List_Table {

	public function __construct( $args = array() ) {
		if ( empty( $args['screen'] ) && function_exists( 'convert_to_screen' ) ) {
			$screen     = convert_to_screen( 'edit-' . SLN_Plugin::POST_TYPE_BOOKING );
			$screen->id = 'sln_discount_history';
			$args['screen'] = $screen;
		}
		parent::__construct( $args );
	}

	/**
	 * Load bookings that used this discount via a standalone WP_Query so that
	 * the global $wp_query (and the surrounding discount post editor) is never touched.
	 */
	public function prepare_items() {
		$discount_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0;
		if ( $discount_id < 1 ) {
			$this->items = array();
			$this->set_pagination_args( array( 'total_items' => 0, 'per_page' => 20 ) );
			return;
		}

		$per_page = 20;
		$paged    = max( 1, (int) $this->get_pagenum() );

		$query = new WP_Query(
			array(
				'post_type'      => SLN_Plugin::POST_TYPE_BOOKING,
				'post_status'    => 'any',
				'meta_key'       => '_sln_booking_discount_' . $discount_id,
				'meta_value'     => '1',
				'posts_per_page' => $per_page,
				'paged'          => $paged,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		$this->items = $query->posts;
		$this->set_pagination_args(
			array(
				'total_items' => (int) $query->found_posts,
				'per_page'    => $per_page,
			)
		);
	}

	public function has_items() {
		return ! empty( $this->items );
	}

	/**
	 * Render the table bypassing WP_Posts_List_Table's column machinery entirely.
	 *
	 * Root cause of the original display bug: WordPress injects an inline <style> block
	 * that adds `display:none` to `.column-{key}` selectors for columns the user has hidden
	 * via Screen Options. Those selectors matched our `<td class="column-booking_*">` cells,
	 * silently hiding all data rows. Removing the `column-*` CSS classes from <td> elements
	 * and using a neutral table class (`sln-discount-history`) fully sidesteps the issue.
	 */
	public function display() {
		$plugin    = SLN_Plugin::getInstance();
		$formatter = $plugin->format();
		$columns   = $this->get_columns();
		$col_count = count( $columns );

		$this->display_tablenav( 'top' );
		?>
		<table class="sln-discount-history widefat striped">
			<thead>
				<tr>
					<?php foreach ( $columns as $key => $label ) : ?>
						<th><?php echo esc_html( $label ); ?></th>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
				<?php if ( $this->has_items() ) : ?>
					<?php foreach ( $this->items as $post ) : ?>
						<?php
						$booking = $plugin->createBooking( $post );
						$dAmount = $booking->getMeta( 'discount_amount' );
						$dAmount = ( is_array( $dAmount ) && ! empty( $dAmount ) ) ? array_sum( $dAmount ) : 0;
						?>
						<tr>
							<td><?php echo esc_html( $booking->getDisplayName() ); ?></td>
							<td><?php
							$date = get_post_meta( $post->ID, '_sln_booking_date', true );
							$time = get_post_meta( $post->ID, '_sln_booking_time', true );
							if ( $date ) {
								try {
									echo esc_html( $formatter->datetime( new SLN_DateTime( $date . ' ' . $time ) ) );
								} catch ( Exception $e ) {
									echo esc_html( $date );
								}
							}
							?></td>
							<td><?php echo esc_html( $formatter->money( $booking->getAmount(), false ) ); ?></td>
							<td><?php echo esc_html( $formatter->money( $dAmount, false ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr>
						<td colspan="<?php echo esc_attr( $col_count ); ?>"><?php esc_html_e( 'No usages found.', 'salon-booking-system' ); ?></td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
		<?php
		$this->display_tablenav( 'bottom' );
	}

	public function get_columns() {
		return array(
			'booking_customer' => __( 'Customer', 'salon-booking-system' ),
			'booking_date'     => __( 'Booking date', 'salon-booking-system' ),
			'booking_amount'   => __( 'Booking amount', 'salon-booking-system' ),
			'booking_discount' => __( 'Booking discount', 'salon-booking-system' ),
		);
	}

	protected function get_bulk_actions() {
		return array();
	}

	public function column_default( $item, $column_name ) {
		return '';
	}

	protected function extra_tablenav( $which ) {
		return;
	}

	protected function display_tablenav( $which ) {
		?>
		<div class="tablenav <?php echo esc_attr( $which ); ?>">
			<?php $this->pagination( $which ); ?>
			<br class="clear" />
		</div>
		<?php
	}
}