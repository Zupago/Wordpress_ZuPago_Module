<?php

/*
Plugin Name: ZuPago HyBrid (HD) Wallet
Plugin URI: https://zupago.pe
Description: ZuPago HyBrid (HD) Wallet Payment gateway for woocommerce
Version: 1.1
Author: ZuPago
Author URI: https://zupago.pe
*/


add_action('plugins_loaded', 'woocommerce_gateway_zupago_init', 0);
function woocommerce_gateway_zupago_init(){
  if(!class_exists('WC_Payment_Gateway')) return;

  class WC_Gateway_Zupago extends WC_Payment_Gateway{
    public function __construct(){
      $this -> id = 'zupago';
      $this -> medthod_title = 'ZuPago HyBrid (HD) Wallet';
	  $this -> icon = get_site_url().'/wp-content/plugins/zupago/zupago.png';
      $this -> has_fields = false;

      $this -> init_form_fields();
      $this -> init_settings();

      $this -> title = $this -> settings['title'];
      $this -> description = $this -> settings['description'];
      $this -> zupayee_acc = $this -> settings['zupayee_acc'];
      $this -> zupayee_acc_btc = $this -> settings['zupayee_acc_btc'];
      $this -> currency_type = $this -> settings['currency_type'];
      $this -> zupayee_name = $this -> settings['zupayee_name'];
      $this -> zupayee_acc_key = $this -> settings['zupayee_acc_key'];
      $this -> redirect_page_id = $this -> settings['redirect_page_id'];
      $this -> liveurl = 'https://zupago.pe/api';
      define('CALLBACK_URL', get_site_url().'/?wc-api=WC_Gateway_ZuPago&ZuPago=callback');

      $this -> msg['message'] = "";
      $this -> msg['class'] = "";

      add_action('woocommerce_api_wc_gateway_zupago', array($this, 'check_zupago_response'));
     	if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
                add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options' ) );
             } else {
                add_action( 'woocommerce_update_options_payment_gateways', array($this, 'process_admin_options' ) );
            }
      add_action('woocommerce_receipt_zupago', array($this, 'receipt_page'));
	}

    function init_form_fields() {
	$this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'ZP'),
                    'type' => 'checkbox',
                    'label' => __('Enable ZuPago HyBrid (HD) Wallet Module.'),
                    'default' => 'no'

                    ),


                'title' => array(
                    'title' => __('Title:', 'ZP'),
                    'type'=> 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'ZP'),
                    'default' => __('ZuPago', 'ZP')


                    ),


                'description' => array(
                    'title' => __('Description:', 'ZP'),
                    'type' => 'textarea',
                    'description' => __('This controls the description which the user sees during checkout.', 'ZP'),
                    'default' => __('Pay With Fastest & Secure Hybrid (HD) Wallet Servers.', 'ZP')


                    ),

                'zupayee_acc' => array(
                    'title' => __('ZuPayee Acc', 'ZP'),
                    'type' => 'text',
                    'description' => __('Enter your ZuPago account you want to receive payments to (like ZU-12346)')

                    ),


                'zupayee_acc_btc' => array(
                    'title' => __('ZuPayee Acc Btc', 'ZP'),
                    'type' => 'text',
                    'description' => __('Enter your ZuPago Bitcoin (BTC) account you want to receive payments to (like ZB-12346)')

                    ),


                    'currency_type' => array(
                    'title' => __('Currency Type', 'ZP'),
                    'type' => 'select',
                    'options'     => array(
	          						'Select Currency Type' => 'Select Currency Type',
	          						'USD' => 'USD',
	          						'EUR' => 'EUR',
	          						'GBP' => 'GBP',
	          						'BTC' => 'BTC'),
                     'description' => __('Currency Type (USD, EUR, GBP, BTC)')
                    ),



                'zupayee_name' => array(
                    'title' => __('ZuPayee Name', 'ZP'),
                    'type' => 'text',
                    'default' => __('woocommerce', 'ZP')

                    ),

                'zupayee_acc_key' => array(
                    'title' => __('API KEY', 'ZP'),
                    'type' => 'text',
                    'description' =>  __('The generated Api key in your ZuPago Account', 'ZP'

                    ),
                ),

                'redirect_page_id' => array(
                    'title' => __('Return Page'),
                    'type' => 'select',
                    'options' => $this -> get_pages('Select Page'),
                    'description' => "URL of success page"
                )
            );
    }

       public function admin_options(){
        echo '<h3>'.__('ZuPago Hybrid (HD) Wallet Gateway', 'ZP').'</h3>';
        echo '<p>'.__('ZuPago The World Fastest & Secure Hybrid (HD) Wallet for online payment.').'</p>';
        echo '<table class="form-table">';
        // Generate the HTML For the settings form.
        $this -> generate_settings_html();
        echo '</table>';

    }

    /**
     *  There are no payment fields for zupago, but we want to show the description if set.
     **/
    function payment_fields(){
        if($this -> description) echo wpautop(wptexturize($this -> description));
    }
    /**
     * Receipt Page
     **/
    function receipt_page($order){
        echo '<p>'.__('Thank you for your order, please click the button below to pay with ZuPago Hybrid (HD) Wallet.', 'ZP').'</p>';
        echo $this -> generate_zupago_form($order);
    }
    /**
     * Generate zupago button link
     **/
    public function generate_zupago_form($order_id){

       global $woocommerce;
    	$order = new WC_Order( $order_id );
        $txnid = $order_id.'_'.date("ymds");

        $redirect_url = ($this -> redirect_page_id=="" || $this -> redirect_page_id==0)?get_site_url() . "/":get_permalink($this -> redirect_page_id);

        $productinfo = "Order $order_id";

        $str = "$this->zupayee_acc|$txnid|$order->order_total|$productinfo|$order->billing_first_name|$order->billing_email|||||||||||$this->zupayee_acc_key";
        $hash = hash('sha512', $str);

        $zupago_args = array(
          'key' => $this -> zupayee_acc,
          'txnid' => $txnid,
          'amount' => $order -> order_total,
          'productinfo' => $productinfo,
          'firstname' => $order -> billing_first_name,
          'lastname' => $order -> billing_last_name,
          'address1' => $order -> billing_address_1,
          'address2' => $order -> billing_address_2,
          'city' => $order -> billing_city,
          'state' => $order -> billing_state,
          'country' => $order -> billing_country,
          'zipcode' => $order -> billing_zip,
          'email' => $order -> billing_email,
          'phone' => $order -> billing_phone,
          'surl' => $redirect_url,
          'furl' => $redirect_url,
          'curl' => $redirect_url,
          'hash' => $hash,
          'pg' => 'NB'
          );

		  $currs=array('U'=>'USD', 'E'=>'EUR', 'G'=>'GBP', 'B'=>'BTC');
		  $cur_l=substr($this -> zupayee_acc, 0,1);
		  $currency=$get_currency=$currs[$cur_l];

        $zupago_args_array = array();
        foreach($zupago_args as $key => $value){
          $zupago_args_array[] = "<input type='hidden' name='$key' value='$value'/>";
        }
        return '<form action="'.$this -> liveurl.'" method="post" id="zupago_payment_form">
<input type="hidden" name="SUGGESTED_MEMO" value="'.$productinfo.'">

<input type="hidden" name="PAYMENT_REF" value="'.$order_id.'" />
<input type="hidden" name="PAYMENT_AMOUNT" value="'.$order -> order_total.'" />
<input type="hidden" name="ZUPAYEE_ACC" value="'.$this -> zupayee_acc.'" />
<input type="hidden" name="ZUPAYEE_ACC_KEY" value="'.$this -> zupayee_acc_key.'" />
<input type="hidden" name="ZUPAYEE_ACC_BTC" value="'.$this -> zupayee_acc_btc.'" />
<input type="hidden" name="CURRENCY_TYPE" value="'.$this -> currency_type.'" />
<input type="hidden" name="ZUPAYEE_NAME" value="'.$this -> zupayee_name.'" />
<input type="hidden" name="SUCCESS_URL" value="'.$redirect_url.'" />
<input type="hidden" name="SUCCESS_URL_METHOD" value="LINK" />
<input type="hidden" name="CANCEL_URL" value="'.$redirect_url.'" />
<input type="hidden" name="CANCEL_URL_METHOD" value="LINK" />
<input type="hidden" name="STATUS_URL" value="'.CALLBACK_URL.'" />
            <input type="submit" class="button-alt" id="submit_zupago_payment_form" value="'.__('Pay via zupago', 'zp').'" /> <a class="button cancel" href="'.$order->get_cancel_order_url().'">'.__('Cancel order &amp; restore cart', 'zp').'</a>
            <script type="text/javascript">
jQuery(function(){
jQuery("body").block(
        {
            message: "<img src=\"'.$woocommerce->plugin_url().'/assets/images/ajax-loader.gif\" alt=\"Redirecting…\" style=\"float:left; margin-right: 10px;\" />'.__('Thank you for your order. We are now redirecting you to Payment Gateway to make payment.', 'ZuPago').'",
                overlayCSS:
        {
            background: "#fff",
                opacity: 0.7
    },
    css: {
        padding:        25,
            textAlign:      "center",
            color:          "#555",
            border:         "4px solid #aaa",
            backgroundColor:"#fff",
            cursor:         "wait",
            lineHeight:"34px"
    }
    });
    jQuery("#submit_zupago_payment_form").click();});</script>
            </form>';


    }
    /**
     * Process the payment and return the result
     **/
    function process_payment($order_id){
        global $woocommerce;
    	$order = new WC_Order( $order_id );
        return array('result' => 'success', 'redirect' => add_query_arg('order',
            $order->id, add_query_arg('key', $order->order_key, get_permalink(get_option('woocommerce_pay_page_id'))))
        );
    }

    /**
     * Check for valid zupago server callback
     **/
    function check_zupago_response(){
        global $woocommerce;

		define('ZUPAYEE_ACC_KEY', strtoupper(md5($this->zupayee_acc_key)));

		// Path to directory to save logs. Make sure it has write permissions.
		//define('PATH_TO_LOG',  '/somewhere/out/of/document_root/');


		$zp_key=get_option('zupago_salt');

		if($zp_key == $_POST['ZUPAYEE_ACC_KEY'])
		{
			// proccessing payment if only hash is valid

			$order = new WC_Order($_POST['PAYMENT_REF']);

			if($_POST['PAYMENT_AMOUNT']==$order->order_total && $_POST['ZUPAYEE_ACC']==$this->zupayee_acc){

				$order -> payment_complete();
                $order -> add_order_note('ZuPago HyBrid (HD) Wallet payment successful<br/>Unnique Id from ZuPago: '.$_REQUEST['mihpayid']);
                $order -> add_order_note($this->msg['message']);
                $woocommerce -> cart -> empty_cart();

			    /*f=fopen(PATH_TO_LOG."good.log", "ab+");
				fwrite($f, date("d.m.Y H:i")."; POST: ".serialize($_POST)."; STRING: $string; HASH: $hash\n");
				fclose($f);*/

		   }else
		   {
				die('Authentication failed.');

		   }


		}else{ // you can also save invalid payments for debug purposes

		   // uncomment code below if you want to log requests with bad hash
		   /*$f=fopen(PATH_TO_LOG."bad.log", "ab+");
		   fwrite($f, date("d.m.Y H:i")."; REASON: bad hash; POST: ".serialize($_POST)."; STRING: $string; HASH: $hash\n");
		   fclose($f);*/

		}

		wp_die('done');

    }

    function showMessage($content){
            return '<div class="box '.$this -> msg['class'].'-box">'.$this -> msg['message'].'</div>'.$content;
        }
     // get all pages
    function get_pages($title = false, $indent = true) {
        $wp_pages = get_pages('sort_column=menu_order');
        $page_list = array();
        if ($title) $page_list[] = $title;
        foreach ($wp_pages as $page) {
            $prefix = '';
            // show indented child pages?
            if ($indent) {
                $has_parent = $page->post_parent;
                while($has_parent) {
                    $prefix .=  ' - ';
                    $next_page = get_page($has_parent);
                    $has_parent = $next_page->post_parent;
                }
            }
            // add to page list array array
            $page_list[$page->ID] = $prefix . $page->post_title;
        }
        return $page_list;
    }
}
   /**
     * Add the Gateway to WooCommerce
     **/
    function woocommerce_add_zupago_gateway($methods) {
        $methods[] = 'WC_Gateway_ZuPago';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_zupago_gateway' );
}
