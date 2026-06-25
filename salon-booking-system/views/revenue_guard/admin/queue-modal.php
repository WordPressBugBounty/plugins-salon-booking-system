<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<style>
#sln-rg-queue-modal {
	position: fixed;
	inset: 0;
	z-index: 100000;
}
#sln-rg-queue-modal .sln-rg-modal__backdrop {
	position: fixed;
	inset: 0;
	background: rgba(0, 53, 83, 0.45);
}
#sln-rg-queue-modal .sln-rg-modal__dialog {
	position: fixed;
	top: 50%;
	left: 50%;
	transform: translate(-50%, -50%);
	background: #fff;
	border: 1px solid #d8e0e6;
	border-radius: 8px;
	width: 92%;
	max-width: 640px;
	max-height: 82vh;
	display: flex;
	flex-direction: column;
	overflow: hidden;
	z-index: 100001;
	box-shadow: 0 12px 40px rgba(0, 53, 83, 0.25);
}
#sln-rg-queue-modal .sln-rg-modal__header {
	display: flex;
	justify-content: space-between;
	align-items: flex-start;
	gap: 12px;
	padding: 18px 20px 14px;
	border-bottom: 1px solid #e9ecef;
}
#sln-rg-queue-modal .sln-rg-modal__title {
	margin: 0;
	font-size: 17px;
	font-weight: 600;
	line-height: 1.3;
	color: #2171b1;
	padding: 0;
}
#sln-rg-queue-modal .sln-rg-modal__intro {
	margin: 4px 0 0;
	font-size: 13px;
	color: #7d8890;
}
#sln-rg-queue-modal .sln-rg-modal__close {
	background: none;
	border: none;
	font-size: 24px;
	line-height: 1;
	cursor: pointer;
	color: #7d8890;
	padding: 0;
	flex-shrink: 0;
	transition: color 0.15s ease;
}
#sln-rg-queue-modal .sln-rg-modal__close:hover {
	color: #003553;
}
#sln-rg-queue-modal .sln-rg-modal__body {
	padding: 8px 20px;
	overflow-y: auto;
}
#sln-rg-queue-modal .sln-rg-queue-list {
	list-style: none;
	margin: 0;
	padding: 0;
}
#sln-rg-queue-modal .sln-rg-queue-item {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	flex-wrap: wrap;
	padding: 14px 8px;
	border-bottom: 1px solid #d8e0e6;
	transition: background-color 0.2s ease;
}
#sln-rg-queue-modal .sln-rg-queue-item:last-child {
	border-bottom: none;
}
#sln-rg-queue-modal .sln-rg-queue-item:hover {
	background-color: #f3f6f9;
	border-radius: 6px;
}
#sln-rg-queue-modal .sln-rg-queue-item__info {
	display: flex;
	flex-direction: column;
	gap: 3px;
	flex: 1 1 auto;
	min-width: 160px;
}
#sln-rg-queue-modal .sln-rg-queue-item__customer {
	font-size: 14px;
	font-weight: 600;
	color: #333;
}
#sln-rg-queue-modal .sln-rg-queue-item__meta {
	display: flex;
	align-items: center;
	flex-wrap: wrap;
	gap: 4px 12px;
	font-size: 13px;
	color: #7d8890;
}
#sln-rg-queue-modal .sln-rg-queue-item__time {
	display: inline-flex;
	align-items: center;
	gap: 5px;
	white-space: nowrap;
}
#sln-rg-queue-modal .sln-rg-queue-item__time svg {
	flex-shrink: 0;
	color: #7d8890;
}
#sln-rg-queue-modal .sln-rg-queue-item__amount {
	font-weight: 600;
	color: #003553;
	white-space: nowrap;
}
#sln-rg-queue-modal .sln-rg-queue-item__actions {
	display: flex;
	flex-wrap: wrap;
	gap: 6px;
	flex-shrink: 0;
}
#sln-rg-queue-modal .sln-rg-pill {
	display: inline-block;
	background-color: transparent;
	color: #2171b1;
	border: 1px solid #2171b1;
	border-radius: 20px;
	padding: 5px 14px;
	font-size: 13px;
	font-weight: 600;
	line-height: 1.2;
	cursor: pointer;
	white-space: nowrap;
	transition: all 0.2s ease;
}
#sln-rg-queue-modal .sln-rg-pill:hover {
	background-color: rgba(33, 113, 177, 0.1);
	border-color: #1a5a8f;
	color: #1a5a8f;
}
#sln-rg-queue-modal .sln-rg-pill:disabled {
	opacity: 0.5;
	cursor: default;
}
#sln-rg-queue-modal .sln-rg-pill--primary {
	background-color: #2171b1;
	color: #fff;
}
#sln-rg-queue-modal .sln-rg-pill--primary:hover {
	background-color: #1a5a8f;
	color: #fff;
}
#sln-rg-queue-modal .sln-rg-pill--muted {
	border-color: #d8e0e6;
	color: #7d8890;
}
#sln-rg-queue-modal .sln-rg-pill--muted:hover {
	border-color: #b9c6cf;
	color: #5a6b75;
	background-color: rgba(125, 136, 144, 0.08);
}
#sln-rg-queue-modal .sln-rg-empty,
#sln-rg-queue-modal .sln-rg-loading,
#sln-rg-queue-modal .sln-rg-error {
	margin: 0;
	padding: 24px 8px;
	text-align: center;
	color: #7d8890;
	font-size: 14px;
}
#sln-rg-queue-modal .sln-rg-modal__footer {
	display: flex;
	gap: 8px;
	justify-content: flex-end;
	padding: 14px 20px 18px;
	border-top: 1px solid #e9ecef;
}
#sln-rg-queue-modal .sln-rg-modal__footer .sln-rg-pill {
	padding: 7px 18px;
}
body.sln-rg-modal-open {
	overflow: hidden;
}
</style>
<div id="sln-rg-queue-modal" class="sln-rg-modal" style="display:none;" aria-hidden="true">
	<div class="sln-rg-modal__backdrop"></div>
	<div class="sln-rg-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="sln-rg-queue-title">
		<div class="sln-rg-modal__header">
			<div>
				<h2 id="sln-rg-queue-title" class="sln-rg-modal__title"><?php esc_html_e( 'Confirm attendance', 'salon-booking-system' ); ?></h2>
				<p class="sln-rg-modal__intro"><?php esc_html_e( 'Mark whether each past appointment was attended, partially delivered, or a no-show.', 'salon-booking-system' ); ?></p>
			</div>
			<button type="button" class="sln-rg-modal__close" aria-label="<?php esc_attr_e( 'Close', 'salon-booking-system' ); ?>">&times;</button>
		</div>
		<div class="sln-rg-modal__body">
			<div id="sln-rg-queue-list" class="sln-rg-queue-list">
				<p class="sln-rg-loading"><?php esc_html_e( 'Loading...', 'salon-booking-system' ); ?></p>
			</div>
		</div>
		<div class="sln-rg-modal__footer">
			<button type="button" class="sln-rg-pill sln-rg-pill--muted sln-rg-modal__close">
				<?php esc_html_e( 'Done', 'salon-booking-system' ); ?>
			</button>
			<button type="button" class="sln-rg-pill sln-rg-pill--primary" id="sln-rg-bulk-attended">
				<?php esc_html_e( 'Mark all as attended', 'salon-booking-system' ); ?>
			</button>
		</div>
	</div>
</div>
