<?php
if (!defined('ABSPATH')) exit;

//require_once woocommerce manually
require_once dirname(__FILE__) . '/../woocommerce/includes/abstracts/abstract-wc-settings-api.php';
require_once dirname(__FILE__) . '/../woocommerce/includes/abstracts/abstract-wc-payment-gateway.php';

class WC_Gateway_Suntech_Base extends WC_Payment_Gateway
{
    protected $web_value = '', $web_password_value = '', $ChkValue, $shipment;
    protected $choose_installment = '', $choose_shipment = 0, $due_date = '';
    protected $installments = [];

    const SUNTECH_EMAIL_MAX_LENGTH = 100;
    const SUNTECH_PRODUCT_NAME_MAX_LENGTH = 100;
    const BUYSAFE_CARD_TYPE_DEFAULT = 0;//or "0", see also suntech spec
    const BUYSAFE_CARD_TYPE_1 = "1";

    const WOO_LOG_NAME_1 = 'woocommerce_';
    const WOO_LOG_NAME_2 = '_log_';

    const SPLITER_IN_NOTE1 = ',';

    protected function display_suntech_form($form_inputs = '', $btn_submit_txt = '結帳')
    {
        if ($this->get_option('test_mode') == 'yes') $action = 'https://test.esafe.com.tw/Service/Etopm.aspx';
        else $action = 'https://www.esafe.com.tw/Service/Etopm.aspx';

        $html = '<form id="form_suntech_submit" method="post" action="' . $action . '">';
        $html .= $form_inputs;
        $html .= '<input type="submit" id="btn_submit_suntech" value="' . $btn_submit_txt . '"></form>';

        return $html;
    }

    protected function get_meta_when_submit($type = '')
    {
        $VALUE_WHEN_KEY_NO_SET = '--noset--';
        switch ($type) {
            case'IP':
                $ret = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : $VALUE_WHEN_KEY_NO_SET;
                break;
            case'URI':
                $ret = isset($_SERVER['REQUEST_URI']) ? htmlspecialchars($_SERVER['REQUEST_URI']) : $VALUE_WHEN_KEY_NO_SET;
                break;
            case'REFERURL':
                $ret = isset($_SERVER['HTTP_REFERER']) ? htmlspecialchars($_SERVER['HTTP_REFERER']) : $VALUE_WHEN_KEY_NO_SET;
                break;
            case'USERAGENT':
                $ret = isset($_SERVER['HTTP_USER_AGENT']) ? htmlspecialchars($_SERVER['HTTP_USER_AGENT']) : $VALUE_WHEN_KEY_NO_SET;
                break;
        }
        return $ret;
    }

    static function get_total($order, $suntech_payment_type = '')
    {
        #return $order->get_total() + $order->get_total_shipping() + $order->get_total_tax();#<--bug
        return $order->get_total();
    }

    static function get_note1($type, $order, $required_fieldname_in_order)
    {
        $note1 = array(
            'unionpay' => 'unionpay',
            'buysafe' => 'buysafe',
            'pay24' => 'pay24',
            'paycode' => 'paycode',
            'webatm' => 'webatm',
            'sunship' => 'sunship',
        );

        $note1 = isset($note1[$type]) ? $note1[$type] : '';
        $note1 .= self::SPLITER_IN_NOTE1 . $order->{$required_fieldname_in_order} . self::SPLITER_IN_NOTE1 . $order->id;
        return $note1;
    }

    static function get_class_id($type)
    {// for option_name=woocommerce_suntech_%_settings FROM wp_options
        $class_id = array(
            'alipay' => 'alipay',
            'unionpay' => 'unionpay',
            'buysafe' => 'buysafe',
            'pay24' => 'pay24',
            'paycode' => 'paycode',
            'webatm' => 'webatm',
            'sunship' => 'sunship',
        );
        return isset($class_id[$type]) ? $class_id[$type] : '';
    }

    static function get_submit_info_by_log($option_value, $type = 'paymenttype')
    {
        list($tmp, $s) = explode($type == 'paymenttype' ? '"note1"'
            : ($type == 'web' ? '"web"'
                : ($type = 'webpwd' ? '"web_password"' : '')
            )
            , $option_value);
        list($tmp, $s) = explode('"', $s);
        return $s;
    }

    function process_payment($order_id)
    {
        $order = new WC_Order($order_id);

        $payment_str = " (" . $this->title;
        if ($this->choose_installment != '') {
            $payment_str .= ", 分" . $this->choose_installment . "期";
        }
        if ($this->choose_shipment == '1' || $this->title == "超商取貨付款") {
            $payment_str .= ", 搭配超商取貨";
            $shipping_info = [
                'city' => '',
                'state' => '',
                'postcode' => '',
                'address_1' => '7-ELEVEN',
                'address_2' => ''
            ];
            $order->set_address($shipping_info, 'shipping');
        }
        if ($this->due_date != '') {
            $NewDate = ' ' . substr($this->due_date, 0, 4) . '/' . substr($this->due_date, 4, 2) . '/' . substr($this->due_date, 6, 2) . ' ';
            $payment_str .= ", 請在付款期限" . $NewDate . "前付款完畢";
        }
        $payment_str .= ")";

        if ($this->get_order_init_notes($order_id) == '') {
            $order->add_order_note('建立新訂單，等待付款' . $payment_str, 1);
            $order->add_order_note($this->choose_installment . '_' . $this->choose_shipment);
        }
        return array('result' => 'success', 'redirect' => $order->get_checkout_payment_url(true));#woocommerce array spec
    }

    function add_suntech_product($ary_product, $qty, $name, $price)
    {
        $ary_product[] = array('qty' => $qty, 'name' => $name, 'price' => $price);
        return $ary_product;
    }

    function get_post_submit_ChkValue($web, $web_password, $total_amount, $term = "")
    {
        if ($term == '') {
            return strtoupper(sha1($web . $web_password . $total_amount));
        }

        return strtoupper(sha1($web . $web_password . $total_amount . $term));
    }

    public function get_order_init_notes($order_id)
    {
        $comment = '';

        $args = array(
            'post_id' => $order_id,
            'approve' => 'approve'
        );
        remove_filter('comments_clauses', array('WC_Comments', 'exclude_order_comments'));
        $comments = get_comments($args);
        add_filter('comments_clauses', array('WC_Comments', 'exclude_order_comments'));

        if (count($comments) > 0) {
            $comment = $comments[0];
        }
        return $comment;
    }
}


