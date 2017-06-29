<?php
/**
 * @package Suntech
 * @version 1.1
 */
/*
Plugin Name: 紅陽_PayCode / 超商代收(代碼)
Plugin URI: https://www.esafe.com.tw/Question_Fd/DownloadPapers.aspx
Description: 紅陽超商代收(代碼)
Author: suntech
Version: 1.1
Author URI: https://www.esafe.com.tw/Question_Fd/DownloadPapers.aspx
*/

if (!defined('ABSPATH')) exit;

//require_once woocommerce manually
require_once dirname(__FILE__) . '/base.php';

class WC_Gateway_Suntech_Paycode extends WC_Gateway_Suntech_Base
{

    public function __construct()
    {
        $this->id = 'suntech_paycode';
        $this->log_option_prefix = self::WOO_LOG_NAME_1 . $this->id . self::WOO_LOG_NAME_2;
        $this->icon = '';
        $this->has_fields = false;//#
        $this->order_button_text = '結帳';

        // Load the settings.
        $this->init_from_fields();
        $this->init_settings();

        // Define user set variables
        $this->enabled = $this->get_option('enabled');
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->web_value = $this->get_option('web_value');
        $this->web_password_value = $this->get_option('web_password_value');
        $this->shipment = $this->get_option('shipment');
        $_due_date = is_numeric($this->get_option('due_date'))?$this->get_option('due_date'):7;
        $this->due_date = date('Ymd', mktime(0, 0, 0, date("m"), date("d") + $_due_date, date("Y")));
        // Add actions
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
    }

