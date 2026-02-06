/**
 * Salon Booking System - Reports Dashboard
 * Modern analytics dashboard with API-driven data visualization
 * 
 * @version 1.0.0
 * @date 2025-11-13
 */

(function($) {
    'use strict';

    // Import WordPress i18n functions for translations
    const { __, _x, _n, sprintf } = wp.i18n;

    /**
     * Dashboard API Client
     */
    const DashboardAPI = {
        /**
         * Get base URL from WordPress (supports both pretty and plain permalinks)
         */
        getBaseURL: function() {
            let baseUrl = '/wp-json/salon/api/v1/';
            if (window.salonDashboard && window.salonDashboard.restUrl) {
                baseUrl = window.salonDashboard.restUrl;
            }
            // Remove trailing slash to avoid double slashes
            return baseUrl.replace(/\/$/, '');
        },
        
        /**
         * Get access token from WordPress
         */
        getToken: function() {
            return window.salonDashboard && window.salonDashboard.apiToken || '';
        },

        /**
         * Get nonce for WordPress REST API
         */
        getNonce: function() {
            return window.salonDashboard && window.salonDashboard.nonce || '';
        },

        /**
         * Make API request
         */
        request: async function(endpoint, params = {}) {
            const baseURL = this.getBaseURL();
            
            // Add shop filter to all requests (Multi-Shop support)
            // Skip for /shops endpoint itself to avoid recursion
            if (endpoint !== '/shops' && typeof ShopManager !== 'undefined') {
                const shopId = ShopManager.getCurrentShop();
                if (shopId > 0) {
                    params.shop = shopId;
                }
            }
            
            // Add cache-busting timestamp to prevent browser caching
            params._t = Date.now();
            
            // Handle both pretty permalinks (/wp-json/) and plain permalinks (index.php?rest_route=)
            let url;
            if (baseURL.includes('?rest_route=')) {
                // Plain permalinks: rest_route is a query parameter
                const [baseUrl, restRoute] = baseURL.split('?rest_route=');
                url = new URL(baseUrl, window.location.origin);
                url.searchParams.set('rest_route', restRoute + endpoint);
            } else {
                // Pretty permalinks: normal URL construction
                url = new URL(baseURL + endpoint, window.location.origin);
            }
            
            // Add additional parameters
            Object.keys(params).forEach(key => {
                if (Array.isArray(params[key])) {
                    params[key].forEach(val => url.searchParams.append(key + '[]', val));
                } else {
                    url.searchParams.set(key, params[key]);
                }
            });

            try {
                const headers = {
                    'Content-Type': 'application/json',
                };
                
                // Try Bearer token first (for API auth)
                const token = this.getToken();
                if (token) {
                    headers['Authorization'] = 'Bearer ' + token;
                }
                
                // Also include WordPress REST nonce for cookie auth fallback
                const nonce = this.getNonce();
                if (nonce) {
                    headers['X-WP-Nonce'] = nonce;
                }

                const response = await fetch(url.toString(), {
                    method: 'GET',
                    headers: headers,
                    credentials: 'same-origin', // Include cookies for WordPress auth
                    cache: 'no-store' // Prevent browser caching
                });

                if (!response.ok) {
                    const errorText = await response.text();
                    console.error('API Error Response:', errorText);
                    throw new Error(`API request failed: ${response.status} ${response.statusText}`);
                }

                const data = await response.json();
                return data.data || data;
            } catch (error) {
                console.error('API Error:', error);
                throw error;
            }
        },

        /**
         * Get enhanced booking statistics
         */
        getEnhancedStats: function(startDate, endDate, options = {}) {
            return this.request('/bookings/stats/enhanced', {
                start_date: startDate,
                end_date: endDate,
                ...options
            });
        },

        /**
         * Get service performance statistics
         */
        getServiceStats: function(startDate, endDate, options = {}) {
            return this.request('/services/stats', {
                start_date: startDate,
                end_date: endDate,
                ...options
            });
        },

        /**
         * Get assistant performance statistics
         */
        getAssistantStats: function(startDate, endDate, options = {}) {
            return this.request('/assistants/stats', {
                start_date: startDate,
                end_date: endDate,
                ...options
            });
        },

        /**
         * Get customer analytics
         */
        getCustomerStats: function(startDate, endDate, options = {}) {
            return this.request('/customers/stats', {
                start_date: startDate,
                end_date: endDate,
                ...options
            });
        },

        /**
         * Get customer retention metrics
         */
        getCustomerRetention: function(startDate, endDate, options = {}) {
            return this.request('/customers/retention', {
                start_date: startDate,
                end_date: endDate,
                rebooking_window: 60,
                at_risk_limit: 10,
                ...options
            });
        },

        /**
         * Get service frequency and CLV metrics
         */
        getFrequencyCLV: function(startDate, endDate) {
            return this.request('/customers/frequency-clv', {
                start_date: startDate,
                end_date: endDate
            });
        },

        /**
         * Get peak times analysis
         */
        getPeakTimes: function(startDate, endDate) {
            return this.request('/bookings/peak-times', {
                start_date: startDate,
                end_date: endDate
            });
        },

        /**
         * Get utilization metrics
         */
        getUtilization: function(startDate, endDate) {
            return this.request('/bookings/utilization', {
                start_date: startDate,
                end_date: endDate
            });
        },

        /**
         * Get no-show statistics
         */
        getNoShowStats: function(startDate, endDate) {
            return this.request('/no-shows/stats', {
                start_date: startDate,
                end_date: endDate
            });
        },

        /**
         * Get no-show bookings list
         */
        getNoShowBookings: function(startDate, endDate, limit = 10) {
            return this.request('/no-shows/bookings', {
                start_date: startDate,
                end_date: endDate,
                limit: limit
            });
        },

        /**
         * Get no-show rate by period
         */
        getNoShowRateByPeriod: function(startDate, endDate, period = 'week') {
            return this.request('/no-shows/rate-by-period', {
                start_date: startDate,
                end_date: endDate,
                period: period
            });
        },

        /**
         * Get cancellation analytics
         */
        getCancellations: function(startDate, endDate) {
            return this.request('/bookings/cancellations', {
                start_date: startDate,
                end_date: endDate
            });
        },

        /**
         * Get shops list (Multi-Shop add-on)
         */
        getShops: function() {
            return this.request('/shops', {});
        }
    };

    /**
     * Shop Manager (Multi-Shop Add-on Support)
     */
    const ShopManager = {
        currentShop: 0,
        
        /**
         * Initialize shop manager
         */
        init: async function() {
            // Check if user is a shop manager with assigned shop (from PHP)
            if (window.salonDashboard && window.salonDashboard.managerShopId) {
                this.currentShop = parseInt(window.salonDashboard.managerShopId);
            }
            
            await this.loadShops();
            this.bindShopSelector();
            this.restoreSelection();
        },
        
        /**
         * Load shops from API
         */
        async loadShops() {
            try {
                const response = await DashboardAPI.getShops();
                const shops = response.items || [];
                this.populateShopSelector(shops);
            } catch (error) {
                console.error('ShopManager: Failed to load shops:', error);
                // If API fails, hide the selector
                const container = document.querySelector('.sln-shop-filter-group');
                if (container) {
                    container.style.display = 'none';
                }
            }
        },
        
        /**
         * Populate shop selector dropdown
         */
        populateShopSelector(shops) {
            const select = document.getElementById('sln-shop-selector');
            if (!select) {
                console.warn('ShopManager: Shop selector element not found');
                return;
            }
            
            // Check if user is a shop manager with assigned shops
            const managerShopIds = window.salonDashboard && window.salonDashboard.managerShopIds ? 
                                   window.salonDashboard.managerShopIds.map(id => parseInt(id)) : [];
            const hasMultipleShops = managerShopIds.length > 1;
            const hasSingleShop = managerShopIds.length === 1;
            
            // Clear existing options
            select.innerHTML = '';
            
            // For admins or managers with multiple shops, add "All Shops" option
            if (!hasSingleShop) {
                const allShopsOption = document.createElement('option');
                allShopsOption.value = '0';
                allShopsOption.textContent = 'All Shops';
                select.appendChild(allShopsOption);
            }
            
            // Filter shops if manager has assigned shops
            const filteredShops = managerShopIds.length > 0 ? 
                shops.filter(shop => managerShopIds.includes(parseInt(shop.id))) : 
                shops;
            
            // Add shop options (filtered for managers, all for admins)
            filteredShops.forEach(shop => {
                const option = document.createElement('option');
                option.value = shop.id;
                option.textContent = shop.name;
                if (shop.address) {
                    option.textContent += ' - ' + shop.address;
                }
                select.appendChild(option);
            });
            
            // Only disable selector if manager has exactly ONE shop
            if (hasSingleShop) {
                select.disabled = true;
                select.style.cursor = 'not-allowed';
                select.style.opacity = '0.6';
                select.title = 'You can only view data from your assigned shop';
            } else if (hasMultipleShops) {
                // Manager with multiple shops - keep selector enabled but filtered
                select.disabled = false;
                select.style.cursor = 'pointer';
                select.style.opacity = '1';
                select.title = 'Select a shop from your assigned shops';
            }
            
        },
        
        /**
         * Bind shop selector change event
         */
        bindShopSelector() {
            const select = document.getElementById('sln-shop-selector');
            if (!select) return;
            
            select.addEventListener('change', (e) => {
                this.currentShop = parseInt(e.target.value) || 0;
                
                // Save to localStorage
                localStorage.setItem('sln_selected_shop', this.currentShop);
                
                // Update dashboard title with selected shop name
                this.updateDashboardTitle(e.target);
                
                // Reload dashboard data
                if (typeof DashboardUI !== 'undefined' && DashboardUI.loadDashboard) {
                    DashboardUI.loadDashboard();
                }
            });
            
        },
        
        /**
         * Update dashboard title with current shop name
         */
        updateDashboardTitle(selectElement) {
            const titleElement = document.querySelector('.sln-dashboard-title');
            if (!titleElement) {
                console.warn('Title element not found');
                return;
            }
            
            // Remove existing shop name span if present
            const existingShopName = titleElement.querySelector('.sln-current-shop-name');
            if (existingShopName) {
                existingShopName.remove();
            }
            
            // If "All Shops" is selected (value 0), don't add shop name
            if (!selectElement || !this.currentShop || this.currentShop === 0) {
                return;
            }
            
            // Get selected shop name
            const selectedOption = selectElement.options[selectElement.selectedIndex];
            
            if (selectedOption && selectedOption.textContent && selectedOption.value != "0") {
                // Extract just the shop name (before any " - address" part)
                const shopName = selectedOption.textContent.split(' - ')[0];
                
                // Create and append shop name span
                const shopNameSpan = document.createElement('span');
                shopNameSpan.className = 'sln-current-shop-name';
                shopNameSpan.textContent = ' - ' + shopName;
                titleElement.appendChild(shopNameSpan);
            } else {
                console.warn('Cannot update title - shop option not loaded yet or is "All Shops"');
            }
        },
        
        /**
         * Restore previously selected shop from localStorage
         */
        restoreSelection() {
            const select = document.getElementById('sln-shop-selector');
            if (!select) {
                console.warn('Shop selector not found in restoreSelection');
                return;
            }
            
            // Check if user is a shop manager
            const managerShopIds = window.salonDashboard && window.salonDashboard.managerShopIds ? 
                                   window.salonDashboard.managerShopIds.map(id => parseInt(id)) : [];
            const hasSingleShop = managerShopIds.length === 1;
            const hasMultipleShops = managerShopIds.length > 1;
            
            // Priority 1: Manager with single shop - use that shop only
            if (hasSingleShop) {
                const managerShopId = parseInt(window.salonDashboard.managerShopId);
                
                if (managerShopId > 0) {
                    select.value = managerShopId;
                    this.currentShop = managerShopId;
                    
                    // DON'T update title for single-shop managers - it's already in HTML from PHP
                    // The shop name was rendered server-side and is already displayed
                    return;
                }
            }
            
            // Priority 2: Manager with multiple shops - restore from localStorage if valid
            if (hasMultipleShops) {
                const savedShop = localStorage.getItem('sln_selected_shop');
                const savedShopId = savedShop ? parseInt(savedShop) : 0;
                
                // Check if saved shop is in manager's assigned shops
                if (savedShopId > 0 && managerShopIds.includes(savedShopId)) {
                    select.value = savedShopId;
                    this.currentShop = savedShopId;
                } else {
                    // Use default (first assigned shop)
                    const managerShopId = parseInt(window.salonDashboard.managerShopId);
                    if (managerShopId > 0) {
                        select.value = managerShopId;
                        this.currentShop = managerShopId;
                    }
                }
                
                // Update title for multi-shop managers
                setTimeout(() => {
                    this.updateDashboardTitle(select);
                }, 500);
                return;
            }
            
            // Priority 3: Admins/non-managers - restore from localStorage
            const savedShop = localStorage.getItem('sln_selected_shop');
            
            if (savedShop) {
                const shopId = parseInt(savedShop) || 0;
                select.value = shopId;
                this.currentShop = shopId;
                
                // For admins, wait a bit for shops to load before updating title
                setTimeout(() => {
                    this.updateDashboardTitle(select);
                }, 500);
            }
        },
        
        /**
         * Get currently selected shop ID
         */
        getCurrentShop() {
            return this.currentShop;
        }
    };

    /**
     * Date Range Manager
     */
    const DateRangeManager = {
        currentRange: 'this_month',
        customDates: null,
        
        ranges: {
            today: {
                label: __('Today', 'salon-booking-system'),
                getDates: function() {
                    const today = new Date();
                    return {
                        start: formatDate(today),
                        end: formatDate(today)
                    };
                }
            },
            yesterday: {
                label: __('Yesterday', 'salon-booking-system'),
                getDates: function() {
                    const yesterday = new Date();
                    yesterday.setDate(yesterday.getDate() - 1);
                    return {
                        start: formatDate(yesterday),
                        end: formatDate(yesterday)
                    };
                }
            },
            this_week: {
                label: __('This Week', 'salon-booking-system'),
                getDates: function() {
                    const now = new Date();
                    const dayOfWeek = now.getDay(); // 0 = Sunday, 1 = Monday, etc.
                    const start = new Date(now);
                    // Calculate Monday (start of week)
                    const diff = dayOfWeek === 0 ? -6 : 1 - dayOfWeek; // If Sunday, go back 6 days; otherwise go to Monday
                    start.setDate(now.getDate() + diff);
                    // Calculate Sunday (end of week)
                    const end = new Date(start);
                    end.setDate(start.getDate() + 6);
                    return {
                        start: formatDate(start),
                        end: formatDate(end)
                    };
                }
            },
            last_7_days: {
                label: __('Last 7 Days', 'salon-booking-system'),
                getDates: function() {
                    const end = new Date();
                    const start = new Date();
                    start.setDate(start.getDate() - 6);
                    return {
                        start: formatDate(start),
                        end: formatDate(end)
                    };
                }
            },
            last_30_days: {
                label: __('Last 30 Days', 'salon-booking-system'),
                getDates: function() {
                    const end = new Date();
                    const start = new Date();
                    start.setDate(start.getDate() - 29);
                    return {
                        start: formatDate(start),
                        end: formatDate(end)
                    };
                }
            },
            this_month: {
                label: __('This Month', 'salon-booking-system'),
                getDates: function() {
                    const now = new Date();
                    const start = new Date(now.getFullYear(), now.getMonth(), 1);
                    const end = new Date(now.getFullYear(), now.getMonth() + 1, 0);
                    return {
                        start: formatDate(start),
                        end: formatDate(end)
                    };
                }
            },
            last_month: {
                label: __('Last Month', 'salon-booking-system'),
                getDates: function() {
                    const now = new Date();
                    const start = new Date(now.getFullYear(), now.getMonth() - 1, 1);
                    const end = new Date(now.getFullYear(), now.getMonth(), 0);
                    return {
                        start: formatDate(start),
                        end: formatDate(end)
                    };
                }
            },
            this_quarter: {
                label: __('This Quarter', 'salon-booking-system'),
                getDates: function() {
                    const now = new Date();
                    const quarter = Math.floor(now.getMonth() / 3);
                    const start = new Date(now.getFullYear(), quarter * 3, 1);
                    const end = new Date(now.getFullYear(), (quarter + 1) * 3, 0);
                    return {
                        start: formatDate(start),
                        end: formatDate(end)
                    };
                }
            },
            this_year: {
                label: __('This Year', 'salon-booking-system'),
                getDates: function() {
                    const now = new Date();
                    const start = new Date(now.getFullYear(), 0, 1);
                    const end = new Date(now.getFullYear(), 11, 31);
                    return {
                        start: formatDate(start),
                        end: formatDate(end)
                    };
                }
            }
        },

        getCurrentDates: function() {
            if (this.currentRange === 'custom' && this.customDates) {
                return this.customDates;
            }
            return this.ranges[this.currentRange].getDates();
        },
        
        setCustomDates: function(startDate, endDate) {
            this.customDates = {
                start: startDate,
                end: endDate
            };
            this.currentRange = 'custom';
        },

        setRange: function(rangeKey) {
            if (this.ranges[rangeKey]) {
                this.currentRange = rangeKey;
                return this.getCurrentDates();
            }
            return null;
        }
    };

    /**
     * Utility functions
     */
    function formatDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    function formatCurrency(amount) {
        const settings = window.salonDashboard || {};
        const currency = settings.currency || 'USD';
        const symbol = settings.currencySymbol || '$';
        
        return symbol + parseFloat(amount).toFixed(2);
    }

    function formatNumber(num) {
        return new Intl.NumberFormat().format(num);
    }

    function formatPercentage(num) {
        return parseFloat(num).toFixed(1) + '%';
    }

    /**
     * Dashboard UI Components
     */
    const DashboardUI = {
        initialized: false,
        
        /**
         * Initialize dashboard
         */
        init: function() {
            if (this.initialized) {
                console.warn('Dashboard already initialized, skipping...');
                return;
            }
            
            this.handleFreeVersionRestrictions();
            this.initProFeatureDialog();
            this.bindProModalButtons();
            
            // Initialize Shop Manager if Multi-Shop is active
            if (document.getElementById('sln-shop-selector')) {
                ShopManager.init();
            }
            
            this.bindEvents();
            this.updateDateRangeDisplay();
            this.loadDashboard();
            
            this.initialized = true;
        },

        /**
         * Bind all PRO modal buttons to open the modal
         */
        bindProModalButtons: function() {
            const buttons = document.querySelectorAll('.sln-open-pro-modal');
            const dialogWrapper = document.getElementById('sln-reports-dashboard-pro-dialog');
            const openButton = dialogWrapper ? dialogWrapper.querySelector('.sln-profeature__open-button') : null;
            
            if (!openButton) {
                return;
            }
            
            buttons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    openButton.click();
                });
            });
        },

        /**
         * Handle free version restrictions
         */
        handleFreeVersionRestrictions: function() {
            const settings = window.salonDashboard || {};
            const isPro = settings.isPro || false;
            
            
            if (!isPro) {
                // Wrap the date range selector
                const $dateRangeContainer = $('.sln-dashboard-filters');
                
                // Check if CTA banner already exists (prevent duplicates)
                const existingBanners = $dateRangeContainer.find('.sln-today-tooltip__cta');
                
                if (existingBanners.length > 0) {
                    return;
                }
                
                
                // Force "This Week" as default for free version
                DateRangeManager.currentRange = 'this_week';
                $('#sln-date-range').val('this_week');
                
                $dateRangeContainer.addClass('sln-dashboard-filters--free');
                
                // Disable all other options except "This Week"
                $('#sln-date-range option').each(function() {
                    if ($(this).val() !== 'this_week') {
                        $(this).prop('disabled', true);
                        // Add "(PRO)" label to disabled options
                        const currentText = $(this).text();
                        if (!currentText.includes('PRO')) {
                            $(this).text(currentText + ' (PRO)');
                        }
                    }
                });
                
                // Get plugin URL from script src
                let pluginUrl = '';
                const scripts = document.getElementsByTagName('script');
                for (let i = 0; i < scripts.length; i++) {
                    const src = scripts[i].src || '';
                    if (src.indexOf('reports-dashboard.js') !== -1) {
                        const jsIndex = src.lastIndexOf('/js/');
                        if (jsIndex !== -1) {
                            pluginUrl = src.substring(0, jsIndex);
                            break;
                        }
                    }
                }
                
                // Add pro feature CTA banner (same style as today tooltip) using vanilla JS
                const ctaBanner = document.createElement('div');
                ctaBanner.className = 'sln-today-tooltip__cta';
                ctaBanner.style.cursor = 'pointer';
                
                const ctaText = document.createElement('div');
                ctaText.className = 'sln-today-tooltip__cta-text';
                
                const ctaLine1 = document.createElement('div');
                ctaLine1.textContent = __('Unlock this feature', 'salon-booking-system');
                ctaText.appendChild(ctaLine1);
                
                const ctaLine2 = document.createElement('div');
                ctaLine2.className = 'sln-today-tooltip__cta-text--strong';
                ctaLine2.textContent = __('Switch to PRO', 'salon-booking-system');
                ctaText.appendChild(ctaLine2);
                
                const crownImg = document.createElement('img');
                crownImg.src = pluginUrl + '/img/crown-pro-icon.png';
                crownImg.className = 'sln-today-tooltip__crown-icon';
                crownImg.alt = 'PRO';
                crownImg.width = 28;
                crownImg.height = 28;
                
                ctaBanner.appendChild(ctaText);
                ctaBanner.appendChild(crownImg);
                
                // Manually initialize the pro feature dialog FIRST
                // This ensures the dialog is properly set up before we add the click handler
                this.initProFeatureDialog();
                
                // Add click handler (same pattern as calendar tooltip)
                ctaBanner.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Find and trigger the EXISTING PRO feature dialog
                    var existingOpenButton = document.querySelector('#sln-reports-dashboard-pro-dialog .sln-profeature__open-button');
                    
                    if (existingOpenButton) {
                        existingOpenButton.click();
                    }
                });
                
                $dateRangeContainer[0].appendChild(ctaBanner);
            }
        },
        
        /**
         * Initialize pro feature dialog (same logic as admin.js sln_ProFeatureTooltip)
         */
        initProFeatureDialog: function() {
            const dialogWrapper = document.querySelector('#sln-reports-dashboard-pro-dialog');
            if (!dialogWrapper) return;
            
            const ctaElement = dialogWrapper.querySelector('.sln-profeature__cta');
            if (!ctaElement) return;
            
            const dialog = ctaElement.querySelector('.sln-profeature__dialog');
            const openButton = ctaElement.querySelector('.sln-profeature__open-button');
            const closeButton = ctaElement.querySelector('.sln-profeature__close-button');
            
            if (!dialog || !openButton || !closeButton) return;
            
            // Add event listeners
            openButton.addEventListener('click', function(event) {
                event.preventDefault();
                dialog.showModal();
                dialog.classList.add('open');
            });
            
            closeButton.addEventListener('click', function(event) {
                event.preventDefault();
                dialog.close();
                dialog.classList.remove('open');
            });
            
            dialog.addEventListener('click', function(event) {
                if (event.target.nodeName === 'DIALOG') {
                    dialog.close();
                    dialog.classList.remove('open');
                }
            });
        },

        /**
         * Bind UI events
         */
        bindEvents: function() {
            // Date range selector
            $('#sln-date-range').on('change', (e) => {
                const rangeKey = $(e.target).val();
                
                console.log('Date range changed to:', rangeKey);
                
                if (rangeKey === 'custom') {
                    // Show custom date picker
                    $('#sln-custom-date-range').show();
                    $('#sln-date-range-display').hide();
                    
                    // Set default values to current month
                    const today = new Date();
                    const startOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
                    const endOfMonth = new Date(today.getFullYear(), today.getMonth() + 1, 0);
                    
                    $('#sln-start-date').val(this.formatDateForInput(startOfMonth));
                    $('#sln-end-date').val(this.formatDateForInput(endOfMonth));
                } else {
                    // Hide custom date picker
                    $('#sln-custom-date-range').hide();
                    $('#sln-date-range-display').show();
                    
                    const newDates = DateRangeManager.setRange(rangeKey);
                    console.log('New date range set:', newDates);
                    this.updateDateRangeDisplay();
                    this.loadDashboard();
                }
            });

            // Apply custom date range
            $('#sln-apply-custom-range').on('click', () => {
                const startDate = $('#sln-start-date').val();
                const endDate = $('#sln-end-date').val();
                
                console.log('Applying custom date range:', startDate, 'to', endDate);
                
                if (!startDate || !endDate) {
                    alert(__('Please select both start and end dates', 'salon-booking-system'));
                    return;
                }
                
                if (startDate > endDate) {
                    alert(__('Start date must be before end date', 'salon-booking-system'));
                    return;
                }
                
                DateRangeManager.setCustomDates(startDate, endDate);
                this.updateDateRangeDisplay();
                this.loadDashboard();
            });

            // Refresh button
            $('#sln-dashboard-refresh').on('click', () => {
                this.loadDashboard();
            });

            // Export button
            $('#sln-dashboard-export').on('click', () => {
                this.exportData();
            });
        },

        /**
         * Update the date range display text
         */
        updateDateRangeDisplay: function() {
            const dates = DateRangeManager.getCurrentDates();
            const displayText = sprintf(
                __('from %1$s to %2$s', 'salon-booking-system'),
                this.formatDisplayDate(dates.start),
                this.formatDisplayDate(dates.end)
            );
            $('#sln-date-range-display').text(displayText);
        },

        /**
         * Format date for display (e.g., "November 1, 2025")
         */
        formatDisplayDate: function(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { 
                month: 'long', 
                day: 'numeric',
                year: 'numeric'
            });
        },

        /**
         * Format date for HTML5 date input (YYYY-MM-DD)
         */
        formatDateForInput: function(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        },

        /**
         * Load dashboard data
         */
        loadDashboard: async function() {
            this.showLoading();

            try {
                const dates = DateRangeManager.getCurrentDates();
                
                // Load all data in parallel
                
                // Check if PRO version for no-show features
                const settings = window.salonDashboard || {};
                const isPro = settings.isPro || false;
                
                const apiCalls = [
                    DashboardAPI.getEnhancedStats(dates.start, dates.end, { 
                        compare_previous_period: true,
                        group_by: 'day'  // Use daily grouping for chart
                    }),
                    DashboardAPI.getServiceStats(dates.start, dates.end, { limit: 10 }),
                    DashboardAPI.getAssistantStats(dates.start, dates.end),
                    DashboardAPI.getCustomerStats(dates.start, dates.end, { limit: 10 }),
                    DashboardAPI.getPeakTimes(dates.start, dates.end),
                    DashboardAPI.getCustomerRetention(dates.start, dates.end),
                    DashboardAPI.getFrequencyCLV(dates.start, dates.end),
                    DashboardAPI.getUtilization(dates.start, dates.end)
                ];
                
                // Only load no-show data on PRO version
                if (isPro) {
                    apiCalls.push(
                        DashboardAPI.getNoShowStats(dates.start, dates.end).catch(err => {
                            console.warn('No-show stats failed:', err);
                            return null;
                        }),
                        DashboardAPI.getNoShowRateByPeriod(dates.start, dates.end, 'week').catch(err => {
                            console.warn('No-show rate by period failed:', err);
                            return null;
                        })
                    );
                } else {
                    // Return null placeholders for free version
                    apiCalls.push(Promise.resolve(null), Promise.resolve(null));
                }
                
                const [stats, services, assistants, customers, peakTimes, retention, frequencyCLV, utilization, noShowStats, noShowRate] = await Promise.all(apiCalls);

                // Debug: Log fetched data
                console.log('Dashboard data loaded:', {
                    stats: stats,
                    services: services,
                    assistants: assistants,
                    dateRange: dates
                });

                // Update UI components
                this.updateKPICards(stats, retention);
                this.updateRevenueChart(stats);
                this.updateBookingHeatmap(peakTimes);
                this.updateUtilizationMetrics(utilization);
                this.updateFrequencyCLVMetrics(frequencyCLV);
                this.updateServicesTable(services);
                this.updateAssistantsTable(assistants);
                this.updateCustomersWidget(customers);
                this.updateAtRiskCustomersTable(retention);
                
                // Update no-show metrics if data is available
                if (noShowStats) {
                    this.updateNoShowMetrics(noShowStats, noShowRate);
                } else {
                }

                this.hideLoading();
            } catch (error) {
                console.error('Failed to load dashboard:', error);
                this.showError('Failed to load dashboard data. Please try again.');
                this.hideLoading();
            }
        },

        /**
         * Update KPI cards
         */
        updateKPICards: function(stats, retention) {
            const current = stats.current_period;
            const comparison = stats.comparison || {};

            // Revenue card (unified with avg value)
            $('#kpi-revenue-value').text(formatCurrency(current.total_revenue));
            $('#kpi-revenue-change').text(
                (comparison.revenue_change_pct >= 0 ? '+' : '') + formatPercentage(comparison.revenue_change_pct)
            ).removeClass('positive negative').addClass(comparison.revenue_change_pct >= 0 ? 'positive' : 'negative');

            // Bookings card
            $('#kpi-bookings .kpi-value').text(formatNumber(current.total_bookings));
            $('#kpi-bookings .kpi-change').text(
                (comparison.bookings_change_pct >= 0 ? '+' : '') + formatPercentage(comparison.bookings_change_pct)
            ).removeClass('positive negative').addClass(comparison.bookings_change_pct >= 0 ? 'positive' : 'negative');
            
            // Update cancellation metrics
            $('#kpi-cancellation-rate').text(formatPercentage(current.cancellation_rate || 0));
            $('#kpi-canceled-revenue').text(formatCurrency(current.canceled_revenue || 0));
            
            // Update bookings by status chart
            this.updateBookingsStatusChart(current.by_status || {});

            // Average value card (unified with revenue)
            $('#kpi-avg-value-value').text(formatCurrency(current.avg_booking_value));
            $('#kpi-avg-value-change').text(
                (comparison.avg_value_change_pct >= 0 ? '+' : '') + formatPercentage(comparison.avg_value_change_pct)
            ).removeClass('positive negative').addClass(comparison.avg_value_change_pct >= 0 ? 'positive' : 'negative');

            // Customers card (unified with rebooking rate)
            $('#kpi-customers-value').text(formatNumber(current.unique_customers));
            $('#kpi-customers-new').text(formatNumber(current.new_customers) + ' New');
            $('#kpi-customers-returning').text(formatNumber(current.returning_customers) + ' Returning');
            
            // Rebooking rate card (unified with customers)
            if (retention) {
                $('#kpi-rebooking-value').text(formatPercentage(retention.rebooking_rate || 0));
                $('#kpi-rebooking-subtitle').text(
                    formatNumber(retention.customers_with_rebooking || 0) + ' of ' + 
                    formatNumber(retention.total_customers_measured || 0) + ' customers rebooked'
                );
            }
        },

        /**
         * Update bookings by status doughnut chart
         */
        updateBookingsStatusChart: function(byStatus) {
            const ctx = document.getElementById('bookings-status-chart');
            if (!ctx) return;
            
            
            // Helper to get count from status object
            const getCount = (statusKey) => {
                if (!byStatus[statusKey]) return 0;
                return byStatus[statusKey].count || byStatus[statusKey] || 0;
            };
            
            // Match calendar chart colors and order exactly
            // Order: Paid/Confirmed, Pay Later, Pending, Cancelled, No-Shows
            const statusData = [
                {
                    label: __('Confirmed/Paid', 'salon-booking-system'),
                    value: getCount('sln-b-confirmed') + getCount('sln-b-paid'),
                    color: '#6AA84F'  // Green (matches calendar)
                },
                {
                    label: __('Pay Later', 'salon-booking-system'),
                    value: getCount('sln-b-paylater'),
                    color: '#6D9EEB'  // Blue (matches calendar)
                },
                {
                    label: __('Cancelled', 'salon-booking-system'),
                    value: getCount('sln-b-canceled'),
                    color: '#E54747'  // Red (matches calendar)
                },
                {
                    label: __('No-Shows', 'salon-booking-system'),
                    value: getCount('no-show'),
                    color: '#1B1B21'  // Dark gray/black (matches calendar)
                }
            ];
            
            // Filter out statuses with zero count
            const filteredData = statusData.filter(item => item.value > 0);
            
            // If no data, exit
            if (filteredData.length === 0) {
                console.warn('No booking status data to display');
                return;
            }
            
            // Prepare chart data
            const labels = filteredData.map(item => item.label);
            const data = filteredData.map(item => item.value);
            const colors = filteredData.map(item => item.color);
            
            
            // If no data, show a placeholder
            if (data.length === 0) {
                console.warn('No booking status data to display');
                return;
            }
            
            // Destroy existing chart if it exists
            if (window.bookingsStatusChart) {
                window.bookingsStatusChart.destroy();
            }
            
            // Create doughnut chart (matching calendar style)
            window.bookingsStatusChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: data,
                        backgroundColor: colors,
                        borderWidth: 2,
                        borderColor: '#fff',
                        hoverBorderWidth: 3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    cutout: '45%', // Match Google Charts pieHole: 0.45
                    plugins: {
                        legend: {
                            display: false // Legend not needed, shown in summary items
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((value / total) * 100).toFixed(1);
                                    return label + ': ' + value + ' (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
        },

        /**
         * Format timeline date for display based on the date format
         * Converts API date formats to human-readable labels
         *
         * @param {string} dateStr - Date string from API (e.g., "2025-12-09", "2025-W50", "2025-12", "2025-Q4", "2025")
         * @return {string} Formatted date label
         */
        formatTimelineDate: function(dateStr) {
            if (!dateStr) return '';
            
            // Handle different date formats
            // Year only: "2025"
            if (/^\d{4}$/.test(dateStr)) {
                return dateStr;
            }
            
            // Quarter: "2025-Q4"
            if (/^\d{4}-Q\d$/.test(dateStr)) {
                const [year, quarter] = dateStr.split('-');
                return quarter + ' ' + year;
            }
            
            // Week: "2025-W50"
            if (/^\d{4}-W\d{1,2}$/.test(dateStr)) {
                const [year, week] = dateStr.split('-W');
                return sprintf(__('Week %1$s, %2$s', 'salon-booking-system'), week, year);
            }
            
            // Month: "2025-12"
            if (/^\d{4}-\d{2}$/.test(dateStr)) {
                const [year, month] = dateStr.split('-');
                const date = new Date(parseInt(year), parseInt(month) - 1, 1);
                return date.toLocaleDateString(undefined, { month: 'short', year: 'numeric' });
            }
            
            // Full date: "2025-12-09"
            if (/^\d{4}-\d{2}-\d{2}$/.test(dateStr)) {
                const date = new Date(dateStr);
                return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
            }
            
            // Fallback: return as-is
            return dateStr;
        },

        /**
         * Update revenue chart using Chart.js
         */
        updateRevenueChart: function(stats) {
            const container = $('#revenue-chart');
            container.empty();
            
            const timeline = stats.current_period.timeline || [];
            
            if (timeline.length === 0) {
                container.html('<p style="text-align:center;padding:40px;color:#666;">' + __('No data available for the selected period', 'salon-booking-system') + '</p>');
                return;
            }
            
            // Create canvas for chart
            const canvas = $('<canvas id="revenue-chart-canvas"></canvas>');
            container.append(canvas);
            
            // Prepare data for Chart.js with formatted date labels
            const self = this;
            const labels = timeline.map(item => self.formatTimelineDate(item.date));
            const revenueData = timeline.map(item => item.revenue);
            const bookingsData = timeline.map(item => item.bookings);
            
            // Get currency symbol
            const currencySymbol = window.salonDashboard?.currencySymbol || '$';
            
            // Destroy existing chart if any
            if (this.revenueChart) {
                this.revenueChart.destroy();
            }
            
            // Create new chart
            const ctx = canvas[0].getContext('2d');
            this.revenueChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: __('Revenue', 'salon-booking-system'),
                            data: revenueData,
                            borderColor: 'rgb(75, 192, 192)',
                            backgroundColor: 'rgba(75, 192, 192, 0.1)',
                            yAxisID: 'y',
                            tension: 0.3
                        },
                        {
                            label: __('Bookings', 'salon-booking-system'),
                            data: bookingsData,
                            borderColor: 'rgb(255, 99, 132)',
                            backgroundColor: 'rgba(255, 99, 132, 0.1)',
                            yAxisID: 'y1',
                            tension: 0.3
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.dataset.yAxisID === 'y') {
                                        label += currencySymbol + context.parsed.y.toLocaleString();
                                    } else {
                                        label += context.parsed.y;
                                    }
                                    return label;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: sprintf(__('Revenue (%s)', 'salon-booking-system'), currencySymbol)
                            },
                            ticks: {
                                callback: function(value) {
                                    return currencySymbol + value.toLocaleString();
                                }
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: __('Bookings', 'salon-booking-system')
                            },
                            grid: {
                                drawOnChartArea: false,
                            },
                        },
                    }
                }
            });
        },

        /**
         * Update booking heatmap
         */
        updateBookingHeatmap: function(peakTimesData) {
            const container = $('#booking-heatmap');
            container.empty();
            
            if (!peakTimesData || !peakTimesData.daily_heatmap || !peakTimesData.hourly_heatmap) {
                container.html('<p style="text-align:center;padding:40px;color:#666;">' + __('No booking pattern data available', 'salon-booking-system') + '</p>');
                return;
            }
            
            const dailyData = peakTimesData.daily_heatmap;
            const hourlyData = peakTimesData.hourly_heatmap;
            
            // Create heatmap table
            const table = $('<table class="sln-heatmap-table"></table>');
            
            // Header row with days of week
            const thead = $('<thead></thead>');
            const headerRow = $('<tr></tr>');
            headerRow.append('<th>' + __('Time', 'salon-booking-system') + '</th>');
            const days = [
                __('Monday', 'salon-booking-system'),
                __('Tuesday', 'salon-booking-system'),
                __('Wednesday', 'salon-booking-system'),
                __('Thursday', 'salon-booking-system'),
                __('Friday', 'salon-booking-system'),
                __('Saturday', 'salon-booking-system'),
                __('Sunday', 'salon-booking-system')
            ];
            days.forEach(day => {
                headerRow.append($('<th></th>').text(day));
            });
            thead.append(headerRow);
            table.append(thead);
            
            // Find max bookings for color intensity calculation
            let maxBookings = 0;
            Object.values(hourlyData).forEach(dayData => {
                Object.values(dayData).forEach(count => {
                    if (count > maxBookings) maxBookings = count;
                });
            });
            
            // Create rows for each hour (8 AM to 8 PM)
            const tbody = $('<tbody></tbody>');
            for (let hour = 8; hour <= 20; hour++) {
                const row = $('<tr></tr>');
                const timeLabel = (hour < 12 ? hour : (hour === 12 ? 12 : hour - 12)) + ':00 ' + (hour < 12 ? 'AM' : 'PM');
                row.append($('<td class="time-label"></td>').text(timeLabel));
                
                // Add cell for each day
                for (let dayNum = 1; dayNum <= 7; dayNum++) {
                    const bookings = hourlyData[dayNum] ? (hourlyData[dayNum][hour] || 0) : 0;
                    const intensity = maxBookings > 0 ? (bookings / maxBookings) : 0;
                    
                    // Color from light blue to dark blue based on intensity
                    const alpha = 0.1 + (intensity * 0.9); // 10% to 100% opacity
                    const backgroundColor = `rgba(75, 192, 192, ${alpha})`;
                    
                    const cell = $('<td class="heatmap-cell"></td>')
                        .css('background-color', backgroundColor)
                        .attr('title', `${days[dayNum-1]} ${timeLabel}: ${bookings} bookings`)
                        .text(bookings > 0 ? bookings : '');
                    
                    row.append(cell);
                }
                tbody.append(row);
            }
            table.append(tbody);
            
            // Add legend
            const legend = $('<div class="heatmap-legend"></div>');
            legend.append('<span>' + __('Less busy', 'salon-booking-system') + '</span>');
            for (let i = 0; i <= 5; i++) {
                const alpha = 0.1 + (i / 5 * 0.9);
                const color = `rgba(75, 192, 192, ${alpha})`;
                legend.append($('<span class="legend-box"></span>').css('background-color', color));
            }
            legend.append('<span>' + __('More busy', 'salon-booking-system') + '</span>');
            
            container.append(table);
            container.append(legend);
            
            // Add some CSS for the heatmap
            if (!$('#heatmap-styles').length) {
                $('<style id="heatmap-styles">')
                    .text(`
                        .sln-heatmap-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                        .sln-heatmap-table th { background: #f0f0f0; padding: 10px; text-align: center; font-weight: 600; border: 1px solid #ddd; }
                        .sln-heatmap-table td { padding: 15px; text-align: center; border: 1px solid #ddd; min-width: 50px; cursor: pointer; transition: transform 0.2s; }
                        .sln-heatmap-table td.time-label { background: #f9f9f9; font-weight: 500; }
                        .sln-heatmap-table td.heatmap-cell:hover { transform: scale(1.1); box-shadow: 0 2px 8px rgba(0,0,0,0.2); }
                        .heatmap-legend { display: flex; align-items: center; gap: 5px; margin-top: 20px; justify-content: center; }
                        .heatmap-legend .legend-box { width: 30px; height: 20px; border: 1px solid #ddd; }
                    `)
                    .appendTo('head');
            }
        },

        /**
         * Update services table
         */
        updateServicesTable: function(services) {
            const tbody = $('#services-table tbody');
            tbody.empty();
            
            // Debug: Log the data being displayed
            console.log('Updating services table with data:', services);

            services.items.forEach(service => {
                const row = $('<tr>')
                    .append($('<td>').text(service.service_name))
                    .append($('<td>').text(formatNumber(service.bookings_count)))
                    .append($('<td>').text(formatCurrency(service.total_revenue)))
                    .append($('<td>').text(formatCurrency(service.avg_revenue)));
                tbody.append(row);
            });
            
            // Update pie charts
            this.updateServicePieCharts(services);
        },

        /**
         * Update service performance pie charts
         */
        updateServicePieCharts: function(services) {
            const settings = window.salonDashboard || {};
            const currencySymbol = settings.currencySymbol || '$';
            
            // Debug: Log service data being charted
            console.log('Updating service charts with data:', services);
            
            // Prepare data for charts
            const bookingsLabels = [];
            const bookingsData = [];
            const revenueLabels = [];
            const revenueData = [];
            
            // Modern color palette for charts
            const colors = [
                'rgba(99, 102, 241, 0.8)',   // Indigo
                'rgba(16, 185, 129, 0.8)',   // Emerald
                'rgba(251, 146, 60, 0.8)',   // Orange
                'rgba(139, 92, 246, 0.8)',   // Purple
                'rgba(236, 72, 153, 0.8)',   // Pink
                'rgba(14, 165, 233, 0.8)',   // Sky Blue
                'rgba(34, 197, 94, 0.8)',    // Green
                'rgba(249, 115, 22, 0.8)',   // Deep Orange
                'rgba(168, 85, 247, 0.8)',   // Violet
                'rgba(59, 130, 246, 0.8)'    // Blue
            ];
            
            services.items.forEach((service, index) => {
                bookingsLabels.push(service.service_name);
                bookingsData.push(service.bookings_count);
                
                revenueLabels.push(service.service_name);
                revenueData.push(service.total_revenue);
            });
            
            // Bookings Pie Chart
            const bookingsCtx = document.getElementById('service-bookings-chart');
            if (bookingsCtx) {
                // Destroy existing chart if it exists
                if (window.serviceBookingsChart) {
                    window.serviceBookingsChart.destroy();
                }
                
                window.serviceBookingsChart = new Chart(bookingsCtx, {
                    type: 'doughnut',
                    data: {
                        labels: bookingsLabels,
                        datasets: [{
                            data: bookingsData,
                            backgroundColor: colors,
                            borderWidth: 2,
                            borderColor: '#fff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    padding: 15,
                                    font: {
                                        size: 12
                                    }
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const label = context.label || '';
                                        const value = context.parsed;
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const percentage = ((value / total) * 100).toFixed(1);
                                        return sprintf(
                                            __('%1$s: %2$s bookings (%3$s%%)', 'salon-booking-system'),
                                            label,
                                            value,
                                            percentage
                                        );
                                    }
                                }
                            }
                        }
                    }
                });
            }
            
            // Revenue Pie Chart
            const revenueCtx = document.getElementById('service-revenue-chart');
            if (revenueCtx) {
                // Destroy existing chart if it exists
                if (window.serviceRevenueChart) {
                    window.serviceRevenueChart.destroy();
                }
                
                window.serviceRevenueChart = new Chart(revenueCtx, {
                    type: 'doughnut',
                    data: {
                        labels: revenueLabels,
                        datasets: [{
                            data: revenueData,
                            backgroundColor: colors,
                            borderWidth: 2,
                            borderColor: '#fff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    padding: 15,
                                    font: {
                                        size: 12
                                    }
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const label = context.label || '';
                                        const value = context.parsed;
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const percentage = ((value / total) * 100).toFixed(1);
                                        return sprintf(
                                            __('%1$s: %2$s%3$s (%4$s%%)', 'salon-booking-system'),
                                            label,
                                            currencySymbol,
                                            value.toFixed(2),
                                            percentage
                                        );
                                    }
                                }
                            }
                        }
                    }
                });
            }
        },

        /**
         * Update assistants table
         */
        updateAssistantsTable: function(assistants) {
            const tbody = $('#assistants-table tbody');
            tbody.empty();

            assistants.items.forEach(assistant => {
                const row = $('<tr>')
                    .append($('<td>').text(assistant.assistant_name))
                    .append($('<td>').text(formatNumber(assistant.bookings_count)))
                    .append($('<td>').text(formatCurrency(assistant.total_revenue)))
                    .append($('<td>').text(assistant.total_hours_worked.toFixed(1) + ' hrs'))
                    .append($('<td>').text(formatPercentage(assistant.utilization_rate)));
                tbody.append(row);
            });
            
            // Update pie charts
            this.updateAssistantPieCharts(assistants);
        },

        /**
         * Update assistant performance pie charts
         */
        updateAssistantPieCharts: function(assistants) {
            const settings = window.salonDashboard || {};
            const currencySymbol = settings.currencySymbol || '$';
            
            // Debug: Log assistant data being charted
            console.log('Updating assistant charts with data:', assistants);
            
            // Prepare data for hours chart
            const hoursLabels = [];
            const hoursData = [];
            const revenueLabels = [];
            const revenueData = [];
            
            // Modern color palette for charts
            const colors = [
                'rgba(99, 102, 241, 0.8)',   // Indigo
                'rgba(16, 185, 129, 0.8)',   // Emerald
                'rgba(251, 146, 60, 0.8)',   // Orange
                'rgba(139, 92, 246, 0.8)',   // Purple
                'rgba(236, 72, 153, 0.8)',   // Pink
                'rgba(14, 165, 233, 0.8)',   // Sky Blue
                'rgba(34, 197, 94, 0.8)',    // Green
                'rgba(249, 115, 22, 0.8)',   // Deep Orange
                'rgba(168, 85, 247, 0.8)',   // Violet
                'rgba(59, 130, 246, 0.8)'    // Blue
            ];
            
            assistants.items.forEach((assistant, index) => {
                hoursLabels.push(assistant.assistant_name);
                hoursData.push(assistant.total_hours_worked);
                
                revenueLabels.push(assistant.assistant_name);
                revenueData.push(assistant.total_revenue);
            });
            
            // Hours Pie Chart
            const hoursCtx = document.getElementById('assistant-hours-chart');
            if (hoursCtx) {
                // Destroy existing chart if it exists
                if (window.assistantHoursChart) {
                    window.assistantHoursChart.destroy();
                }
                
                window.assistantHoursChart = new Chart(hoursCtx, {
                    type: 'doughnut',
                    data: {
                        labels: hoursLabels,
                        datasets: [{
                            data: hoursData,
                            backgroundColor: colors,
                            borderWidth: 2,
                            borderColor: '#fff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    padding: 15,
                                    font: {
                                        size: 12
                                    }
                                }
                            },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((value / total) * 100).toFixed(1);
                                    return sprintf(
                                        __('%1$s: %2$s hrs (%3$s%%)', 'salon-booking-system'),
                                        label,
                                        value.toFixed(1),
                                        percentage
                                    );
                                }
                            }
                        }
                        }
                    }
                });
            }
            
            // Revenue Pie Chart
            const revenueCtx = document.getElementById('assistant-revenue-chart');
            if (revenueCtx) {
                // Destroy existing chart if it exists
                if (window.assistantRevenueChart) {
                    window.assistantRevenueChart.destroy();
                }
                
                window.assistantRevenueChart = new Chart(revenueCtx, {
                    type: 'doughnut',
                    data: {
                        labels: revenueLabels,
                        datasets: [{
                            data: revenueData,
                            backgroundColor: colors,
                            borderWidth: 2,
                            borderColor: '#fff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    padding: 15,
                                    font: {
                                        size: 12
                                    }
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const label = context.label || '';
                                        const value = context.parsed;
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const percentage = ((value / total) * 100).toFixed(1);
                                        return sprintf(
                                            __('%1$s: %2$s%3$s (%4$s%%)', 'salon-booking-system'),
                                            label,
                                            currencySymbol,
                                            value.toFixed(2),
                                            percentage
                                        );
                                    }
                                }
                            }
                        }
                    }
                });
            }
        },

        /**
         * Update customers widget
         */
        updateCustomersWidget: function(customers) {
            const list = $('#top-customers-list');
            list.empty();

            customers.top_customers.forEach((customer, index) => {
                const item = $('<div class="customer-item">')
                    .append($('<span class="rank">').text('#' + (index + 1)))
                    .append($('<span class="name">').text(customer.customer_name))
                    .append($('<span class="bookings">').text(formatNumber(customer.bookings_count) + ' bookings'))
                    .append($('<span class="spent">').text(formatCurrency(customer.total_spent)));
                list.append(item);
            });
        },

        /**
         * Update at-risk customers table
         */
        updateAtRiskCustomersTable: function(retention) {
            const tbody = $('#at-risk-customers-table tbody');
            tbody.empty();

            if (!retention || !retention.at_risk_customers || retention.at_risk_customers.length === 0) {
                tbody.append('<tr><td colspan="5" class="no-data-cell">No at-risk customers found</td></tr>');
                return;
            }

            retention.at_risk_customers.forEach((customer) => {
                // Calculate severity class based on days since last visit
                let severityClass = '';
                if (customer.days_since_last_visit > 120) {
                    severityClass = 'severity-high';
                } else if (customer.days_since_last_visit > 90) {
                    severityClass = 'severity-medium';
                }

                const row = $('<tr>').addClass(severityClass)
                    .append($('<td>')
                        .append($('<div class="customer-name">').text(customer.customer_name))
                        .append($('<div class="customer-email">').text(customer.customer_email))
                    )
                    .append($('<td>').text(customer.last_visit_date))
                    .append($('<td>')
                        .append($('<span class="days-since">').text(customer.days_since_last_visit + ' days'))
                    )
                    .append($('<td>').text(formatCurrency(customer.total_spent)))
                    .append($('<td>').text(formatNumber(customer.total_bookings)));
                
                tbody.append(row);
            });
        },

        /**
         * Update frequency and CLV metrics
         */
        updateFrequencyCLVMetrics: function(data) {
            if (!data) return;

            // Update metric cards
            $('#metric-clv').text(formatCurrency(data.avg_customer_lifetime_value || 0));
            $('#metric-avg-visits').text(formatNumber(data.avg_visits_per_customer || 0) + ' visits/customer');
            
            $('#metric-frequency').text(formatNumber(data.avg_days_between_visits || 0) + ' days');
            $('#metric-projected-annual').text(formatNumber(data.projected_annual_visits || 0) + ' visits/year projected');

            // Update CLV distribution chart
            this.updateCLVChart(data.clv_distribution || {});
        },

        /**
         * Update CLV distribution bar chart
         */
        updateCLVChart: function(distribution) {
            const ctx = document.getElementById('clv-distribution-chart');
            if (!ctx) return;

            // Prepare data
            const labels = [];
            const data = [];
            const colors = [
                'rgba(231, 76, 60, 0.8)',   // $0-100 - Red
                'rgba(230, 126, 34, 0.8)',  // $100-250 - Orange
                'rgba(241, 196, 15, 0.8)',  // $250-500 - Yellow
                'rgba(46, 204, 113, 0.8)',  // $500-1000 - Green
                'rgba(52, 152, 219, 0.8)',  // $1000+ - Blue
            ];

            // Add data in order
            const order = ['0-100', '100-250', '250-500', '500-1000', '1000+'];
            order.forEach(range => {
                labels.push('$' + range);
                data.push(distribution[range] || 0);
            });

            // Destroy existing chart if it exists
            if (window.clvChart) {
                window.clvChart.destroy();
            }

            // Create bar chart
            window.clvChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: __('Number of Customers', 'salon-booking-system'),
                        data: data,
                        backgroundColor: colors,
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return sprintf(
                                        __('%s customers', 'salon-booking-system'),
                                        context.parsed.y
                                    );
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            },
                            title: {
                                display: true,
                                text: __('Number of Customers', 'salon-booking-system')
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: __('Lifetime Value Range', 'salon-booking-system')
                            }
                        }
                    }
                }
            });
        },

        /**
         * Update utilization metrics and charts
         */
        updateUtilizationMetrics: function(data) {
            if (!data) return;

            // Update KPIs
            $('#util-rate').text(formatPercentage(data.utilization_rate || 0));
            $('#util-booked-hours').text(formatNumber(data.total_booked_hours || 0));
            $('#util-available-hours').text(formatNumber(data.total_available_hours || 0));
            
            $('#util-peak-day').text(data.peak_day || '--');
            $('#util-peak-day-bookings').text(formatNumber(data.peak_day_bookings || 0) + ' bookings');
            
            $('#util-peak-hour').text(data.peak_hour || '--');
            $('#util-peak-hour-bookings').text(formatNumber(data.peak_hour_bookings || 0) + ' bookings');
            
            $('#util-avg-bookings').text(formatNumber(data.avg_bookings_per_day || 0));

            // Update charts
            this.updateDayOfWeekChart(data.by_day_of_week || {});
            this.updateHourChart(data.by_hour || {});
        },

        /**
         * Update day of week chart
         */
        updateDayOfWeekChart: function(dayData) {
            const ctx = document.getElementById('day-of-week-chart');
            if (!ctx) return;

            const daysEn = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
            const days = [
                __('Monday', 'salon-booking-system'),
                __('Tuesday', 'salon-booking-system'),
                __('Wednesday', 'salon-booking-system'),
                __('Thursday', 'salon-booking-system'),
                __('Friday', 'salon-booking-system'),
                __('Saturday', 'salon-booking-system'),
                __('Sunday', 'salon-booking-system')
            ];
            const bookings = [];
            const revenue = [];

            daysEn.forEach(day => {
                const data = dayData[day] || { bookings: 0, revenue: 0 };
                bookings.push(data.bookings);
                revenue.push(data.revenue);
            });

            // Destroy existing chart
            if (window.dayOfWeekChart) {
                window.dayOfWeekChart.destroy();
            }

            // Create chart
            window.dayOfWeekChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: days,
                    datasets: [{
                        label: __('Bookings', 'salon-booking-system'),
                        data: bookings,
                        backgroundColor: 'rgba(102, 126, 234, 0.8)',
                        borderWidth: 0,
                        yAxisID: 'y'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.dataset.label || '';
                                    const value = context.parsed.y;
                                    return label + ': ' + value;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            },
                            title: {
                                display: true,
                                text: __('Number of Bookings', 'salon-booking-system')
                            }
                        }
                    }
                }
            });
        },

        /**
         * Update hour chart
         */
        updateHourChart: function(hourData) {
            const ctx = document.getElementById('hour-chart');
            if (!ctx) return;

            const hours = [];
            const bookings = [];

            // Filter to business hours (typically 8 AM - 8 PM)
            for (let h = 8; h <= 20; h++) {
                hours.push(h + ':00');
                const data = hourData[h] || { bookings: 0, revenue: 0 };
                bookings.push(data.bookings);
            }

            // Destroy existing chart
            if (window.hourChart) {
                window.hourChart.destroy();
            }

            // Create chart
            window.hourChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: hours,
                    datasets: [{
                        label: __('Bookings', 'salon-booking-system'),
                        data: bookings,
                        backgroundColor: 'rgba(245, 87, 108, 0.2)',
                        borderColor: 'rgba(245, 87, 108, 1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return sprintf(
                                        __('Bookings: %s', 'salon-booking-system'),
                                        context.parsed.y
                                    );
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            },
                            title: {
                                display: true,
                                text: __('Number of Bookings', 'salon-booking-system')
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: __('Time of Day', 'salon-booking-system')
                            }
                        }
                    }
                }
            });
        },

        /**
         * Update no-show tracking metrics
         */
        updateNoShowMetrics: function(stats, rateData) {
            
            if (!stats) {
                return;
            }

            // Update KPIs
            $('#noshow-total').text(stats.no_show_count || 0);
            $('#noshow-rate').text((stats.no_show_rate || 0) + '%');
            $('#noshow-affected').text(stats.total_bookings || 0);

            // Update recent no-shows list
            this.updateRecentNoShowsList(stats.recent_no_shows || []);

            // Update trend chart
            this.updateNoShowTrendChart(rateData);
        },

        /**
         * Update recent no-shows list
         */
        updateRecentNoShowsList: function(noShows) {
            const container = $('#recent-noshows-list');
            
            if (!noShows || noShows.length === 0) {
                container.html('<p class="sln-no-data">' + __('No no-shows in this period', 'salon-booking-system') + '</p>');
                return;
            }

            let html = '';
            noShows.forEach(noShow => {
                const bookingDate = new Date(noShow.booking_date);
                const markedDate = noShow.marked_at ? new Date(noShow.marked_at) : null;
                
                // Format services
                const servicesHtml = (noShow.services || []).map(service => 
                    `<span class="sln-noshow-service">${this.escapeHtml(service)}</span>`
                ).join('');

                html += `
                    <div class="sln-noshow-item">
                        <div class="sln-noshow-item-header">
                            <div class="sln-noshow-customer-name">${this.escapeHtml(noShow.customer_name)}</div>
                            <div class="sln-noshow-date">${bookingDate.toLocaleDateString()}</div>
                        </div>
                        <div class="sln-noshow-item-body">
                            ${servicesHtml}
                            ${markedDate ? `
                                <div class="sln-noshow-marked-by">
                                    Marked on ${markedDate.toLocaleDateString()} ${markedDate.toLocaleTimeString()}
                                    ${noShow.marked_by_name ? ' by ' + this.escapeHtml(noShow.marked_by_name) : ''}
                                </div>
                            ` : ''}
                        </div>
                    </div>
                `;
            });

            container.html(html);
        },

        /**
         * Update no-show trend chart
         */
        updateNoShowTrendChart: function(rateData) {
            const ctx = document.getElementById('noshow-trend-chart');
            if (!ctx || !rateData || !rateData.periods) return;

            const labels = [];
            const rates = [];

            rateData.periods.forEach(period => {
                const startDate = new Date(period.period_start);
                labels.push(startDate.toLocaleDateString());
                rates.push(period.rate);
            });

            // Destroy existing chart
            if (window.noShowTrendChart) {
                window.noShowTrendChart.destroy();
            }

            // Create chart
            window.noShowTrendChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: __('No-Show Rate (%)', 'salon-booking-system'),
                        data: rates,
                        backgroundColor: 'rgba(244, 67, 54, 0.1)',
                        borderColor: '#f44336',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    aspectRatio: 2,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return sprintf(
                                        __('No-Show Rate: %s%%', 'salon-booking-system'),
                                        context.parsed.y.toFixed(2)
                                    );
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            ticks: {
                                callback: function(value) {
                                    return value + '%';
                                }
                            },
                            title: {
                                display: true,
                                text: __('No-Show Rate (%)', 'salon-booking-system')
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: __('Period', 'salon-booking-system')
                            }
                        }
                    }
                }
            });
        },

        /**
         * Escape HTML to prevent XSS
         */
        escapeHtml: function(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
        },

        /**
         * Show loading state
         */
        showLoading: function() {
            $('#sln-dashboard-content').addClass('loading');
            $('#sln-dashboard-loader').show();
        },

        /**
         * Hide loading state
         */
        hideLoading: function() {
            $('#sln-dashboard-content').removeClass('loading');
            $('#sln-dashboard-loader').hide();
        },

        /**
         * Show error message
         */
        showError: function(message) {
            $('#sln-dashboard-error').text(message).show();
            setTimeout(() => {
                $('#sln-dashboard-error').hide();
            }, 5000);
        },

        /**
         * Export data (placeholder)
         */
        exportData: function() {
            alert(__('Export functionality coming soon!', 'salon-booking-system'));
        }
    };

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        const dashboardElement = $('#sln-reports-dashboard');
        
        if (dashboardElement.length) {
            DashboardUI.init();
        } else {
            console.warn('Dashboard element #sln-reports-dashboard not found!');
        }
    });

    // Expose to global scope for external access
    window.SalonDashboard = {
        API: DashboardAPI,
        DateRange: DateRangeManager,
        UI: DashboardUI,
        formatCurrency: formatCurrency,
        formatNumber: formatNumber,
        formatPercentage: formatPercentage
    };

})(jQuery);

