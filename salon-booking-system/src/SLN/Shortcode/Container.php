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
}
