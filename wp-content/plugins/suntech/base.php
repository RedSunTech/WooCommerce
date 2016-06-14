<?php 
if ( ! defined( 'ABSPATH' ) ) exit;

//require_once woocommerce manually
require_once dirname(__FILE__).'/../woocommerce/includes/abstracts/abstract-wc-settings-api.php';
require_once dirname(__FILE__).'/../woocommerce/includes/abstracts/abstract-wc-payment-gateway.php';

class WC_Gateway_Suntech_Base extends WC_Payment_Gateway {

  const SUNTECH_EMAIL_MAX_LENGTH = 100;
  const SUNTECH_PRODUCT_NAME_MAX_LENGTH = 100;
  const BUYSAFE_CARD_TYPE_DEFAULT = "";//or "0", see also suntech spec
  const BUYSAFE_CARD_TYPE_1 = "1";

  const WOO_LOG_NAME_1 = 'woocommerce_';
  const WOO_LOG_NAME_2 = '_log_';

  const SPLITER_IN_NOTE1=',';

  protected function display_suntech_form($form_inputs='', $btn_submit_txt='立即付款'){
    $thanks = '謝謝您的訂購，請點擊按鈕導到紅陽金流付款流程頁面....';

    return '<p>'.$thanks.'</p><form id="form_suntech_submit" method="post" action="https://www.esafe.com.tw/Service/Etopm.aspx">
'.$form_inputs.'
<input type="submit" id="btn_submit_suntech" value="'.$btn_submit_txt.'">
</form>
<script>
//alert("'.$thanks.'");
//autoSubmit document.getElementById("btn_submit_suntech").style.display="none";document.getElementById("form_suntech_submit").submit();
</script>
';

  }

  protected function get_meta_when_submit($type=''){
    $VALUE_WHEN_KEY_NO_SET = '--noset--';
    switch($type){
      case'IP':$ret=isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : $VALUE_WHEN_KEY_NO_SET;break;
      case'URI':$ret=isset($_SERVER['REQUEST_URI']) ? htmlspecialchars($_SERVER['REQUEST_URI']) : $VALUE_WHEN_KEY_NO_SET;break;
      case'REFERURL':$ret=isset($_SERVER['HTTP_REFERER']) ? htmlspecialchars($_SERVER['HTTP_REFERER']) : $VALUE_WHEN_KEY_NO_SET;break;
      case'USERAGENT':$ret=isset($_SERVER['HTTP_USER_AGENT']) ? htmlspecialchars($_SERVER['HTTP_USER_AGENT']) : $VALUE_WHEN_KEY_NO_SET;break;
    }
    return $ret;
  }

  static function get_total($order, $suntech_payment_type=''){
    #return $order->get_total() + $order->get_total_shipping() + $order->get_total_tax();#<--bug
    return $order->get_total();
  }

  static function get_note1($type, $order, $required_fieldname_in_order){
    $note1=array(
      'alipay'=>'alipay',
      'buysafecardtype1'=>'buysafecardtype1',
      'buysafe'=>'buysafe',
      'pay24'=>'pay24',
      'paycode'=>'paycode',
      'webatm'=>'webatm',
    );
    
    $note1 = isset($note1[$type]) ? $note1[$type] : '';
    $note1.= self::SPLITER_IN_NOTE1.$order->{$required_fieldname_in_order}.self::SPLITER_IN_NOTE1.$order->id;
    return $note1;
  }

  static function get_class_id($type){// for option_name=woocommerce_suntech_%_settings FROM wp_options
     $class_id=array(
      'alipay'=>'alipay',
      'buysafecardtype1'=>'buysafecardtype1',
      'buysafe'=>'buysafe',
      'pay24'=>'pay24',
      'paycode'=>'paycode',
      'webatm'=>'webatm',
    );
    return isset($class_id[$type]) ? $class_id[$type] : '';
  }

  static function get_submit_info_by_log($option_value, $type='paymenttype'){
    list($tmp,$s) = explode($type=='paymenttype' ? '"note1"' 
                              : ($type=='web' ? '"web"' 
                                : ($type='webpwd' ? '"web_password"': '') 
                                )
                           , $option_value);
    list($tmp,$s) = explode('"',$s);
    return $s;
  }

  function process_payment( $order_id ) {
    $order = new WC_Order( $order_id );
    return array('result'=>'success', 'redirect'=>$order->get_checkout_payment_url( true ));#woocommerce array spec
  }

  function add_suntech_product($ary_product, $qty, $name, $price){
    $ary_product[] = array('qty'=>$qty,'name'=>$name,'price'=>$price);
    return $ary_product;
  }

  function get_post_submit_ChkValue($web, $web_password, $total_amount){
    return strtoupper( sha1( $web . $web_password. $total_amount) );
  }

}