    public function init_from_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('是否啟用', 'woocommerce'),
                'type' => 'checkbox',
                'label' => __('啟用紅陽超商代收_代碼付款(PayCode)', 'woocommerce'),
                'default' => 'yes'
            ),
            'title' => array(
                'title' => __('顯示名稱', 'woocommerce'),
                'type' => 'text',
                'default' => __('超商代收(代碼繳費)', 'woocommerce'),
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => __('金流描述', 'woocommerce'),
                'type' => 'textarea',
                'description' => __('顯示在前台的描述文字', 'woocommerce'),
                'default' => __('超商代收(代碼繳費)，至超商透過代碼進行付款', 'woocommerce')
            ),
            'web_value' => array('title' => '商家代號', 'type' => 'text', 'description' => '請登入紅陽後台查詢商家代號(需檢查前後不可有空白)'),
            'web_password_value' => array('title' => '商家交易密碼', 'type' => 'password', 'description' => '請到紅陽官網 https://www.esafe.com.tw 登入商家專區設定交易密碼'),
            'order_button_text' => array('title' => '前台按鈕顯示文字', 'type' => 'text', 'default' => '取得代碼'),
            'due_date' => array('title' => '付款期限(天)', 'type' => 'text', 'default' => '7', 'description' => '不設定即為7天'),
            'shipment' => array(
                'title' => __('搭配超商取貨', 'woocommerce'),
                'type' => 'checkbox',
                'label' => __('啟用', 'woocommerce'),
                'default' => 'no'
            ),
            'test_mode' => array(
                'title' => __('測試模式', 'woocommerce'),
                'type' => 'checkbox',
                'label' => __('啟用', 'woocommerce'),
                'default' => 'no'
            ),
        );
    }

    /**
     * Output for the order received page.
     *
     * @access public
     * @return void
     */
    function receipt_page($order_id)
    {
        global $woocommerce;
        $woocommerce->cart->empty_cart();
        $order = new WC_Order($order_id);

        $ary_items = $order->get_items();
        if (!count($ary_items)) {
            die('本訂單查無產品品項，無法進行付款，請到重新下單。');
        }

        $ary_product = array();
        foreach ($ary_items as $item) {
            $product = $order->get_product_from_item($item);
            $ary_product = $this->add_suntech_product($ary_product, $item['qty'], $item['name'], $order->get_item_subtotal($item, false));
        }

        $total_amount = self::get_total($order);
        $web_password = $this->web_password_value;
        $cur_user = wp_get_current_user();
        $_web = $this->web_value;
        $_Td = $order->id;
        $_OrderInfo = '在' . $_SERVER['SERVER_NAME'] . '的訂單編號' . $order->id . '新增於' . $order->order_date;
        $_sna = $order->billing_first_name . $order->billing_last_name;
        $_sdt = $order->billing_phone;
        $_email = $order->billing_email;
        if (!filter_var($_email, FILTER_VALIDATE_EMAIL) or strlen($_email) > self::SUNTECH_EMAIL_MAX_LENGTH) {
            $_email = '';
        }
        $_note1 = self::get_note1('paycode', $order, 'billing_email');

        $comment = $this->get_order_init_notes($order_id)->comment_content;
        list($_Term, $_CargoFlag) = explode('_', $comment);
        $_ChkValue = $this->get_post_submit_ChkValue($this->web_value, $this->web_password_value, $total_amount);

        $update_option_datetime = date('YmdHis');
        $opt_name = $this->log_option_prefix . $update_option_datetime;
        $_note2 = $update_option_datetime;

        $_UserNo = $cur_user->ID > 0 ? $cur_user->ID : $_sna;
        $_BillDate = date('Ymd');

//
        $sum_product = 0;
        foreach ($ary_product as $v) {
            $sum_product += $v['qty'] * $v['price'];
        }
        $sum_product = round($sum_product, 0);
        $price_offset = $total_amount - $sum_product;
        if ($price_offset > 0) {
            $ary_product = $this->add_suntech_product($ary_product, 1, '運費 稅率 其它', $price_offset);
        }

//
        $idx = 1;
        $_str_inputs_product = '';
        foreach ($ary_product as $v) {
            $str_input = '';//reset
            $str_input .= '<input type="hidden" name="ProductName' . $idx . '" value="' . $v['name'] . '">';
            $str_input .= '<input type="hidden" name="ProductPrice' . $idx . '" value="' . $v['price'] . '">';
            $str_input .= '<input type="hidden" name="ProductQuantity' . $idx . '" value="' . $v['qty'] . '">';
            $_str_inputs_product .= $str_input;
            $idx++;
        }


        update_option($opt_name, serialize(array(
            'DueDate' => $this->due_date, 'UserNo' => $_UserNo, 'BillDate' => $_BillDate, 'str_input_product' => $_str_inputs_product,
            'web' => $_web, 'web_password' => $web_password, 'MN' => $total_amount, 'Td' => $_Td, 'OrderInfo' => $_OrderInfo, 'sna' => $_sna, 'sdt' => $_sdt, 'email' => $_email, 'note1' => $_note1,
            'note2' => $_note2, 'ChkValue' => $_ChkValue,
            'ip' => $this->get_meta_when_submit('IP'),
            'uri' => $this->get_meta_when_submit('URI'),
            'referurl' => $this->get_meta_when_submit('REFERURL'),
            'useragent' => $this->get_meta_when_submit('USERAGENT'),
        )));

        $html = '<input type="hidden" name="DueDate" value="' . $this->due_date . '">
                <input type="hidden" name="UserNo" value="' . $_UserNo . '">
                <input type="hidden" name="BillDate" value="' . $_BillDate . '">
                ' . $_str_inputs_product . '
                <input type="hidden" name="web" value="' . $_web . '">
                <input type="hidden" name="MN" value="' . $total_amount . '">
                <input type="hidden" name="OrderInfo" value="' . $_OrderInfo . '">
                <input type="hidden" name="Td" value="' . $_Td . '">
                <input type="hidden" name="sna" value="' . $_sna . '">
                <input type="hidden" name="sdt" value="' . $_sdt . '">
                <input type="hidden" name="email" value="' . $_email . '">
                <input type="hidden" name="note1" value="' . $_note1 . '">
                <input type="hidden" name="note2" value="' . $_note2 . '">
                <input type="hidden" name="ChkValue" value="' . $_ChkValue . '">
                <input type= "hidden" name="CargoFlag" value="' . $_CargoFlag . '">';

        echo $this->display_suntech_form($html, $this->get_option('order_button_text'));
    }

    public function payment_fields()
    {
        if (!empty($this->description)) {
            echo $this->description . '<br /><br />';
        }

        if ($this->shipment == 'yes') {
            echo '<div class="" style="padding-top: 15px"><label>';
            echo '<input type="checkbox" name="shipment" value="ship"/>';
            echo '超商取貨</label></div>';
        }
    }

    public function validate_fields()
    {
        $choose_shipment = isset($_POST['shipment']) ? $_POST['shipment'] : '';

        if ($choose_shipment != '') {
            if ($choose_shipment == 'ship') {
                $this->choose_shipment = '1';
            } else {
                wc_add_notice('發生錯誤，請重新選擇！', 'error');
                return false;
            }
        }

        wc_add_notice('謝謝您的訂購，訂購流程尚未完成，請點擊按鈕進入結帳流程頁面...');
        return true;
    }
}

function add_woocommerce_suntechpaycode_gateway($methods)
{
    $methods[] = 'WC_Gateway_Suntech_Paycode';
    return $methods;
}

add_filter('woocommerce_payment_gateways', 'add_woocommerce_suntechpaycode_gateway');

