<?php

namespace Opencart\Catalog\Model\Extension\Itispay\Payment;

use Opencart\System\Engine\Model;

class Itispay extends Model
{
    protected function validateRequiredData($data, $extra = [])
    {
        $required = array_merge(['order_id', 'itispay_invoice_id'], $extra);
        $invalid = [];
        foreach ($required as $item) {
            if (!isset($data[$item]) || empty($data[$item])) {
                $invalid[] = $item;
            }
        }
        return $invalid;
    }

    public function addOrder($data)
    {
        $invalid = $this->validateRequiredData($data);
        if (count($invalid) === 0) {
            $query = "INSERT INTO `" . DB_PREFIX . "itispay_order` SET `order_id` = '" . (int)$data['order_id'] . "', `itispay_invoice_id` = '" . $this->db->escape($data['itispay_invoice_id']) . "'";

                try {
                    if (isset($data['wallet_hash']) && !empty($data['wallet_hash'])) {
                        $keys = ['amount', 'pending_amount', 'wallet_hash', 'psys_cid', 'currency', 'status', 'expire_utc', 'qr_code', 'source_currency', 'source_rate', 'expected_confirmations'];
                        $queryArr = [];
                        foreach ($keys as $key) {
                            if (isset($data[$key])) {
                                $queryArr[] = "`$key`='" . $this->db->escape($data[$key]) . "'";
                            }
                        }
                        if (!empty($queryArr)) {
                            $query .= ', ' . implode(', ', $queryArr);
                        }
                    }

                    return $this->db->query($query);
                } catch (Exception $e) {
                    $this->log->write('ItisPay::addOrder exception: ' . $e->getMessage());
                }
        } else {
            $this->log->write('ItisPay::addOrder ' . implode(', ', $invalid) . ' fields are missing');
        }
        return false;
    }

    public function updateOrder($data)
    {
        $invalid = $this->validateRequiredData($data, ['wallet_hash']);
        if (count($invalid) === 0) {
            try {
                $keys = ['pending_amount', 'status', 'qr_code', 'confirmations', 'tx_urls'];
                $queryArr = [];
                foreach ($keys as $key) {
                    if (isset($data[$key])) {
                        $queryArr[] = "`$key`='" . $this->db->escape($data[$key]) . "'";
                    }
                }
                if (!empty($queryArr)) {
                    $query = "UPDATE `" . DB_PREFIX . "itispay_order` SET ";
                    $query .= implode(', ', $queryArr);
                    $query .= " WHERE `order_id` = '" . (int)$data['order_id'] . "' AND `itispay_invoice_id` = '" . $this->db->escape($data['itispay_invoice_id']) . "'";
                    return $this->db->query($query);
                }

            } catch (Exception $e) {
                $this->log->write('ItisPay::updateOrder exception: ' . $e->getMessage());
            }
        } else {
            $this->log->write('ItisPay::updateOrder ' . implode(', ', $invalid) . ' fields are missing');
        }
        return false;
    }

    public function getOrder($order_id)
    {
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "itispay_order` WHERE `order_id` = '" . (int)$order_id . "' LIMIT 1");

        return $query->row;
    }

    public function getMethods($address)
    {
        $this->load->language('extension/itispay/payment/itispay');

        $option_data['itispay'] = [
            'code' => 'itispay.itispay',
            'name' => $this->language->get('text_title')
        ];

        return [
            'code' => 'itispay',
            'name' => $this->language->get('text_title'),
            'option' => $option_data,
            'sort_order' => $this->config->get('payment_itispay_sort_order')
        ];
    }
}
