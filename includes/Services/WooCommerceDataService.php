<?php

namespace MFX_Reporting\Services;

use MFX_Reporting\Services\DebugLogger;

/**
 * WooCommerce Data Service
 * Handles fetching and formatting WooCommerce data for reports
 */
class WooCommerceDataService {

    /**
     * Get yesterday's date in Y-m-d format
     */
    public function getYesterdayDate() {
        return date('Y-m-d', strtotime('-1 day'));
    }

    /**
     * Safely get billing email from order, handling refund objects
     */
    private function getOrderBillingEmail($order) {
        // Handle refund objects - get parent order
        if ($order instanceof \WC_Order_Refund) {
            $parent_id = $order->get_parent_id();
            if (!$parent_id) {
                return null;
            }
            $order = wc_get_order($parent_id);
            if (!$order) {
                return null;
            }
        }

        return $order->get_billing_email();
    }

    /**
     * Check if an order should be excluded from reports
     * Excludes Janet Dawson (by email or by first name + last name)
     */
    private function shouldExcludeOrder($order) {
        // Handle refund objects - get parent order
        if ($order instanceof \WC_Order_Refund) {
            $parent_id = $order->get_parent_id();
            if (!$parent_id) {
                return true; // Exclude if we can't get parent
            }
            $order = wc_get_order($parent_id);
            if (!$order) {
                return true; // Exclude if we can't get parent
            }
        }

        // Check by email
        $email = $order->get_billing_email();
        if ($email === 'jandawson@gmail.com') {
            return true;
        }

        // Check by first name and last name
        $first_name = trim($order->get_billing_first_name());
        $last_name = trim($order->get_billing_last_name());

        if (strtolower($first_name) === 'janet' && strtolower($last_name) === 'dawson') {
            return true;
        }

        return false;
    }

    /**
     * Check if a subscription should be excluded from reports
     * Excludes Janet Dawson (by email or by first name + last name)
     */
    private function shouldExcludeSubscription($subscription) {
        if (!$subscription instanceof \WC_Subscription) {
            return true;
        }

        // Check by email
        $email = $subscription->get_billing_email();
        if ($email === 'jandawson@gmail.com') {
            return true;
        }

        // Check by first name and last name
        $first_name = trim($subscription->get_billing_first_name());
        $last_name = trim($subscription->get_billing_last_name());

        if (strtolower($first_name) === 'janet' && strtolower($last_name) === 'dawson') {
            return true;
        }

        return false;
    }
    
     /**
     * Get comprehensive report data for a date range
     */
    public function getReportData($period = 'weekly', $date = null) {
        error_log('MFX Reporting: getReportData called - Period: ' . $period . ', Date: ' . ($date ?: 'null'));

        if (empty($date)) {
            $date = $this->getYesterdayDate();
        }

        if (!function_exists('wc_get_orders')) {
            throw new \Exception('WooCommerce is not active');
        }

        $date_range = $this->getDateRange($period, $date);
        error_log('MFX Reporting: Date range - Start: ' . $date_range['start'] . ', End: ' . $date_range['end']);
        
        $metrics = [
            'period' => $period,
            'date' => $date,
            'date_range' => $date_range,
            'net_revenue' => $this->getNetRevenue($date_range),
            'gross_revenue' => $this->getGrossRevenue($date_range),
            'discounts_given' => $this->getDiscountsGiven($date_range),
            'refunds' => $this->getRefunds($date_range),
            'trials_started' => $this->getTrialsStarted($date_range),
            'new_members' => $this->getNewMembers($date_range),
            'cancellations' => $this->getCancellations($date_range),
            'net_paid_subscriber_growth' => 0,
            'rolling_ltv' => $this->getRollingLTV($date_range),
            'trial_order_percentage' => $this->getTrialOrderPercentage($date_range),
            'detailed_orders' => $this->getDetailedOrders($date_range),
            'detailed_cancellations' => $this->getDetailedCancellations($date_range)
        ];
        
        $metrics['net_paid_subscriber_growth'] = $metrics['new_members'] - $metrics['cancellations'];
        
        return $metrics;
    }
    
