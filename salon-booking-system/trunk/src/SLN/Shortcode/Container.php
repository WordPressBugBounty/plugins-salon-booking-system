<?php // algolplus

class SLN_Shortcode_Container
{
    const NAME = 'salon';

    private $plugin;
    private $attrs;

    function __construct(SLN_Plugin $plugin, $attrs)
    {
        $this->plugin = $plugin;
        $this->attrs = $attrs;
    }

    public static function init(SLN_Plugin $plugin)
    {
        add_shortcode(self::NAME, array(__CLASS__, 'create'));
    }

    public static function create($attrs)
    {
        SLN_TimeFunc::startRealTimezone();

        $obj = new self(SLN_Plugin::getInstance(), $attrs);
        $ret = $obj->execute();

        SLN_TimeFunc::endRealTimezone();

        return $ret;
    }

    public function execute()
    {
        $data = [];
        return $this->render($data);
    }

    protected function render($data = [])
    {
        $salon = $this;
        return $this->plugin->loadView('shortcode/container', compact('data', 'salon'));
    }

    public function getStyleShortcode()
    {
        return $this->attrs['style'] ?? $this->plugin->getSettings()->getStyleShortcode();
    }

    /**
     * Generates a WordPress-style shortcode string with the current attributes.
     *
     * This method builds a shortcode using the provided shortcode name and all attributes
     * stored in the `$this->attrs` array. Only scalar attribute values will be included.
     * Each attribute value is escaped with `esc_attr()`.
     *
     * Example output:
     * [shortcode_name attr1="value1" attr2="value2"]
     * [shortcode_name] (if no attributes or only non-scalar attributes were present)
     *
     * @param string $shortcode_name The name of the shortcode to generate (e.g. "salon_booking").
     * @return string The constructed shortcode string with attributes.
     */
    public function getShortcodeStringWithAttrs(string $shortcode_name): string
    {
        $sanitized_shortcode_name = sanitize_key($shortcode_name);

        $attribute_parts = [];

        foreach ($this->attrs as $key => $value) {
            // Only process scalar values (strings, integers, floats, booleans).
            if (!is_scalar($value)) {
                continue; // Skip non-scalar attributes.
            }

            // Ensure key is a string for esc_attr(), though usually keys are strings.
            $escaped_key = esc_attr((string)$key);
            // Cast scalar value to string before escaping for consistency.
            $escaped_value = esc_attr((string)$value);

            $attribute_parts[] = sprintf('%s="%s"', $escaped_key, $escaped_value);
        }

        $attributes_string = implode(' ', $attribute_parts);

        // Construct the shortcode string, handling the case of no attributes.
        if (!empty($attributes_string)) {
            return sprintf('[%s %s]', $sanitized_shortcode_name, $attributes_string);
        } else {
            return sprintf('[%s]', $sanitized_shortcode_name);
        }
    }
}
