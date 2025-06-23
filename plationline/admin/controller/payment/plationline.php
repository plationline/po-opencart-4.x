<?php

namespace Opencart\Admin\Controller\Extension\PlatiOnline\Payment;

use Opencart\System\Library\Extension\PlatiOnline\PO5;

class PlatiOnline extends \Opencart\System\Engine\Controller
{
    public function index(): void
    {
        $this->load->language('extension/plationline/payment/plationline');
        $this->document->setTitle($this->language->get('heading_title') . ' - ' . $this->language->get('text_PO_version'));
        $this->load->model('setting/setting');

        $data['breadcrumbs'] = [];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment')
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/plationline/payment/plationline', 'user_token=' . $this->session->data['user_token'])
        ];

        $data['save'] = $this->url->link('extension/plationline/payment/plationline.save', 'user_token=' . $this->session->data['user_token']);
        $data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment');

        $data['payment_plationline_f_login_ron'] = $this->config->get('payment_plationline_f_login_ron');
        $data['payment_plationline_f_login_eur'] = $this->config->get('payment_plationline_f_login_eur');
        $data['payment_plationline_f_login_usd'] = $this->config->get('payment_plationline_f_login_usd');

        $data['payment_plationline_title'] = [];

        $languages = $this->model_localisation_language->getLanguages();

        foreach ($languages as $language) {
            $data['payment_plationline_title'][$language['language_id']] = $this->config->get('payment_plationline_title_' . $language['language_id']);
        }

        $data['payment_plationline_description'] = [];

        foreach ($languages as $language) {
            $data['payment_plationline_description'][$language['language_id']] = $this->config->get('payment_plationline_description_' . $language['language_id']);
        }

        $data['languages'] = $languages;

        $data['payment_plationline_show_logos'] = $this->config->get('payment_plationline_show_logos');
        $data['payment_plationline_rsa_auth'] = $this->config->get('payment_plationline_rsa_auth');
        $data['payment_plationline_rsa_itsn'] = $this->config->get('payment_plationline_rsa_itsn');
        $data['payment_plationline_iv_auth'] = $this->config->get('payment_plationline_iv_auth');
        $data['payment_plationline_iv_itsn'] = $this->config->get('payment_plationline_iv_itsn');
        $data['payment_plationline_relay_method'] = $this->config->get('payment_plationline_relay_method');
        $data['payment_plationline_itsn_method'] = $this->config->get('payment_plationline_itsn_method');
        $data['payment_plationline_test'] = $this->config->get('payment_plationline_test');

        $this->load->language('extension/payment/plationline');

        $data['po_order_statuses']['payment_plationline_order_status_pending']['name'] = $this->language->get('entry_order_status_pending');
        $data['po_order_statuses']['payment_plationline_order_status_pending']['value'] = $data['payment_plationline_order_status_pending'] = $this->config->get('payment_plationline_order_status_pending');

        $data['po_order_statuses']['payment_plationline_order_status_approved']['name'] = $this->language->get('entry_order_status_approved');
        $data['po_order_statuses']['payment_plationline_order_status_approved']['value'] = $data['payment_plationline_order_status_approved'] = $this->config->get('payment_plationline_order_status_approved');

        $data['po_order_statuses']['payment_plationline_order_status_on_hold']['name'] = $this->language->get('entry_order_status_on_hold');
        $data['po_order_statuses']['payment_plationline_order_status_on_hold']['value'] = $data['payment_plationline_order_status_on_hold'] = $this->config->get('payment_plationline_order_status_on_hold');

        $data['po_order_statuses']['payment_plationline_order_status_decline']['name'] = $this->language->get('entry_order_status_decline');
        $data['po_order_statuses']['payment_plationline_order_status_decline']['value'] = $data['payment_plationline_order_status_decline'] = $this->config->get('payment_plationline_order_status_decline');

        $data['po_order_statuses']['payment_plationline_order_status_error']['name'] = $this->language->get('entry_order_status_error');
        $data['po_order_statuses']['payment_plationline_order_status_error']['value'] = $data['payment_plationline_order_status_error'] = $this->config->get('payment_plationline_order_status_error');

        $data['po_order_statuses']['payment_plationline_order_status_settled']['name'] = $this->language->get('entry_order_status_settled');
        $data['po_order_statuses']['payment_plationline_order_status_settled']['value'] = $data['payment_plationline_order_status_settled'] = $this->config->get('payment_plationline_order_status_settled');

