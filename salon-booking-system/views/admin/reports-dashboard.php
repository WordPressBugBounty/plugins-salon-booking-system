<?php
/**
 * Modern Reports Dashboard Template
 *
 * @var SLN_Plugin $plugin
 */

// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
?>

<div id="sln-salon--admin">
    <?php if (!defined('SLN_VERSION_PAY')): ?>
    <!-- Hidden PRO Feature Dialog (same as calendar page) -->
    <div id="sln-reports-dashboard-pro-dialog" class="sln-profeature sln-profeature--disabled sln-profeature__tooltip-wrapper" style="position: absolute; left: -9999px; visibility: hidden;">
        <?php echo $plugin->loadView(
            'metabox/_pro_feature_tooltip',
            array(
                'additional_classes' => 'sln-profeature__cta--reports-dashboard',
                'trigger' => 'reports-dashboard',
            )
        ); ?>
    </div>
    <?php endif; ?>
    
    <!-- Initialize dashboard settings -->
    <script type="text/javascript">
        window.salonDashboard = {
            isPro: <?php echo defined('SLN_VERSION_PAY') ? 'true' : 'false'; ?>,
            ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
            restUrl: '<?php echo rest_url('sln/v1/'); ?>',
            nonce: '<?php echo wp_create_nonce('wp_rest'); ?>'
        };
    </script>
    
