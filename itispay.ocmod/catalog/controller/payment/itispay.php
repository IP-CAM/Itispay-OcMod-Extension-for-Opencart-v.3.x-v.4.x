<?php
namespace Opencart\Catalog\Controller\Extension\Itispay\Payment;

use Opencart\System\Engine\Controller;
use Opencart\System\Library\Extension\Itispay\Client;

class Itispay extends Controller
{
    /** @var Client */
    private $itispay;
    private $secretKey;

    const ITISPAY_OPENCART_EXTENSION_VERSION = '4.0.0';

    public function index()
    {
        $this->load->language('extension/itispay/payment/itispay');
        $this->load->model('checkout/order');
        $this->setupItispayClient();

        $shop = $this->itispay->getShopInfo();
        $data = [];
        $data['white_label'] = $shop['data']['white_label'] ?? false;

        if (!isset($data['button_confirm'])) {
            $data['button_confirm'] = $this->language->get('button_confirm');
        }
		$data['fail'] = $this->session->data['fail'] ?? false;
        $data['action'] = $this->url->link('extension/itispay/payment/itispay.confirm', '');

        return $this->load->view('extension/itispay/payment/itispay', $data);
    }

    public function confirm(): void
    {
        $this->setupItispayClient();
        $this->load->model('checkout/order');
        $this->load->model('extension/itispay/payment/itispay');

        $orderId = $this->session->data['order_id'];
        $order_info = $this->model_checkout_order->getOrder($orderId);
        $shop = $this->itispay->getShopInfo();

        $description = [];

        foreach ($this->cart->getProducts() as $product) {
            $description[] = $product['quantity'] . ' × ' . $product['name'];
        }

        $amount = $order_info['total'] * $this->currency->getvalue($order_info['currency_code']);

        $siteTitle = is_array($this->config->get('config_meta_title')) ? implode(',', $this->config->get('config_meta_title')) : $this->config->get('config_meta_title');
        $orderName = $siteTitle . ' Order #' . $order_info['order_id'];
        $request = array(
            'source_amount' => number_format($amount, 8, '.', ''),
            'source_currency' => $order_info['currency_code'],
            'order_name' => $orderName,
            'order_number' => $order_info['order_id'],
            'description' => implode(',', $description),
            'cancel_url' => $this->url->link('extension/itispay/payment/itispay.callback', ''),
            'callback_url' => $this->url->link('extension/itispay/payment/itispay.callback', ''),
            'success_url' => $this->url->link('extension/itispay/payment/itispay.success', ''),
            'email' => $order_info['email'],
            'plugin' => 'opencart',
            'version' => self::ITISPAY_OPENCART_EXTENSION_VERSION,
            'return_existing' => true
        );

            $this->log->write("Order request" . json_encode($request));
            $response = $this->itispay->createTransaction($request);
            $this->log->write("Order responst" . json_encode($response));
        if ($response && $response['status'] !== 'error' && !empty($response['data'])) {
            $orderData = array(
                'order_id' => $order_info['order_id'],
                'itispay_invoice_id' => $response['data']['txn_id']
            );
            $orderData = array_merge($orderData, $response['data']);
            $this->model_extension_itispay_payment_itispay->addOrder($orderData);
            $this->model_checkout_order->addHistory($order_info['order_id'], $this->config->get('payment_itispay_order_status_id'), '', true);
            $this->session->data['fail'] = false;
            if (isset($shop['data']['white_label']) && $shop['data']['white_label']) {
                $this->response->redirect($this->url->link('extension/itispay/payment/itispay.invoice', ''));
            } else {
                $this->response->redirect($response['data']['invoice_url']);
            }
        } else {
            $this->log->write("Order #" . $order_info['order_id'] . " is not valid. " . ($response['data']['message'] ?? ''));
			$this->session->data['fail'] = implode(',', json_decode($response['data']['message'] ?? '{}', true));
            $this->response->redirect($this->url->link('checkout/checkout', ''));
        }
    }