    /**
     * Get date range based on period type
     */
    private function getDateRange($period, $date) {
        $end_date = $date . ' 23:59:59';
        
        switch ($period) {
            case 'weekly':
                $start_date = date('Y-m-d', strtotime($date . ' -6 days')) . ' 00:00:00';
                break;
            case 'monthly':
                // For monthly reports, always report on the complete month containing the given date
                $refDate = new \DateTime($date);
                $start_date = $refDate->modify('first day of this month')->format('Y-m-d') . ' 00:00:00';
                $refDate = new \DateTime($date); // Reset after modify
                $end_date = $refDate->modify('last day of this month')->format('Y-m-d') . ' 23:59:59';
                break;
            default:
                $start_date = $date . ' 00:00:00';
        }
        
        return [
            'start' => $start_date,
            'end' => $end_date
        ];
    }
    
    /**
     * Get Net Revenue: Total sales (not including shipping or taxes) minus discounts and refunds
     */
    private function getNetRevenue($date_range) {
        $gross_revenue = $this->getGrossRevenue($date_range);
        $discounts = $this->getDiscountsGiven($date_range);
        $refunds = $this->getRefunds($date_range);
        
        return $gross_revenue - $discounts - $refunds;
    }
    
    /**
     * Get Gross Revenue: Total sales, not including shipping or taxes
     */
    private function getGrossRevenue($date_range) {
        $orders = wc_get_orders([
            'status' => ['completed', 'processing', 'on-hold', 'refunded'],
            'date_created' => $date_range['start'] . '...' . $date_range['end'],
            'limit' => -1
        ]);
        
        $gross_revenue = 0;
        
        foreach ($orders as $order) {
            if (!$order instanceof \WC_Order) {
                continue;
            }

            // Exclude Janet Dawson orders
            if ($this->shouldExcludeOrder($order)) {
                continue;
            }

            $subtotal = $order->get_subtotal();
            $gross_revenue += $subtotal;
        }
        
        return $gross_revenue;
    }
    
    /**
     * Get total discounts given in the date range
     */
    private function getDiscountsGiven($date_range) {
        $orders = wc_get_orders([
            'status' => ['completed', 'processing', 'on-hold', 'refunded'],
            'date_created' => $date_range['start'] . '...' . $date_range['end'],
            'limit' => -1
        ]);
        
        $total_discounts = 0;
        
        foreach ($orders as $order) {
            if (!$order instanceof \WC_Order) {
                continue;
            }

            // Exclude Janet Dawson orders
            if ($this->shouldExcludeOrder($order)) {
                continue;
            }

            $total_discounts += $order->get_total_discount();
        }
        
        return $total_discounts;
    }
    
    /**
     * Get total refunds in the date range
     */
    private function getRefunds($date_range) {
        $refunds = wc_get_orders([
            'status' => ['refunded'],
            'date_modified' => $date_range['start'] . '...' . $date_range['end'],
            'limit' => -1
        ]);
        
        $total_refunds = 0;
        
        foreach ($refunds as $refund) { 
            if (!$refund instanceof \WC_Order) {
                continue;
            }
            
            $total_refunds += $refund->get_total_refunded();
        }
        
        return $total_refunds;
    }
    
    /**
     * Get number of free trial subscriptions started
     */
    private function getTrialsStarted($date_range) {
        if (!function_exists('wcs_get_subscriptions')) {
            return 0;
        }
        
        $subscriptions = wcs_get_subscriptions([
            'subscription_status' => ['active', 'pending', 'cancelled', 'on-hold'],
            'date_created' => $date_range['start'] . '...' . $date_range['end'],
            'limit' => -1
        ]);
        
        $trial_count = 0;
        
        foreach ($subscriptions as $subscription) {
            if (!$subscription instanceof \WC_Subscription) {
                continue;
            }
            
            if ($this->subscriptionHasTrial($subscription)) {
                $trial_count++;
            }
        }
        
        return $trial_count;
    }
    
