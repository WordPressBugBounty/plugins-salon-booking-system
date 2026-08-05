<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

if (!defined("SLN_VERSION_PAY")) {
    if (!isset($cta_url) || !$cta_url) {
        $cta_url = defined( 'SLN_PRICING_URL' ) ? SLN_PRICING_URL : 'https://www.salonbookingsystem.com/plugin-pricing-2/';
    }
    if (!isset($additional_classes) || !$additional_classes) {
        $additional_classes = "";
    }
    if (!isset($trigger) || !$trigger) {
        $trigger = "";
    }

    echo '<div class="sln-profeature__cta ' . esc_attr($additional_classes) . '">';
    echo '<a href="#nogo" class="sln-profeature__open-button" data-tiptext="' . esc_html__('Switch to PRO to unlock this feature! Click to know more.', 'salon-booking-system') . '"><span class="sr-only">' . esc_html__('Switch to PRO to unlock this feature!', 'salon-booking-system') . '</span></a>';
    echo '<dialog class="sln-profeature__dialog">';
    echo '<h3 class="sln-profeature__tooltip__title">' . esc_html__('Unlock this feature today for a special price.', 'salon-booking-system') . '</h3>';

    $bullets = array(
        esc_html__('Get access to all PRO features', 'salon-booking-system'),
        esc_html__('Get access to Mobile Web App', 'salon-booking-system'),
        esc_html__('Activate online payments', 'salon-booking-system'),
        esc_html__('Get email priority support', 'salon-booking-system'),
        esc_html__('Download our add-ons for free', 'salon-booking-system'),
    );
    foreach ($bullets as $bullet) {
        echo '<h4 class="sln-profeature__tooltip__bullet">' . wp_kses(
                $bullet,
                array(
                    'a' => array(
                        'href' => array(),
                        'target' => array(),
                        'rel' => array(),
                    ),
                    'strong' => array(),
                    'em' => array(),
                    'span' => array(
                        'class' => array(),
                    ),
                    'div' => array(
                        'class' => array(),
                    ),
                )
            ) . '</h4>';

    }
    echo '<div class="sln-profeature__tooltip__cta">';
    echo '<a href="' . esc_url($cta_url) . '" target="_blank">' . wp_kses(
            __('Switch to <strong>PR<span>O</span></strong>', 'salon-booking-system'),
            array(
                'strong' => array(),
                'span' => array(),
            )
        ) . '</a>';
    echo '<h6 class="sln-profeature__tooltip__btn-info">' . esc_html__('Get 15% discount', 'salon-booking-system') . '</h6>';
    echo '</div>';
    echo '<a href="#nogo" class="sln-profeature__close-button"><span class="sr-only">Close dialog</span></a>';
    echo '<div class="sln-profeature__dialog-fakedrop"></div>';
    echo '</dialog>';
    echo '</div>';
}
