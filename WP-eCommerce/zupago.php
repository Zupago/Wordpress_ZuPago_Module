<?php
/*
Plugin Name: ZuPago HyBrid (HD) Wallet
Plugin URI: https://zupago.pe
Description: ZuPago HyBrid (HD) Wallet Payment gateway for We-Commerce
Version: 1.1
Author: ZuPago
Author URI: https://zupago.pe
*/

$nzshpcrt_gateways[$num]['name'] = __( 'ZuPago HyBrid (HD) Wallet', 'wpsc' );
$nzshpcrt_gateways[$num]['internalname'] = 'zupago';
$nzshpcrt_gateways[$num]['function'] = 'gateway_zupago';
$nzshpcrt_gateways[$num]['form'] = "form_zupago";
$nzshpcrt_gateways[$num]['submit_function'] = "submit_zupago";
$nzshpcrt_gateways[$num]['payment_type'] = "zupago";
$nzshpcrt_gateways[$num]['display_name'] = __( 'ZuPago HyBrid (HD) Wallet', 'wpsc' );
$nzshpcrt_gateways[$num]['image'] = WPSC_URL . '/images/zupago.png';

function gateway_zupago($separator, $sessionid)
{
	global $wpdb, $current_site;
	$purchase_log_sql = $wpdb->prepare( "SELECT * FROM `".WPSC_TABLE_PURCHASE_LOGS."` WHERE `sessionid`= %s LIMIT 1", $sessionid );
	$purchase_log = $wpdb->get_results($purchase_log_sql,ARRAY_A) ;

	$cart_sql = "SELECT * FROM `".WPSC_TABLE_CART_CONTENTS."` WHERE `purchaseid`='".$purchase_log[0]['id']."'";
	$cart = $wpdb->get_results($cart_sql,ARRAY_A) ;

	// ZuPago post variables
	$data['STATUS_URL'] = add_query_arg( 'zupago_callback', 'true', home_url( '/' ) );
	$data['CANCEL_URL_METHOD'] = "GET";
	$data['SUGGESTED_MEMO'] = "Purchase ID: ".$purchase_log[0]['id'];
	$data['PAYMENT_REF'] = $purchase_log[0]['id'];
	$data['PAYMENT_AMOUNT'] = $purchase_log[0]['id'];
	$data['ZUPAYEE_ACC'] = get_option('zupago_payee');
	$data['ZUPAYEE_ACC_KEY'] = get_option('account_key');
	$data['ZUPAYEE_ACC_BTC'] = get_option('zupago_btc');
	$data['CURRENCY_TYPE'] = get_option('zupago_curcode');
	$data['SUCCESS_URL'] = home_url( '/?zupago_results' );
	$data['CANCEL_URL'] =  home_url( '/?zupago_results' );
	$data['ZUPAYEE_NAME'] = get_option('zupago_payee_name');
	$data['PAYMENT_URL_METHOD'] = "LINK";
	$data['BAGGAGE_FIELDS']="cs1";
	$data['cs1'] = $sessionid;

	

	// Get Currency details abd price
	$local_currency_code = get_option('zupago_curcode');
	$zupago_currency_code = get_option('zupago_curcode');

	// ZuPago only processes in the set currency.  This is USD or EUR dependent on what the Chornopay account is set up with.
	// This must match the ZuPago settings set up in wordpress.  Convert to the zupago currency and calculate total.
	$curr=new CURRENCYCONVERTER();
	$decimal_places = 2;
	$total_price = 0;

	$i = 1;

	$all_donations = true;
	$all_no_shipping = true;

	foreach($cart as $item)
	{
		$product_data = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `" . $wpdb->posts . "` WHERE `id`= %d LIMIT 1", $item['prodid'] ), ARRAY_A );
		$product_data = $product_data[0];
		$variation_count = count($product_variations);

		//Does this even still work in 3.8? We're not using this table.
		$variation_sql = $wpdb->prepare( "SELECT * FROM `".WPSC_TABLE_CART_ITEM_VARIATIONS."` WHERE `cart_id` = %d", $item['id'] );
		$variation_data = $wpdb->get_results( $variation_sql, ARRAY_A );
		$variation_count = count($variation_data);

		if($variation_count >= 1)
      	{
      		$variation_list = " (";
      		$j = 0;

      		foreach($variation_data as $variation)
        	{
        		if($j > 0)
          		{
          			$variation_list .= ", ";
          		}
        		$value_id = $variation['venue_id'];
        		$value_data = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `".WPSC_TABLE_VARIATION_VALUES."` WHERE `id`= %d LIMIT 1", $value_id ), ARRAY_A);
        		$variation_list .= $value_data[0]['name'];
        		$j++;
        	}
      		$variation_list .= ")";
      	}
      	else
        {
        	$variation_list = '';
        }

    	$local_currency_productprice = $item['price'];

			$local_currency_shipping = $item['pnp'];


			$zupago_currency_productprice = $local_currency_productprice;
			$zupago_currency_shipping = $local_currency_shipping;

    	$data['amount_'.$i] = number_format(sprintf("%01.2f", $zupago_currency_productprice),$decimal_places,'.','');
    	$data['quantity_'.$i] = $item['quantity'];

		$total_price = $total_price + ($data['amount_'.$i] * $data['quantity_'.$i]);

    	$i++;
	}
  	$base_shipping = $purchase_log[0]['base_shipping'];
  	if(($base_shipping > 0) && ($all_donations == false) && ($all_no_shipping == false))
    {
		$data['handling_cart'] = number_format($base_shipping,$decimal_places,'.','');
		$total_price += number_format($base_shipping,$decimal_places,'.','');
    }

	$data['PAYMENT_AMOUNT'] = $total_price;

	// Create Form to post to ZuPago
	$output = "
		<form id=\"zupago_form\" name=\"zupago_form\" method=\"post\" action=\"https://zupago.pe/api\">\n";

	foreach($data as $n=>$v) {
			$output .= "			<input type=\"hidden\" name=\"$n\" value=\"$v\" />\n";
	}

	$output .= "			<input type=\"submit\" value=\"Continue to Zupago\" />
		</form>
	";

	echo($output);

	if(get_option('zupago_debug') == 0)
	{
		echo "<script language=\"javascript\" type=\"text/javascript\">document.getElementById('zupago_form').submit();</script>";
	}

  	exit();
}