        $data['po_order_statuses']['payment_plationline_order_status_pending_settled']['name'] = $this->language->get('entry_order_status_pending_settled');
        $data['po_order_statuses']['payment_plationline_order_status_pending_settled']['value'] = $data['payment_plationline_order_status_pending_settled'] = $this->config->get('payment_plationline_order_status_pending_settled');

        $data['po_order_statuses']['payment_plationline_order_status_credited']['name'] = $this->language->get('entry_order_status_credited');
        $data['po_order_statuses']['payment_plationline_order_status_credited']['value'] = $data['payment_plationline_order_status_credited'] = $this->config->get('payment_plationline_order_status_credited');

        $data['po_order_statuses']['payment_plationline_order_status_pending_credited']['name'] = $this->language->get('entry_order_status_pending_credited');
        $data['po_order_statuses']['payment_plationline_order_status_pending_credited']['value'] = $data['payment_plationline_order_status_pending_credited'] = $this->config->get('payment_plationline_order_status_pending_credited');

        $data['po_order_statuses']['payment_plationline_order_status_voided']['name'] = $this->language->get('entry_order_status_voided');
        $data['po_order_statuses']['payment_plationline_order_status_voided']['value'] = $data['payment_plationline_order_status_voided'] = $this->config->get('payment_plationline_order_status_voided');

        $data['po_order_statuses']['payment_plationline_order_status_pending_voided']['name'] = $this->language->get('entry_order_status_pending_voided');
        $data['po_order_statuses']['payment_plationline_order_status_pending_voided']['value'] = $data['payment_plationline_order_status_pending_voided'] = $this->config->get('payment_plationline_order_status_pending_voided');

        $data['po_order_statuses']['payment_plationline_order_status_cbk']['name'] = $this->language->get('entry_order_status_cbk');
        $data['po_order_statuses']['payment_plationline_order_status_cbk']['value'] = $data['payment_plationline_order_status_cbk'] = $this->config->get('payment_plationline_order_status_cbk');

        $data['po_order_statuses']['payment_plationline_order_status_expired']['name'] = $this->language->get('entry_order_status_expired');
        $data['po_order_statuses']['payment_plationline_order_status_expired']['value'] = $data['payment_plationline_order_status_expired'] = $this->config->get('payment_plationline_order_status_expired');

        $this->load->model('localisation/order_status');

        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();
        $data['payment_plationline_geo_zone_id'] = $this->config->get('payment_plationline_geo_zone_id');

        $this->load->model('localisation/geo_zone');