    /**
     * Get number of new paid subscriptions (excludes free trials)
     */
    private function getNewMembers($date_range) {
        if (!function_exists('wcs_get_subscriptions')) {
            return 0;
        }
        
        $subscriptions = wcs_get_subscriptions([
            'subscription_status' => ['active', 'on-hold', 'pending-cancel', 'cancelled', 'expired'],
            'date_created' => $date_range['start'] . '...' . $date_range['end'],
            'limit' => -1
        ]);
        
        $new_members = 0;
        
        foreach ($subscriptions as $subscription) {
            if (!$subscription instanceof \WC_Subscription) {
                continue;
            }
            
            // Skip subscriptions that have a trial period
            if ($this->subscriptionHasTrial($subscription)) {
                continue;
            }
            
            if ($subscription->get_total() > 0 && $subscription->get_status() === 'active') {
                $new_members++;
            }
        }
        
        return $new_members;
    }
    
    /**
     * Get number of subscription cancellations (people who canceled, not expired)
     * Excludes free trial cancellations - only counts paying subscription cancellations
     */
    private function getCancellations($date_range) {
        if (!function_exists('wcs_get_subscriptions')) {
            return 0;
        }

        $subscriptions = wcs_get_subscriptions([
            'subscription_status' => ['cancelled'],
            'date_modified' => $date_range['start'] . '...' . $date_range['end'],
            'limit' => -1
        ]);

        $cancellations = 0;

        foreach ($subscriptions as $subscription) {
            // Exclude Janet Dawson subscriptions
            if ($this->shouldExcludeSubscription($subscription)) {
                continue;
            }

            // Exclude free trial cancellations - only count paying subscriptions
            if ($this->subscriptionHasTrial($subscription)) {
                continue;
            }

            $date_created = $subscription->get_date_created();
            $date_modified = $subscription->get_date_modified();
            $date_cancelled = $subscription->get_date('cancelled');

            $effective_cancel_date = $date_cancelled ?: $date_modified;

            if (!$effective_cancel_date) {
                continue;
            }

            $cancel_timestamp = is_object($effective_cancel_date) && method_exists($effective_cancel_date, 'getTimestamp')
                ? $effective_cancel_date->getTimestamp()
                : strtotime($effective_cancel_date);

            $start_timestamp = strtotime($date_range['start']);
            $end_timestamp = strtotime($date_range['end']);

            if ($cancel_timestamp < $start_timestamp || $cancel_timestamp > $end_timestamp) {
                continue;
            }

            $cancellations++;
        }

        return $cancellations;
    }
    
    /**
     * Get Rolling LTV: Total revenue from active customers divided by number of active customers in date range
     * NOTE: Uses manual filtering because wcs_get_subscriptions() doesn't support date_created filtering reliably
     */
    private function getRollingLTV($date_range) {
        if (!function_exists('wcs_get_subscriptions')) {
            return 0;
        }
        
        // TEST 1: Try WooCommerce date filtering first
        $wc_filtered_subscriptions = wcs_get_subscriptions([
            'subscription_status' => ['active', 'cancelled', 'expired'],
            'date_created' => $date_range['start'] . '...' . $date_range['end'],
            'limit' => -1
        ]);        
        
        // TEST 2: Get all subscriptions for manual filtering
        $all_subscriptions = wcs_get_subscriptions([
            'subscription_status' => ['active', 'cancelled', 'expired'],
            'limit' => -1
        ]);
        
        if (empty($all_subscriptions)) {
            return 0;
        }
        
        $start_timestamp = strtotime($date_range['start']);
        $end_timestamp = strtotime($date_range['end']);
        
        $total_revenue = 0;
        $active_customers = [];
        $manual_filtered_count = 0;
        
        // Manual filtering with detailed logging
        foreach ($all_subscriptions as $subscription) {
            if (!$subscription instanceof \WC_Subscription) {
                continue;
            }
            
            $date_created = $subscription->get_date_created();
            if (!$date_created) {
                continue;
            }
            
            $created_timestamp = is_object($date_created) && method_exists($date_created, 'getTimestamp') 
                ? $date_created->getTimestamp() 
                : strtotime($date_created);
            
            $formatted_date = date('Y-m-d', $created_timestamp);
            
            if ($created_timestamp >= $start_timestamp && $created_timestamp <= $end_timestamp) {
                $manual_filtered_count++;
                
                $customer_id = $subscription->get_customer_id();
                if ($customer_id) {
                    $active_customers[$customer_id] = true;
                }
                
                // Calculate revenue for this subscription
                $monthly_price = $this->getMonthlySubscriptionPrice($subscription);
                $total_revenue += $monthly_price;
            }
        }
        
        $customer_count = count($active_customers);
        $ltv_result = $customer_count > 0 ? round($total_revenue / $customer_count, 2) : 0;
        
        if ($customer_count === 0) {
            return 0;
        }
        
        return $ltv_result;
    }
    
