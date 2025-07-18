<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
/**
 * @var SLN_Wrapper_Attendant $attendant
 */
try {
    $_calendar_list = $GLOBALS['sln_googlescope']->get_calendar_list(array('writer', 'owner'));
} catch (Exception $e) {
	esc_html_e('Calendar is not configured', 'salon-booking-system');

    return;
}
if (empty($_calendar_list)) {
	esc_html_e('Calendar is not configured', 'salon-booking-system');

    return;
}
$attendantGCalendar = $attendant->getGoogleCalendar();
?>
    <label class="screen-reader-text" for="excerpt">
        <?php esc_html_e('Assistant Google Calendar', 'salon-booking-system') ?>
    </label>
    <div class="col-xs-12 col-sm-12 form-group sln-select sln-select--info-label">
        <label for="_sln_attendant_google_calendar"><?php esc_html_e('Calendars', 'salon-booking-system') ?></label>
        <select id="_sln_attendant_google_calendar" name="_sln_attendant_google_calendar">
            <?php
            foreach ($_calendar_list as $k => $value) {
                $lbl = $value['label'];
                $sel = ($value['id'] == $attendantGCalendar) ? "selected" : "";
                echo "<option value='$k' $sel>$lbl</option>";
            }
            ?>
        </select>
    </div>
    <div class="clearfix"></div>
<style>
    .post-type-sln_attendant .select2.select2-container{
        overflow:hidden;
    }
</style>
<?php
