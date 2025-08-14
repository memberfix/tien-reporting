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
     * Get comprehensive report data for a date range
     */
    public function getReportData($period = 'weekly', $date = null) {
        if (empty($date)) {
            $date = $this->getYesterdayDate();
        }
        
        // Ensure we have WooCommerce
        if (!function_exists('wc_get_orders')) {
            throw new \Exception('WooCommerce is not active');
        }
        
        // Calculate date range based on period
        $date_range = $this->getDateRange($period, $date);
        
        // Get all the metrics
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
            'net_paid_subscriber_growth' => 0, // Calculated below
            'rolling_ltv' => $this->getRollingLTV(),
            'detailed_orders' => $this->getDetailedOrders($date_range),
            'detailed_cancellations' => $this->getDetailedCancellations($date_range)
        ];
        
        // Calculate net paid subscriber growth
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
                $start_date = date('Y-m-d', strtotime($date . ' -29 days')) . ' 00:00:00';
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
            'status' => ['completed', 'processing', 'on-hold'],
            'date_created' => $date_range['start'] . '...' . $date_range['end'],
            'limit' => -1
        ]);
        
        $gross_revenue = 0;
        
        foreach ($orders as $order) {
            if (!$order instanceof \WC_Order) {
                continue;
            }
            
            // Get subtotal (excluding shipping and taxes)
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
            'status' => ['completed', 'processing', 'on-hold'],
            'date_created' => $date_range['start'] . '...' . $date_range['end'],
            'limit' => -1
        ]);
        
        $total_discounts = 0;
        
        foreach ($orders as $order) {
            if (!$order instanceof \WC_Order) {
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
            'type' => 'shop_order_refund',
            'date_created' => $date_range['start'] . '...' . $date_range['end'],
            'limit' => -1
        ]);
        
        $total_refunds = 0;
        
        foreach ($refunds as $refund) {
            if (!$refund instanceof \WC_Order_Refund) {
                continue;
            }
            
            $total_refunds += abs($refund->get_amount());
        }
        
        return $total_refunds;
    }
    
    /**
     * Get number of free trial subscriptions started
     */
    private function getTrialsStarted($date_range) {
        // Check if WooCommerce Subscriptions is active
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
            if ($this->subscriptionHasTrial($subscription)) {
                $trial_count++;
            }
        }
        
        return $trial_count;
    }
    
    /**
     * Get number of new PAID subscribers (not including free trial people)
     */
    private function getNewMembers($date_range) {
        // Check if WooCommerce Subscriptions is active
        if (!function_exists('wcs_get_subscriptions')) {
            return 0;
        }
        
        $subscriptions = wcs_get_subscriptions([
            'subscription_status' => ['active', 'pending'],
            'date_created' => $date_range['start'] . '...' . $date_range['end'],
            'limit' => -1
        ]);
        
        $new_members = 0;
        
        foreach ($subscriptions as $subscription) {
            // Only count as new member if it's not a trial or if trial period has ended
            if (!$this->subscriptionHasTrial($subscription) || $this->subscriptionTrialHasEnded($subscription)) {
                $new_members++;
            }
        }
        
        return $new_members;
    }
    
    /**
     * Get number of subscription cancellations (people who canceled, not expired)
     */
    private function getCancellations($date_range) {
        // Check if WooCommerce Subscriptions is active
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
            // Check if it was cancelled (not expired)
            if ($subscription->get_status() === 'cancelled') {
                $cancellations++;
            }
        }
        
        return $cancellations;
    }
    
    /**
     * Get Rolling LTV: Average tenure of subscriber in months * subscription price per month
     */
    private function getRollingLTV() {
        // Check if WooCommerce Subscriptions is active
        if (!function_exists('wcs_get_subscriptions')) {
            return 0;
        }
        
        // Get all subscriptions (active and cancelled) to calculate average tenure
        $subscriptions = wcs_get_subscriptions([
            'subscription_status' => ['active', 'cancelled', 'expired'],
            'limit' => -1
        ]);
        
        if (empty($subscriptions)) {
            return 0;
        }
        
        $total_tenure_months = 0;
        $total_monthly_revenue = 0;
        $subscription_count = 0;
        
        foreach ($subscriptions as $subscription) {
            $start_date = $subscription->get_date_created();
            $end_date = $subscription->get_status() === 'active' ? current_time('timestamp') : $subscription->get_date_modified();
            
            if ($start_date && $end_date) {
                $tenure_months = (strtotime($end_date) - strtotime($start_date)) / (30 * 24 * 60 * 60); // Approximate months
                $total_tenure_months += $tenure_months;
                
                // Get monthly subscription price
                $monthly_price = $this->getMonthlySubscriptionPrice($subscription);
                $total_monthly_revenue += $monthly_price;
                
                $subscription_count++;
            }
        }
        
        if ($subscription_count === 0) {
            return 0;
        }
        
        $average_tenure = $total_tenure_months / $subscription_count;
        $average_monthly_price = $total_monthly_revenue / $subscription_count;
        
        return $average_tenure * $average_monthly_price;
    }
    
    /**
     * Get monthly subscription price from a subscription
     */
    private function getMonthlySubscriptionPrice($subscription) {
        $total = $subscription->get_total();
        $period = $subscription->get_billing_period();
        $interval = $subscription->get_billing_interval();
        
        // Convert to monthly price
        switch ($period) {
            case 'day':
                return $total * (30 / $interval);
            case 'week':
                return $total * (4.33 / $interval); // 4.33 weeks per month
            case 'month':
                return $total / $interval;
            case 'year':
                return ($total / $interval) / 12;
            default:
                return $total; // Default to actual price
        }
    }
    
    /**
     * Check if a subscription has a trial period
     */
    private function subscriptionHasTrial($subscription) {
        // Get the subscription's products to check for trial
        $subscription_items = $subscription->get_items();
        
        foreach ($subscription_items as $item) {
            $product_id = $item->get_product_id();
            
            // Check if this product has a trial period using the correct API
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
        // Get trial end date using the correct method
        if (method_exists($subscription, 'get_time')) {
            $trial_end = $subscription->get_time('trial_end');
            if ($trial_end && $trial_end < time()) {
                return true;
            }
        }
        
        // Alternative method: check if subscription has moved past trial
        if (method_exists($subscription, 'get_status') && $subscription->get_status() === 'active') {
            // If it's active and has trial products, trial likely ended
            $has_trial = $this->subscriptionHasTrial($subscription);
            if ($has_trial) {
                // Check if subscription has been active for more than trial period
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
                return 86400; // 24 * 60 * 60
            case 'week':
                return 604800; // 7 * 24 * 60 * 60
            case 'month':
                return 2592000; // 30 * 24 * 60 * 60
            case 'year':
                return 31536000; // 365 * 24 * 60 * 60
            default:
                return 86400; // Default to day
        }
    }
    
    /**
     * Format comprehensive report data for Google Sheets
     */
    public function formatReportForGoogleSheets($report_data) {
        $formatted_data = [];
        
        // Add report header
        $formatted_data[] = [
            ucfirst($report_data['period']) . ' Report - ' . $report_data['date'],
            '',
            '',
            ''
        ];
        
        $formatted_data[] = []; // Empty row
        
        // Add metrics summary
        $formatted_data[] = ['Metric', 'Value', 'Currency', 'Notes'];
        
        $formatted_data[] = [
            'Net Revenue (' . $report_data['period'] . ')',
            '$' . number_format($report_data['net_revenue'], 2),
            'USD',
            'Total sales minus discounts and refunds (excluding shipping/taxes)'
        ];
        
        $formatted_data[] = [
            'Gross Revenue (' . $report_data['period'] . ')',
            '$' . number_format($report_data['gross_revenue'], 2),
            'USD',
            'Total sales excluding shipping and taxes'
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
            'Average tenure (months) × subscription price per month'
        ];
        
        // Add empty row (row 12)
        $formatted_data[] = [];
        
        // Add empty row (row 13)
        $formatted_data[] = [];
        
        // Add detailed orders section starting from row 14
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
        
        // Add individual order details
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
        
        // Add empty row before cancellations
        $formatted_data[] = [];
        
        // Add cancellations section
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
        
        // Add individual cancellation details
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
        $orders = wc_get_orders([
            'status' => ['completed', 'processing', 'on-hold'],
            'date_created' => $date_range['start'] . '...' . $date_range['end'],
            'limit' => -1
        ]);
        
        $detailed_orders = [];
        
        foreach ($orders as $order) {
            if (!$order instanceof \WC_Order) {
                continue;
            }
            
            // Calculate order metrics
            $subtotal = $order->get_subtotal();
            $discount = $order->get_discount_total();
            $refund_total = $order->get_total_refunded();
            $gross_revenue = $subtotal; // Excluding shipping and taxes
            $net_revenue = $gross_revenue - $discount - $refund_total;
            
            // Check if this is a trial order
            $is_trial = $this->isTrialOrder($order);
            
            // Check if this is a new member
            $is_new_member = $this->isNewMemberOrder($order);
            
            // Get product names
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
        
        return $detailed_orders;
    }
    
    /**
     * Get detailed cancellations for the date range
     */
    private function getDetailedCancellations($date_range) {
        // Check if WooCommerce Subscriptions is active
        if (!function_exists('wcs_get_subscriptions')) {
            return [];
        }
        // DEBUG: Log the date range being requested
        DebugLogger::log("getDetailedCancellations called", $date_range);
        
        $subscriptions = wcs_get_subscriptions([
            'subscription_status' => ['cancelled'],
            'date_modified' => $date_range['start'] . '...' . $date_range['end'],
            'limit' => -1
        ]);

        DebugLogger::log("WooCommerce returned " . count($subscriptions) . " cancelled subscriptions");
        
        $detailed_cancellations = [];
        
        foreach ($subscriptions as $subscription) {
            if (!$subscription instanceof \WC_Subscription) {
                continue;
            }
            
            // DEBUG: Log each subscription's details
            $sub_id = $subscription->get_id();
            $date_created = $subscription->get_date_created();
            $date_modified = $subscription->get_date_modified();
            $date_cancelled = $subscription->get_date('cancelled'); // Get actual cancellation date
            
            // Helper function to safely format dates
            $format_date = function($date) {
                if (!$date) return 'N/A';
                if (is_string($date)) return $date;
                if (is_object($date) && method_exists($date, 'date')) return $date->date('Y-m-d H:i:s');
                return 'Unknown format';
            };
            
            DebugLogger::log("Processing subscription $sub_id", [
                'created' => $format_date($date_created),
                'modified' => $format_date($date_modified), 
                'cancelled' => $format_date($date_cancelled),
                'status' => $subscription->get_status()
            ]);
            
            // Calculate days active
            $days_active = $date_created->diff($date_modified)->days;
            
            // Get subscription value
            $subscription_value = $subscription->get_total();
            
            // Get cancellation reason (if available)
            $cancellation_reason = $subscription->get_meta('_cancellation_reason', true);
            if (empty($cancellation_reason)) {
                $cancellation_reason = 'Not specified';
            }
            
            // Get product names
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
        // Check if order has subscription items
        if (!function_exists('wcs_order_contains_subscription')) {
            return false;
        }
        
        if (!wcs_order_contains_subscription($order)) {
            return false;
        }
        
        // Check if any subscription from this order has trial
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
        // Check if order has subscription items
        if (!function_exists('wcs_order_contains_subscription')) {
            return false;
        }
        
        if (!wcs_order_contains_subscription($order)) {
            return false;
        }
        
        // Check if this creates a new paid subscription (not trial)
        $subscriptions = wcs_get_subscriptions_for_order($order);
        foreach ($subscriptions as $subscription) {
            // New member if it's NOT a trial OR if trial has ended
            if (!$this->subscriptionHasTrial($subscription) || $this->subscriptionTrialHasEnded($subscription)) {
                return true;
            }
        }
        
        return false;
    }
}
