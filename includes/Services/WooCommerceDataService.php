<?php

namespace MFX_Reporting\Services;

/**
 * WooCommerce Data Service
 * Handles fetching and formatting WooCommerce data for reports
 */
class WooCommerceDataService {
    
    /**
     * Get daily orders data for a specific date
     */
    public function getDailyOrdersData($date = null) {
        if (!$date) {
            $date = $this->getYesterdayDate();
        }
        
        // Ensure we have WooCommerce
        if (!function_exists('wc_get_orders')) {
            throw new \Exception('WooCommerce is not active');
        }
        
        // Set date range for the specific day
        $start_date = $date . ' 00:00:00';
        $end_date = $date . ' 23:59:59';
        
        // Get orders for the date
        $orders = wc_get_orders([
            'status' => ['completed', 'processing', 'on-hold'],
            'date_created' => $start_date . '...' . $end_date,
            'limit' => -1, // Get all orders
            'orderby' => 'date',
            'order' => 'ASC'
        ]);
        
        $orders_data = [];
        $total_revenue = 0;
        $order_count = 0;
        
        foreach ($orders as $order) {
            // Skip if this is not a WC_Order (could be refund or other type)
            if (!$order instanceof \WC_Order) {
                continue;
            }
            
            $order_total = $order->get_total();
            $total_revenue += $order_total;
            $order_count++;
            
            // Get order items (products)
            $items = $order->get_items();
            
            foreach ($items as $item) {
                $product = $item->get_product();
                
                $orders_data[] = [
                    'first_name' => $order->get_billing_first_name(),
                    'last_name' => $order->get_billing_last_name(),
                    'email' => $order->get_billing_email(),
                    'order_id' => $order->get_id(),
                    'order_total' => $order_total,
                    'product_id' => $product ? $product->get_id() : '',
                    'product_name' => $product ? $product->get_name() : $item->get_name()
                ];
            }
            
            // If order has no items, still include the order
            if (empty($items)) {
                $orders_data[] = [
                    'first_name' => $order->get_billing_first_name(),
                    'last_name' => $order->get_billing_last_name(),
                    'email' => $order->get_billing_email(),
                    'order_id' => $order->get_id(),
                    'order_total' => $order_total,
                    'product_id' => '',
                    'product_name' => 'No items'
                ];
            }
        }
        
        return [
            'orders' => $orders_data,
            'total_revenue' => $total_revenue,
            'order_count' => $order_count,
            'date' => $date
        ];
    }
    
    /**
     * Format data for Google Sheets export
     * Headers: First Name, Last Name, Email, Order ID, Order Total, Product ID, Product Name
     */
    public function formatForGoogleSheets($orders_data) {
        $formatted_data = [];
        
        // Add header row - exactly as specified
        $formatted_data[] = [
            'First Name',
            'Last Name', 
            'Email',
            'Order ID',
            'Order Total',
            'Product ID',
            'Product Name'
        ];
        
        // Add data rows
        foreach ($orders_data['orders'] as $order) {
            $formatted_data[] = [
                $order['first_name'],
                $order['last_name'],
                $order['email'],
                $order['order_id'],
                '$' . number_format($order['order_total'], 2),
                $order['product_id'],
                $order['product_name']
            ];
        }
        
        return $formatted_data;
    }
    
    /**
     * Get yesterday's date in Y-m-d format
     */
    public function getYesterdayDate() {
        return date('Y-m-d', strtotime('-1 day'));
    }
    
    /**
     * Get comprehensive report data for a date range
     */
    public function getReportData($period = 'daily', $date = null) {
        if (!$date) {
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
            'rolling_ltv' => $this->getRollingLTV()
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
            case 'daily':
                $start_date = $date . ' 00:00:00';
                break;
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
            if ($subscription->has_trial()) {
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
            if (!$subscription->has_trial() || $subscription->get_trial_end_date() < time()) {
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
        
        return $formatted_data;
    }
}
