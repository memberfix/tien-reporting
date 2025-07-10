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
}
