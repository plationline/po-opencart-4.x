<?php

namespace Opencart\Admin\Controller\Extension\PlatiOnline\Event;

use Opencart\System\Engine\Controller;

class AddAdminFunctionality extends Controller
{
    public function afterOrderHistory(&$route, &$data, &$output): void
    {
        if ($route === 'sale/order_info') {
            $this->load->language('extension/plationline/payment/plationline');

            $order_info = $this->model_sale_order->getOrder($data['order_id']);

            $data['plationline_payment'] = str_starts_with($data['payment_method_code'], 'plationline.');

            $data['po_total'] = number_format($order_info['total'], 2, '.', '');

            $data['transaction_id'] = $order_info['transaction_id'];

            $new_tab_definition = [
                'code' => 'plationline', // Unique ID for your new tab
                'title' => $this->language->get('heading_title'), // Title from your language file
                'content' => $this->load->view('extension/plationline/module/admin_functionality', $data) // Points to the content div's ID
            ];
            $data['tabs'][] = $new_tab_definition;
        }
    }
}