    /**
     * Get monthly subscription price from a subscription
     */
    private function getMonthlySubscriptionPrice($subscription) {
        $total = $subscription->get_total();
        $period = $subscription->get_billing_period();
        $interval = $subscription->get_billing_interval();
        
                switch ($period) {
            case 'day':
                return $total * (30 / $interval);
            case 'week':
                return $total * (4.33 / $interval);             case 'month':
                return $total / $interval;
            case 'year':
                return ($total / $interval) / 12;
            default:
                return $total;         }
    }
    
    /**
     * Check if a subscription has a trial period
     */
    private function subscriptionHasTrial($subscription) {
        $subscription_items = $subscription->get_items();
        
        foreach ($subscription_items as $item) {
            $product_id = $item->get_product_id();
            
            if (class_exists('\WC_Subscriptions_Product') && \WC_Subscriptions_Product::get_trial_length($product_id) > 0) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if a subscription's trial period has ended
     */
    private function subscriptionTrialHasEnded($subscription) {
        if (method_exists($subscription, 'get_time')) {
            $trial_end = $subscription->get_time('trial_end');
            if ($trial_end && $trial_end < time()) {
                return true;
            }
        }
        
        if (method_exists($subscription, 'get_status') && $subscription->get_status() === 'active') {
            $has_trial = $this->subscriptionHasTrial($subscription);
            if ($has_trial) {
                                $date_created = $subscription->get_date_created();
                $subscription_items = $subscription->get_items();
                
                foreach ($subscription_items as $item) {
                    $product_id = $item->get_product_id();
                    
                    if (class_exists('\WC_Subscriptions_Product')) {
                        $trial_length = \WC_Subscriptions_Product::get_trial_length($product_id);
                        $trial_period = \WC_Subscriptions_Product::get_trial_period($product_id);
                        
                        if ($trial_length > 0 && $trial_period) {
                            $trial_end_time = $date_created->getTimestamp() + ($trial_length * $this->getPeriodInSeconds($trial_period));
                            if (time() > $trial_end_time) {
                                return true;
                            }
                        }
                    }
                }
            }
        }
        
        return false;
    }
    
    /**
     * Convert trial period to seconds
     */
    private function getPeriodInSeconds($period) {
        switch ($period) {
            case 'day':
                return 86400;             case 'week':
                return 604800;             case 'month':
                return 2592000;             case 'year':
                return 31536000;             default:
                return 86400;         }
    }
    
    /**
     * Format comprehensive report data for Google Sheets
     */
    public function formatReportForGoogleSheets($report_data) {
        $formatted_data = [];
        
                $formatted_data[] = [
            ucfirst($report_data['period']) . ' Report - ' . $report_data['date'],
            '',
            '',
            ''
        ];
        
        $formatted_data[] = [];         
                $formatted_data[] = ['Metric', 'Value', 'Currency', 'Notes'];
        
        $formatted_data[] = [
            'Net Revenue (' . $report_data['period'] . ')',
            '$' . number_format($report_data['net_revenue'], 2),
            'USD',
            'Total sales excluding discounts, taxes, refunds, and woopayments transaction fees'
        ];
        
        $formatted_data[] = [
            'Gross Revenue (' . $report_data['period'] . ')',
            '$' . number_format($report_data['gross_revenue'], 2),
            'USD',
            'Total sales including shipping, taxes, and discounts (excludes woopayments transaction fees)'
        ];
        
        $formatted_data[] = [
            'Discounts Given (' . $report_data['period'] . ')',
            '$' . number_format($report_data['discounts_given'], 2),
            'USD',
            'Total discounts applied to orders'
        ];
        
        $formatted_data[] = [
            'Refunds (' . $report_data['period'] . ')',
            '$' . number_format($report_data['refunds'], 2),
            'USD',
            'Total amount refunded to customers'
        ];
        
        $formatted_data[] = [
            'Trials Started (' . $report_data['period'] . ')',
            $report_data['trials_started'],
            'Count',
            'Number of free trial subscriptions started'
        ];
        
        $formatted_data[] = [
            'New Members (' . $report_data['period'] . ')',
            $report_data['new_members'],
            'Count',
            'New paid subscribers (excluding free trials)'
        ];
        
        $formatted_data[] = [
            'Cancellations (' . $report_data['period'] . ')',
            $report_data['cancellations'],
            'Count',
            'Number of subscriptions cancelled by customers'
        ];
        
        $formatted_data[] = [
            'Net Paid Subscriber Growth (' . $report_data['period'] . ')',
            $report_data['net_paid_subscriber_growth'],
            'Count',
            'New paid subscribers minus cancellations'
        ];
        
        $formatted_data[] = [
            'Rolling LTV',
            '$' . number_format($report_data['rolling_ltv'], 2),
            'USD',
            'Average revenue per active customer'
        ];
        
        $formatted_data[] = [
            'Trial Order Percentage',
            number_format($report_data['trial_order_percentage'], 2) . '%',
            '',
            'Percentage of orders that are trials'
        ];
        
                $formatted_data[] = [];
        
                $formatted_data[] = [];
        
                $formatted_data[] = [
            'Order ID',
            'Date',
            'Customer',
            'Email',
            'Gross Revenue',
            'Net Revenue',
            'Discounts',
            'Refunds',
            'Is Trial',
            'New Member',
            'Products'
        ];
        
                if (isset($report_data['detailed_orders'])) {
            foreach ($report_data['detailed_orders'] as $order) {
                $formatted_data[] = [
                    $order['order_id'],
                    $order['date'],
                    $order['customer_name'],
                    $order['customer_email'],
                    '$' . number_format($order['gross_revenue'], 2),
                    '$' . number_format($order['net_revenue'], 2),
                    '$' . number_format($order['discounts'], 2),
                    '$' . number_format($order['refunds'], 2),
                    $order['is_trial'] ? 'Yes' : 'No',
                    $order['is_new_member'] ? 'Yes' : 'No',
                    $order['products']
                ];
            }
        }
        
                $formatted_data[] = [];
        
                $formatted_data[] = [
            'CANCELLATIONS',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            ''
        ];
        
        $formatted_data[] = [
            'Subscription ID',
            'Date Cancelled',
            'Customer',
            'Email',
            'Subscription Value',
            'Cancellation Reason',
            'Days Active',
            'Products',
            '',
            '',
            ''
        ];
        
                if (isset($report_data['detailed_cancellations'])) {
            foreach ($report_data['detailed_cancellations'] as $cancellation) {
                $formatted_data[] = [
                    $cancellation['subscription_id'],
                    $cancellation['date_cancelled'],
                    $cancellation['customer_name'],
                    $cancellation['customer_email'],
                    '$' . number_format($cancellation['subscription_value'], 2),
                    $cancellation['cancellation_reason'],
                    $cancellation['days_active'],
                    $cancellation['products'],
                    '',
                    '',
                    ''
                ];
            }
        }
        
        return $formatted_data;
    }
    
    /**
     * Get detailed orders for the date range
     */
    private function getDetailedOrders($date_range) {
        try {
            $regular_orders = wc_get_orders([
                'status' => ['completed', 'processing', 'on-hold'],
                'date_created' => $date_range['start'] . '...' . $date_range['end'],
                'limit' => -1
            ]);

            $refunded_orders = wc_get_orders([
                'status' => ['refunded'],
                'date_modified' => $date_range['start'] . '...' . $date_range['end'],
                'limit' => -1
            ]);

            error_log('MFX Reporting: getDetailedOrders - Regular orders: ' . count($regular_orders) . ', Refunded orders: ' . count($refunded_orders));
        } catch (\Exception $e) {
            error_log('MFX Reporting: getDetailedOrders error - ' . $e->getMessage());
            return [];
        }

        $detailed_orders = [];

        // Process regular orders first
        foreach ($regular_orders as $order) {
            if (!$order instanceof \WC_Order) {
                continue;
            }

            // Exclude Janet Dawson orders
            if ($this->shouldExcludeOrder($order)) {
                continue;
            }

            $gross_revenue = $order->get_total();
            $discount = $order->get_discount_total();
            $refund_total = $order->get_total_refunded();
            $sub_revenue = $order->get_subtotal();
            $net_revenue = $sub_revenue - $discount - $refund_total;

            $is_trial = $this->isTrialOrder($order);

            $is_new_member = $this->isNewMemberOrder($order);

            $products = [];
            foreach ($order->get_items() as $item) {
                $product = $item->get_product();
                if ($product) {
                    $products[] = $product->get_name();
                }
            }

            $detailed_orders[] = [
                'order_id' => $order->get_id(),
                'date' => $order->get_date_created()->date('Y-m-d H:i:s'),
                'customer_name' => trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()),
                'customer_email' => $order->get_billing_email(),
                'gross_revenue' => $gross_revenue,
                'net_revenue' => $net_revenue,
                'discounts' => $discount,
                'refunds' => $refund_total,
                'is_trial' => $is_trial,
                'is_new_member' => $is_new_member,
                'products' => implode(', ', $products)
            ];
        }

        // Process refunded orders separately - show as negative amounts with refund date
        foreach ($refunded_orders as $order) {
            if (!$order instanceof \WC_Order) {
                continue;
            }

            // Exclude Janet Dawson orders
            if ($this->shouldExcludeOrder($order)) {
                continue;
            }

            // Get refund date (use date_modified as the refund date)
            $date_modified = $order->get_date_modified();
            $date_created = $order->get_date_created();

            if ($date_modified && method_exists($date_modified, 'date')) {
                $refund_date = $date_modified->date('Y-m-d H:i:s');
            } elseif ($date_created && method_exists($date_created, 'date')) {
                $refund_date = $date_created->date('Y-m-d H:i:s');
            } else {
                $refund_date = date('Y-m-d H:i:s');
            }

            $refund_total = $order->get_total_refunded();

            $is_trial = $this->isTrialOrder($order);

            $is_new_member = false; // Refunds are not new members

            $products = [];
            foreach ($order->get_items() as $item) {
                $product = $item->get_product();
                if ($product) {
                    $products[] = $product->get_name();
                }
            }

            // Add refund as a negative entry
            $detailed_orders[] = [
                'order_id' => $order->get_id() . ' (REFUND)',
                'date' => $refund_date,
                'customer_name' => trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()),
                'customer_email' => $order->get_billing_email(),
                'gross_revenue' => -$refund_total,
                'net_revenue' => -$refund_total,
                'discounts' => 0,
                'refunds' => $refund_total,
                'is_trial' => $is_trial,
                'is_new_member' => $is_new_member,
                'products' => implode(', ', $products)
            ];
        }

        return $detailed_orders;
    }
    
