<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
 // phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
$sln_upcoming_count = count($data['new']['items']);
?>
<div class="sln-account__top">
	<div class="sln-account__top__user">
		<span class="sln-account__avatar" aria-hidden="true"><?php echo esc_html($data['user_initials']); ?></span>
		<div class="sln-account__top__greeting">
			<p class="sln-account__top__welcome"><?php esc_html_e('Welcome back', 'salon-booking-system'); ?></p>
			<p class="sln-account__top__name"><?php echo esc_html($data['user_name']); ?></p>
		</div>
		<?php if ($data['customer_fidelity_score_enabled']): ?>
			<span class="sln-account__score">
				<?php echo $data['customer_fidelity_score'] ?>
				<span class="sr-only"><?php esc_html_e('Current Score', 'salon-booking-system') ?></span>
			</span>
		<?php endif ?>
		<a class="sln-account__iconbtn sln-account__logout-link" href="<?php echo esc_url(wp_logout_url(home_url())); ?>" aria-label="<?php esc_attr_e('Log out', 'salon-booking-system'); ?>">
			<svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h3"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg>
		</a>
	</div>
	<!-- Nav tabs -->
	<nav id="sln-account__nav" class="sln-account__tabs" aria-label="<?php esc_attr_e('Account sections', 'salon-booking-system'); ?>">
		<ul class="nav nav-tabs sln-account__nav" role="tablist">
			<li class="sln-account__nav__item sln-account__nav__appointments active" role="presentation">
				<a href="#sln-account__appointments__content" data-target="#sln-account__appointments__content" aria-controls="sln-account__appointments__content" role="tab" data-toggle="tab">
					<svg aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4.5" width="18" height="16" rx="2.5"/><path d="M3 9.5h18M8 2.5v4M16 2.5v4"/></svg>
					<span><?php esc_html_e('Appointments', 'salon-booking-system')?></span>
				</a>
			</li>
			<?php do_action('sln.my_account.nav'); ?>
			<?php if (class_exists('SalonPackages\Addon') && slnpackages_is_pro_version_salon() && slnpackages_is_license_active()) { ?>
				<li class="sln-account__nav__item sln-account__nav__packages" role="presentation">
					<a href="#sln-account__packages__content" data-target="#sln-account__packages__content" aria-controls="sln-account__packages__content" role="tab" data-toggle="tab">
						<svg aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="M3 8l9 5 9-5M12 13v8"/></svg>
						<span><?php esc_html_e('Packages', 'sln-package')?></span>
					</a>
				</li>
			<?php } ?>
			<li class="sln-account__nav__item sln-account__nav__profile" role="presentation">
				<a href="#sln-account__profile__content" data-target="#sln-account__profile__content" aria-controls="sln-account__profile__content" role="tab" data-toggle="tab">
					<svg aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-7 8-7s8 3 8 7"/></svg>
					<span><?php esc_html_e('Profile', 'salon-booking-system')?></span>
				</a>
			</li>
		</ul>
	</nav>
