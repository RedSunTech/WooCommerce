<?php 
/**
 * @package Suntech
 * @version 1.0
 */
/*
Plugin Name: Suntech_buysafe紅陽buysafe
Plugin URI: https://www.esafe.com.tw/Question_Fd/DownloadPapers.aspx
Description: 紅陽buysafe
Author: suntech
Version: 1.0
Author URI: https://www.esafe.com.tw/Question_Fd/DownloadPapers.aspx
*/

if ( ! defined( 'ABSPATH' ) ) exit;

//require_once woocommerce manually
require_once dirname(__FILE__).'/base.php';

class WC_Gateway_Suntech_Buysafe extends WC_Gateway_Suntech_Base{

  public function __construct() {
    $this->id = 'suntech_buysafe';
    $this->log_option_prefix = self::WOO_LOG_NAME_1.$this->id.self::WOO_LOG_NAME_2;
    $this->icon = '';
    $this->has_fields = false;//#
    $this->order_button_text = $this->get_option('order_button_text');

    // Load the settings.    
    $this->init_from_fields();
    $this->init_settings();

    // Define user set variables
    $this->enabled = $this->get_option('enabled');
    $this->title                    = $this->get_option( 'title' );
    $this->description = $this->get_option( 'description' );
    $this->web_value = $this->get_option('web_value');
    $this->web_password_value = $this->get_option('web_password_value');

    // Add actions
    add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
    add_action( 'woocommerce_receipt_'.$this->id, array( $this, 'receipt_page' ) );

  }

  public function init_from_fields(){

    $this->form_fields = array(
                        'enabled' => array(
                                'title'   => __( '是否啟用', 'woocommerce' ),
                                'type'    => 'checkbox',
                                'label'   => __( '啟用紅陽buysafe', 'woocommerce' ),
                                'default' => 'yes'
                        ),
                        'title' => array(
                                'title'       => __( '顯示名稱', 'woocommerce' ),
                                'type'        => 'text',
                                'default'     => __( '紅陽buysafe', 'woocommerce' ),
                                'desc_tip'    => true,
                        ),
                        'description' => array(
                                'title'       => __( '金流描述', 'woocommerce' ),
                                'type'        => 'textarea',
                                'description' => __( '顯示在前台的描述文字', 'woocommerce' ),
                                'default'     => __( '進入紅陽buysafe流程', 'woocommerce' )
                        ),
                        'web_value'=>array( 'title'=>'商家代號', 'type'=>'text', 'description'=>'請登入紅陽後台查詢商家代號(需檢查前後不可有空白)' ),
                        'web_password_value'=>array( 'title'=>'商家交易密碼', 'type'=>'password', 'description'=>'紅陽後台 https://www.esafe.com.tw/FunctionFolder/Mem_ChagePassword.aspx 的交易密碼' ),
                        'order_button_text'=>array( 'title'=>'前台按鈕顯示文字', 'type'=>'text', 'default'=>'進入繳費流程' ),

                        );

  
  }

  /**
  * Output for the order received page.
  *
  * @access public
  * @return void
  */
  function receipt_page( $order ) {
    $order = new WC_Order( $order );

/*var_dump($order->get_total());
var_dump($order->get_total_shipping());
var_dump($order->get_total_tax());*/
$total_amount = self::get_total($order);
$web_password = $this->web_password_value;

$_web = $this->web_value;
$_MN = round($total_amount, 0);
$_Td = $order->id;
$_OrderInfo = '在'.$_SERVER['SERVER_NAME'].'的訂單編號'.$order->id.'新增於'.$order->order_date;
$_sna = $order->billing_first_name . $order->billing_last_name;
$_sdt = $order->billing_phone;
$_email = $order->billing_email;
if(!filter_var($_email, FILTER_VALIDATE_EMAIL) or strlen($_email)>self::SUNTECH_EMAIL_MAX_LENGTH){
  $_email = '';
}
$_note1 = self::get_note1('buysafe',$order,'billing_email');
$_Card_Type = self::BUYSAFE_CARD_TYPE_DEFAULT;
$_ChkValue = $this->get_post_submit_ChkValue( $_web, $web_password, $_MN );

$update_option_datetime = date('YmdHis');
$opt_name = $this->log_option_prefix.$update_option_datetime;
$_note2 = $update_option_datetime;

update_option($opt_name, serialize(array(
  'web'=>$_web,'web_password'=>$web_password,'MN'=>$_MN,'Td'=>$_Td,'OrderInfo'=>$_OrderInfo,'sna'=>$_sna,'sdt'=>$_sdt,'email'=>$_email,'note1'=>$_note1,
  'note2'=>$_note2,'Card_Type'=>$_Card_Type,'ChkValue'=>$_ChkValue,
  'ip'=>$this->get_meta_when_submit('IP'),
  'uri'=>$this->get_meta_when_submit('URI'),
  'referurl'=>$this->get_meta_when_submit('REFERURL'),
  'useragent'=>$this->get_meta_when_submit('USERAGENT'),
  )));

echo $this->display_suntech_form('<input type="hidden" name="web" value="'.$_web.'">
<input type="hidden" name="MN" value="'.$_MN.'">
<input type="hidden" name="Td" value="'.$_Td.'">
<input type="hidden" name="OrderInfo" value="'.$_OrderInfo.'">
<input type="hidden" name="sna" value="'.$_sna.'">
<input type="hidden" name="sdt" value="'.$_sdt.'">
<input type="hidden" name="email" value="'.$_email.'">
<input type="hidden" name="note1" value="'.$_note1.'">
<input type="hidden" name="note2" value="'.$_note2.'">
<input type="hidden" name="Card_Type" value="'.$_Card_Type.'">
<input type="hidden" name="ChkValue" value="'.$_ChkValue.'">');

  }



}


function add_woocommerce_suntechbuysafe_gateway($methods) {
  $methods[] = 'WC_Gateway_Suntech_Buysafe';
  return $methods;
} 
add_filter('woocommerce_payment_gateways', 'add_woocommerce_suntechbuysafe_gateway');
