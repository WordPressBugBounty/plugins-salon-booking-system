<?php if ($size == '400') : ?>
    <?php if ($errors) : ?>
        <div class="col-xs-12">
            <span class="errors-area" data-class="sln-alert sln-alert-medium sln-alert--problem">
                <div class="sln-alert sln-alert-medium sln-alert--problem sln-service-error">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo esc_html($error) ?></p>
                    <?php endforeach ?>
                </div>
            </span>
        </div>
    <?php endif ?>
<?php else: ?>

    <?php if ($errors) : ?>
        <span class="sln-service__errors sln-list__item__errors errors-area" data-class="sln-alert sln-alert-medium sln-alert--problem">
                    <span class="errors-area" data-class="sln-alert sln-alert-medium sln-alert--problem">
                        <div class="sln-alert sln-alert-medium sln-alert--problem sln-service-error">
                            <?php foreach ($errors as $error): ?>
                                <p><?php echo esc_html($error) ?></p>
                            <?php endforeach ?>
                        </div>
                    </span>
        </span>
        <div class="sln-list__item__fkbkg"></div>
    <?php endif ?>
<?php endif ?>