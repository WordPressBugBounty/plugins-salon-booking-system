<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Column matching list for the CSV import wizard.
 *
 * One row per Salon Booking field: the CSV column it reads from (pre-selected
 * when the header looks like the field), and a sample value from the file.
 * The JS (sln_changeImportMatching) refreshes samples and the status badge.
 *
 * @var array $headers
 * @var array $rows
 * @var array $columns
 * @var array $required
 */

$labels = array(
	'first_name'          => __('First name', 'salon-booking-system'),
	'last_name'           => __('Last name', 'salon-booking-system'),
	'email'               => __('Email', 'salon-booking-system'),
	'mobile_phone'        => __('Mobile phone', 'salon-booking-system'),
	'phone'               => __('Phone', 'salon-booking-system'),
	'address'             => __('Address', 'salon-booking-system'),
	'personal_note'       => __('Personal note', 'salon-booking-system'),
	'administration_note' => __('Administration note', 'salon-booking-system'),
	'name'                => __('Name', 'salon-booking-system'),
	'description'         => __('Description', 'salon-booking-system'),
	'price'               => __('Price', 'salon-booking-system'),
	'duration'            => __('Duration', 'salon-booking-system'),
	'services'            => __('Services', 'salon-booking-system'),
);

// Header spellings commonly found in exports from other tools (normalized: lowercase, letters and digits only).
$synonyms = array(
	'first_name'          => array('firstname', 'first', 'givenname', 'name', 'nome', 'prenom', 'vorname', 'nombre'),
	'last_name'           => array('lastname', 'last', 'surname', 'familyname', 'cognome', 'nom', 'nachname', 'apellido', 'apellidos'),
	'email'               => array('email', 'emailaddress', 'mail', 'mailaddress', 'emailid', 'indirizzoemail', 'email1', 'posta', 'courriel'),
	'mobile_phone'        => array('mobilephone', 'mobile', 'phone', 'phonenumber', 'telephone', 'tel', 'cell', 'cellphone', 'cellulare', 'telefono', 'telefon', 'mobilenumber', 'contactnumber'),
	'phone'               => array('phone', 'phonenumber', 'mobile', 'mobilephone', 'telephone', 'tel', 'telefono', 'cellulare'),
	'address'             => array('address', 'streetaddress', 'indirizzo', 'adresse', 'direccion'),
	'personal_note'       => array('personalnote', 'note', 'notes', 'comment', 'comments', 'note1'),
	'administration_note' => array('administrationnote', 'adminnote', 'internalnote', 'staffnote'),
);

$normalize = function ($value) {
	$value = function_exists('mb_strtolower') ? mb_strtolower((string) $value) : strtolower((string) $value);
	return preg_replace('/[^a-z0-9]/', '', remove_accents($value));
};

// Pre-select a header for each field: exact name first, then known synonyms; each header is used once.
$guess = array();
$used  = array();
foreach (array(false, true) as $useSynonyms) {
	foreach ($columns as $col) {
		if (isset($guess[$col])) {
			continue;
		}
		$candidates = array($normalize($col), $normalize(isset($labels[$col]) ? $labels[$col] : ''));
		if ($useSynonyms && isset($synonyms[$col])) {
			$candidates = array_merge($candidates, $synonyms[$col]);
		}
		foreach ($candidates as $candidate) {
			if ($candidate === '') {
				continue;
			}
			foreach ($headers as $header) {
				if (!isset($used[$header]) && $normalize($header) === $candidate) {
					$guess[$col]     = $header;
					$used[$header] = true;
					break 2;
				}
			}
		}
	}
}

$samples = function ($header) use ($rows) {
	$values = array();
	foreach ($rows as $row) {
		if (isset($row[$header]) && trim((string) $row[$header]) !== '') {
			$values[] = trim((string) $row[$header]);
		}
	}
	return array_slice($values, 0, 3);
};
?>
<ul class="sln-import-map__list">
	<li class="sln-import-map__head" aria-hidden="true">
		<span><?php esc_html_e('Salon Booking field', 'salon-booking-system') ?></span>
		<span><?php esc_html_e('Column in your file', 'salon-booking-system') ?></span>
		<span><?php esc_html_e('Sample from your file', 'salon-booking-system') ?></span>
	</li>
	<?php foreach ($columns as $i => $col):
		$isRequired = in_array($col, $required, true);
		$label      = isset($labels[$col]) ? $labels[$col] : ucfirst(str_replace('_', ' ', $col));
		if (function_exists('mb_strtoupper') && $label === mb_strtoupper($label) && preg_match('/\p{L}{2}/u', $label)) {
			$label = mb_strtoupper(mb_substr($label, 0, 1)) . mb_strtolower(mb_substr($label, 1)); // "CUSTOMER EMAIL" -> "Customer email"
		}
		$selected   = isset($guess[$col]) ? $guess[$col] : '';
		$values     = $selected !== '' ? $samples($selected) : array();
		$selectId   = 'import_matching_' . sanitize_key($col);
		$state      = $selected !== '' ? 'matched' : ($isRequired ? 'missing' : 'skipped');
		?>
		<li class="sln-import-map__row sln-import-map__row--<?php echo esc_attr($state) ?>" data-col="<?php echo esc_attr($i) ?>" data-required="<?php echo $isRequired ? '1' : '0' ?>">
			<label class="sln-import-map__field" for="<?php echo esc_attr($selectId) ?>">
				<span class="sln-import-map__status" aria-hidden="true"></span>
				<span class="sln-import-map__name"><?php echo esc_html($label) ?></span>
				<?php if ($isRequired): ?>
					<span class="sln-import-map__badge"><?php esc_html_e('Required', 'salon-booking-system') ?></span>
				<?php endif ?>
			</label>
			<div class="sln-import-map__select">
				<select id="<?php echo esc_attr($selectId) ?>" name="import_matching[<?php echo esc_attr($col) ?>]"
				        data-action="sln_import_matching_select" data-col="<?php echo esc_attr($i) ?>"
				        <?php echo $isRequired ? 'required="required"' : '' ?> autocomplete="off">
					<option value=""><?php echo $isRequired ? esc_html__('Choose a column…', 'salon-booking-system') : esc_html__('Don’t import', 'salon-booking-system') ?></option>
					<?php foreach ($headers as $header):
						if ((string) $header === '') { continue; } ?>
						<option value="<?php echo esc_attr($header) ?>" <?php selected($selected, $header) ?>><?php echo esc_html($header) ?></option>
					<?php endforeach ?>
				</select>
			</div>
			<div class="sln-import-map__sample" data-import-sample>
				<?php if ($values): ?>
					<?php foreach ($values as $value): ?>
						<span class="sln-import-map__value" title="<?php echo esc_attr($value) ?>"><?php echo esc_html($value) ?></span>
					<?php endforeach ?>
				<?php else: ?>
					<span class="sln-import-map__empty"><?php echo $selected !== '' ? esc_html__('Empty in the first rows', 'salon-booking-system') : '—' ?></span>
				<?php endif ?>
			</div>
		</li>
	<?php endforeach; ?>
</ul>
