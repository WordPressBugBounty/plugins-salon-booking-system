<?php // phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped ?>
<div class="sln-progbar__wrapper">
    <?php
    $steps = $salon->getSteps();
    if (($key = array_search('fbphone', $steps)) !== false) {
        unset($steps[$key]);
    }
    $stepstotal = count($steps);
    ?>
    </h1>
    <div class="sln-progbar__text">
        <?php
        esc_html_e('Step', 'salon-booking-system');
        $sommaA = 1;
        foreach ($steps as $step) {
            echo ' <span>';
            if ($step == $salon->getCurrentStep()) {
                echo $sommaA . '/' . $stepstotal;
            }
            echo '</span>';
            $sommaA++;
        }
        ?>
    </div>
    <div class="sln-progbar">
        <?php
        $revsteps = array_reverse($steps);
        $sommaB = 1;
        foreach ($revsteps as $step) {
            echo '<div class="sln-progbar__item ';
            if ($step == $salon->getCurrentStep()) {
                echo 'sln-progbar__item--current';
            }
            echo ' sln-progbar__item--' . $step . '" data-zindex="' . $sommaB . '"><span class="sr-only">' . $step . '</span></div>';
            $sommaB++;
        }
        ?>
    </div>
</div>