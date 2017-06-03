<?php
require dirname(__FILE__) . '/wp-config.php';
require_once dirname(__FILE__) . '/wp-content/plugins/woocommerce/includes/class-wc-order.php';
require_once dirname(__FILE__) . '/wp-content/plugins/suntech/base.php';

$home_url = home_url();
$html_output = '';
$html_output_paycode = '<script>alert("您已完成您的訂購流程，\n下一步:請持您的繳費代碼到超商繳費完成付費，繳費代碼已寄至您Email。");location.href="' . $home_url . '";</script>';
$html_output_24pay = '<script>alert("您已完成您的訂購流程，\n下一步:請到您附近的超商繳費完成付費。");location.href="' . $home_url . '";</script>';
$html_output_sunpay = '<script>alert("您已完成您的訂購流程，\n下一步:到貨後至超商領取。");location.href="' . $home_url . '";</script>';
$paid_msg = "完成繳費";
$cargo_init_msg = "<br />交貨便代碼：%s，<a href=\"http://myship.7-11.com.tw/cc2b_track.asp?payment_no=%s\" target=\"_blank\">查看</a>";
$html_output_default = '<script>alert("' . $paid_msg . '，請到訂單查詢中查詢您的訂單狀態。");location.href="' . $home_url . '";</script>';

$web = isset($_POST['web']) ? $_POST['web'] : '';
$opt_datetime = date('YmdHis');
$ary_POST = array();
foreach ($_POST as $k => $v) {
    $ary_POST[$k] = $v;
}
$ary_POST['referurl'] = isset($_SERVER['HTTP_REFERER']) ? esc_sql($_SERVER['HTTP_REFERER']) : 'noset';
$ary_POST['ip'] = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'noset';
$ary_POST['useragent'] = isset($_SERVER['HTTP_USER_AGENT']) ? esc_sql($_SERVER['HTTP_USER_AGENT']) : 'noset';
update_option('woocommerce_' . $web . '_respond_' . $opt_datetime, serialize($ary_POST));

$note1_spliter = WC_Gateway_Suntech_Base::SPLITER_IN_NOTE1;
$note1_spliter_lower_urlencode = strtolower(urlencode(WC_Gateway_Suntech_Base::SPLITER_IN_NOTE1));
$note1_spliter_upper_urlencode = strtoupper(urlencode(WC_Gateway_Suntech_Base::SPLITER_IN_NOTE1));
$is_note1_spliter_lower_urlencode = strpos($_POST['note1'], $note1_spliter_lower_urlencode) !== false;
$is_note1_spliter_upper_urlencode = strpos($_POST['note1'], $note1_spliter_upper_urlencode) !== false;
if ($is_note1_spliter_lower_urlencode) {
    $note1_spliter = $note1_spliter_lower_urlencode;
} elseif ($is_note1_spliter_upper_urlencode) {
    $note1_spliter = $note1_spliter_upper_urlencode;
}

$is_suntech_posting =
    isset($_POST['note1'])
    && (strpos($_POST['note1'], WC_Gateway_Suntech_Base::SPLITER_IN_NOTE1) !== false
        || $is_note1_spliter_lower_urlencode
        || $is_note1_spliter_upper_urlencode
    );

