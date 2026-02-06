<?php

/**
 * AJAX Endpoint: Get Fresh Available Dates
 * 
 * Purpose: Fetch current available dates array to prevent staleness
 * 
 * Problem Solved:
 * - Page caching causes old dates to be shown
 * - Long sessions cause dates array to become stale
 * - Bookings made/cancelled don't update existing page's dates
 * 
 * Usage: Called on date step load to refresh dates array
 */
class SLN_Action_Ajax_GetFreshDates extends SLN_Action_Ajax_Abstract
{
    /**
     * Execute AJAX request
     * Returns fresh dates and fullDays arrays
     */
    public function execute()
    {
        // Get booking builder to access current booking context
        $bb = $this->plugin->getBookingBuilder();
        
        // Get customer timezone if applicable
        $timezone = '';
        if ($this->plugin->getSettings()->isDisplaySlotsCustomerTimezone()) {
            $timezone = $bb->get('customer_timezone');
            if (empty($timezone) && isset($_POST['customer_timezone'])) {
                $timezone = sanitize_text_field(wp_unslash($_POST['customer_timezone']));
            }
        }
        
        // Get current intervals with fresh data from cache
        $dateTime = $bb->getDateTime();
        if (!$dateTime) {
            // Fallback to current time if no booking in progress
            $dateTime = new SLN_DateTime();
        }
        
        $intervals = $this->plugin->getIntervals($dateTime);
        
        // Convert to array format with timezone support
        $intervalsArray = $intervals->toArray($timezone);
        
        // Return only the data needed to update calendar
        return array(
            'success' => true,
            'dates' => $intervalsArray['dates'],
            'fullDays' => $intervalsArray['fullDays'],
            'years' => $intervalsArray['years'],
            'months' => $intervalsArray['months'],
            'days' => $intervalsArray['days'],
            'suggestedDate' => $intervalsArray['suggestedDate'],
            'suggestedDay' => $intervalsArray['suggestedDay'],
            'suggestedMonth' => $intervalsArray['suggestedMonth'],
            'suggestedYear' => $intervalsArray['suggestedYear'],
            'universalSuggestedDate' => $intervalsArray['universalSuggestedDate'],
            'timestamp' => current_time('mysql'), // For debugging
        );
    }
}