<div id="sln-reports-dashboard" class="sln-bootstrap">
    <h1 class="sln-dashboard-title">
        <?php esc_html_e('Dashboard', 'salon-booking-system') ?>
        <?php if (!empty($current_shop_name)): ?>
            <span class="sln-current-shop-name"> - <?php echo esc_html($current_shop_name); ?></span>
        <?php endif; ?>
    </h1>

    <!-- Date Range Filter -->
    <div class="sln-dashboard-filters">
        <label for="sln-date-range"><?php esc_html_e('Date Range:', 'salon-booking-system') ?></label>
        <select id="sln-date-range" class="sln-date-range-selector">
            <option value="today"><?php esc_html_e('Today', 'salon-booking-system') ?></option>
            <option value="yesterday"><?php esc_html_e('Yesterday', 'salon-booking-system') ?></option>
            <option value="this_week"><?php esc_html_e('This Week', 'salon-booking-system') ?></option>
            <option value="last_7_days"><?php esc_html_e('Last 7 Days', 'salon-booking-system') ?></option>
            <option value="last_30_days"><?php esc_html_e('Last 30 Days', 'salon-booking-system') ?></option>
            <option value="this_month" selected><?php esc_html_e('This Month', 'salon-booking-system') ?></option>
            <option value="last_month"><?php esc_html_e('Last Month', 'salon-booking-system') ?></option>
            <option value="this_quarter"><?php esc_html_e('This Quarter', 'salon-booking-system') ?></option>
            <option value="this_year"><?php esc_html_e('This Year', 'salon-booking-system') ?></option>
            <option value="custom"><?php esc_html_e('Custom Range', 'salon-booking-system') ?></option>
        </select>
        <span id="sln-date-range-display" class="sln-date-range-display" style="margin-left: 10px; color: #666; font-style: italic;"></span>
        
        <!-- Custom Date Range Picker -->
        <div id="sln-custom-date-range" class="sln-custom-date-range" style="display: none; margin-left: 20px;">
            <label for="sln-start-date"><?php esc_html_e('From:', 'salon-booking-system') ?></label>
            <input type="date" id="sln-start-date" class="sln-date-input" />
            <label for="sln-end-date" style="margin-left: 10px;"><?php esc_html_e('To:', 'salon-booking-system') ?></label>
            <input type="date" id="sln-end-date" class="sln-date-input" />
            <button id="sln-apply-custom-range" class="button button-primary" style="margin-left: 10px;">
                <?php esc_html_e('Apply', 'salon-booking-system') ?>
            </button>
        </div>
        
        <!-- Shop Selector (Multi-Shop Add-on) -->
        <?php if (class_exists('\SalonMultishop\Addon')): ?>
        <div class="sln-dashboard-filter-group sln-shop-filter-group">
            <label for="sln-shop-selector"><?php esc_html_e('Shop:', 'salon-booking-system') ?></label>
            <select id="sln-shop-selector" class="sln-shop-selector">
                <option value="0"><?php esc_html_e('All Shops', 'salon-booking-system') ?></option>
                <!-- Populated by JavaScript -->
            </select>
        </div>
        <?php endif; ?>
        
        <!-- Action Buttons -->
        <button id="sln-dashboard-refresh" class="button button-secondary" style="margin-left: auto; display: inline-flex; align-items: center; gap: 5px;">
            <span class="dashicons dashicons-update"></span>
            <?php esc_html_e('Refresh', 'salon-booking-system') ?>
        </button>
        <button id="sln-dashboard-export" class="button button-secondary" style="display: inline-flex; align-items: center; gap: 5px;">
            <span class="dashicons dashicons-download"></span>
            <?php esc_html_e('Export', 'salon-booking-system') ?>
        </button>
    </div>

    <!-- Manager Warning Message -->
    <?php if (!empty($warning_message)): ?>
    <div class="notice notice-warning sln-manager-no-shop-warning">
        <p><strong><?php echo esc_html($warning_message); ?></strong></p>
        <p><?php esc_html_e('Please contact your administrator to assign you to a shop.', 'salon-booking-system'); ?></p>
    </div>
    <?php endif; ?>

    <!-- Error Display -->
    <div id="sln-dashboard-error" class="notice notice-error" style="display: none;"></div>

    <!-- Loading Indicator -->
    <div id="sln-dashboard-loader" class="sln-dashboard-loader" style="display: none;">
        <div class="sln-spinner"></div>
        <p><?php esc_html_e('Loading dashboard data...', 'salon-booking-system') ?></p>
    </div>

    <!-- Dashboard Content -->
    <?php if (empty($warning_message)): ?>
    <div id="sln-dashboard-content" class="sln-dashboard-content">
        
        <!-- KPI Cards Row -->
        <div class="sln-kpi-cards">
            <div class="sln-kpi-card sln-kpi-card--unified" id="kpi-revenue-avg">
                <div class="kpi-icon">
                    <span class="dashicons dashicons-money-alt"></span>
                </div>
                <div class="kpi-content">
                    <div class="kpi-group">
                        <h3 class="kpi-title"><?php esc_html_e('Total Revenue', 'salon-booking-system') ?></h3>
                        <div id="kpi-revenue-value" class="kpi-value">--</div>
                        <div id="kpi-revenue-change" class="kpi-change">--</div>
                    </div>
                    <div class="kpi-divider"></div>
                    <div class="kpi-group">
                        <h3 class="kpi-title"><?php esc_html_e('Avg. Booking Value', 'salon-booking-system') ?></h3>
                        <div id="kpi-avg-value-value" class="kpi-value">--</div>
                        <div id="kpi-avg-value-change" class="kpi-change">--</div>
                    </div>
                </div>
            </div>

            <div class="sln-kpi-card sln-kpi-card--with-chart" id="kpi-bookings">
                <div class="kpi-left">
                    <div class="kpi-icon">
                        <span class="dashicons dashicons-calendar-alt"></span>
                    </div>
                    <div class="kpi-content">
                        <h3 class="kpi-title"><?php esc_html_e('Total Bookings', 'salon-booking-system') ?></h3>
                        <div class="kpi-value">--</div>
                        <div class="kpi-change">--</div>
                        <div class="kpi-metrics">
                            <div class="kpi-metric kpi-metric--warning">
                                <span class="kpi-metric-label"><?php esc_html_e('Cancellation Rate:', 'salon-booking-system') ?></span>
                                <span id="kpi-cancellation-rate" class="kpi-metric-value">--</span>
                            </div>
                            <div class="kpi-metric kpi-metric--danger">
                                <span class="kpi-metric-label"><?php esc_html_e('Lost Revenue:', 'salon-booking-system') ?></span>
                                <span id="kpi-canceled-revenue" class="kpi-metric-value">--</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="kpi-chart">
                    <canvas id="bookings-status-chart" width="100" height="100"></canvas>
                </div>
            </div>

            <div class="sln-kpi-card sln-kpi-card--unified" id="kpi-customers-rebooking">
                <div class="kpi-icon">
                    <span class="dashicons dashicons-groups"></span>
                </div>
                <div class="kpi-content">
                    <div class="kpi-group">
                        <h3 class="kpi-title"><?php esc_html_e('Unique Customers', 'salon-booking-system') ?></h3>
                        <div id="kpi-customers-value" class="kpi-value">--</div>
                        <div class="kpi-badges">
                            <span id="kpi-customers-new" class="badge badge-new">-- New</span>
                            <span id="kpi-customers-returning" class="badge badge-returning">-- Returning</span>
                        </div>
                    </div>
                    <div class="kpi-divider"></div>
                    <div class="kpi-group">
                        <h3 class="kpi-title"><?php esc_html_e('Rebooking Rate (60d)', 'salon-booking-system') ?></h3>
                        <div id="kpi-rebooking-value" class="kpi-value">--</div>
                        <div class="kpi-subtitle">
                            <span id="kpi-rebooking-subtitle">-- of -- customers rebooked</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Revenue Chart -->
        <div class="sln-chart-container">
            <h2><?php esc_html_e('Revenue & Bookings Trend', 'salon-booking-system') ?></h2>
            <div id="revenue-chart" class="sln-chart">
                <p class="chart-placeholder"><?php esc_html_e('Chart visualization will be implemented with Chart.js', 'salon-booking-system') ?></p>
            </div>
        </div>

        <!-- Booking Heatmap -->
        <div class="sln-chart-container <?php echo !defined('SLN_VERSION_PAY') ? 'sln-pro-feature-locked' : ''; ?>">
            <h2><?php esc_html_e('Booking Patterns - Peak Times Heatmap', 'salon-booking-system') ?></h2>
            <div id="booking-heatmap" class="sln-heatmap">
                <p class="chart-placeholder"><?php esc_html_e('Loading heatmap...', 'salon-booking-system') ?></p>
            </div>
            <?php if (!defined('SLN_VERSION_PAY')): ?>
            <div class="sln-pro-overlay">
                <div class="sln-pro-overlay-content">
                    <img src="<?php echo plugin_dir_url(dirname(dirname(__FILE__))) ?>img/crown-pro-icon.png" class="sln-pro-icon" alt="PRO" width="48" height="48">
                    <h3><?php esc_html_e('PRO Feature', 'salon-booking-system') ?></h3>
                    <p><?php esc_html_e('Unlock advanced booking pattern analysis', 'salon-booking-system') ?></p>
                    <button class="sln-pro-cta-button sln-open-pro-modal">
                        <?php esc_html_e('Upgrade to PRO', 'salon-booking-system') ?>
                    </button>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Utilization Section -->
        <div class="sln-utilization-section <?php echo !defined('SLN_VERSION_PAY') ? 'sln-pro-feature-locked' : ''; ?>">
            <div class="sln-utilization-header">
                <h2><?php esc_html_e('Capacity & Peak Times', 'salon-booking-system') ?></h2>
            </div>

            <!-- Utilization KPI Row -->
            <div class="sln-utilization-kpis">
                <div class="util-kpi">
                    <div class="util-kpi-icon">
                        <span class="dashicons dashicons-chart-pie"></span>
                    </div>
                    <div class="util-kpi-content">
                        <div class="util-kpi-label"><?php esc_html_e('Utilization Rate', 'salon-booking-system') ?></div>
                        <div id="util-rate" class="util-kpi-value">--%</div>
                        <div class="util-kpi-detail">
                            <span id="util-booked-hours">--</span> / <span id="util-available-hours">--</span> hours
                        </div>
                    </div>
                </div>

                <div class="util-kpi">
                    <div class="util-kpi-icon">
                        <span class="dashicons dashicons-calendar"></span>
                    </div>
                    <div class="util-kpi-content">
                        <div class="util-kpi-label"><?php esc_html_e('Peak Day', 'salon-booking-system') ?></div>
                        <div id="util-peak-day" class="util-kpi-value">--</div>
                        <div id="util-peak-day-bookings" class="util-kpi-detail">-- bookings</div>
                    </div>
                </div>

                <div class="util-kpi">
                    <div class="util-kpi-icon">
                        <span class="dashicons dashicons-clock"></span>
                    </div>
                    <div class="util-kpi-content">
                        <div class="util-kpi-label"><?php esc_html_e('Peak Hour', 'salon-booking-system') ?></div>
                        <div id="util-peak-hour" class="util-kpi-value">--</div>
                        <div id="util-peak-hour-bookings" class="util-kpi-detail">-- bookings</div>
                    </div>
                </div>

                <div class="util-kpi">
                    <div class="util-kpi-icon">
                        <span class="dashicons dashicons-chart-line"></span>
                    </div>
                    <div class="util-kpi-content">
                        <div class="util-kpi-label"><?php esc_html_e('Avg. Daily Bookings', 'salon-booking-system') ?></div>
                        <div id="util-avg-bookings" class="util-kpi-value">--</div>
                        <div class="util-kpi-detail"><?php esc_html_e('per working day', 'salon-booking-system') ?></div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="sln-utilization-charts">
                <div class="sln-chart-container">
                    <h3><?php esc_html_e('Bookings by Day of Week', 'salon-booking-system') ?></h3>
                    <div class="chart-wrapper">
                        <canvas id="day-of-week-chart"></canvas>
                    </div>
                </div>

                <div class="sln-chart-container">
                    <h3><?php esc_html_e('Bookings by Hour', 'salon-booking-system') ?></h3>
                    <div class="chart-wrapper">
                        <canvas id="hour-chart"></canvas>
                    </div>
                </div>
            </div>
            <?php if (!defined('SLN_VERSION_PAY')): ?>
            <div class="sln-pro-overlay">
                <div class="sln-pro-overlay-content">
                    <img src="<?php echo plugin_dir_url(dirname(dirname(__FILE__))) ?>img/crown-pro-icon.png" class="sln-pro-icon" alt="PRO" width="48" height="48">
                    <h3><?php esc_html_e('PRO Feature', 'salon-booking-system') ?></h3>
                    <p><?php esc_html_e('Unlock capacity utilization and peak time insights', 'salon-booking-system') ?></p>
                    <button class="sln-pro-cta-button sln-open-pro-modal">
                        <?php esc_html_e('Upgrade to PRO', 'salon-booking-system') ?>
                    </button>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- No-Show Tracking Section -->
        <div class="sln-noshow-section <?php echo !defined('SLN_VERSION_PAY') ? 'sln-pro-feature-locked' : ''; ?>">
            <div class="sln-noshow-header">
                <h2><?php esc_html_e('No-Show Tracking', 'salon-booking-system') ?></h2>
                <p class="sln-section-description"><?php esc_html_e('Track and analyze customer no-show patterns', 'salon-booking-system') ?></p>
            </div>

            <!-- No-Show KPI Row -->
            <div class="sln-noshow-kpis">
                <div class="sln-noshow-kpi">
                    <div class="noshow-kpi-icon" style="background: #f44336;">
                        <span class="dashicons dashicons-warning"></span>
                    </div>
                    <div class="noshow-kpi-content">
                        <div class="noshow-kpi-title"><?php esc_html_e('Total No-Shows', 'salon-booking-system') ?></div>
                        <div id="noshow-total" class="noshow-kpi-value">--</div>
                    </div>
                </div>
                <div class="sln-noshow-kpi">
                    <div class="noshow-kpi-icon" style="background: #ff9800;">
                        <span class="dashicons dashicons-chart-line"></span>
                    </div>
                    <div class="noshow-kpi-content">
                        <div class="noshow-kpi-title"><?php esc_html_e('No-Show Rate', 'salon-booking-system') ?></div>
                        <div id="noshow-rate" class="noshow-kpi-value">--</div>
                    </div>
                </div>
                <div class="sln-noshow-kpi">
                    <div class="noshow-kpi-icon" style="background: #2196f3;">
                        <span class="dashicons dashicons-calendar-alt"></span>
                    </div>
                    <div class="noshow-kpi-content">
                        <div class="noshow-kpi-title"><?php esc_html_e('Affected Bookings', 'salon-booking-system') ?></div>
                        <div id="noshow-affected" class="noshow-kpi-value">--</div>
                    </div>
                </div>
            </div>

            <!-- No-Show Chart and Recent List -->
            <div class="sln-noshow-content-row">
                <div class="sln-chart-container sln-noshow-chart">
                    <h3><?php esc_html_e('No-Show Rate Trend', 'salon-booking-system') ?></h3>
                    <div class="chart-wrapper">
                        <canvas id="noshow-trend-chart"></canvas>
                    </div>
                </div>

                <div class="sln-noshow-list-container">
                    <h3><?php esc_html_e('Recent No-Shows', 'salon-booking-system') ?></h3>
                    <div id="recent-noshows-list" class="sln-noshow-list">
                        <p class="sln-no-data"><?php esc_html_e('Loading...', 'salon-booking-system') ?></p>
                    </div>
                </div>
            </div>
            <?php if (!defined('SLN_VERSION_PAY')): ?>
            <div class="sln-pro-overlay">
                <div class="sln-pro-overlay-content">
                    <img src="<?php echo plugin_dir_url(dirname(dirname(__FILE__))) ?>img/crown-pro-icon.png" class="sln-pro-icon" alt="PRO" width="48" height="48">
                    <h3><?php esc_html_e('PRO Feature', 'salon-booking-system') ?></h3>
                    <p><?php esc_html_e('Unlock no-show tracking and analytics', 'salon-booking-system') ?></p>
                    <button class="sln-pro-cta-button sln-open-pro-modal">
                        <?php esc_html_e('Upgrade to PRO', 'salon-booking-system') ?>
                    </button>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Tables Row -->
        <div class="sln-dashboard-tables">
            
            <!-- Service Performance Table -->
            <div class="sln-table-container">
                <h2><?php esc_html_e('Top Services', 'salon-booking-system') ?></h2>
                
                <!-- Service Performance Charts -->
                <div class="sln-assistant-charts-row">
                    <div class="sln-chart-column">
                        <h3><?php esc_html_e('Performance by Bookings', 'salon-booking-system') ?></h3>
                        <div class="sln-pie-chart">
                            <canvas id="service-bookings-chart"></canvas>
                        </div>
                    </div>
                    <div class="sln-chart-column">
                        <h3><?php esc_html_e('Performance by Revenue', 'salon-booking-system') ?></h3>
                        <div class="sln-pie-chart">
                            <canvas id="service-revenue-chart"></canvas>
                        </div>
                    </div>
                </div>
                
                <table id="services-table" class="sln-data-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Service', 'salon-booking-system') ?></th>
                            <th><?php esc_html_e('Bookings', 'salon-booking-system') ?></th>
                            <th><?php esc_html_e('Revenue', 'salon-booking-system') ?></th>
                            <th><?php esc_html_e('Avg. Price', 'salon-booking-system') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="4" class="loading-cell"><?php esc_html_e('Loading...', 'salon-booking-system') ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Assistant Performance Table -->
            <div class="sln-table-container">
                <h2><?php esc_html_e('Assistant Performance', 'salon-booking-system') ?></h2>
                
                <!-- Assistant Performance Charts -->
                <div class="sln-assistant-charts-row">
                    <div class="sln-chart-column">
                        <h3><?php esc_html_e('Performance by Hours', 'salon-booking-system') ?></h3>
                        <div class="sln-pie-chart">
                            <canvas id="assistant-hours-chart"></canvas>
                        </div>
                    </div>
                    <div class="sln-chart-column">
                        <h3><?php esc_html_e('Performance by Revenue', 'salon-booking-system') ?></h3>
                        <div class="sln-pie-chart">
                            <canvas id="assistant-revenue-chart"></canvas>
                        </div>
                    </div>
                </div>
                
                <table id="assistants-table" class="sln-data-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Assistant', 'salon-booking-system') ?></th>
                            <th><?php esc_html_e('Bookings', 'salon-booking-system') ?></th>
                            <th><?php esc_html_e('Revenue', 'salon-booking-system') ?></th>
                            <th><?php esc_html_e('Hours', 'salon-booking-system') ?></th>
                            <th><?php esc_html_e('Utilization', 'salon-booking-system') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="5" class="loading-cell"><?php esc_html_e('Loading...', 'salon-booking-system') ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div>

        <!-- Customer Metrics Section -->
        <div class="sln-metrics-grid <?php echo !defined('SLN_VERSION_PAY') ? 'sln-pro-feature-locked' : ''; ?>">
            <div class="sln-metric-card">
                <div class="metric-icon">
                    <span class="dashicons dashicons-chart-area"></span>
                </div>
                <div class="metric-content">
                    <h3 class="metric-label"><?php esc_html_e('Avg. Customer Lifetime Value', 'salon-booking-system') ?></h3>
                    <div id="metric-clv" class="metric-value">--</div>
                    <div class="metric-detail">
                        <span id="metric-avg-visits">-- visits/customer</span>
                    </div>
                </div>
            </div>

            <div class="sln-metric-card">
                <div class="metric-icon">
                    <span class="dashicons dashicons-backup"></span>
                </div>
                <div class="metric-content">
                    <h3 class="metric-label"><?php esc_html_e('Avg. Visit Frequency', 'salon-booking-system') ?></h3>
                    <div id="metric-frequency" class="metric-value">--</div>
                    <div class="metric-detail">
                        <span id="metric-projected-annual">-- visits/year projected</span>
                    </div>
                </div>
            </div>
            <?php if (!defined('SLN_VERSION_PAY')): ?>
            <div class="sln-pro-overlay">
                <div class="sln-pro-overlay-content">
                    <img src="<?php echo plugin_dir_url(dirname(dirname(__FILE__))) ?>img/crown-pro-icon.png" class="sln-pro-icon" alt="PRO" width="48" height="48">
                    <h3><?php esc_html_e('PRO Feature', 'salon-booking-system') ?></h3>
                    <p><?php esc_html_e('Unlock customer lifetime value and frequency insights', 'salon-booking-system') ?></p>
                    <button class="sln-pro-cta-button sln-open-pro-modal">
                        <?php esc_html_e('Upgrade to PRO', 'salon-booking-system') ?>
                    </button>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- CLV Distribution Chart -->
        <div class="sln-chart-container <?php echo !defined('SLN_VERSION_PAY') ? 'sln-pro-feature-locked' : ''; ?>">
            <h2><?php esc_html_e('Customer Lifetime Value Distribution', 'salon-booking-system') ?></h2>
            <div class="chart-wrapper">
                <canvas id="clv-distribution-chart"></canvas>
            </div>
            <?php if (!defined('SLN_VERSION_PAY')): ?>
            <div class="sln-pro-overlay">
                <div class="sln-pro-overlay-content">
                    <img src="<?php echo plugin_dir_url(dirname(dirname(__FILE__))) ?>img/crown-pro-icon.png" class="sln-pro-icon" alt="PRO" width="48" height="48">
                    <h3><?php esc_html_e('PRO Feature', 'salon-booking-system') ?></h3>
                    <p><?php esc_html_e('Unlock customer lifetime value distribution analysis', 'salon-booking-system') ?></p>
                    <button class="sln-pro-cta-button sln-open-pro-modal">
                        <?php esc_html_e('Upgrade to PRO', 'salon-booking-system') ?>
                    </button>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Top Customers Widget -->
        <div class="sln-customers-widget">
            <h2><?php esc_html_e('Top Customers', 'salon-booking-system') ?></h2>
            <div id="top-customers-list" class="customers-list">
                <p class="loading-placeholder"><?php esc_html_e('Loading...', 'salon-booking-system') ?></p>
            </div>
        </div>

        <!-- At-risk Customers Table -->
        <div class="sln-table-container <?php echo !defined('SLN_VERSION_PAY') ? 'sln-pro-feature-locked' : ''; ?>">
            <h2><?php esc_html_e('At-risk Customers', 'salon-booking-system') ?></h2>
            <p class="sln-table-subtitle"><?php esc_html_e('High-value customers who haven\'t returned in 60+ days', 'salon-booking-system') ?></p>
            <table id="at-risk-customers-table" class="sln-data-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Customer', 'salon-booking-system') ?></th>
                        <th><?php esc_html_e('Last Visit', 'salon-booking-system') ?></th>
                        <th><?php esc_html_e('Days Since', 'salon-booking-system') ?></th>
                        <th><?php esc_html_e('Total Spent', 'salon-booking-system') ?></th>
                        <th><?php esc_html_e('Bookings', 'salon-booking-system') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="5" class="loading-cell"><?php esc_html_e('Loading...', 'salon-booking-system') ?></td>
                    </tr>
                </tbody>
            </table>
            <?php if (!defined('SLN_VERSION_PAY')): ?>
            <div class="sln-pro-overlay">
                <div class="sln-pro-overlay-content">
                    <img src="<?php echo plugin_dir_url(dirname(dirname(__FILE__))) ?>img/crown-pro-icon.png" class="sln-pro-icon" alt="PRO" width="48" height="48">
                    <h3><?php esc_html_e('PRO Feature', 'salon-booking-system') ?></h3>
                    <p><?php esc_html_e('Unlock at-risk customer tracking and retention insights', 'salon-booking-system') ?></p>
                    <button class="sln-pro-cta-button sln-open-pro-modal">
                        <?php esc_html_e('Upgrade to PRO', 'salon-booking-system') ?>
                    </button>
                </div>
            </div>
            <?php endif; ?>
        </div>

    </div>
    <?php endif; // End: if empty($warning_message) ?>