    /**
     * Get detailed cancellations for the date range
     * Excludes free trial cancellations - only includes paying subscription cancellations
     */
    private function getDetailedCancellations($date_range) {
                if (!function_exists('wcs_get_subscriptions')) {
            return [];
        }

        $subscriptions = wcs_get_subscriptions([
            'subscription_status' => ['cancelled'],
            'date_modified' => $date_range['start'] . '...' . $date_range['end'],
            'limit' => -1
        ]);

        $detailed_cancellations = [];

        foreach ($subscriptions as $subscription) {
            if (!$subscription instanceof \WC_Subscription) {
                continue;
            }

            // Exclude Janet Dawson subscriptions
            if ($this->shouldExcludeSubscription($subscription)) {
                continue;
            }

            // Exclude free trial cancellations - only count paying subscriptions
            if ($this->subscriptionHasTrial($subscription)) {
                continue;
            }

            $date_created = $subscription->get_date_created();
            $date_modified = $subscription->get_date_modified();
            $date_cancelled = $subscription->get_date('cancelled');

            $effective_cancel_date = $date_cancelled ?: $date_modified;

            if (!$effective_cancel_date) {
                continue;
            }

            $cancel_timestamp = is_object($effective_cancel_date) && method_exists($effective_cancel_date, 'getTimestamp')
                ? $effective_cancel_date->getTimestamp()
                : strtotime($effective_cancel_date);

            $start_timestamp = strtotime($date_range['start']);
            $end_timestamp = strtotime($date_range['end']);

            if ($cancel_timestamp < $start_timestamp || $cancel_timestamp > $end_timestamp) {
                continue;
            }
            
            $format_date = function($date) {
                if (!$date) return 'N/A';
                if (is_string($date)) return $date;
                if (is_object($date) && method_exists($date, 'date')) return $date->date('Y-m-d H:i:s');
                return 'Unknown format';
            };
            
            $created_date_obj = $date_created;
            $cancel_date_obj = $effective_cancel_date;
            
            if (is_string($created_date_obj)) {
                $created_date_obj = new \DateTime($created_date_obj);
            }
            if (is_string($cancel_date_obj)) {
                $cancel_date_obj = new \DateTime($cancel_date_obj);
            }
            
            $days_active = 0;
            if ($created_date_obj && $cancel_date_obj && 
                $created_date_obj instanceof \DateTimeInterface && 
                $cancel_date_obj instanceof \DateTimeInterface) {
                $days_active = $created_date_obj->diff($cancel_date_obj)->days;
            }
            
            $subscription_value = $subscription->get_total();
            
            $cancellation_reason = $subscription->get_meta('_cancellation_reason', true);
            if (empty($cancellation_reason)) {
                $cancellation_reason = 'Not specified';
            }
            
            $products = [];
            foreach ($subscription->get_items() as $item) {
                $product = $item->get_product();
                if ($product) {
                    $products[] = $product->get_name();
                }
            }
            
            $detailed_cancellations[] = [
                'subscription_id' => $subscription->get_id(),
                'date_cancelled' => $format_date($date_cancelled),
                'customer_name' => trim($subscription->get_billing_first_name() . ' ' . $subscription->get_billing_last_name()),
                'customer_email' => $subscription->get_billing_email(),
                'subscription_value' => $subscription_value,
                'cancellation_reason' => $cancellation_reason,
                'days_active' => $days_active,
                'products' => implode(', ', $products)
            ];
        }
        
        return $detailed_cancellations;
    }
    
