<?php

namespace Opencart\Admin\Controller\Extension\Itispay\Payment;

use Opencart\Extension\Itispay\System\Library\Itispay\ItispayClient;
use Opencart\System\Engine\Controller;

class Itispay extends Controller
{
    private array $error = [];

    public function index(): void
    {
        $this->load->language('extension/itispay/payment/itispay');
        $this->load->model('extension/itispay/payment/itispay');
        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('localisation/geo_zone');
        $data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

        $this->load->model('localisation/order_status');
        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

        $data['save'] = $this->url->link('extension/itispay/payment/itispay.save', 'user_token=' . $this->session->data['user_token']);
        $data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment');

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
            'href' => $this->url->link('extension/itispay/payment/itispay', 'user_token=' . $this->session->data['user_token'])
        ];

        $fields = ['payment_itispay_status', 'payment_itispay_api_secret_key', 'payment_itispay_order_status_id', 'payment_itispay_pending_status_id', 'payment_itispay_confirming_status_id',
            'payment_itispay_paid_status_id', 'payment_itispay_invalid_status_id', 'payment_itispay_expired_status_id',
            'payment_itispay_changeback_status_id', 'payment_itispay_canceled_status_id',
            'payment_itispay_sort_order'
        ];

        foreach ($fields as $field) {
            $data[$field] = $this->config->get($field);
        }

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/itispay/payment/itispay', $data));
    }

    public function save(): void
    {
        $this->load->language('extension/itispay/payment/itispay');

        $this->load->model('extension/itispay/payment/itispay');

        if (!$this->user->hasPermission('modify', 'extension/itispay/payment/itispay')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        if (!$this->error) {
            $this->load->model('setting/setting');

            $this->model_setting_setting->editSetting('payment_itispay', $this->request->post);

            $data['success'] = $this->language->get('text_success');
        }

        $data['error'] = $this->error;

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($data));
    }

    public function install(): void
    {
        $this->load->model('extension/itispay/payment/itispay');
		$this->model_extension_itispay_payment_itispay->install();
    }

    public function uninstall(): void
    {
        $this->load->model('extension/itispay/payment/itispay');
		$this->model_extension_itispay_payment_itispay->uninstall();
    }
}