if ($is_suntech_posting) {
    global $wpdb;
    $note1 = $_POST['note1'];
    $ChkValue = $_POST['ChkValue'];
    $errcode = isset($_POST['errcode']) ? $_POST['errcode'] : '';
    $web = $_POST['web'];
    $buysafeno = $_POST['buysafeno'];
    $paycode = isset($_POST['paycode']) ? $_POST['paycode'] : "";
    $MN = isset($_POST['MN']) ? $_POST['MN'] : '';
    $CargoNo = isset($_POST['CargoNo']) ? $_POST['CargoNo'] : '';
    $StoreType = isset($_POST['StoreType']) ? $_POST['StoreType'] : '';
    $StoreName = isset($_POST['StoreName']) ? urldecode($_POST['StoreName']) : '';
    list($payment_type, $order_email, $order_id) = explode($note1_spliter, $note1);
    $setting = get_option('woocommerce_suntech_' . WC_Gateway_Suntech_Base::get_class_id($payment_type) . '_settings');
    $webpwd = $setting['web_password_value'];
    $is_succ_from_suntech = $errcode === '0' || $errcode === 0 || $errcode === '00' || $errcode === 00;
    $is_interrupt = $errcode === '';

    try{
        $order = new WC_Order($order_id);
    }
    catch(Exception $e) {
        $logger = wc_get_logger();
        $logger->alert( 'note1: "' . $_POST['note1'] . '" ' . $e->getMessage());
        echo '<script>alert("訂單不存在。");location.href="' . $home_url . '";</script>';
        exit;
    }

    switch ($payment_type) {
        case 'unionpay':
        case 'buysafe':
        case 'webatm':
            if ($is_succ_from_suntech and $ChkValue == strtoupper(sha1($web . $webpwd . $buysafeno . $MN . $errcode))) {
                if ($order->has_status('pending')) {
                    $order->add_order_note($paid_msg, 1);
                    $order->payment_complete();
                }
                $html_output = $html_output_default;
            } elseif ($CargoNo != '' and $ChkValue == strtoupper(sha1($web . $webpwd . $buysafeno . $MN . $errcode . $CargoNo))) {
                if ($order->has_status('pending')) {
                    $order->add_order_note($paid_msg . sprintf($cargo_init_msg, $CargoNo, $CargoNo), 1);
                    $shipping_info['address_1'] = '7-ELEVEN' . $StoreName;
                    $order->set_address($shipping_info, 'shipping');
                    $order->payment_complete();
                }
                $html_output = $html_output_default;
            }

            break;
        case '24pay':
        case 'pay24':
            $pay_type = isset($_POST['PayType'])?$_POST['PayType']:'0';
            $_24payment_type = [
                '0'  => '',
                '1'  => '超商條碼',
                '2'  => '郵局條碼',
                '3'  => '虛擬帳號'
            ] ;
            if ($is_succ_from_suntech and $ChkValue == strtoupper(sha1($web . $webpwd . $buysafeno . $MN . $errcode))) {
                $order->add_order_note($paid_msg . '(' . $_24payment_type[$pay_type] . ')', 1);
                $order->payment_complete();
                echo '0000';
                exit;
            } else if ($is_succ_from_suntech and $ChkValue == strtoupper(sha1($web . $webpwd . $buysafeno . $MN . $errcode . $CargoNo))) {
                $order->add_order_note($paid_msg . '(' . $_24payment_type[$pay_type] . ')' . sprintf($cargo_init_msg, $CargoNo, $CargoNo), 1);
                $shipping_info['address_1'] = '7-ELEVEN' . $StoreName;
                $order->set_address($shipping_info, 'shipping');
                $order->payment_complete();
                echo '0000';
                exit;
            } else if ($is_interrupt and $ChkValue == strtoupper(sha1($web . $webpwd . $buysafeno . $MN . $_POST['EntityATM']))) {
                $BarcodeA = isset($_POST['BarcodeA']) ? $_POST['BarcodeA'] : '';
                $BarcodeB = isset($_POST['BarcodeB']) ? $_POST['BarcodeB'] : '';
                $BarcodeC = isset($_POST['BarcodeC']) ? $_POST['BarcodeC'] : '';
                $PostBarcodeA = isset($_POST['PostBarcodeA']) ? $_POST['PostBarcodeA'] : '';
                $PostBarcodeB = isset($_POST['PostBarcodeB']) ? $_POST['PostBarcodeB'] : '';
                $PostBarcodeC = isset($_POST['PostBarcodeC']) ? $_POST['PostBarcodeC'] : '';
                $barcode_msg = '商店自行產生繳費單專用訊息：<br/>●超商第一段條碼：%s<br/>超商第二段條碼：%s<br/>超商第三段條碼：%s<br/>●郵局第一段條碼：%s<br/>郵局第二段條碼：%s<br/>郵局第三段條碼：%s<br/>●ATM轉帳帳號(金額大於3萬請臨櫃匯款)：<br/>台新銀行代碼：812，分行代碼：0687<br/>帳號：%s';
                if ($order->has_status('pending')) {
                    $order->add_order_note('請至email列印繳費單，至超商繳費', 1);
                    $order->add_order_note(sprintf($barcode_msg, $BarcodeA, $BarcodeB, $BarcodeC, $PostBarcodeA, $PostBarcodeB, $PostBarcodeC, $_POST['EntityATM']));
                    $order->update_status('on-hold');
                }
                $html_output = $html_output_24pay;
            }

            break;
        case 'paycode':
            if ($is_succ_from_suntech and $ChkValue == strtoupper(sha1($web . $webpwd . $buysafeno . $MN . $errcode))) {
                $order->add_order_note($paid_msg . '，等待店家出貨', 1);
                $order->payment_complete();
                echo '0000';
                exit;
            } else if ($is_succ_from_suntech and $ChkValue == strtoupper(sha1($web . $webpwd . $buysafeno . $MN . $errcode . $CargoNo))) {
                $order->add_order_note($paid_msg . sprintf($cargo_init_msg, $CargoNo, $CargoNo), 1);
                $shipping_info['address_1'] = '7-ELEVEN' . $StoreName;
                $order->set_address($shipping_info, 'shipping');
                $order->payment_complete();
                echo '0000';
                exit;
            } else if ($paycode != '' and $ChkValue == strtoupper(sha1($web . $webpwd . $buysafeno . $MN . $paycode))) {
                if ($order->has_status('pending')) {
                    $order->add_order_note('繳費代碼:' . $paycode, 1);
                    $order->update_status('on-hold');
                }
                $html_output = $html_output_paycode;
            }
            break;
        case 'sunship':
            if ($CargoNo != '' and $ChkValue == strtoupper(sha1($web . $webpwd . $buysafeno . $MN . $errcode . $CargoNo))) {
                if ($order->has_status('pending')) {
                    $order->add_order_note(sprintf($cargo_init_msg, $CargoNo, $CargoNo), 1);
                    $shipping_info['address_1'] = '7-ELEVEN' . $StoreName;
                    $order->set_address($shipping_info, 'shipping');
                    $order->update_status('processing');
                }
                $html_output = $html_output_sunpay;
            }
            else if($is_succ_from_suntech and $ChkValue == strtoupper(sha1($web . $webpwd . $buysafeno . $MN . $errcode))) {
                $order->add_order_note('已付款取貨', 1);
                $order->update_status('completed');
                echo '0000';
            }
            break;
    }

    // 物流回傳
    if ($StoreType != '' and $ChkValue == strtoupper(sha1($web . $webpwd . $buysafeno . $StoreType))) {
        $StoreMsg = isset($_POST['StoreMsg']) ? urldecode($_POST['StoreMsg']): '';
        if ($StoreType == "1010") {
            $order->update_status('completed');
        }
        $order->add_order_note($StoreMsg . "(" . $StoreType . ")", 1);
        echo '0000';
        exit;
    }
}
else {
    $logger = wc_get_logger();
    $logger->alert( 'note1: "' . $_POST['note1'] . '"');
    echo '<script>location.href="' . $home_url . '";</script>';
    exit;
}

echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
if ($html_output != '') {
    echo $html_output;
} elseif (isset($_GET['is_suntech_24pay'])) {
    echo $html_output_24pay;
} elseif (isset($_GET['is_suntech_paycode'])) {
    echo $html_output_paycode;
} else {
    if ($order->has_status('pending')) {
        $order->add_order_note('交易失敗，' . urldecode($_POST['errmsg']) . "(" . $errcode . ")", 1);
        $order->update_status('cancelled');
    }
    echo '<script>alert("交易失敗，請重新交易。' . (($_POST['errmsg'] != '') ? '\n原因：' . urldecode($_POST['errmsg']) : '') . '");location.href="' . $home_url . '";</script>';
}

