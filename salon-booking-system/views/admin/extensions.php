<?php // phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped

global $sln_license;
$products = isset($sln_license) ? $sln_license->getEddProducts() : [];

function get_clean_description($content) {
    $content = trim(wp_strip_all_tags(preg_replace('/\[\/?[\w\-]+[^\]]*\]/', '', $content)));
    $content = preg_replace('/\s+/', ' ', $content);

    if (mb_strlen($content) > 250) {
        $content = mb_substr($content, 0, 250) . '...';
    }

    return $content;
}

?>

<div class="wrap sln-bootstrap">
    <h1><?php esc_html_e('Extensions', 'salon-booking-system'); ?></h1>
</div>

<section class="extensions-section">
    <h2>Add-ons included with your plan</h2>
    <h5>Install all the add-ons below and increase your salon powers.</h5>
    <?php if (count($products)) { ?>
        <div class="extensions-wrapper">
        <?php foreach ($products as $product) {
            $info = $product->info;
            if ($info->id == 23261)
                continue;
            if ($product->is_all_access_product)
                continue;
            if ($product->is_excluded_from_all_access)
                continue;

            $action = $actionLabel = 'Install';
            $files = isset($product->files) ? (array)$product->files : [];
            if (count($files)) {
                foreach ($files as $file) {
                    $res = SLN_Action_Ajax_InstallPlugin::get_plugin($file->name);
                    if ($res['success']) {
                        if ($res['check_version']) {
                            $action = $actionLabel = $res['is_activate'] ? 'Deactivate' : 'Activate';
                        } else {
                            $actionLabel = 'Update';
                        }
                    }
                }
            } else {
                continue;
            }

            $version = $product->licensing->version;
            $content = get_clean_description($info->content);
            $htmlThumb = '<img src="' . ($info->thumbnail ? $info->thumbnail : SLN_PLUGIN_URL . '/img/image.png') . '" alt="' . $info->title . '" />';
            $htmlPrice = '<span class="free"><img src="' . SLN_PLUGIN_URL . '/img/free1.png" alt="' . $info->title . '" /></span>';
        ?>
        <div data-id="<?php echo $info->id; ?>" data-action="<?php echo strtolower($action); ?>" class="extensions-item<?php echo count($files) ? '' : ' disabled' ?>">
            <div class="extensions-inner">
                <div class="extensions-thumb"><?php echo $htmlThumb; ?></div>
                <div class="extensions-name"><?php echo $info->title; ?></div>
                <div class="extensions-descr"><?php echo esc_html($content); ?></div>
                <div class="extensions-bottom">
                    <div class="extensions-wrap">
                        <div class="extensions-error"></div>
                    </div>
                    <div class="extensions-action">
                        <a href="#" class="extensions-button blue">
                            <span class="label"><?php echo $actionLabel; ?></span>
                            <span class="loader" style="display:none; margin-left:5px;">🔄</span>
                        </a>
                        <div class="extensions-version"><?php echo ($version ? 'v.' . $version : ''); ?></div>
                        <div class="extensions-price"><?php echo $htmlPrice; ?></div>
                    </div>
                </div>
            </div>
        </div>
        <?php } ?>
        </div>
    <?php } else { ?>
        <p>Products not found or an error occurred while fetching the data.</p>
    <?php } ?>
</section>