</div>

<style>
/* Basic Dashboard Styles */
#sln-reports-dashboard {
    max-width: 1400px;
    margin: 20px auto;
}

/* Dashboard Title with Shop Name */
.sln-dashboard-title {
    display: flex;
    align-items: center;
    gap: 0;
}

.sln-current-shop-name {
    color: #666;
    font-weight: 400;
    font-size: 0.85em;
}

/* Manager No Shop Warning */
.sln-manager-no-shop-warning {
    background: #fff3cd;
    border-left: 4px solid #ffc107;
    padding: 20px;
    margin: 20px 0;
}

.sln-manager-no-shop-warning p {
    margin: 0;
    line-height: 1.6;
}

.sln-manager-no-shop-warning p:first-child {
    font-size: 16px;
    margin-bottom: 10px;
}

.sln-manager-no-shop-warning strong {
    color: #856404;
}

.sln-dashboard-title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
}

.sln-dashboard-filters {
    background: #fff;
    padding: 15px 20px;
    margin-bottom: 20px;
    border: 1px solid #ddd;
    border-radius: 4px;
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}

.sln-date-range-selector {
    min-width: 200px;
    margin-left: 10px;
}

/* Shop Selector */
.sln-dashboard-filter-group {
    display: flex;
    align-items: center;
    gap: 10px;
}

