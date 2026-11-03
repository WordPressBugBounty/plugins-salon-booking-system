<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Optional color of a service category, used by the weekly schedule as the
 * category indicator. Stored as term meta; categories without a color fall
 * back to the plugin main color.
 */
class SLN_WeeklySchedule_CategoryColor
{
    const META_KEY = 'sln_color';
    const NONCE    = 'sln_category_color_nonce';

    public static function init()
    {
        $taxonomy = SLN_Plugin::TAXONOMY_SERVICE_CATEGORY;
        add_action($taxonomy . '_add_form_fields', array(__CLASS__, 'renderAddField'), 20);
        add_action($taxonomy . '_edit_form_fields', array(__CLASS__, 'renderEditField'), 20);
        add_action('created_' . $taxonomy, array(__CLASS__, 'save'));
        add_action('edited_' . $taxonomy, array(__CLASS__, 'save'));
    }

    /**
     * @return string Hex color or empty string.
     */
    public static function get($termId)
    {
        $color = sanitize_hex_color((string) get_term_meta($termId, 'sln_color', true));

        return $color ? $color : '';
    }

    public static function renderAddField()
    {
        ?>
        <div class="form-field term-sln-color-wrap">
            <label for="sln-category-color"><?php esc_html_e('Color', 'salon-booking-system'); ?></label>
            <?php self::renderInput(''); ?>
            <p class="description"><?php esc_html_e('Optional. Used to mark the category in the weekly schedule.', 'salon-booking-system'); ?></p>
        </div>
        <?php
    }

    public static function renderEditField($term)
    {
        ?>
        <tr class="form-field term-sln-color-wrap">
            <th scope="row"><label for="sln-category-color"><?php esc_html_e('Color', 'salon-booking-system'); ?></label></th>
            <td>
                <?php self::renderInput(self::get($term->term_id)); ?>
                <p class="description"><?php esc_html_e('Optional. Used to mark the category in the weekly schedule.', 'salon-booking-system'); ?></p>
            </td>
        </tr>
        <?php
    }

    private static function renderInput($value)
    {
        wp_nonce_field(self::NONCE, self::NONCE);
        ?>
        <input type="color" id="sln-category-color" name="sln_category_color" value="<?php echo esc_attr($value ? $value : '#000000'); ?>" style="width:4em;height:2.5em;padding:0">
        <label style="display:inline-block;margin-left:.75em">
            <input type="checkbox" name="sln_category_color_enabled" value="1" <?php checked((bool) $value); ?>>
            <?php esc_html_e('Use this color', 'salon-booking-system'); ?>
        </label>
        <?php
    }

    public static function save($termId)
    {
        if (!isset($_POST[self::NONCE])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::NONCE])), self::NONCE)
            || !current_user_can(get_taxonomy(SLN_Plugin::TAXONOMY_SERVICE_CATEGORY)->cap->edit_terms)) {
            return;
        }
        $color = isset($_POST['sln_category_color']) ? sanitize_hex_color(wp_unslash($_POST['sln_category_color'])) : '';
        if (!empty($_POST['sln_category_color_enabled']) && $color) {
            update_term_meta($termId, 'sln_color', $color);
        } else {
            delete_term_meta($termId, 'sln_color');
        }
    }
}
