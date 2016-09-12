<?php
require dirname(__FILE__).'/wp-config.php';
require_once dirname(__FILE__).'/wp-content/plugins/woocommerce/includes/class-wc-order.php';
require_once dirname(__FILE__).'/wp-content/plugins/suntech/base.php';

$home_url = 'http://'.$_SERVER['SERVER_NAME'];
$html_output = '';
$html_output_paycode = '<script>alert("您已完成您的訂購流程，\n下一步:請持您的繳費代碼到超商繳費完成付費，繳費代碼已寄至您Email。");location.href="'.$home_url.'";</script>';
$html_output_24pay = '<script>alert("您已完成您的訂購流程，\n下一步:請到您附近的超商繳費完成付費。");location.href="'.$home_url.'";</script>';
$html_output_default = '<script>alert("完成繳費，等待店家出貨。(為您導回首頁)");location.href="'.$home_url.'";</script>';

$web = isset($_POST['web']) ? $_POST['web'] : '';
$opt_datetime = date('YmdHis');
$ary_POST = array();
foreach($_POST as $k=>$v){
  $ary_POST[$k] = $v;
}
$ary_POST['referurl'] = isset($_SERVER['HTTP_REFERER']) ? esc_sql($_SERVER['HTTP_REFERER']) : 'noset';
$ary_POST['ip'] = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'noset';
$ary_POST['useragent'] = isset($_SERVER['HTTP_USER_AGENT']) ? esc_sql($_SERVER['HTTP_USER_AGENT']) : 'noset';
update_option('woocommerce_'.$web.'_respond_'.$opt_datetime, serialize($ary_POST));

$note1_spliter = WC_Gateway_Suntech_Base::SPLITER_IN_NOTE1;
$note1_spliter_lower_urlencode = strtolower(urlencode(WC_Gateway_Suntech_Base::SPLITER_IN_NOTE1));
$note1_spliter_upper_urlencode = strtoupper(urlencode(WC_Gateway_Suntech_Base::SPLITER_IN_NOTE1));
$is_note1_spliter_lower_urlencode = strpos($_POST['note1'],$note1_spliter_lower_urlencode)!==false;
$is_note1_spliter_upper_urlencode = strpos($_POST['note1'],$note1_spliter_upper_urlencode)!==false;
if($is_note1_spliter_lower_urlencode){
  $note1_spliter = $note1_spliter_lower_urlencode;
}elseif($is_note1_spliter_upper_urlencode){
  $note1_spliter = $note1_spliter_upper_urlencode;
}

$is_suntech_posting = 
isset($_POST['note1']) 
&& (strpos($_POST['note1'],WC_Gateway_Suntech_Base::SPLITER_IN_NOTE1)!==false 
  || $is_note1_spliter_lower_urlencode
  || $is_note1_spliter_upper_urlencode
);

if($is_suntech_posting){
  global $wpdb;
  $note1= $_POST['note1'];
  $ChkValue = $_POST['ChkValue'];
  $errcode = $_POST['errcode'];
  $web = $_POST['web'];
  $buysafeno = $_POST['buysafeno'];
  $paycode = $_POST['paycode'];
  $MN = $_POST['MN'];
  list($payment_type, $order_email, $order_id) = explode($note1_spliter, $note1);
  $setting = get_option('woocommerce_suntech_'.WC_Gateway_Suntech_Base::get_class_id($payment_type).'_settings');
  $webpwd = $setting['web_password_value'];
  $is_succ_from_suntech = $errcode==='0'||$errcode===0||$errcode==='00'||$errcode===00;
  $is_interrupt = $errcode==='';
  
  //$payment_type = str_replace(WC_Gateway_Suntech_Base::SPLITER_IN_NOTE1, '', $payment_type);  
  //$alipay_in_note1 = str_replace(WC_Gateway_Suntech_Base::SPLITER_IN_NOTE1, '', WC_Gateway_Suntech_Base::get_note1('alipay'));
  //$buysafecardtype1_in_note1 = str_replace(WC_Gateway_Suntech_Base::SPLITER_IN_NOTE1, '', WC_Gateway_Suntech_Base::get_note1('buysafecardtype1'));
  //$buysafe_in_note1 = str_replace(WC_Gateway_Suntech_Base::SPLITER_IN_NOTE1, '', WC_Gateway_Suntech_Base::get_note1('buysafe'));
  //$webatm_in_note1 = str_replace(WC_Gateway_Suntech_Base::SPLITER_IN_NOTE1, '', WC_Gateway_Suntech_Base::get_note1('webatm'));
  //$pay24_in_note1 = str_replace(WC_Gateway_Suntech_Base::SPLITER_IN_NOTE1, '', WC_Gateway_Suntech_Base::get_note1('pay24'));
  //$paycode_in_note1 = str_replace(WC_Gateway_Suntech_Base::SPLITER_IN_NOTE1, '', WC_Gateway_Suntech_Base::get_note1('paycode'));
  
  switch($payment_type){
  case 'alipay':
    if($is_succ_from_suntech and $ChkValue==strtoupper(sha1($web.$webpwd.$MN))){
      $order = new WC_Order( $order_id );
      $order->update_status( 'completed' );
      $html_output = $html_output_default;
    }
  break;
  case 'buysafecardtype1':
  case 'buysafe':
  case 'webatm':
    if($is_succ_from_suntech and $ChkValue==strtoupper(sha1($web.$webpwd.$buysafeno.$MN.$errcode))){
      $order = new WC_Order( $order_id );
      $order->update_status( 'completed' );
      $html_output = $html_output_default;
    }
  break;
  case 'pay24':
    if($is_succ_from_suntech and $ChkValue==strtoupper(sha1($web.$webpwd.$buysafeno.$MN.$errcode))){
      $order = new WC_Order( $order_id );
      //$order->update_status( 'completed' );
      $order->payment_complete();
      $html_output = $html_output_default; 
    }
    else if ($is_interrupt and $ChkValue==strtoupper(sha1($web.$webpwd.$buysafeno.$MN.$_POST['EntityATM']))) {
      $order = new WC_Order( $order_id );
      $order->update_status( 'on-hold', __( '等待付款', 'woocommerce' ) );
      $html_output = $html_output_24pay; 
    }

  break;
  case 'paycode':
    if($is_succ_from_suntech and $ChkValue==strtoupper(sha1($web.$webpwd.$buysafeno.$MN.$errcode))){
      $order = new WC_Order( $order_id );
      //$order->update_status( 'completed' );
      $order->payment_complete();
      $html_output = $html_output_default;
    }
    else if ($is_interrupt and $ChkValue==strtoupper(sha1($web.$webpwd.$buysafeno.$MN.$paycode))) {
      $order = new WC_Order( $order_id );
      $order->update_status( 'on-hold', __( '等待付款', 'woocommerce' ) );
      $html_output = $html_output_paycode; 
    }

  break;
  }
}

echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
if($html_output!=''){
echo $html_output;
}elseif(isset($_GET['is_suntech_24pay'])){
echo $html_output_24pay;
}elseif(isset($_GET['is_suntech_paycode'])){
echo $html_output_paycode;
}
else{
echo '交易失敗，請重新交易。'.(($_POST['errmsg']!='') ? '<br>原因：'.urldecode($_POST['errmsg']):'');
}
echo'<p><a href="'.$home_url.'">回首頁</a></p>';
?>