.sln-dashboard-filter-group label {
    font-weight: 600;
    font-size: 14px;
    color: #333;
}

.sln-shop-selector {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    min-width: 200px;
    background: #fff;
    font-size: 14px;
    cursor: pointer;
    transition: border-color 0.2s ease;
}

.sln-shop-selector:focus {
    outline: none;
    border-color: #2171B1;
    box-shadow: 0 0 0 1px #2171B1;
}

.sln-shop-selector:hover {
    border-color: #2171B1;
}

.sln-dashboard-loader {
    text-align: center;
    padding: 40px;
}

.sln-spinner {
    border: 4px solid #f3f3f3;
    border-top: 4px solid #3498db;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    animation: spin 1s linear infinite;
    margin: 0 auto 15px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* KPI Cards */
.sln-kpi-cards {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}

@media (max-width: 1400px) {
    .sln-kpi-cards {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 900px) {
    .sln-kpi-cards {
        grid-template-columns: 1fr;
    }
}

.sln-kpi-card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    min-width: 0; /* Prevent grid overflow */
}

.sln-kpi-card:hover {
    border-color: #2171B1;
}

.sln-kpi-card--with-chart {
    justify-content: space-between;
}

.sln-kpi-card--with-chart .kpi-left {
    display: flex;
    align-items: center;
    gap: 15px;
    flex: 1;
}

.sln-kpi-card--with-chart .kpi-chart {
    flex-shrink: 0;
    width: 100px;
    height: 100px;
}

.sln-kpi-card--with-chart .kpi-chart canvas {
    max-width: 100%;
    height: auto !important;
}

.kpi-icon {
    font-size: 32px;
    color: #3498db;
    flex-shrink: 0;
}

.kpi-content {
    flex: 1;
    min-width: 0;
}

/* Unified KPI Cards with Multiple Metrics */
.sln-kpi-card--unified .kpi-content {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.sln-kpi-card--unified .kpi-group {
    flex: 1;
}

.sln-kpi-card--unified .kpi-divider {
    height: 1px;
    background: linear-gradient(to right, transparent, #ddd 20%, #ddd 80%, transparent);
    margin: 0;
}

.kpi-title {
    font-size: 14px;
    color: #666;
    margin: 0 0 10px 0;
    font-weight: normal;
}

.kpi-value {
    font-size: 28px;
    font-weight: bold;
    color: #2c3e50;
    margin-bottom: 5px;
}

.kpi-subtitle {
    font-size: 12px;
    color: #888;
    margin-top: 5px;
}

.kpi-change {
    font-size: 14px;
}

.kpi-change.positive {
    color: #27ae60;
}

.kpi-change.negative {
    color: #e74c3c;
}

.kpi-metrics {
    margin-top: 10px;
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.kpi-metric {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 11px;
    padding: 4px 8px;
    border-radius: 4px;
    background: #f8f9fa;
}

.kpi-metric--warning {
    border-left: 3px solid #ff9800;
}

.kpi-metric--danger {
    border-left: 3px solid #f44336;
}

.kpi-metric-label {
    color: #666;
    font-weight: 500;
}

.kpi-metric-value {
    color: #333;
    font-weight: 600;
    margin-left: auto;
}

.kpi-badges {
    display: flex;
    gap: 10px;
}

.kpi-badges .badge {
    display: inline-block;
    padding: 4px 8px;
    font-size: 12px;
    border-radius: 12px;
}

.kpi-badges .badge-new {
    background: #e8f5e9 !important;
    color: #2e7d32 !important;
}

.kpi-badges .badge-returning {
    background: #e3f2fd !important;
    color: #1565c0 !important;
}

/* Chart Container */
.sln-chart-container {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 30px;
}

.sln-chart-container h2 {
    margin-top: 0;
    font-size: 18px;
}

.sln-chart {
    min-height: 300px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.chart-placeholder {
    color: #999;
    font-style: italic;
}

/* Tables */
.sln-dashboard-tables {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.sln-table-container {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
}

.sln-table-container h2 {
    margin-top: 0;
    font-size: 18px;
    margin-bottom: 10px;
}

.sln-table-subtitle {
    font-size: 13px;
    color: #888;
    margin: 0 0 15px 0;
    font-style: italic;
}

.sln-data-table {
    width: 100%;
    border-collapse: collapse;
}

.sln-data-table th {
    text-align: left;
    padding: 12px 8px;
    border-bottom: 2px solid #ddd;
    font-weight: 600;
    color: #555;
    font-size: 13px;
}

.sln-data-table td {
    padding: 12px 8px;
    border-bottom: 1px solid #eee;
}

.sln-data-table tbody tr:hover {
    background: #f9f9f9;
}

/* At-risk customer severity colors */
.sln-data-table tbody tr.severity-medium {
    background-color: #fff4e6;
}

.sln-data-table tbody tr.severity-high {
    background-color: #ffe5e5;
}

.customer-name {
    font-weight: 600;
    color: #333;
    margin-bottom: 3px;
}

.customer-email {
    font-size: 12px;
    color: #888;
}

.days-since {
    font-weight: 600;
    color: #e74c3c;
}

.no-data-cell {
    text-align: center;
    color: #999;
    font-style: italic;
}

.loading-cell {
    text-align: center;
    color: #999;
    font-style: italic;
}

/* Utilization Section */
.sln-utilization-section {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 25px;
    margin-bottom: 30px;
}

.sln-utilization-header h2 {
    margin: 0 0 20px 0;
    font-size: 20px;
}

.sln-utilization-kpis {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
    margin-bottom: 25px;
}

@media (max-width: 1200px) {
    .sln-utilization-kpis {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .sln-utilization-kpis {
        grid-template-columns: 1fr;
    }
}

.util-kpi {
    background: #1a5a8e;
    border-radius: 8px;
    padding: 20px;
    color: #fff;
    display: flex;
    align-items: center;
    gap: 15px;
}

.util-kpi-icon {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.util-kpi-icon .dashicons {
    font-size: 26px;
    width: 26px;
    height: 26px;
    color: #fff;
}

.util-kpi-content {
    flex: 1;
}

.util-kpi-label {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 5px;
    opacity: 0.9;
}

.util-kpi-value {
    font-size: 24px;
    font-weight: bold;
    margin-bottom: 3px;
}

.util-kpi-detail {
    font-size: 11px;
    opacity: 0.9;
}

.sln-utilization-charts {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
}

@media (max-width: 768px) {
    .sln-utilization-charts {
        grid-template-columns: 1fr;
    }
}

.sln-utilization-charts h3 {
    font-size: 15px;
    margin-bottom: 15px;
}

/* Customer Metrics Grid */
.sln-metrics-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}

@media (max-width: 768px) {
    .sln-metrics-grid {
        grid-template-columns: 1fr;
    }
}

.sln-metric-card {
    background: #2171B1;
    border-radius: 8px;
    padding: 25px;
    color: #fff;
    display: flex;
    align-items: center;
    gap: 20px;
}

.metric-icon {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.metric-icon .dashicons {
    font-size: 32px;
    width: 32px;
    height: 32px;
    color: #fff;
}

.metric-content {
    flex: 1;
}

.metric-label {
    font-size: 13px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin: 0 0 8px 0;
    opacity: 0.9;
}

.metric-value {
    font-size: 32px;
    font-weight: bold;
    margin-bottom: 5px;
}

.metric-detail {
    font-size: 13px;
    opacity: 0.9;
}

/* Customers Widget */
.sln-customers-widget {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 30px;
}

.sln-customers-widget h2 {
    margin-top: 0;
    font-size: 18px;
}

.customers-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.customer-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 10px;
    border: 1px solid #eee;
    border-radius: 4px;
}

.customer-item .rank {
    font-weight: bold;
    color: #3498db;
    font-size: 18px;
    min-width: 30px;
}

.customer-item .name {
    flex: 1;
    font-weight: 500;
}

.customer-item .bookings {
    color: #666;
    font-size: 13px;
}

.customer-item .spent {
    font-weight: 600;
    color: #2c3e50;
}

.loading-placeholder {
    text-align: center;
    color: #999;
    font-style: italic;
}

/* PRO Feature Lock Overlay */
.sln-pro-feature-locked {
    position: relative;
    opacity: 0.6;
    pointer-events: none;
}

.sln-pro-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.75);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 100;
    pointer-events: all;
    border-radius: 8px;
}

.sln-pro-overlay-content {
    text-align: center;
    padding: 40px 20px;
    max-width: 400px;
}

.sln-pro-icon {
    margin-bottom: 20px;
    animation: bounce 2s infinite;
}

@keyframes bounce {
    0%, 20%, 50%, 80%, 100% {
        transform: translateY(0);
    }
    40% {
        transform: translateY(-10px);
    }
    60% {
        transform: translateY(-5px);
    }
}

.sln-pro-overlay-content h3 {
    font-size: 24px;
    color: #2171B1;
    margin: 0 0 10px 0;
    font-weight: bold;
}

.sln-pro-overlay-content p {
    font-size: 14px;
    color: #666;
    margin: 0 0 20px 0;
}

.sln-pro-cta-button {
    display: inline-block;
    background: #2171B1;
    color: #fff;
    padding: 12px 30px;
    border-radius: 6px;
    text-decoration: none;
    font-weight: 600;
    font-size: 14px;
    transition: background 0.3s;
    border: none;
    cursor: pointer;
    position: relative;
    z-index: 101;
}

.sln-pro-cta-button:hover {
    background: #1a5a8e;
    color: #fff;
    text-decoration: none;
}

/* Responsive Design */
@media (max-width: 768px) {
    .sln-kpi-cards {
        grid-template-columns: 1fr;
    }
    
    .sln-kpi-card--with-chart {
        flex-direction: column;
    }
    
    .sln-kpi-card--with-chart .kpi-left {
        width: 100%;
    }
    
    .sln-kpi-card--with-chart .kpi-chart {
        width: 150px;
        height: 150px;
        margin: 0 auto;
    }

    .sln-dashboard-tables {
        grid-template-columns: 1fr;
    }

    .sln-dashboard-title {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
}

/* Assistant Performance Charts */
.sln-assistant-charts-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin: 20px 0 30px 0;
}

.sln-chart-column {
    background: #f9f9f9;
    padding: 20px;
    border-radius: 8px;
    border: 1px solid #ddd;
}

.sln-chart-column h3 {
    margin: 0 0 15px 0;
    font-size: 16px;
    font-weight: 600;
    color: #333;
    text-align: center;
}

.sln-pie-chart {
    min-height: 300px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.sln-pie-chart canvas {
    max-width: 100%;
    height: auto !important;
}

@media (max-width: 768px) {
    .sln-assistant-charts-row {
        grid-template-columns: 1fr;
    }
}

/* Pro Feature Lock Styles - Matching Today Tooltip CTA Style */
.sln-dashboard-filters--free .sln-today-tooltip__cta {
    display: inline-flex;
    align-items: center;
    justify-content: space-between;
    background: #BDD7EC;
    border-radius: 8px;
    transition: all 0.2s ease;
    cursor: pointer;
    gap: 10px;
    white-space: nowrap;
    flex-shrink: 0;
    align-self: center;
    margin: 0px 30px;
}

.sln-dashboard-filters--free .sln-today-tooltip__cta:hover {
    background: #a8c9e0;
}

.sln-dashboard-filters--free .sln-today-tooltip__cta-text {
    color: #1a4d6b;
    font-size: 12px;
    line-height: 1.3;
}

.sln-dashboard-filters--free .sln-today-tooltip__cta-text--strong {
    font-weight: bold;
    font-size: 14px;
    margin-top: 1px;
}

.sln-dashboard-filters--free .sln-today-tooltip__crown-icon {
    width: 28px;
    height: 28px;
    flex-shrink: 0;
    display: block;
}

/* No-Show Tracking Section */
.sln-noshow-section {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 25px;
    margin-bottom: 30px;
}

.sln-noshow-header h2 {
    margin: 0 0 5px 0;
    font-size: 20px;
    font-weight: 600;
    color: #333;
}

.sln-section-description {
    margin: 0 0 20px 0;
    color: #666;
    font-size: 14px;
}

.sln-noshow-kpis {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 25px;
}

@media (max-width: 1024px) {
    .sln-noshow-kpis {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .sln-noshow-kpis {
        grid-template-columns: 1fr;
    }
}

.sln-noshow-kpi {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
}

.noshow-kpi-icon {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.noshow-kpi-icon .dashicons {
    color: #fff;
    font-size: 24px;
    width: 24px;
    height: 24px;
}

.noshow-kpi-content {
    flex: 1;
    min-width: 0;
}

.noshow-kpi-title {
    font-size: 13px;
    color: #666;
    margin-bottom: 5px;
    font-weight: 500;
}

.noshow-kpi-value {
    font-size: 24px;
    font-weight: 700;
    color: #333;
}

.sln-noshow-content-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

@media (max-width: 1024px) {
    .sln-noshow-content-row {
        grid-template-columns: 1fr;
    }
}

.sln-noshow-chart {
    min-width: 0;
}

.sln-noshow-list-container {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 20px;
}

.sln-noshow-list-container h3 {
    font-size: 16px;
    margin: 0 0 15px 0;
    font-weight: 600;
    color: #333;
}

.sln-noshow-list {
    max-height: 400px;
    overflow-y: auto;
}

.sln-noshow-item {
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 6px;
    padding: 15px;
    margin-bottom: 10px;
}

.sln-noshow-item:last-child {
    margin-bottom: 0;
}

.sln-noshow-item-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.sln-noshow-customer-name {
    font-weight: 600;
    color: #333;
    font-size: 14px;
}

.sln-noshow-date {
    font-size: 12px;
    color: #666;
}

.sln-noshow-item-body {
    font-size: 13px;
    color: #666;
    line-height: 1.6;
}

.sln-noshow-service {
    display: inline-block;
    background: #e3f2fd;
    color: #1976d2;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 12px;
    margin-right: 5px;
    margin-bottom: 5px;
}

.sln-noshow-marked-by {
    font-size: 11px;
    color: #999;
    margin-top: 8px;
}

.sln-no-data {
    text-align: center;
    color: #999;
    padding: 40px 20px;
    font-style: italic;
}
</style>
</div> <!-- Close #sln-reports-dashboard -->
</div> <!-- Close #sln-salon--admin -->