function nzshpcrt_zupago_callback()
{
	global $wpdb;
	// needs to execute on page start
	// look at page 36

	if(isset($_GET['zupago_callback']) && ($_GET['zupago_callback'] == 'true'))
	{

	
		$zp_key=get_option('zupago_salt');

		if($zp_key == $_POST['ZUPAYEE_ACC_KEY'])
		{

					$sessionid = trim(stripslashes($_POST['cs1']));
					$data = array(
						'processed'  => 2,
						'transactid' => $_POST['PAYMENT_REF'],
						'date'       => time(),
					);
					wpsc_update_purchase_log_details( $sessionid, $data, 'sessionid' );
					transaction_results($sessionid, false, $_POST['PAYMENT_REF']);

					die('PAYMENT OK.');
	            	
		}
		else
		{
			die('Authentication failed.');

		}

	}
}

function nzshpcrt_zupago_results()
{
	// Function used to translate the ZuPago returned cs1=sessionid POST variable into the recognised GET variable for the transaction results page.
	if(isset($_POST['cs1']) && ($_POST['cs1'] !='') && ($_GET['sessionid'] == ''))
	{
		$_GET['sessionid'] = $_POST['cs1'];
	}
}

function submit_zupago()
{
	
	if(isset($_POST['zupago_curcode']))
    {
    	update_option('zupago_curcode', $_POST['zupago_curcode']);
    }

  	if(isset($_POST['zupago_payee']))
    {
    	update_option('zupago_payee', $_POST['zupago_payee']);
    }

  	if(isset($_POST['zupago_payee_name']))
    {
    	update_option('zupago_payee_name', $_POST['zupago_payee_name']);
    }

  	if(isset($_POST['zupago_url']))
    {
    	update_option('zupago_url', $_POST['zupago_url']);
    }

  	if(isset($_POST['zupago_return_url']))
    {
    	update_option('zupago_return_url', $_POST['zupago_return_url']);
    }

	if(isset($_POST['account_key']))
    {
    	update_option('account_key', $_POST['account_key']);
    }
	if(isset($_POST['zupago_btc']))
    {
    	update_option('zupago_btc', $_POST['zupago_btc']);
    }
	

	
	
	return true;
}