    public function invoice()
    {
        $this->load->model('checkout/order');
        $this->load->language('extension/itispay/payment/itispay');
        $this->load->model('extension/itispay/payment/itispay');
        $this->setupItispayClient();

        $orderId = isset($this->session->data['order_id']) ? $this->session->data['order_id'] : null;

        if (!$orderId){
            $this->response->redirect($this->url->link('common/home', ''));
        }

        $itispayOrder = $this->model_extension_itispay_payment_itispay->getOrder($orderId);
        if (!$itispayOrder){
            $this->response->redirect($this->url->link('common/home', ''));
        }

        $data = [];
        $data['itispay_invoice_id'] = $itispayOrder['itispay_invoice_id'];

        $order_info = $this->model_checkout_order->getOrder($orderId);
        if (empty($order_info)) {
            $this->response->redirect($this->url->link('common/home', ''));
        }

        $data['breadcrumbs'] = [];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home')
        ];

        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_checkout'),
            'href' => $this->url->link('checkout/checkout', '')
        ];

        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');

        $this->response->setOutput($this->load->view('extension/itispay/payment/itispay_invoice', $data));
    }

    public function cancel()
    {
        $this->response->redirect($this->url->link('checkout/cart', ''));
    }

    public function success()
    {
        if (isset($this->session->data['order_id'])) {
            $this->load->model('checkout/order');
            $this->load->model('extension/itispay/payment/itispay');

            $order = $this->model_extension_itispay_payment_itispay->getOrder($this->session->data['order_id']);
        } else {
            $order = '';
        }

        if (empty($order)) {
            $this->response->redirect($this->url->link('common/home', ''));
        } else {
            $this->response->redirect($this->url->link('checkout/success', ''));
        }
    }

    function verifyCallbackData($post)
    {
        $secretKey = $this->secretKey;
        if (!isset($post['verify_hash'])) {
            return false;
        }

        $verifyHash = $post['verify_hash'];
        unset($post['verify_hash']);
        ksort($post);
        if (isset($post['expire_utc'])){
            $post['expire_utc'] = (string)$post['expire_utc'];
        }
        if (isset($post['tx_urls'])){
            $post['tx_urls'] = html_entity_decode($post['tx_urls']);
        }
        $postString = serialize($post);
        $checkKey = hash_hmac('sha1', $postString, $secretKey);
        if ($checkKey != $verifyHash) {
            return false;
        }

        return true;
    }

    public function callback()
    {
        $this->setupItispayClient();
        if ($this->verifyCallbackData($this->request->post)) {
            $this->load->model('checkout/order');
            $this->load->model('extension/itispay/payment/itispay');

            $order_id = $this->request->post['order_number'];
            $order_info = $this->model_checkout_order->getOrder($order_id);

            $data = $this->request->post;

            if (!empty($order_info)) {
                $ext_order = $this->model_extension_itispay_payment_itispay->getOrder($order_id);
                if (!empty($ext_order) && isset($ext_order['wallet_hash']) && !empty($ext_order['wallet_hash'])) {
                    $data['itispay_invoice_id'] = $data['txn_id'];
                    $data['order_id'] = $order_id;
                    if (isset($data['tx_urls'])){
                        $data['tx_urls'] = html_entity_decode($data['tx_urls']);
                    }
                    $this->model_extension_itispay_payment_itispay->updateOrder($data);
                }

                switch ($data['status']) {
                    case 'completed':
                    case 'mismatch':
                        $cg_order_status = 'payment_itispay_paid_status_id';
                        break;
                    case 'cancelled':
                        $cg_order_status = 'payment_itispay_canceled_status_id';
                        break;
                    case 'expired':
                        if ($data['source_amount'] > 0) {
                            $cg_order_status = 'payment_itispay_invalid_status_id';
                        } else {
                            $cg_order_status = 'payment_itispay_canceled_status_id';
                        }
                        break;
                    default:
                        $cg_order_status = NULL;
                }

                if (!is_null($cg_order_status)) {
                    $comment = '';
                    if (isset($data['comment']) && !empty($data['comment'])) {
                        $comment = $data['comment'];
                    }
                    $this->model_checkout_order->addHistory($order_id, $this->config->get($cg_order_status), $comment/*, true*/);
                }
            } else {
                $this->log->write('ItisPay order with id '. $order_id . ' not found');
            }
            $this->response->addHeader('HTTP/1.1 200 OK');
        } else {
            $this->log->write('ItisPay response looks suspicious. Skip updating order');
            $this->response->addHeader('HTTP/1.1 400 Bad Request');
        }
    }

    private function setupItispayClient()
    {
        $this->secretKey = $this->config->get('payment_itispay_api_secret_key');
        $this->itispay = new \Opencart\System\Library\Extension\Itispay\Client($this->secretKey);
    }
}
