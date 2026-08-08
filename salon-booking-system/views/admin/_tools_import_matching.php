<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
/**
 * @var array $headers
 * @var array $rows
 * @var array $columns
 * @var array $required
 */

$selectItems = array(
	'' => __('Select a column', 'salon-booking-system'),
);
foreach($headers as $header) {
	$selectItems[$header] = $header;
}
?>

<thead>
	<tr class="sln-import-table__head">
		<?php foreach($columns as $col): ?>
			<th scope="col">
				<span class="sln-import-table__col"><?php echo esc_html(ucfirst(str_replace('_', ' ', $col))) ?></span>
				<?php if (in_array($col, $required)): ?><span class="sln-import-table__req" title="<?php esc_attr_e('Required field', 'salon-booking-system') ?>">*</span><?php endif ?>
			</th>
		<?php endforeach; ?>
	</tr>
</thead>
<tbody>
	<tr class="sln-import-table__selects">
		<?php foreach($columns as $i => $col): ?>
			<td>
				<div class="form-group sln-select sln-select--info-label">
					<?php
					$settings = array('attrs' => array('data-action' => 'sln_import_matching_select', 'data-col' => $i));
					if (in_array($col, $required)) {
						$settings['attrs']['required'] = 'required';
					}
					SLN_Form::fieldSelect("import_matching[{$col}]", $selectItems, $col, $settings, true) ?>
				</div>
			</td>
		<?php endforeach; ?>
	</tr>
	<?php foreach($rows as $row): ?>
		<tr class="import_matching">
			<?php foreach($columns as $i => $col): ?>
				<td data-col="<?php echo esc_attr($i); ?>" placeholder="<?php esc_attr_e('Preview', 'salon-booking-system'); ?>"><span class="<?php echo isset($row[$col]) ? 'pull-left' : 'half-opacity'; ?>"><?php echo isset($row[$col]) ? esc_html($row[$col]) : esc_html__('Preview', 'salon-booking-system'); ?></span></td>
			<?php endforeach; ?>
		</tr>
	<?php endforeach; ?>
	<tr class="empty">
		<?php foreach($columns as $col): ?>
			<td>&nbsp;</td>
		<?php endforeach; ?>
	</tr>
</tbody>