function form_zupago()
{
	//$select_currency=(get_option('zupago_curcode') = "selected='selected'";
	$select_currency[get_option('zupago_curcode')] = "selected='selected'";
	$zupago_payee = ( get_option('zupago_payee')=='' ? '' : get_option('zupago_payee') );
	$zupago_return_url = ( get_option('zupago_return_url')=='' ? get_option('transact_url') : get_option('zupago_return_url') );
	$zupago_payee_name = ( get_option('zupago_payee_name')=='' ? '' : get_option('zupago_payee_name') );
	$account_key = ( get_option('account_key')=='' ? '' : get_option('account_key') );
	$zupago_btc = ( get_option('zupago_btc')=='' ? '' : get_option('zupago_btc') );
	//$zupago_payment_id = ( get_option('zupago_payment_id')=='' ? '' : get_option('zupago_payment_id') );

	

	if (!isset($select_currency['USD'])) $select_currency['USD'] = '';
	if (!isset($select_currency['EUR'])) $select_currency['EUR'] = '';

	$output = "
		<tr>
			<td>" . __( 'Accepted Currency', 'wpsc' ) . "</td>
			<td>
				<select name='zupago_curcode'>
					<option " . $select_currency['USD'] . " value='USD'>" . __( 'USD - U.S. Dollar', 'wpsc' ) . "</option>
					<option " . $select_currency['EUR'] . " value='EUR'>" . __( 'EUR - Euros', 'wpsc' ) . "</option>
				</select>
				<p class='description'>
					" . __( 'The currency code that ZuPago will process the payment in. All products must be set up in this currency.', 'wpsc' ) . "
				</p>
		</tr>
		<tr>
			<td>" . __( 'Payee Name', 'wpsc' ) . "</td>
			<td>
				<input type='text' size='40' value='" . $zupago_payee_name . "' name='zupago_payee_name' />
		</tr>
		<tr>
			<td>" . __( 'Payee Account', 'wpsc' ) . "</td>
			<td>
				<input type='text' size='40' value='" . $zupago_payee . "' name='zupago_payee' />
				<p class='description'>
					" . __( 'Payee account ID (like ZU-10510)', 'wpsc' ) . "
				</p>
		</tr>
		<tr>
			<td>" . __( 'Payee Account BTC', 'wpsc' ) . "</td>
			<td>
				<input type='text' size='40' value='" . $zupago_btc . "' name='zupago_btc' />
				<p class='description'>
					" . __( 'Payee account ID (like ZB-10510)', 'wpsc' ) . "
				</p>
		</tr>
		
		
		<tr>
			<td>" . __( 'Account Key', 'wpsc' ) . "</td>
			<td>
				<input type='text' size='40' value='" . $account_key . "' name='account_key' />
				<p class='description'>
					" . __( 'account key (like VXpOc1JWSmh.................JTalZpVjFrOQ==)', 'wpsc' ) . "
				</p>
		</tr>
		<tr>
			<td>" . __( 'Return URL', 'wpsc' ) . "</td>
			<td>
				<input type='text' size='40' value='".$zupago_return_url."' name='zupago_return_url' />
				<p class='description'>
					" . __( 'Enter this URL in the Zupago web client against the Product ID that you have set up. This page is the transaction details page that you have configured in Shop Options.  It can not be edited on this page.', 'wpsc' ) . "
				</p>
		</tr>";
		
		

	return $output;
}


add_action('init', 'nzshpcrt_zupago_callback');
add_action('init', 'nzshpcrt_zupago_results');

?>