</div>
<!-- Tab panes -->
<div class="tab-content sln-account__body">
	<div role="tabpanel" class="tab-pane sln-account__tabpanel sln-account__tabpanel--bookings active" id="sln-account__appointments__content">
		<?php if ($data['cancelled']): ?>
			<p class="hint sln-account__notice"><?php esc_html_e('The booking has been cancelled', 'salon-booking-system');?></p>
		<?php endif?>

		<?php if ($data['booking_url']): ?>
			<a class="sln-account__btn sln-account__btn--primary sln-account__btn--cta" href="<?php echo esc_url($data['booking_url']) ?>">
				<svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
				<?php esc_html_e('New reservation', 'salon-booking-system')?>
			</a>
		<?php endif; ?>

		<div class="sln-account__segment" role="group" aria-label="<?php esc_attr_e('Show appointments', 'salon-booking-system'); ?>">
			<button type="button" class="sln-account__segment__btn" aria-pressed="true" aria-controls="sln-account__upcoming">
				<?php
				// translators: %d: number of upcoming appointments
				echo esc_html(sprintf(__('Upcoming (%d)', 'salon-booking-system'), $sln_upcoming_count));
				?>
			</button>
			<button type="button" class="sln-account__segment__btn" aria-pressed="false" aria-controls="sln-account__history">
				<?php esc_html_e('Past', 'salon-booking-system'); ?>
			</button>
		</div>

		<div id="sln-account__upcoming" class="sln-account__appts">
			<?php if ($sln_upcoming_count): ?>
				<h2 class="sln-account__section-title"><?php esc_html_e('Next appointment', 'salon-booking-system'); ?></h2>
				<?php
				$data['table_data'] = array(
					'items'   => array_slice($data['new']['items'], 0, 1),
					'mode'    => 'new',
					'variant' => 'next',
				);
				include '_salon_my_account_details_table_rows.php';
				?>
				<?php if ($sln_upcoming_count > 1): ?>
					<h2 class="sln-account__section-title"><?php esc_html_e('Later', 'salon-booking-system'); ?></h2>
					<div class="sln-account__appts__list">
						<?php
						$data['table_data'] = array(
							'items'   => array_slice($data['new']['items'], 1),
							'mode'    => 'new',
							'variant' => 'compact',
						);
						include '_salon_my_account_details_table_rows.php';
						?>
					</div>
				<?php endif; ?>
				<?php unset($data['table_data']); ?>
			<?php else: ?>
				<p class="sln-account__empty"><?php esc_html_e('No upcoming appointments', 'salon-booking-system');?></p>
			<?php endif;?>
		</div>

		<div id="sln-account__history" class="sln-account__history sln-account__appts" hidden>
			<?php if (!empty($data['history']['items'])): ?>
				<div id="sln-salon-my-account-history-content--" class="sln-account__appts__list">
					<?php
					$data['table_data'] = $data['history'];
					$data['table_data']['mode'] = 'history';
					$data['table_data']['variant'] = 'compact';

					include '_salon_my_account_details_table_rows.php';
					unset($data['table_data']);
					?>
				</div>
				<?php if (empty($data['history']['end'])): ?>
					<p class="salon-scroll-down--note sln-account__history__scrollformore"><?php esc_html_e('Scroll down to load past reservations', 'salon-booking-system'); ?></p>
				<?php endif; ?>
			<?php else: ?>
				<p class="sln-account__empty"><?php esc_html_e('No bookings', 'salon-booking-system');?></p>
			<?php endif;?>
		</div>
	</div>
	<?php do_action('sln.my_account.content', array_merge($data['history']['items'], $data['new']['items'])); ?>
    <?php if (class_exists('SalonPackages\Addon') && slnpackages_is_pro_version_salon() && slnpackages_is_license_active()) { ?>
        <div role="tabpanel" class="tab-pane sln-account__tabpanel sln-account__tabpanel--packages" id="sln-account__packages__content">
            <?php echo SLN_Plugin::getInstance()->templating()->loadView('shortcode/salon_my_account/_salon_my_account_packages'); ?>
        </div>
    <?php } ?>
    <div role="tabpanel" class="tab-pane sln-account__tabpanel sln-account__tabpanel--profile" id="sln-account__profile__content">
        <?php include '_salon_my_account_profile.php';?>
    </div>
	<div id="ratingModal" class="modal fade sln-account__modal" role="dialog" aria-modal="true" aria-labelledby="sln-rating-title" tabindex="-1">
		<div class="modal-dialog">
			<div class="modal-content">
				<button type="button" class="close sln-account__iconbtn sln-account__modal__close" data-dismiss="modal" aria-label="<?php esc_attr_e('Close', 'salon-booking-system'); ?>">
					<svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
				</button>
				<div class="modal-body">
					<div id="step1" class="sln-account__modal__step">
						<p class="sln-account__modal__eyebrow"><?php esc_html_e('Hi', 'salon-booking-system');?> <?php echo esc_html($data['user_name']); ?>!</p>
						<h2 id="sln-rating-title" class="sln-account__modal__title"><?php esc_html_e('How was your experience with us?', 'salon-booking-system');?></h2>

						<div class="sln-account__modal__field" role="group" aria-labelledby="sln-rating-stars-label">
							<p id="sln-rating-stars-label" class="sln-account__label"><?php esc_html_e('Rate our service', 'salon-booking-system');?> <span class="sln-account__required" aria-hidden="true">*</span></p>
							<div class="sln-account__stars">
								<div class="rating"></div>
							</div>
						</div>

						<div class="sln-account__modal__field">
							<p id="sln-rating-comment-label" class="sln-account__label"><?php esc_html_e('Your comments', 'salon-booking-system');?> <span class="sln-account__required" aria-hidden="true">*</span></p>
							<textarea class="sln-account__modal__textarea" aria-labelledby="sln-rating-comment-label" aria-required="true" placeholder="<?php esc_attr_e('please, drop us some lines to understand if your experience has been  in line  with your expectations', 'salon-booking-system');?>"></textarea>
						</div>

						<p class="sln-account__modal__error" role="alert" hidden><?php esc_html_e('Please choose a rating and write a few words before sending.', 'salon-booking-system'); ?></p>

						<div class="sln-account__modal__actions">
							<button type="button" class="btn btn-primary sln-account__btn sln-account__btn--primary sln-account__btn--wide" onclick="sln_myAccount.sendRate();"><?php esc_html_e('Send your review', 'salon-booking-system');?></button>
							<button type="button" class="btn btn-default sln-account__btn sln-account__btn--text" data-dismiss="modal"><?php esc_html_e('Cancel', 'salon-booking-system');?></button>
						</div>
					</div>
					<div id="step2" class="sln-account__modal__step sln-account__modal__done">
						<span class="sln-account__modal__done-icon" aria-hidden="true">
							<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5 9-10"/></svg>
						</span>
						<p class="sln-account__modal__title"><?php esc_html_e('Thank you for your review. It will help us improving our services.', 'salon-booking-system');?></p>
						<p class="sln-account__hint"><?php esc_html_e('We hope to see you again at', 'salon-booking-system');?> <?php echo esc_html($data['gen_name']); ?></p>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
