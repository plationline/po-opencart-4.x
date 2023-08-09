<?php

namespace Opencart\Catalog\Model\Extension\PlatiOnline\Payment;

class PlatiOnline extends \Opencart\System\Engine\Model
{
    public function getMethods(array $address): array
    {
        $this->load->language('extension/plationline/payment/plationline');

        if (!$this->config->get('config_checkout_payment_address')) {
            $status = true;
        } elseif (!$this->config->get('payment_plationline_geo_zone_id')) {
            $status = true;
        } else {
            $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "zone_to_geo_zone` WHERE `geo_zone_id` = '" . (int)$this->config->get('payment_plationline_geo_zone_id') . "' AND `country_id` = '" . (int)$address['country_id'] . "' AND (`zone_id` = '" . (int)$address['zone_id'] . "' OR `zone_id` = '0')");

            if ($query->num_rows) {
                $status = true;
            } else {
                $status = false;
            }
        }

        $method_data = [];

        if ($status) {
            $option_data['plationline'] = [
                'code' => 'plationline.plationline',
                'name' => $this->config->get('payment_plationline_title_' . $this->config->get('config_language_id'))
            ];

            $method_data = array(
                'code' => 'plationline',
                'name' => 'PlatiOnline',
                'sort_order' => $this->config->get('payment_plationline_sort_order'),
                'option'     => $option_data,
            );
        }

        return $method_data;
    }
}
