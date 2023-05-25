<?php

class ControllerExtensionPaymentPassimpay extends Controller
{

    public function index()
    {
        $this->language->load('extension/payment/passimpay');
        $order_id = $this->session->data['order_id'];
        $this->load->model('checkout/order');
        $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

        $platform_id = $this->config->get('payment_passimpay_merchant_id');
        $apikey = $this->config->get('payment_passimpay_apikey');
        $amount = number_format($order_info['total'], 2, '.', '');
        //$desc = $this->language->get('order_description') . $order_id;

        $this->load->model('localisation/currency');

        $currency_info = $this->model_localisation_currency->getCurrencies();

        if ($this->config->get('config_currency') !== 'USD'){
            $amount = number_format( round($amount * $currency_info['USD']['value']), 2, '.', '' );
        }

        $payload = http_build_query(['platform_id' => $platform_id, 'order_id' => $order_id, 'amount' => $amount]);
        $hash = hash_hmac('sha256', $payload, $apikey);

        $data = [
            'platform_id' => $platform_id,
            'order_id' => $order_id,
            'amount' => $amount,
            'hash' => $hash
        ];

        $post_data = http_build_query($data);

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($curl, CURLOPT_URL, 'https://passimpay.io/api/createorder');
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_ENCODING, 'gzip');
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        //curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        //curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        $result = curl_exec($curl);
        curl_close($curl);

        $result = json_decode($result, true);

        // Варианты ответов
        // В случае успеха
        if (isset($result['result']) && $result['result'] == 1) {
            $url = $result['url'];
            $data['url'] = $url;
        } // В случае ошибки
        else {
            $error = $result['message']; // Текст ошибки
            $data['error'] = $error;
        }

        $this->load->model('checkout/order');
        if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/extension/payment/passimpay')) {
            return $this->load->view($this->config->get('config_template') . '/template/extension/payment/passimpay', $data);
        } else {
            return $this->load->view('/extension/payment/passimpay', $data);
        }
    }

    public function success(){
        $this->cart->clear();
        $this->response->redirect($this->url->link('checkout/success', '', true));
    }

    public function fail(){
        $this->response->redirect($this->url->link('checkout/confirm', '', true));
    }

    public function notify(){

        $apikey = $this->config->get('payment_passimpay_apikey');

        $hash = $_POST['hash'];

        $data = [
            'platform_id' => (int) $_POST['platform_id'], // ID платформы
            'payment_id' => (int) $_POST['payment_id'], // ID валюты
            'order_id' => (int) $_POST['order_id'], // Payment ID Вашей платформы
            'amount' => $_POST['amount'], // сумма транзакции
            'txhash' => $_POST['txhash'], // Хэш или ID транзакции. ID транзакции можно найти в истории транзакций PassimPay в Вашем аккаунте.
            'address_from' => $_POST['address_from'], // адрес отправителя
            'address_to' => $_POST['address_to'], // адрес получателя
            'fee' => $_POST['fee'], // комиссия сети
        ];

        if (isset($_POST['confirmations']))
        {
            $data['confirmations'] = $_POST['confirmations']; // количество подтверждений сети (Bitcoin, Litecoin, Dogecoin, Bitcoin Cash)
        }

        $payload = http_build_query($data);

        if (!isset($hash) || hash_hmac('sha256', $payload, $apikey) != $hash)
        {
            return false;
        }

        // платеж зачислен
        // ваш код...

        $order_id = isset($this->request->post['order_id'])
            ? (int)$this->request->post['order_id']
            : 0;

        if (!$order_id) {
            exit;
        }

        $this->load->model('checkout/order');
        $order_info = $this->model_checkout_order->getOrder($order_id);
        $amount = number_format($order_info['total'], 2, '.', '');
        $comment = 'Passimpay Transaction id: ' . $_POST['txhash'];

        $this->log->write('Passimpay: ' . $order_id . ' - ' . $order_id . ' - ' . 'Transaction completed.' . ' Transaction id: ' . $_POST['txhash']);

        $this->model_checkout_order->addOrderHistory($order_id, $this->config->get('payment_passimpay_order_status_id'), $comment, $notify = true, $override = false);

    }

}