    /**
     * Check if an order is for a trial subscription
     */
    private function isTrialOrder($order) {
        if (!function_exists('wcs_order_contains_subscription')) {
            return false;
        }
        
        if (!wcs_order_contains_subscription($order)) {
            return false;
        }
        
        $subscriptions = wcs_get_subscriptions_for_order($order);
        foreach ($subscriptions as $subscription) {
            if ($this->subscriptionHasTrial($subscription)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if an order represents a new member (paid subscription, not trial)
     */
    private function isNewMemberOrder($order) {
        // Check if this order contains a subscription
        if (!function_exists('wcs_order_contains_subscription')) {
            return false;
        }
        
        if (!wcs_order_contains_subscription($order) || $this->isTrialOrder($order)) {
            return false;
        }
        
        // Get subscriptions for this order
        $subscriptions = wcs_get_subscriptions_for_order($order);

        if (!empty($subscriptions) && $order->get_total() > 0) {
            return true;
        }           
        return false;
    }
    
    /**
     * Get percentage of trial orders
     */
    private function getTrialOrderPercentage($date_range) {
        $orders = wc_get_orders([
            'status' => ['completed', 'processing', 'on-hold'],
            'date_created' => $date_range['start'] . '...' . $date_range['end'],
            'limit' => -1
        ]);
        
        $total_orders = count($orders);
        $trial_orders = 0;
        
        foreach ($orders as $order) {
            if (!$order instanceof \WC_Order) {
                continue;
            }

            // Exclude Janet Dawson orders
            if ($this->shouldExcludeOrder($order)) {
                continue;
            }

            if ($this->isTrialOrder($order)) {
                $trial_orders++;
            }
        }
        
        return $total_orders > 0 ? round(($trial_orders / $total_orders) * 100, 2) : 0;
    }
}