        $data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();
        $data['payment_plationline_status'] = $this->config->get('payment_plationline_status');
        $data['payment_plationline_sort_order'] = $this->config->get('payment_plationline_sort_order');
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $data['is_secure'] = $_SERVER['HTTPS'];
        $this->response->setOutput($this->load->view('extension/plationline/payment/plationline', $data));
    }

    public function save(): void
    {
        $this->load->language('extension/plationline/payment/plationline');

        $json = [];

        if (!$this->user->hasPermission('modify', 'extension/plationline/payment/plationline')) {
            $json['error'] = $this->language->get('error_permission');
        }

        $languages = $this->model_localisation_language->getLanguages();

        foreach ($languages as $language) {
            if (empty($this->request->post['payment_plationline_title_' . $language['language_id']])) {
                $json['error']['title_' . $language['language_id']] = $this->language->get('error_required');
            }
            if (empty($this->request->post['payment_plationline_description_' . $language['language_id']])) {
                $json['error']['description_' . $language['language_id']] = $this->language->get('error_required');
            }
            if (empty($this->request->post['payment_plationline_f_login_ron'])) {
                $json['error']['f_login_ron'] = $this->language->get('error_required');
            }
            if (empty($this->request->post['payment_plationline_rsa_auth'])) {
                $json['error']['rsa_auth'] = $this->language->get('error_required');
            }
            if (empty($this->request->post['payment_plationline_rsa_itsn'])) {
                $json['error']['rsa_itsn'] = $this->language->get('error_required');
            }
            if (empty($this->request->post['payment_plationline_iv_auth'])) {
                $json['error']['iv_auth'] = $this->language->get('error_required');
            }
            if (empty($this->request->post['payment_plationline_iv_itsn'])) {
                $json['error']['iv_itsn'] = $this->language->get('error_required');
            }
        }

        if (!$json) {
            $this->load->model('setting/setting');
            $this->model_setting_setting->editSetting('payment_plationline', $this->request->post);
            $json['success'] = $this->language->get('text_success');
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function install(): void
    {
        $this->createOrderStatuses();
        $this->addEvents();
    }

    private function get_all_po_statuses(): array
    {
        $this->load->language('extension/payment/plationline');
        $data = array();
        $data['entry_order_status_pending'] = $this->language->get('entry_order_status_pending');
        $data['entry_order_status_approved'] = $this->language->get('entry_order_status_approved');
        $data['entry_order_status_on_hold'] = $this->language->get('entry_order_status_on_hold');
        $data['entry_order_status_decline'] = $this->language->get('entry_order_status_decline');
        $data['entry_order_status_error'] = $this->language->get('entry_order_status_error');
        $data['entry_order_status_settled'] = $this->language->get('entry_order_status_settled');
        $data['entry_order_status_pending_settled'] = $this->language->get('entry_order_status_pending_settled');
        $data['entry_order_status_credited'] = $this->language->get('entry_order_status_credited');
        $data['entry_order_status_pending_credited'] = $this->language->get('entry_order_status_pending_credited');
        $data['entry_order_status_voided'] = $this->language->get('entry_order_status_voided');
        $data['entry_order_status_pending_voided'] = $this->language->get('entry_order_status_pending_voided');
        $data['entry_order_status_cbk'] = $this->language->get('entry_order_status_cbk');
        $data['entry_order_status_expired'] = $this->language->get('entry_order_status_expired');
        return $data;
    }

    private function createOrderStatuses()
    {
        $data = array();
        $lang_id = (int)$this->config->get('config_language_id');

        $this->load->model('localisation/order_status');
        $this->load->language('extension/plationline/payment/plationline');
        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

        $os = $this->get_all_po_statuses();
        $data = array_merge($data, $os);

        $raw_order_status_data[] = array('order_status' => array($lang_id => array('name' => $this->language->get('entry_order_status_pending'))));
        $raw_order_status_data[] = array('order_status' => array($lang_id => array('name' => $this->language->get('entry_order_status_approved'))));
        $raw_order_status_data[] = array('order_status' => array($lang_id => array('name' => $this->language->get('entry_order_status_on_hold'))));
        $raw_order_status_data[] = array('order_status' => array($lang_id => array('name' => $this->language->get('entry_order_status_decline'))));
        $raw_order_status_data[] = array('order_status' => array($lang_id => array('name' => $this->language->get('entry_order_status_error'))));
        $raw_order_status_data[] = array('order_status' => array($lang_id => array('name' => $this->language->get('entry_order_status_settled'))));
        $raw_order_status_data[] = array('order_status' => array($lang_id => array('name' => $this->language->get('entry_order_status_pending_settled'))));
        $raw_order_status_data[] = array('order_status' => array($lang_id => array('name' => $this->language->get('entry_order_status_credited'))));
        $raw_order_status_data[] = array('order_status' => array($lang_id => array('name' => $this->language->get('entry_order_status_pending_credited'))));
        $raw_order_status_data[] = array('order_status' => array($lang_id => array('name' => $this->language->get('entry_order_status_voided'))));
        $raw_order_status_data[] = array('order_status' => array($lang_id => array('name' => $this->language->get('entry_order_status_pending_voided'))));
        $raw_order_status_data[] = array('order_status' => array($lang_id => array('name' => $this->language->get('entry_order_status_cbk'))));
        $raw_order_status_data[] = array('order_status' => array($lang_id => array('name' => $this->language->get('entry_order_status_expired'))));

        foreach ($raw_order_status_data as $order_status_data) {
            $status_already_exists = false;
            foreach ($data['order_statuses'] as $existingOrderStatus) {
                if ($existingOrderStatus['name'] == $order_status_data['order_status'][$lang_id]['name']) {
                    $status_already_exists = true;
                    break;
                }
            }
            if (!$status_already_exists) {
                $this->model_localisation_order_status->addOrderStatus($order_status_data);
            }
        }
    }

    private function addEvents()
    {
        $this->load->model('setting/event');
        $event_data = [
            'code' => 'plationline_add_admin_functionality_after_order_history', // Unique identifier for your event
            'description' => 'Adds custom admin functionality for transaction remote actions',
            'trigger' => 'admin/view/sale/order_info', // The event to hook into
            'action' => 'extension/plationline/event/add_admin_functionality.afterOrderHistory', // Your event handler method
            'status' => true, // Enable the event
            'sort_order' => 1 // Order in which events are executed for the same trigger
        ];
        $this->model_setting_event->addEvent($event_data);
    }

    public function uninstall(): void
    {
        $this->load->model('setting/event');
        $this->model_setting_event->deleteEventByCode('plationline_add_admin_functionality_after_order_history');
    }

    public function query()
    {
        include_once(DIR_EXTENSION . 'plationline/system/library/PO5.php');
        $po = new PO5();

        $this->load->model('sale/order');
        $this->load->model('localisation/order_status');
        $this->load->language('extension/plationline/payment/plationline');

        $order_info = $this->model_sale_order->getOrder((int)$this->request->post('order_id'));

        $po->f_login = $this->config->get("payment_plationline_f_login_" . strtolower($order_info['currency_code']));
        $po->setRSAKeyEncrypt($this->config->get("payment_plationline_rsa_auth"));
        $po->setIV($this->config->get("payment_plationline_iv_auth"));

        $f_request['f_website'] = str_replace('www.', '', $_SERVER['SERVER_NAME']);
        $f_request['f_order_number'] = $order_info['order_id'];
        $f_request['x_trans_id'] = $order_info['transaction_id']; // transaction ID

        $response_query = $po->query($f_request, 0);

        if ($po->get_xml_tag_content($response_query, 'PO_ERROR_CODE') == 1) {
            echo $po->get_xml_tag_content($response_query, 'PO_ERROR_REASON');
        } else {
            $o = $po->get_xml_tag($response_query, 'ORDER');
            $tranzaction = $po->get_xml_tag($o, 'TRANZACTION');
            $starefin1 = $po->get_xml_tag_content($po->get_xml_tag($tranzaction, 'STATUS_FIN1'), 'CODE');
            $starefin2 = $po->get_xml_tag_content($po->get_xml_tag($tranzaction, 'STATUS_FIN2'), 'CODE');

            switch ($starefin1) {
                case '13':
                    $order_status_id = $this->config->get('payment_plationline_order_status_on_hold');
                    break;
                case '2':
                    $order_status_id = $this->config->get('payment_plationline_order_status_approved');
                    break;
                case '8':
                    $order_status_id = $this->config->get('payment_plationline_order_status_decline');
                    break;
                case '3':
                    $order_status_id = $this->config->get('payment_plationline_order_status_pending_settled');
                    break;
                case '5':
                    //$starefin = 'Incasata';
                    /* Verify X_STARE_FIN2 status*/
                    switch ($starefin2) {
                        case '1':
                            $order_status_id = $this->config->get('payment_plationline_order_status_pending_credited');
                            break;
                        case '2':
                            $order_status_id = $this->config->get('payment_plationline_order_status_credited');
                            break;
                        case '3':
                            $order_status_id = $this->config->get('payment_plationline_order_status_cbk');
                            break;
                        case '4':
                            $order_status_id = $this->config->get('payment_plationline_order_status_settled');
                            break;
                    }
                    break;
                case '6':
                    $order_status_id = $this->config->get('payment_plationline_order_status_pending_voided');
                    break;
                case '7':
                    $order_status_id = $this->config->get('payment_plationline_order_status_voided');
                    break;
                case '9':
                    $order_status_id = $this->config->get('payment_plationline_order_status_expired');
                    break;
                case '10':
                case '16':
                case '17':
                    $order_status_id = $this->config->get('payment_plationline_order_status_error');
                    break;
                case '1':
                    $order_status_id = $this->config->get('payment_plationline_order_status_pending');
                    break;
                default:
                    $order_status_id = -1;
            }

            $order_status_info = $this->model_localisation_order_status->getOrderStatus($order_status_id);

            echo $this->language->get('text_order_status_query') . $order_status_info['name'];
        }
        die();
    }

    public function void()
    {
        include_once(DIR_EXTENSION . 'plationline/system/library/PO5.php');
        $po = new PO5();

        $this->load->model('sale/order');
        $this->load->model('localisation/order_status');
        $this->load->language('extension/plationline/payment/plationline');

        $order_info = $this->model_sale_order->getOrder((int)$this->request->post('order_id'));

        $po->f_login = $this->config->get("payment_plationline_f_login_" . strtolower($order_info['currency_code']));
        $po->setRSAKeyEncrypt($this->config->get("payment_plationline_rsa_auth"));
        $po->setIV($this->config->get("payment_plationline_iv_auth"));

        $f_request['f_website'] = str_replace('www.', '', $_SERVER['SERVER_NAME']);
        $f_request['f_order_number'] = $order_info['order_id'];
        $f_request['x_trans_id'] = $order_info['transaction_id']; // transaction ID

        $response_void = $po->void($f_request, 7);

        if ($po->get_xml_tag_content($response_void, 'PO_ERROR_CODE') == 1) {
            $message = $po->get_xml_tag_content($response_void, 'PO_ERROR_REASON');
        } else {
            switch ($po->get_xml_tag_content($response_void, 'X_RESPONSE_CODE')) {
                case '7':
                    $message = 'Successfully requested <b>VOID</b> for Plationline Transaction ID: <b>' . $order_info['transaction_id'] . '</b>';
                    break;
                case '10':
                    $message = 'Requested <b>VOID</b> for Plationline Transaction ID: <b>' . $order_info['transaction_id'] . '</b> but got error <b>10</b>';
                    break;
            }
        }
        echo $message;
        die();
    }

    public function settle()
    {
        include_once(DIR_EXTENSION . 'plationline/system/library/PO5.php');
        $po = new PO5();

        $this->load->model('sale/order');
        $this->load->model('localisation/order_status');
        $this->load->language('extension/plationline/payment/plationline');

        $order_info = $this->model_sale_order->getOrder((int)$this->request->post('order_id'));

        $po->f_login = $this->config->get("payment_plationline_f_login_" . strtolower($order_info['currency_code']));
        $po->setRSAKeyEncrypt($this->config->get("payment_plationline_rsa_auth"));
        $po->setIV($this->config->get("payment_plationline_iv_auth"));

        $f_request['f_website'] = str_replace('www.', '', $_SERVER['SERVER_NAME']);
        $f_request['f_order_number'] = $order_info['order_id'];
        $f_request['x_trans_id'] = $order_info['transaction_id'];
        $f_request['f_shipping_company'] = '-';
        $f_request['f_awb'] = '-';

        $response_settle = $po->settle($f_request, 3);

        if ($po->get_xml_tag_content($response_settle, 'PO_ERROR_CODE') == 1) {
            $message = $po->get_xml_tag_content($response_settle, 'PO_ERROR_REASON');
        } else {
            switch ($po->get_xml_tag_content($response_settle, 'X_RESPONSE_CODE')) {
                case '3':
                    $message = 'Successfully requested <b>SETTLE</b> for Plationline Transaction ID: <b>' . $order_info['transaction_id'] . '</b>';
                    break;
                case '10':
                    $message = 'Requested <b>SETTLE</b> for Plationline Transaction ID: <b>' . $order_info['transaction_id'] . '</b> but got error <b>10</b>';
                    break;
            }
        }
        echo $message;
        die();
    }

    public function refund()
    {
        include_once(DIR_EXTENSION . 'plationline/system/library/PO5.php');
        $po = new PO5();

        $this->load->model('sale/order');
        $this->load->model('localisation/order_status');
        $this->load->language('extension/plationline/payment/plationline');

        $order_info = $this->model_sale_order->getOrder((int)$this->request->post('order_id'));

        $po->f_login = $this->config->get("payment_plationline_f_login_" . strtolower($order_info['currency_code']));
        $po->setRSAKeyEncrypt($this->config->get("payment_plationline_rsa_auth"));
        $po->setIV($this->config->get("payment_plationline_iv_auth"));

        $f_request['f_website'] = str_replace('www.', '', $_SERVER['SERVER_NAME']);
        $f_request['f_order_number'] = $order_info['order_id'];
        $f_request['x_trans_id'] = $order_info['transaction_id'];
        $f_request['f_amount'] = (float)$this->request->post('amount');

        $response_refund = $po->refund($f_request, 1);

        if ($po->get_xml_tag_content($response_refund, 'PO_ERROR_CODE') == 1) {
            $message = $po->get_xml_tag_content($response_refund, 'PO_ERROR_REASON');
        } else {
            switch ($po->get_xml_tag_content($response_refund, 'X_RESPONSE_CODE')) {
                case '1':
                    $message = 'Successfully requested <b>REFUND</b> for Plationline Transaction ID: <b>' . $order_info['transaction_id'] . '</b>';
                    break;
                case '10':
                    $message = 'Requested <b>REFUND</b> for Plationline Transaction ID: <b>' . $order_info['transaction_id'] . '</b> but got error <b>10</b>';
                    break;
            }
        }
        echo $message;
        die();
    }
}
