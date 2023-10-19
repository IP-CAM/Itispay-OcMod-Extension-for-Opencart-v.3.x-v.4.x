<?php

namespace Opencart\Admin\Model\Extension\Itispay\Payment;

use Opencart\System\Engine\Model;

class Itispay extends Model
{
    public function install()
    {
        try {
            $result = $this->db->query("
      CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "itispay_order` (
        `itispay_order_id` INT(11) NOT NULL AUTO_INCREMENT,
        `order_id` INT(11) NOT NULL,
        `itispay_invoice_id` VARCHAR(40),
        `amount` VARCHAR(40) DEFAULT '',
        `pending_amount` VARCHAR(40) DEFAULT '',
        `wallet_hash` VARCHAR(120) DEFAULT '',
        `psys_cid` VARCHAR(10) DEFAULT '',
        `currency` VARCHAR(10) DEFAULT '',
        `status` VARCHAR(10) DEFAULT 'new',
        `source_currency` VARCHAR(10) DEFAULT '',
        `source_rate` VARCHAR(40) DEFAULT '',
        `expire_utc` DATETIME DEFAULT NULL,
        `qr_code` BLOB DEFAULT NULL,
        `confirmations` TINYINT(2) DEFAULT 0,
        `expected_confirmations` TINYINT(2) DEFAULT 0,
        `tx_urls` TEXT DEFAULT NULL,
        PRIMARY KEY (`itispay_order_id`)
      ) ENGINE=MyISAM DEFAULT COLLATE=utf8_general_ci;
    ");
        } catch (Exception $exception) {
            $this->log->write('ItisPay plugin install table failed: ' . $exception->getMessage());
        }

        $this->load->model('setting/setting');

        $defaults = array();

        $defaults['payment_itispay_receive_currencies'] = '';
        $defaults['payment_itispay_white_label'] = false;
        $defaults['payment_itispay_order_status_id'] = 1;
        $defaults['payment_itispay_pending_status_id'] = 1;
        $defaults['payment_itispay_confirming_status_id'] = 1;
        $defaults['payment_itispay_paid_status_id'] = 5;
        $defaults['payment_itispay_invalid_status_id'] = 10;
        $defaults['payment_itispay_changeback_status_id'] = 13;
        $defaults['payment_itispay_expired_status_id'] = 14;
        $defaults['payment_itispay_canceled_status_id'] = 7;
        $defaults['payment_itispay_sort_order'] = 1;

        $this->model_setting_setting->editSetting('payment_itispay', $defaults);
    }

    public function uninstall()
    {
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "itispay_order`;");
    }
}
