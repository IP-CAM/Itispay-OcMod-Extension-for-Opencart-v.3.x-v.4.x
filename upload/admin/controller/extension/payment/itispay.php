<?php

require_once(DIR_SYSTEM . 'library/itispay/Client.php');

class ControllerExtensionPaymentitispay extends Controller
{
    private $error = array();

    public function index()
    {
        $this->load->language('extension/payment/itispay');
        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/setting');
        $this->load->model('localisation/order_status');
        $this->load->model('localisation/geo_zone');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('payment_itispay', $this->request->post);
            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true));
        }

        $data['action'] = $this->url->link('extension/payment/itispay', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true);
        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();
        $data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();
        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/payment/itispay', 'user_token=' . $this->session->data['user_token'], true)
        );

        $fields = array('payment_itispay_status', 'payment_itispay_api_secret_key', 'payment_itispay_order_status_id', 'payment_itispay_pending_status_id', 'payment_itispay_confirming_status_id',
            'payment_itispay_paid_status_id', 'payment_itispay_invalid_status_id', 'payment_itispay_expired_status_id',
            'payment_itispay_changeback_status_id', 'payment_itispay_canceled_status_id', 'payment_itispay_white_label',
            'payment_itispay_sort_order'
        );

        $data['white_label_options'] = [
            'false' => 'Disabled',
            'true' => 'Enabled',
        ];

        foreach ($fields as $field) {
            if (isset($this->request->post[$field])) {
                $data[$field] = $this->request->post[$field];
            } else {
                $data[$field] = $this->config->get($field);
            }
        }

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/payment/itispay', $data));
    }

    protected function validate()
    {
        if (!$this->user->hasPermission('modify', 'extension/payment/itispay')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        if (!class_exists('Client')) {
            $this->error['warning'] = $this->language->get('error_composer');
        }

        return !$this->error;
    }


    public function install()
    {
        $this->load->model('extension/payment/itispay');

        $this->model_extension_payment_itispay->install();
    }

    public function uninstall()
    {
        $this->load->model('extension/payment/itispay');

        $this->model_extension_payment_itispay->uninstall();
    }
}
