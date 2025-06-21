<?php

namespace Opencart\Catalog\Controller\Extension\PlatiOnline\Payment;

use function array_multisort;
use function html_entity_decode;
use function json_encode;
use function strip_tags;
use function strtolower;
use function substr;
use function trim;

class PlatiOnline extends \Opencart\System\Engine\Controller
{
    public function index()
    {
        $this->load->language('extension/plationline/payment/plationline');

        $data['payment_plationline_title'] = nl2br($this->config->get('payment_plationline_title_' . $this->config->get('config_language_id')));
        $data['payment_plationline_description'] = nl2br($this->config->get('payment_plationline_description_' . $this->config->get('config_language_id')));
        $data['payment_plationline_show_logos'] = $this->config->get('payment_plationline_show_logos');

        $data['language'] = $this->config->get('config_language');

        return $this->load->view('extension/plationline/payment/plationline', $data);
    }

    public function callback()
    {
        $this->load->language('extension/plationline/payment/plationline');

        include_once(DIR_EXTENSION . 'plationline/system/library/PO5.php');
        $po = new \Opencart\System\Library\Extension\PlatiOnline\PO5();
        $po->setRSAKeyDecrypt($this->config->get("payment_plationline_rsa_itsn"));
        $po->setIVITSN($this->config->get("payment_plationline_iv_itsn"));

        $po_f_relay_method = $this->config->get("payment_plationline_relay_method");

        $this->cart->clear();
        $this->load->model('checkout/order');

        switch ($po_f_relay_method) {
            case 'PTOR':
                $authorization_response = $po->auth_response($_POST['F_Relay_Message'], $_POST['F_Crypt_Message']);
                $X_RESPONSE_CODE = $po->get_xml_tag_content($authorization_response, 'X_RESPONSE_CODE');
                $order_id = $po->get_xml_tag_content($authorization_response, 'F_ORDER_NUMBER');
                $trans_id = $po->get_xml_tag_content($authorization_response, 'X_TRANS_ID');
                $vX_RESPONSE_REASON_TEXT = $po->get_xml_tag_content($authorization_response, 'X_RESPONSE_REASON_TEXT');

                switch ($X_RESPONSE_CODE) {
                    case '2':
                        //	aprobata
                        $order_info = $this->model_checkout_order->getOrder($order_id);
                        $this->model_checkout_order->addHistory($order_id, $this->config->get('payment_plationline_order_status_approved'), 'PlatiOnline.ro Tranzaction ID: <strong>' . $trans_id . '</strong>', TRUE);
                        // Stock subtraction
                        $order_product_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_product WHERE order_id = '" . (int)$order_id . "'");

                        foreach ($order_product_query->rows as $order_product) {
                            $this->db->query("UPDATE " . DB_PREFIX . "product SET quantity = (quantity - " . (int)$order_product['quantity'] . ") WHERE product_id = '" . (int)$order_product['product_id'] . "' AND subtract = '1'");

                            $order_option_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_option WHERE order_id = '" . (int)$order_id . "' AND order_product_id = '" . (int)$order_product['order_product_id'] . "'");

                            foreach ($order_option_query->rows as $option) {
                                $this->db->query("UPDATE " . DB_PREFIX . "product_option_value SET quantity = (quantity - " . (int)$order_product['quantity'] . ") WHERE product_option_value_id = '" . (int)$option['product_option_value_id'] . "' AND subtract = '1'");
                            }
                        }
                        echo '<html>' . "\n";
                        echo '<head>' . "\n";
                        echo '  <meta http-equiv="Refresh" content="0; url=' . $this->url->link('checkout/success', 'language=' . $this->config->get('config_language'), true) . '">' . "\n";
                        echo '</head>' . "\n";
                        echo '<body>' . "\n";
                        echo '  <p>Please follow <a href="' . $this->url->link('checkout/success', 'language=' . $this->config->get('config_language'), true) . '">link</a>!</p>' . "\n";
                        echo '</body>' . "\n";
                        echo '</html>' . "\n";
                        exit();
                        break;
                    case '13':
                        //	on hold
                        $this->model_checkout_order->addHistory($order_id, $this->config->get('payment_plationline_order_status_on_hold'), 'PlatiOnline.ro Tranzaction ID: <strong>' . $trans_id . '</strong>', TRUE);

                        $data['continue'] = $this->url->link('account/order.info', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token'] . '&order_id=' . $order_id);
                        $data['text_on_hold'] = $this->language->get('text_on_hold');
                        $data['text_title'] = $this->language->get('text_title');
                        $data['text_response'] = $this->language->get('text_response');
                        $data['text_failure_wait'] = sprintf($this->language->get('text_failure_wait'), $this->url->link('account/order.info', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token'] . '&order_id=' . $order_id));

                        return $this->response->setOutput($this->load->view('extension/plationline/payment/plationline_on_hold', $data));
                        break;
                    case '8':
                        //	refuzata
                        $this->model_checkout_order->addHistory($order_id, $this->config->get('payment_plationline_order_status_decline'), 'PlatiOnline.ro Tranzaction ID: <strong>' . $trans_id . '</strong>', TRUE);

                        $aX_RESPONSE_REASON_TEXT = explode('^', $vX_RESPONSE_REASON_TEXT);

                        if (count($aX_RESPONSE_REASON_TEXT) == 2) {
                            $data['text_failure_message'] = sprintf($this->language->get('text_failure_message'), $aX_RESPONSE_REASON_TEXT[0], $aX_RESPONSE_REASON_TEXT[1]);
                        } else {
                            $data['text_failure_message'] = sprintf($this->language->get('text_failure_message'), $aX_RESPONSE_REASON_TEXT[0], $aX_RESPONSE_REASON_TEXT[0]);
                        }

                        $data['text_failure'] = $this->language->get('text_failure');
                        $data['text_title'] = $this->language->get('text_title');
                        $data['text_response'] = $this->language->get('text_response');
                        $data['text_failure_wait'] = sprintf($this->language->get('text_failure_wait'), $this->url->link('account/order.info', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token'] . '&order_id=' . $order_id));

                        $data['continue'] = $this->url->link('account/order.info', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token'] . '&order_id=' . $order_id);

                        return $this->response->setOutput($this->load->view('extension/plationline/payment/plationline_failure', $data));
                        break;
                    case '10':
                    case '16':
                    case '17':
                        //	eroare
                        $order_info = $this->model_checkout_order->getOrder($order_id);
                        if ($order_info['order_status_id'] != $this->config->get('payment_plationline_order_status_approved')) {
                            $this->model_checkout_order->addHistory($order_id, $this->config->get('payment_plationline_order_status_error'), 'PlatiOnline.ro Tranzaction ID: <strong>' . $trans_id . '</strong>', TRUE);
                        }

                        $data['text_error'] = $this->language->get('text_error');
                        $data['text_title'] = $this->language->get('text_title');
                        $data['text_response'] = $this->language->get('text_response');
                        $data['text_failure_wait'] = sprintf($this->language->get('text_failure_wait'), $this->url->link('account/order.info', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token'] . '&order_id=' . $order_id));

                        $data['continue'] = $this->url->link('account/order.info', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token'] . '&order_id=' . $order_id);

                        return $this->response->setOutput($this->load->view('extension/plationline/payment/plationline_error', $data));
                        break;
                }
                break;
            case 'POST_S2S_PO_PAGE':
                $authorization_response = $po->auth_response($_POST['F_Relay_Message'], $_POST['F_Crypt_Message']);
                $X_RESPONSE_CODE = $po->get_xml_tag_content($authorization_response, 'X_RESPONSE_CODE');
                $order_id = $po->get_xml_tag_content($authorization_response, 'F_ORDER_NUMBER');
                $trans_id = $po->get_xml_tag_content($authorization_response, 'X_TRANS_ID');
                $raspuns_procesat = true;

                switch ($X_RESPONSE_CODE) {
                    case '2':
                        //	aprobata
                        $this->model_checkout_order->addHistory($order_id, $this->config->get('payment_plationline_order_status_approved'), 'PlatiOnline.ro Tranzaction ID: <strong>' . $trans_id . '</strong>', TRUE);
                        // Stock subtraction
                        $order_product_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_product WHERE order_id = '" . (int)$order_id . "'");

                        foreach ($order_product_query->rows as $order_product) {
                            $this->db->query("UPDATE " . DB_PREFIX . "product SET quantity = (quantity - " . (int)$order_product['quantity'] . ") WHERE product_id = '" . (int)$order_product['product_id'] . "' AND subtract = '1'");

                            $order_option_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_option WHERE order_id = '" . (int)$order_id . "' AND order_product_id = '" . (int)$order_product['order_product_id'] . "'");

                            foreach ($order_option_query->rows as $option) {
                                $this->db->query("UPDATE " . DB_PREFIX . "product_option_value SET quantity = (quantity - " . (int)$order_product['quantity'] . ") WHERE product_option_value_id = '" . (int)$option['product_option_value_id'] . "' AND subtract = '1'");
                            }
                        }
                        break;
                    case '13':
                        //	on hold
                        $this->model_checkout_order->addHistory($order_id, $this->config->get('payment_plationline_order_status_on_hold'), 'PlatiOnline.ro Tranzaction ID: <strong>' . $trans_id . '</strong>', TRUE);
                        break;
                    case '8':
                        //	refuzata
                        $this->model_checkout_order->addHistory($order_id, $this->config->get('payment_plationline_order_status_decline'), 'PlatiOnline.ro Tranzaction ID: <strong>' . $trans_id . '</strong>', TRUE);
                        break;
                    case '10':
                    case '16':
                    case '17':
                        //	eroare
                        $this->model_checkout_order->addHistory($order_id, $this->config->get('payment_plationline_order_status_error'), 'PlatiOnline.ro Tranzaction ID: <strong>' . $trans_id . '</strong>', TRUE);
                        break;
                    default:
                        $raspuns_procesat = false;
                }
                header('User-Agent:Mozilla/5.0 (Plati Online Relay Response Service)');

                if ($raspuns_procesat) {
                    header('PO_Transaction_Response_Processing: true');
                } else {
                    header('PO_Transaction_Response_Processing: retry');
                }
                break;
            case 'POST_S2S_MT_PAGE':
                $authorization_response = $po->auth_response($_POST['F_Relay_Message'], $_POST['F_Crypt_Message']);
                $X_RESPONSE_CODE = $po->get_xml_tag_content($authorization_response, 'X_RESPONSE_CODE');
                $order_id = $po->get_xml_tag_content($authorization_response, 'F_ORDER_NUMBER');
                $trans_id = $po->get_xml_tag_content($authorization_response, 'X_TRANS_ID');
                $vX_RESPONSE_REASON_TEXT = $po->get_xml_tag_content($authorization_response, 'X_RESPONSE_REASON_TEXT');
                $raspuns_procesat = true;

                switch ($X_RESPONSE_CODE) {
                    case '2':
                        //	aprobata
                        $order_info = $this->model_checkout_order->getOrder($order_id);
                        $this->model_checkout_order->addHistory($order_id, $this->config->get('payment_plationline_order_status_approved'), 'PlatiOnline.ro Tranzaction ID: <strong>' . $trans_id . '</strong>', TRUE);
                        // Stock subtraction
                        $order_product_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_product WHERE order_id = '" . (int)$order_id . "'");

                        foreach ($order_product_query->rows as $order_product) {
                            $this->db->query("UPDATE " . DB_PREFIX . "product SET quantity = (quantity - " . (int)$order_product['quantity'] . ") WHERE product_id = '" . (int)$order_product['product_id'] . "' AND subtract = '1'");

                            $order_option_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_option WHERE order_id = '" . (int)$order_id . "' AND order_product_id = '" . (int)$order_product['order_product_id'] . "'");

                            foreach ($order_option_query->rows as $option) {
                                $this->db->query("UPDATE " . DB_PREFIX . "product_option_value SET quantity = (quantity - " . (int)$order_product['quantity'] . ") WHERE product_option_value_id = '" . (int)$option['product_option_value_id'] . "' AND subtract = '1'");
                            }
                        }
                        echo '<html>' . "\n";
                        echo '<head>' . "\n";
                        echo '  <meta http-equiv="Refresh" content="0; url=' . $this->url->link('checkout/success', 'language=' . $this->config->get('config_language'), true) . '">' . "\n";
                        echo '</head>' . "\n";
                        echo '<body>' . "\n";
                        echo '  <p>Please follow <a href="' . $this->url->link('checkout/success', 'language=' . $this->config->get('config_language'), true) . '">link</a>!</p>' . "\n";
                        echo '</body>' . "\n";
                        echo '</html>' . "\n";
                        exit();
                        break;
                    case '13':
                        //	on hold
                        $this->model_checkout_order->addHistory($order_id, $this->config->get('payment_plationline_order_status_on_hold'), 'PlatiOnline.ro Tranzaction ID: <strong>' . $trans_id . '</strong>', TRUE);

                        $data['continue'] = $this->url->link('account/order.info', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token'] . '&order_id=' . $order_id);
                        $data['text_on_hold'] = $this->language->get('text_on_hold');
                        $data['text_title'] = $this->language->get('text_title');
                        $data['text_response'] = $this->language->get('text_response');
                        $data['text_failure_wait'] = sprintf($this->language->get('text_failure_wait'), $this->url->link('account/order.info', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token'] . '&order_id=' . $order_id));

                        return $this->response->setOutput($this->load->view('extension/plationline/payment/plationline_on_hold', $data));
                        break;
                    case '8':
                        //	refuzata
                        $this->model_checkout_order->addHistory($order_id, $this->config->get('payment_plationline_order_status_decline'), 'PlatiOnline.ro Tranzaction ID: <strong>' . $trans_id . '</strong>', TRUE);

                        $aX_RESPONSE_REASON_TEXT = explode('^', $vX_RESPONSE_REASON_TEXT);

                        if (count($aX_RESPONSE_REASON_TEXT) == 2) {
                            $data['text_failure_message'] = sprintf($this->language->get('text_failure_message'), $aX_RESPONSE_REASON_TEXT[0], $aX_RESPONSE_REASON_TEXT[1]);
                        } else {
                            $data['text_failure_message'] = sprintf($this->language->get('text_failure_message'), $aX_RESPONSE_REASON_TEXT[0], $aX_RESPONSE_REASON_TEXT[0]);
                        }

                        $data['text_failure'] = $this->language->get('text_failure');
                        $data['text_title'] = $this->language->get('text_title');
                        $data['text_response'] = $this->language->get('text_response');
                        $data['text_failure_wait'] = sprintf($this->language->get('text_failure_wait'), $this->url->link('account/order.info', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token'] . '&order_id=' . $order_id));

                        $data['continue'] = $this->url->link('account/order.info', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token'] . '&order_id=' . $order_id);

                        return $this->response->setOutput($this->load->view('extension/plationline/payment/plationline_failure', $data));
                        break;
                    case '10':
                    case '16':
                    case '17':
                        //	eroare
                        $this->model_checkout_order->addHistory($order_id, $this->config->get('payment_plationline_order_status_error'), 'PlatiOnline.ro Tranzaction ID: <strong>' . $trans_id . '</strong>', TRUE);

                        $data['text_error'] = $this->language->get('text_error');
                        $data['text_title'] = $this->language->get('text_title');
                        $data['text_response'] = $this->language->get('text_response');
                        $data['text_failure_wait'] = sprintf($this->language->get('text_failure_wait'), $this->url->link('account/order.info', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token'] . '&order_id=' . $order_id));

                        $data['continue'] = $this->url->link('account/order.info', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token'] . '&order_id=' . $order_id);

                        return $this->response->setOutput($this->load->view('extension/plationline/payment/plationline_error', $data));
                        break;
                    default:
                        $raspuns_procesat = false;
                }
                header('User-Agent:Mozilla/5.0 (Plati Online Relay Response Service)');

                if ($raspuns_procesat) {
                    header('PO_Transaction_Response_Processing: true');
                } else {
                    header('PO_Transaction_Response_Processing: retry');
                }
                break;

            case 'SOAP_PO_PAGE':
                $soap_xml = file_get_contents("php://input");
                $soap_parsed = $po->parse_soap_response($soap_xml);
                $authorization_response = $po->auth_response($po->get_xml_tag_content($soap_parsed, 'F_RELAY_MESSAGE'), $po->get_xml_tag_content($soap_parsed, 'F_CRYPT_MESSAGE'));
                $X_RESPONSE_CODE = $po->get_xml_tag_content($authorization_response, 'X_RESPONSE_CODE');
                $order_id = $po->get_xml_tag_content($authorization_response, 'F_ORDER_NUMBER');
                $trans_id = $po->get_xml_tag_content($authorization_response, 'X_TRANS_ID');
                $raspuns_procesat = true;

                switch ($X_RESPONSE_CODE) {
                    case '2':
                        //	aprobata
                        $this->model_checkout_order->addHistory($order_id, $this->config->get('payment_plationline_order_status_approved'), 'PlatiOnline.ro Tranzaction ID: <strong>' . $trans_id . '</strong>', TRUE);
                        // Stock subtraction
                        $order_product_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_product WHERE order_id = '" . (int)$order_id . "'");

                        foreach ($order_product_query->rows as $order_product) {
                            $this->db->query("UPDATE " . DB_PREFIX . "product SET quantity = (quantity - " . (int)$order_product['quantity'] . ") WHERE product_id = '" . (int)$order_product['product_id'] . "' AND subtract = '1'");

                            $order_option_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_option WHERE order_id = '" . (int)$order_id . "' AND order_product_id = '" . (int)$order_product['order_product_id'] . "'");

                            foreach ($order_option_query->rows as $option) {
                                $this->db->query("UPDATE " . DB_PREFIX . "product_option_value SET quantity = (quantity - " . (int)$order_product['quantity'] . ") WHERE product_option_value_id = '" . (int)$option['product_option_value_id'] . "' AND subtract = '1'");
                            }
                        }
                        break;
                    case '13':
                        //	on hold
                        $this->model_checkout_order->addHistory($order_id, $this->config->get('payment_plationline_order_status_on_hold'), 'PlatiOnline.ro Tranzaction ID: <strong>' . $trans_id . '</strong>', TRUE);
                        break;
                    case '8':
                        //	refuzata
                        $this->model_checkout_order->addHistory($order_id, $this->config->get('payment_plationline_order_status_decline'), 'PlatiOnline.ro Tranzaction ID: <strong>' . $trans_id . '</strong>', TRUE);
                        break;
                    case '10':
                    case '16':
                    case '17':
                        //	eroare
                        $this->model_checkout_order->addHistory($order_id, $this->config->get('payment_plationline_order_status_error'), 'PlatiOnline.ro Tranzaction ID: <strong>' . $trans_id . '</strong>', TRUE);
                        break;
                    default:
                        $raspuns_procesat = false;
                }
                header('User-Agent:Mozilla/5.0 (Plati Online Relay Response Service)');

                if ($raspuns_procesat) {
                    header('PO_Transaction_Response_Processing: true');
                } else {
                    header('PO_Transaction_Response_Processing: retry');
                }
                break;
            case 'SOAP_MT_PAGE':
                $soap_xml = file_get_contents("php://input");
                $soap_parsed = $po->parse_soap_response($soap_xml);
                $authorization_response = $po->auth_response($po->get_xml_tag_content($soap_parsed, 'F_RELAY_MESSAGE'), $po->get_xml_tag_content($soap_parsed, 'F_CRYPT_MESSAGE'));
                $X_RESPONSE_CODE = $po->get_xml_tag_content($authorization_response, 'X_RESPONSE_CODE');
                $order_id = $po->get_xml_tag_content($authorization_response, 'F_ORDER_NUMBER');
                $trans_id = $po->get_xml_tag_content($authorization_response, 'X_TRANS_ID');
                $vX_RESPONSE_REASON_TEXT = $po->get_xml_tag_content($authorization_response, 'X_RESPONSE_REASON_TEXT');
                $raspuns_procesat = true;

                switch ($X_RESPONSE_CODE) {
                    case '2':
                        //	aprobata
                        $order_info = $this->model_checkout_order->getOrder($order_id);
                        $this->model_checkout_order->addHistory($order_id, $this->config->get('payment_plationline_order_status_approved'), 'PlatiOnline.ro Tranzaction ID: <strong>' . $trans_id . '</strong>', TRUE);
                        // Stock subtraction
                        $order_product_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_product WHERE order_id = '" . (int)$order_id . "'");

                        foreach ($order_product_query->rows as $order_product) {
                            $this->db->query("UPDATE " . DB_PREFIX . "product SET quantity = (quantity - " . (int)$order_product['quantity'] . ") WHERE product_id = '" . (int)$order_product['product_id'] . "' AND subtract = '1'");

                            $order_option_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_option WHERE order_id = '" . (int)$order_id . "' AND order_product_id = '" . (int)$order_product['order_product_id'] . "'");

                            foreach ($order_option_query->rows as $option) {
                                $this->db->query("UPDATE " . DB_PREFIX . "product_option_value SET quantity = (quantity - " . (int)$order_product['quantity'] . ") WHERE product_option_value_id = '" . (int)$option['product_option_value_id'] . "' AND subtract = '1'");
                            }
                        }
                        echo '<html>' . "\n";
                        echo '<head>' . "\n";
                        echo '  <meta http-equiv="Refresh" content="0; url=' . $this->url->link('checkout/success', 'language=' . $this->config->get('config_language'), true) . '">' . "\n";
                        echo '</head>' . "\n";
                        echo '<body>' . "\n";
                        echo '  <p>Please follow <a href="' . $this->url->link('checkout/success', 'language=' . $this->config->get('config_language'), true) . '">link</a>!</p>' . "\n";
                        echo '</body>' . "\n";
                        echo '</html>' . "\n";
                        exit();
                        break;
                    case '13':
                        //	on hold
                        $this->model_checkout_order->addHistory($order_id, $this->config->get('payment_plationline_order_status_on_hold'), 'PlatiOnline.ro Tranzaction ID: <strong>' . $trans_id . '</strong>', TRUE);

                        $data['continue'] = $this->url->link('account/order.info', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token'] . '&order_id=' . $order_id);
                        $data['text_on_hold'] = $this->language->get('text_on_hold');
                        $data['text_title'] = $this->language->get('text_title');
                        $data['text_response'] = $this->language->get('text_response');
                        $data['text_failure_wait'] = sprintf($this->language->get('text_failure_wait'), $this->url->link('account/order.info', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token'] . '&order_id=' . $order_id));

                        return $this->response->setOutput($this->load->view('extension/plationline/payment/plationline_on_hold', $data));
                        break;
                    case '8':
                        //	refuzata
                        $this->model_checkout_order->addHistory($order_id, $this->config->get('payment_plationline_order_status_decline'), 'PlatiOnline.ro Tranzaction ID: <strong>' . $trans_id . '</strong>', TRUE);

                        $aX_RESPONSE_REASON_TEXT = explode('^', $vX_RESPONSE_REASON_TEXT);

                        if (count($aX_RESPONSE_REASON_TEXT) == 2) {
                            $data['text_failure_message'] = sprintf($this->language->get('text_failure_message'), $aX_RESPONSE_REASON_TEXT[0], $aX_RESPONSE_REASON_TEXT[1]);
                        } else {
                            $data['text_failure_message'] = sprintf($this->language->get('text_failure_message'), $aX_RESPONSE_REASON_TEXT[0], $aX_RESPONSE_REASON_TEXT[0]);
                        }

                        $data['text_failure'] = $this->language->get('text_failure');
                        $data['text_title'] = $this->language->get('text_title');
                        $data['text_response'] = $this->language->get('text_response');
                        $data['text_failure_wait'] = sprintf($this->language->get('text_failure_wait'), $this->url->link('account/order.info', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token'] . '&order_id=' . $order_id));

                        $data['continue'] = $this->url->link('account/order.info', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token'] . '&order_id=' . $order_id);

                        return $this->response->setOutput($this->load->view('extension/plationline/payment/plationline_failure', $data));
                        break;
                    case '10':
                    case '16':
                    case '17':
                        //	eroare
                        $this->model_checkout_order->addHistory($order_id, $this->config->get('payment_plationline_order_status_error'), 'PlatiOnline.ro Tranzaction ID: <strong>' . $trans_id . '</strong>', TRUE);

                        $data['text_error'] = $this->language->get('text_error');
                        $data['text_title'] = $this->language->get('text_title');
                        $data['text_response'] = $this->language->get('text_response');
                        $data['text_failure_wait'] = sprintf($this->language->get('text_failure_wait'), $this->url->link('account/order.info', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token'] . '&order_id=' . $order_id));

                        $data['continue'] = $this->url->link('account/order.info', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token'] . '&order_id=' . $order_id);

                        return $this->response->setOutput($this->load->view('extension/plationline/payment/plationline_error', $data));
                    default:
                        $raspuns_procesat = false;
                }
                header('User-Agent:Mozilla/5.0 (Plati Online Relay Response Service)');

                if ($raspuns_procesat) {
                    header('PO_Transaction_Response_Processing: true');
                } else {
                    header('PO_Transaction_Response_Processing: retry');
                }
                break;
        }
    }

    public function itsn()
    {
        $this->load->language('extension/plationline/payment/plationline');
        include_once(DIR_EXTENSION . 'plationline/system/library/PO5.php');
        $po = new \Opencart\System\Library\Extension\PlatiOnline\PO5();

        $po->setRSAKeyDecrypt($this->config->get("payment_plationline_rsa_itsn"));
        $po->setIVITSN($this->config->get("payment_plationline_iv_itsn"));
        $po_itsn_method = $this->config->get("payment_plationline_itsn_method");

        switch ($po_itsn_method) {
            case 'POST':
                $call_itsn = $po->itsn($_POST['f_itsn_message'], $_POST['f_crypt_message']);
                $po->f_login = $this->config->get("payment_plationline_f_login_" . \strtolower($po->get_xml_tag_content($call_itsn, 'F_CURRENCY')));
                $po->setRSAKeyEncrypt($this->config->get("payment_plationline_rsa_auth"));
                $po->setIV($this->config->get("payment_plationline_iv_auth"));

                $f_request['f_website'] = $po->f_login;
                $f_request['f_order_number'] = $po->get_xml_tag_content($call_itsn, 'F_ORDER_NUMBER');
                $f_request['x_trans_id'] = $po->get_xml_tag_content($call_itsn, 'X_TRANS_ID');

                $raspuns_itsn = $po->query($f_request, 0);

                if ($po->get_xml_tag_content($raspuns_itsn, 'PO_ERROR_CODE') == 1) {
                    die ($po->get_xml_tag_content($raspuns_itsn, 'PO_ERROR_REASON'));
                }

                $order_itsn = $po->get_xml_tag($raspuns_itsn, 'ORDER');
                $tranzaction = $po->get_xml_tag($order_itsn, 'TRANZACTION');
                $starefin1 = $po->get_xml_tag_content($po->get_xml_tag($tranzaction, 'STATUS_FIN1'), 'CODE');
                $starefin2 = $po->get_xml_tag_content($po->get_xml_tag($tranzaction, 'STATUS_FIN2'), 'CODE');

                $trans_id = $po->get_xml_tag_content($tranzaction, 'X_TRANS_ID');
                $order_id = $po->get_xml_tag_content($order_itsn, 'F_ORDER_NUMBER');

                $this->load->model('checkout/order');

                $stare1 = '<f_response_code>1</f_response_code>';
                switch ($starefin1) {
                    case '13':
                        //$starefin = 'In proces de verificare';
                        $this->model_checkout_order->addHistory($order_id, $this->config->get('payment_plationline_order_status_on_hold'), 'PlatiOnline.ro Tranzaction ID Status Notification: <strong>' . $trans_id . '</strong>', TRUE);
                        break;
                    case '2':
                        //$starefin = 'Autorizata';
                        $order_info = $this->model_checkout_order->getOrder($order_id);
                        if ($order_info['order_status_id'] != $this->config->get('payment_plationline_order_status_approved')) {
                            $this->model_checkout_order->addHistory($order_id, $this->config->get('payment_plationline_order_status_approved'), 'PlatiOnline.ro Tranzaction ID Status Notification: <strong>' . $trans_id . '</strong>', TRUE);
                            // Stock subtraction
                            $order_product_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_product WHERE order_id = '" . (int)$order_id . "'");

                            foreach ($order_product_query->rows as $order_product) {
                                $this->db->query("UPDATE " . DB_PREFIX . "product SET quantity = (quantity - " . (int)$order_product['quantity'] . ") WHERE product_id = '" . (int)$order_product['product_id'] . "' AND subtract = '1'");

                                $order_option_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_option WHERE order_id = '" . (int)$order_id . "' AND order_product_id = '" . (int)$order_product['order_product_id'] . "'");

                                foreach ($order_option_query->rows as $option) {
                                    $this->db->query("UPDATE " . DB_PREFIX . "product_option_value SET quantity = (quantity - " . (int)$order_product['quantity'] . ") WHERE product_option_value_id = '" . (int)$option['product_option_value_id'] . "' AND subtract = '1'");
                                }
                            }
                        }
                        break;
                    case '8':
                        //$starefin = 'Refuzata';
                        $order_info = $this->model_checkout_order->getOrder($order_id);
                        if ($order_info['order_status_id'] != $this->config->get('payment_plationline_order_status_approved')) {
                            $this->model_checkout_order->addHistory($order_id, $this->config->get('payment_plationline_order_status_decline'), 'Tranzactia a fost refuzata. PlatiOnline.ro Tranzaction ID Status Notification: <strong>' . $trans_id . '</strong>', TRUE);
                        }
                        break;
                    case '3':
                        //$starefin = 'In curs de incasare';
                        $this->model_checkout_order->addHistory($order_id, $this->config->get('payment_plationline_order_status_pending_settled'), 'Tranzactie in curs de incasare. PlatiOnline.ro Tranzaction ID Status Notification: <strong>' . $trans_id . '</strong>', TRUE);
                        break;
                    case '5':
                        //$starefin = 'Incasata';
                        /* Verify X_STARE_FIN2 status*/
                        switch ($starefin2) {
                            case '1':
                                //$starefin='In curs de creditare';
                                $this->model_checkout_order->addHistory($order_id, $this->config->get('payment_plationline_order_status_pending_credited'), 'Tranzactie in curs de creditare. PlatiOnline.ro Tranzaction ID Status Notification: <strong>' . $trans_id . '</strong>', TRUE);
                                break;
                            case '2':
                                //$starefin='Creditata';
                                $this->model_checkout_order->addHistory($order_id, $this->config->get('payment_plationline_order_status_credited'), 'Suma a fost creditata inapoi pe cardul clientului. PlatiOnline.ro Tranzaction ID Status Notification: <strong>' . $trans_id . '</strong>', TRUE);
                                break;
                            case '3':
                                //$starefin='Refuz la plata';
                                $this->model_checkout_order->addHistory($order_id, $this->config->get('payment_plationline_order_status_cbk'), 'Suma a fost creditata inapoi pe cardul clientului conform refuzului de plata al acestuia. PlatiOnline.ro Tranzaction ID Status Notification: <strong>' . $trans_id . '</strong>', TRUE);
                                break;
                            case '4':
                                //$starefin='Incasata';
                                $this->model_checkout_order->addHistory($order_id, $this->config->get('payment_plationline_order_status_settled'), 'Suma a fost incasata de pe cardul clientului. PlatiOnline.ro Tranzaction ID Status Notification: <strong>' . $trans_id . '</strong>', TRUE);
                                break;
                        }
                        break;
                    case '6':
                        //$starefin= 'In curs de anulare';
                        $this->model_checkout_order->addHistory($order_id, $this->config->get('payment_plationline_order_status_pending_voided'), 'In curs de anulare. PlatiOnline.ro Tranzaction ID Status Notification: <strong>' . $trans_id . '</strong>', TRUE);
                        break;
                    case '7':
                        //$starefin='Anulata';
                        $this->model_checkout_order->addHistory($order_id, $this->config->get('payment_plationline_order_status_voided'), 'Suma a fost deblocata si este disponibila pe cardul clientului. PlatiOnline.ro Tranzaction ID Status Notification: <strong>' . $trans_id . '</strong>', TRUE);
                        break;
                    case '9':
                        //$starefin='Expirata 30 zile';
                        $this->model_checkout_order->addHistory($order_id, $this->config->get('payment_plationline_order_status_expired'), 'Tranzactie expirata 7 zile. PlatiOnline.ro Tranzaction ID Status Notification: <strong>' . $trans_id . '</strong>', TRUE);
                        break;
                    case '10':
                    case '16':
                    case '17':
                        //$starefin='Eroare';
                        $order_info = $this->model_checkout_order->getOrder($order_id);
                        if ($order_info['order_status_id'] != $this->config->get('payment_plationline_order_status_approved')) {
                            $this->model_checkout_order->addHistory($order_id, $this->config->get('payment_plationline_order_status_error'), 'Eroare autorizare. PlatiOnline.ro Tranzaction ID Status Notification: <strong>' . $trans_id . '</strong>', TRUE);
                        }
                        break;
                    case '1':
                        //$starefin='In curs de autorizare';
                        $this->model_checkout_order->addHistory($order_id, $this->config->get('payment_plationline_order_status_pending'), 'In curs de autorizare. PlatiOnline.ro Tranzaction ID Status Notification: <strong>' . $trans_id . '</strong>', TRUE);
                        break;
                    default:
                        $stare1 = '<f_response_code>0</f_response_code>';
                }

                header("Content-type: text/xml");
                /* send ITSN response */
                $raspuns_xml = '<?xml version="1.0" encoding="UTF-8"?>';
                $raspuns_xml .= '<itsn>';
                $raspuns_xml .= '<x_trans_id>' . $trans_id . '</x_trans_id>';
                $raspuns_xml .= '<merchServerStamp>' . date('Y-m-d\TH:i:sP') . '</merchServerStamp>';
                $raspuns_xml .= $stare1;
                $raspuns_xml .= '</itsn>';

                echo $raspuns_xml;
                die();
        }
    }

    public function confirm()
    {
        $this->load->model('checkout/order');
        $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

        $f_request = [];
        $f_request['f_order_number'] = $order_info['order_id'];
        $f_request['f_amount'] = $this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value'], false);
        $f_request['f_currency'] = $order_info['currency_code'];

        if (strtolower($order_info['currency_code']) === 'lei') {
            $f_request['f_currency'] = 'RON';
        }

        $f_request['f_language'] = substr($this->language->get('code'), 0, 2);

        $permitted_languages = ['en', 'ro', 'it', 'fr', 'de', 'es'];
        if (!in_array($f_request['f_language'], $permitted_languages)) {
            $f_request['f_language'] = 'en';
        }

        $customer_info = [];

        //contact
        if ($order_info['email']) {
            $customer_info['contact']['f_email'] = $order_info['email'];
        }

        if ($order_info['telephone'] && strlen($order_info['telephone']) >= 4) {
            $customer_info['contact']['f_phone'] = $order_info['telephone'];
            $customer_info['contact']['f_mobile_number'] = $order_info['telephone'];
        }
        $customer_info['contact']['f_send_sms'] = 1; // 1 - sms client notification 0 - no notification
        if (substr($order_info['payment_firstname'], 0, 50)) {
            $customer_info['contact']['f_first_name'] = substr($order_info['payment_firstname'], 0, 50);
        } else if (substr($order_info['shipping_firstname'], 0, 50)) {
            $customer_info['contact']['f_first_name'] = substr($order_info['shipping_firstname'], 0, 50);
        }
        if (substr($order_info['payment_lastname'], 0, 50)) {
            $customer_info['contact']['f_last_name'] = substr($order_info['payment_lastname'], 0, 50);
        } elseif (substr($order_info['shipping_lastname'], 0, 50)) {
            $customer_info['contact']['f_last_name'] = substr($order_info['shipping_lastname'], 0, 50);
        }

        //$customer_info['contact']['f_middle_name'] 	 = '';

        //invoice
        $customer_info['invoice']['f_company'] = $order_info['payment_company'] ?: $order_info['shipping_company'] ?: '-';
        $customer_info['invoice']['f_cui'] = '-';
        $customer_info['invoice']['f_reg_com'] = '-';
        $customer_info['invoice']['f_cnp'] = '-';
        $customer_info['invoice']['f_zip'] = $order_info['payment_postcode'] ?: $order_info['shipping_postcode'] ?: '-';
        if ($order_info['payment_country']) {
            $customer_info['invoice']['f_country'] = $order_info['payment_country'];
        } elseif ($order_info['shipping_country']) {
            $customer_info['invoice']['f_country'] = $order_info['shipping_country'];
        }
        if ($order_info['payment_zone']) {
            $customer_info['invoice']['f_state'] = $order_info['payment_zone'];
        } elseif ($order_info['shipping_zone']) {
            $customer_info['invoice']['f_state'] = $order_info['shipping_zone'];
        }
        if ($order_info['payment_city']) {
            $customer_info['invoice']['f_city'] = $order_info['payment_city'];
        } elseif ($order_info['shipping_city']) {
            $customer_info['invoice']['f_city'] = $order_info['shipping_city'];
        }

        if (substr(trim($order_info['payment_address_1'] . ' ' . $order_info['payment_address_2']), 0, 100)) {
            $customer_info['invoice']['f_address'] = substr($order_info['payment_address_1'] . ' ' . $order_info['payment_address_2'], 0, 100);
        } elseif (substr(trim($order_info['shipping_address_1'] . ' ' . $order_info['shipping_address_2']), 0, 100)) {
            $customer_info['invoice']['f_address'] = substr($order_info['shipping_address_1'] . ' ' . $order_info['shipping_address_2'], 0, 100);
        }

        $f_request['customer_info'] = $customer_info;

        $shipping_info = [];

        if ($this->cart->hasShipping()) {
            $shipping_info['same_info_as'] = 0;
            //contact
            if ($order_info['email']) {
                $shipping_info['contact']['f_email'] = $order_info['email'];
            }

            if ($order_info['telephone'] && strlen($order_info['telephone']) >= 4) {
                $shipping_info['contact']['f_phone'] = $order_info['telephone'];
                $shipping_info['contact']['f_mobile_number'] = $order_info['telephone'];
            }
            $shipping_info['contact']['f_send_sms'] = 1; // 1 - sms client notification 0 - no notification
            if (substr($order_info['shipping_firstname'], 0, 50)) {
                $shipping_info['contact']['f_first_name'] = substr($order_info['shipping_firstname'], 0, 50);
            }
            if (substr($order_info['shipping_lastname'], 0, 50)) {
                $shipping_info['contact']['f_last_name'] = substr($order_info['shipping_lastname'], 0, 50);
            }

            //address
            $shipping_info['address']['f_company'] = $order_info['shipping_company'] ?: '-';
            $shipping_info['address']['f_zip'] = $order_info['shipping_postcode'] ?: '-';
            $shipping_info['address']['f_country'] = $order_info['shipping_country'] ?: '-';
            $shipping_info['address']['f_state'] = $order_info['shipping_zone'] ?: '-';
            $shipping_info['address']['f_city'] = $order_info['shipping_city'] ?: '-';

            $shipping_info['address']['f_address'] = substr(trim($order_info['shipping_address_1'] . ' ' . $order_info['shipping_address_2']), 0, 100);
            $f_request['shipping_info'] = $shipping_info;
        } else {
            $shipping_info['same_info_as'] = 1;
        }

        $transaction_relay_response = [];
        $transaction_relay_response['f_relay_response_url'] = $this->url->link('extension/plationline/payment/plationline.callback', 'language=' . $this->config->get('config_language'));
        $transaction_relay_response['f_relay_method'] = $this->config->get('payment_plationline_relay_method');
        $transaction_relay_response['f_post_declined'] = 1;
        $transaction_relay_response['f_relay_handshake'] = 1;
        $f_request['transaction_relay_response'] = $transaction_relay_response;

        $f_request['f_order_cart'] = [];

        foreach ($this->cart->getProducts() as $product) {
            $item = [];
            $item['prodid'] = $product['product_id'];
            $item['name'] = substr(strip_tags(html_entity_decode($product['name'], ENT_QUOTES)), 0, 250);
            $item['description'] = '';
            $item['qty'] = $product['quantity'];
            $item['itemprice'] = $this->currency->format($product['price'], $order_info['currency_code'], $order_info['currency_value'], false);
            $item['vat'] = 0;
            $item['stamp'] = date('Y-m-d');
            $item['prodtype_id'] = 0;
            $f_request['f_order_cart'][] = $item;
        }

        $this->load->model('setting/extension');

        // Totals
        $totals = [];
        $taxes = $this->cart->getTaxes();
        $total = 0;

        $sort_order = [];

        $results = $this->model_setting_extension->getExtensionsByType('total');

        foreach ($results as $key => $value) {
            $sort_order[$key] = $this->config->get('total_' . $value['code'] . '_sort_order');
        }

        array_multisort($sort_order, SORT_ASC, $results);

        foreach ($results as $result) {
            if ($this->config->get('total_' . $result['code'] . '_status')) {
                $this->load->model('extension/' . $result['extension'] . '/total/' . $result['code']);

                // __call can not pass-by-reference so we get PHP to call it as an anonymous function.
                ($this->{'model_extension_' . $result['extension'] . '_total_' . $result['code']}->getTotal)($totals, $taxes, $total);
            }
        }

        $c = 1;
        $coupons = array();
        foreach ($totals as $t) {
            if ($t['code'] === 'tax') {
                $item = array();
                $item['prodid'] = $t['code'];
                $item['name'] = substr(strip_tags(html_entity_decode($t['title'], ENT_QUOTES)), 0, 250);
                $item['description'] = '';
                $item['qty'] = 1;
                $item['itemprice'] = $this->currency->format($t['value'], $order_info['currency_code'], $order_info['currency_value'], false);
                $item['vat'] = 0;
                $item['stamp'] = date('Y-m-d');
                $item['prodtype_id'] = 0;
                $f_request['f_order_cart'][] = $item;
            }

            // daca avem reduceri, le adaugam la final
            if ($t['value'] < 0) {
                $coupon = array();
                $coupon['key'] = $t['code'];
                $coupon['value'] = $this->currency->format(abs($t['value']), $order_info['currency_code'], $order_info['currency_value'], false);
                $coupon['percent'] = 0;
                $coupon['workingname'] = $t['title'];
                $coupon['type'] = 0;
                $coupon['scop'] = 0;
                $coupon['vat'] = 0;
                $coupons['coupon' . $c] = $coupon;
                $c++;
            }
        }

        if (!empty($coupons)) {
            $f_request['f_order_cart'] = array_merge($f_request['f_order_cart'], $coupons);
        }

        if ($this->cart->hasShipping()) {
            $shipping_method = explode('.', $this->session->data['shipping_method']['code']);

            if (isset($shipping_method[0]) && isset($shipping_method[1]) && isset($this->session->data['shipping_methods'][$shipping_method[0]]['quote'][$shipping_method[1]])) {
                $shipping_method_info = $this->session->data['shipping_methods'][$shipping_method[0]]['quote'][$shipping_method[1]];
                $shipping_total = $this->tax->calculate($shipping_method_info['cost'], $shipping_method_info['tax_class_id'], true);
                $shipping_tax = $this->tax->getTax($shipping_method_info['cost'], $shipping_method_info['tax_class_id'], true);

                $shipping = array();
                $shipping['name'] = substr(strip_tags($shipping_method_info['name']), 0, 50);
                $shipping['price'] = $this->currency->format($shipping_total - $shipping_tax, $order_info['currency_code'], $order_info['currency_value'], false);
                $shipping['pimg'] = 0;
                $shipping['vat'] = $this->currency->format($shipping_tax, $order_info['currency_code'], $order_info['currency_value'], false);

                $f_request['f_order_cart']['shipping'] = $shipping;
            }
        }

        $this->load->model('localisation/order_status');
        $this->model_localisation_order_status->getOrderStatuses();

        $f_request['f_order_string'] = 'Comanda nr. ' . $this->session->data['order_id'] . ' pe ' . $this->config->get("config_url");

        include_once(DIR_EXTENSION . 'plationline/system/library/PO5.php');
        $po = new \Opencart\System\Library\Extension\PlatiOnline\PO5();

        $po->f_login = $this->config->get("payment_plationline_f_login_" . strtolower($f_request['f_currency']));
        $f_request['f_website'] = str_replace('www.', '', $_SERVER['SERVER_NAME']);
        $po->setRSAKeyEncrypt($this->config->get("payment_plationline_rsa_auth"));
        $po->setIV($this->config->get("payment_plationline_iv_auth"));
        $po->test_mode = $this->config->get("payment_plationline_test");

        try {
            $auth = $po->auth($f_request, 2);
            $this->model_checkout_order->addHistory($this->session->data['order_id'], $this->config->get('payment_plationline_order_status_pending'));
            $response = ['redirect' => $auth['redirect_url']];
            $this->cart->clear();
        } catch (\Exception $e) {
            $response = ['error' => $e->getMessage(), 'redirect' => $this->url->link('checkout/failure', 'language=' . $this->config->get('config_language'), true)];
        } finally {
            unset($this->session->data['shipping_method']);
            unset($this->session->data['shipping_methods']);
            unset($this->session->data['payment_method']);
            unset($this->session->data['payment_methods']);
            unset($this->session->data['guest']);
            unset($this->session->data['comment']);
            unset($this->session->data['order_id']);
            unset($this->session->data['coupon']);
            unset($this->session->data['reward']);
            unset($this->session->data['voucher']);
            unset($this->session->data['vouchers']);
            unset($this->session->data['totals']);
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($response));
    }
}

?>
