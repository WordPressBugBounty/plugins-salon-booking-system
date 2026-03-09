<?php // phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped ?>
<div class="sbc-calendar booking-main" data-attrs="<?php echo esc_attr(json_encode($data['attrs'])); ?>">

    <?php /* ── Tab bar: visible on mobile, hidden on desktop ── */ ?>
    <div class="sbc-tabs" role="tablist">
        <?php $isFirst = true; ?>
        <?php foreach ($data['attendants'] as $attId => $att): ?>
            <button
                class="sbc-tab<?php echo $isFirst ? ' is-active' : ''; ?>"
                data-target="sbc-col-<?php echo esc_attr($attId); ?>"
                role="tab"
                type="button"
            >
                <?php if (!empty($att['img'])): ?>
                    <img class="sbc-tab-avatar" src="<?php echo esc_url($att['img']); ?>" alt="<?php echo esc_attr($att['name']); ?>">
                <?php endif; ?>
                <span><?php echo esc_html($att['name']); ?></span>
            </button>
            <?php $isFirst = false; ?>
        <?php endforeach; ?>
    </div>

    <?php /* ── Assistant columns grid ── */ ?>
    <div class="sbc-grid">
        <?php $isFirst = true; ?>
        <?php foreach ($data['attendants'] as $attId => $att): ?>
            <div
                class="sbc-col<?php echo $isFirst ? ' is-active' : ''; ?>"
                id="sbc-col-<?php echo esc_attr($attId); ?>"
                data-page-size="<?php echo esc_attr($data['page_size']); ?>"
                data-total-days="<?php echo esc_attr(count($data['dates'])); ?>"
            >
                <?php /* Assistant header */ ?>
                <div class="sbc-att-header">
                    <div class="sbc-att-info">
                        <?php if (!empty($att['img'])): ?>
                            <img
                                class="sbc-att-avatar"
                                src="<?php echo esc_url($att['img']); ?>"
                                alt="<?php echo esc_attr($att['name']); ?>"
                            >
                        <?php else: ?>
                            <div class="sbc-att-avatar sbc-att-avatar--placeholder">
                                <?php echo esc_html(mb_substr($att['name'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                        <span class="sbc-att-name"><?php echo esc_html($att['name']); ?></span>
                    </div>
                    <?php
                        $totalPages = ceil(count($data['dates']) / $data['page_size']);
                        $hasNext    = $totalPages > 1;
                    ?>
                    <div class="sbc-att-nav">
                        <button class="sbc-nav-btn sbc-nav-btn--prev" type="button"
                                aria-label="<?php esc_attr_e('Previous', 'salon-booking-system'); ?>"
                                style="display:none">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12.5 15L7.5 10L12.5 5" stroke="#234C66" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>
                        <button class="sbc-nav-btn sbc-nav-btn--next" type="button"
                                aria-label="<?php esc_attr_e('Next', 'salon-booking-system'); ?>"
                                <?php echo !$hasNext ? 'style="display:none"' : ''; ?>>
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M7.5 15L12.5 10L7.5 5" stroke="#234C66" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <?php /* Day sections */ ?>
                <div class="sbc-days">
                    <?php $dayIndex = 0; ?>
                    <?php foreach ($data['dates'] as $datetime): ?>
                        <?php
                            $date   = $datetime->format('Y-m-d');
                            $events = !empty($att['events'][$date]) ? $att['events'][$date] : [];
                            $count  = count($events);
                        ?>
                        <div class="sbc-day<?php echo $dayIndex >= $data['page_size'] ? ' sbc-day--hidden' : ''; ?>"
                             data-day-index="<?php echo $dayIndex; ?>"
                        >
                            <div class="sbc-day-header">
                                <div class="sbc-day-name-row">
                                    <span class="sbc-day-name">
                                        <?php echo SLN_TimeFunc::translateDate('l', $datetime->getTimestamp(), $datetime->getTimezone()); ?>
                                    </span>
                                    <span class="sbc-count<?php echo $count === 0 ? ' sbc-count--zero' : ''; ?>">
                                        <?php echo $count; ?>
                                    </span>
                                </div>
                                <span class="sbc-day-date">
                                    <?php echo SLN_TimeFunc::translateDate('F d, Y', $datetime->getTimestamp(), $datetime->getTimezone()); ?>
                                </span>
                            </div>

                            <?php if (empty($events)): ?>
                                <div class="sbc-no-bookings"><?php esc_html_e('No bookings', 'salon-booking-system'); ?></div>
                            <?php else: ?>
                                <div class="sbc-bookings">
                                    <?php foreach ($events as $event):
                                        $isSuccess   = in_array($event['status_type'], array('success'));
                                        $statusClass = $isSuccess ? 'sbc-status--paid' : 'sbc-status--pending';
                                        $timeRange   = !empty($event['time_end'])
                                            ? esc_html($event['time']) . ' - ' . esc_html($event['time_end'])
                                            : esc_html($event['time']);
                                    ?>
                                        <div class="sbc-booking-card">
                                            <div class="sbc-card-top">
                                                <span class="sbc-time"><?php echo $timeRange; ?></span>
                                                <span class="sbc-status <?php echo esc_attr($statusClass); ?>">
                                                    <?php echo esc_html($event['status']); ?>
                                                </span>
                                            </div>
                                            <div class="sbc-client"><?php echo esc_html($event['title']); ?></div>
                                            <ul class="sbc-services">
                                                <?php foreach ($event['services'] as $service): ?>
                                                    <li><?php echo esc_html($service); ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php $dayIndex++; ?>
                    <?php endforeach; ?>
                </div>

            </div>
            <?php $isFirst = false; ?>
        <?php endforeach; ?>
    </div>

</div>
