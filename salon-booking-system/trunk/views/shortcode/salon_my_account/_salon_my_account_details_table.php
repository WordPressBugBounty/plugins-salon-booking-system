<?php
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch
?>
<table class="table">
	<thead>
	<tr>
		<td><?php esc_html_e('ID','salon-booking-system');?></td>
		<td><?php esc_html_e('When','salon-booking-system');?></td>
		<td><?php esc_html_e('Services','salon-booking-system');?></td>
		<?php if($data['attendant_enabled']): ?>
			<td><?php esc_html_e('Assistants','salon-booking-system');?></td>
		<?php endif; ?>
		<?php if(!$data['hide_prices']): ?>
			<td><?php esc_html_e('Price','salon-booking-system');?></td>
		<?php endif; ?>
		<td><?php esc_html_e('Status','salon-booking-system');?></td>
		<td><?php esc_html_e('Action','salon-booking-system');?></td>
	</tr>
	</thead>
	<tbody>
	<?php include '_salon_my_account_details_table_rows.php' ?>
	</tbody>
</table>
