<?php
/*
	Plugin Name: GTPay WooCommerce Payment Gateway
	Plugin URI: adigon2006@yahoo.com
	Description: GTpay Woocommerce Payment Gateway allows you to accept payment on your Woocommerce store via Visa Cards, Mastercards, Verve Cards.
	Version: 3.1.0
	Author: Adigun Adekunle
	Author URI: http://adigzconceptz.com/
	License:           GPL-2.0+
 	License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
*/
if ( ! defined( 'ABSPATH' ) )
	exit;
	
add_action('plugins_loaded', 'wc_gtpay_init', 0);

function wc_gtpay_init() 
{

if ( !class_exists( 'WC_Payment_Gateway' ) ) return;

// gateway class
class WC_GTpay_Gateway extends WC_Payment_Gateway
{

	public function __construct(){

			$this->id 					= 'gtpay_gateway';
    		$this->icon 				= apply_filters('woocommerce_gtpay_icon', plugins_url( 'logo/pay-via-gtpay.jpg' , __FILE__ ) );
			$this->has_fields 			= false;
        	$this->payment_url 			= 'https://ibank.gtbank.com/GTPay/Tranx.aspx';
			$this->notify_url        	= WC()->api_request_url( 'WC_GTpay_Gateway' );
        	$this->method_title     	= 'GTPay Payment Gateway';
        	$this->method_description  	= 'GTPay Payment Gateway allows you to receive Mastercard, Verve Card and Visa Card Payments On your Woocommerce Powered Site.';


			// Load the form fields.
			$this->init_form_fields();

			// Load the settings.
			$this->init_settings();


			// Define user set variables
			$this->title 					= $this->get_option( 'title' );
			$this->description 				= $this->get_option( 'description' );
			$this->GTPayMerchantId 		= $this->get_option( 'GTPayMerchantId' );
			$this->hashKey					= $this->get_option('hashKey');

			//Actions
			add_action('woocommerce_receipt_gtpay_gateway', array($this, 'receipt_page'));
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

			// Payment listener/API hook
			add_action( 'woocommerce_api_wc_gtpay_gateway', array( $this, 'check_gtpay_response' ) );

			// Check if the gateway can be used
			if ( ! $this->is_valid_for_use() ) {
				$this->enabled = false;
			}
		}	
	
	function init_form_fields(){
			$this->form_fields = array(
				'enabled' => array(
					'title' 		=> 'Enable/Disable',
					'type' 			=> 'checkbox',
					'label' 		=>'Enable GTPay Payment Gateway',
					'description' 	=> 'Enable or disable the gateway.',
            		'desc_tip'      => true,
					'default' 		=> 'yes'
				),
				'title' => array(
					'title' 		=> 'Title',
					'type' 			=> 'text',
					'description' 	=> 'This controls the title which the user sees during checkout.',
        			'desc_tip'      => false,
					'default' 		=> 'GTPay Payment Gateway'
				),
				'description' => array(
					'title' 		=> 'Description',
					'type' 			=> 'textarea',
					'description' 	=> 'This controls the description which the user sees during checkout.',
					'default' 		=> 'Pay Via GTpay: Accepts Interswitch, Mastercard, Verve cards and Visa cards.'
				),
				'GTPayMerchantId' => array(
					'title' 		=> 'GTPay Merchant ID',
					'type' 			=> 'text',
					'description' 	=> 'Enter Your GTPay Merchant ID, this can be gotten on your account page when you login on GTPay' ,
					'default' 		=> '',
        			'desc_tip'      => true
				),
				'hashKey' => array(
					'title' 		=> 'Hash Key',
					'type' 			=> 'text',
					'description' 	=> 'Enter Your Hash Key here, provided by GTPay.' ,
					'default' 		=> '',
        			'desc_tip'      => true
				),
			);
		}
		
	public function is_valid_for_use(){

			if( ! in_array( get_woocommerce_currency(), array('NGN') ) ){
				$this->msg = 'GTPay doesn\'t support your store currency, set it to Nigerian Naira &#8358; <a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=wc-settings&tab=general">here</a>';
				return false;
			}

			return true;
		}	
		
	 public function admin_options(){
            echo '<h3>GTPay Payment Gateway</h3>';
            echo '<p>GTPay Payment Gateway allows you to accept payment through different channels such as Interswitch, Mastercard, Verve cards and Visa cards.</p>';


			if ($this->is_valid_for_use() ){

	            echo '<table class="form-table">';
	            $this->generate_settings_html();
	            echo '</table>';
            }
			else{	 ?>
			<div class="inline error"><p><strong>GTPay Payment Gateway Disabled</strong>: <?php echo $this->msg ?></p></div>

			<?php }
        }	
	
	function generateTransID()
{
$s = date('Ymd');
$s .= substr(str_shuffle(str_repeat("0123456789",5)),0,8);	
return $s;
}
	
	function get_gtpay_args( $order ) {
            $txid = $this->generateTransID();
			$order_id 		= $order->id;
			$order_total	= $order->get_total() * 100;
			$merchantID 	= $this->GTPayMerchantId;
			$memo        	= "Payment for Order ID: $order_id on ". get_bloginfo('name');

			$success_url  	= $this->get_return_url( $order );
			
			$hash = preg_replace('/[^a-zA-Z0-9_%\[().\]\\/-]/s', '', $this->hashKey);
			
			$concat = $txid.$order_total.$success_url.$hash;
			
			$paramhash = hash('sha512',$concat);

			// gtpay Args
			$gtpay_args = array(
			
			    'gtpay_mert_id' => $merchantID,
				'gtpay_tranx_id' => $txid,
				'gtpay_tranx_amt' => $order_total,
				'gtpay_tranx_curr' => 566,
				'gtpay_cust_id' => $order_id, 
				'gtpay_cust_name' => '',
				'gtpay_tranx_memo' => $memo,
				'gtpay_no_show_gtbank' => 'yes',
				'gtpay_echo_data' => '',
				'gtpay_gway_name' => '',
				'gtpay_tranx_hash' => $paramhash,
				'gtpay_tranx_noti_url' => $success_url,
			);

			$gtpay_args = apply_filters( 'woocommerce_gtpay_args', $gtpay_args );
			return $gtpay_args;
		}	
		
	 function generate_gtpay_form( $order_id ) {

			$order = wc_get_order( $order_id );

			$gtpay_args = $this->get_gtpay_args( $order );

			$gtpay_args_array = array();

			foreach ($gtpay_args as $key => $value) {
				$gtpay_args_array[] = '<input type="hidden" name="'.esc_attr( $key ).'" value="'.esc_attr( $value ).'" />';
			}

			wc_enqueue_js( '
				$.blockUI({
						message: "' . esc_js( __( 'Thank you for your order. We are now redirecting you to the gateway to make payment.', 'woocommerce' ) ) . '",
						baseZ: 99999,
						overlayCSS:
						{
							background: "#fff",
							opacity: 0.6
						},
						css: {
							padding:        "20px",
							zindex:         "9999999",
							textAlign:      "center",
							color:          "#555",
							border:         "3px solid #aaa",
							backgroundColor:"#fff",
							cursor:         "wait",
							lineHeight:		"24px",
						}
					});
				jQuery("#submit_gtpay_payment_form").click();
			' );

			return '<form action="' . $this->payment_url . '" method="post" id="gtpay_payment_form" target="_top">
					' . implode( '', $gtpay_args_array ) . '
					<!-- Button Fallback -->
					<div class="payment_buttons">
						<input type="submit" class="button alt" id="submit_gtpay_payment_form" value="Make Payment" /> <a class="button cancel" href="' . esc_url( $order->get_cancel_order_url() ) . '">Cancel order &amp; restore cart</a>
					</div>
					<script type="text/javascript">
						jQuery(".payment_buttons").hide();
					</script>
				</form>';
		}	
		
	function process_payment( $order_id ) {

			$order = wc_get_order( $order_id );

	        return array(
	        	'result' 	=> 'success',
				'redirect'	=> $order->get_checkout_payment_url( true )
	        );}
				
	function receipt_page( $order ) {
			echo '<p>Thank you - your order is now pending payment. You should be automatically redirected to the gateway to make payment</p>';

			echo $this->generate_gtpay_form( $order );
		}	
    
	function check_gtpay_response( $posted ) {
// gtpay processing response
if(isset($_POST['gtpay_tranx_id']))
{
$txdid = 	$_POST['gtpay_tranx_id'];	
$order_id = $_POST['gtpay_cust_id']	;  
$hash = preg_replace('/[^a-zA-Z0-9_%\[().\]\\/-]/s', '', $this->hashKey);
$merchantID 	= $this->GTPayMerchantId;
$all = $merchantID.$tcdid.$hash;
$hashparam = hash('sha512',$all);
$order 	= wc_get_order($order_id);
$order_total	= $order->get_total();
$urlloc = 'https://ibank.gtbank.com/GTPayService/gettransactionstatus.xml?mertid='.$merchantID.'&tranxid='.$txdid.'&hash='.$hashparam;
$xml = simplexml_load_file($urlloc);
do_action( 'wc_gtpay_after_payment', $xml);	
$Amount = $xml->Amount/100;
$MerchantRef =  $xml->MerchantReference;
$merid =  $xml->MertID;
$ResponseCode =  $xml->ResponseCode;
$ResponseDescription = $xml->ResponseDescription;

if(isset($ResponseCode) && $ResponseCode == "00")
{
if($merid != $merchantID)	
{


		                //Update the order status
						$order->update_status('on-hold', '');

						//Error Note
						$message = 'Thank you for shopping with us.<br />Your payment transaction was successful, but the amount was paid to the wrong merchant account. Illegal hack attempt.<br />Your order is currently on-hold.<br />Kindly contact us for more information regarding your order and payment status.';
						$message_type = 'notice';

						//Add Customer Order Note
	                    $order->add_order_note($message.'<br />GTPay Transaction ID: '.$MerchantRef, 1);

	                    //Add Admin Order Note
	                    $order->add_order_note('Look into this order. <br />This order is currently on hold.<br />Reason: Illegal hack attempt. The order was successfull but the money was paid to the wrong GTpay account.<br /> Your GTPay Merchant ID '. $this->GTPayMerchantId .' the Merchant ID the payment was sent to '.$merid.'<br />GTPay Transaction ID: '.$MerchantRef);

						// Reduce stock levels
						$order->reduce_order_stock();

						// Empty cart
						wc_empty_cart();
	
}
else
{
if($Amount != $order_total )	
{
 //Update the order status
							$order->update_status('on-hold', '');

							//Error Note
							$message = 'Thank you for shopping with us.<br />Your payment transaction was successful, but the amount paid is not the same as the total order amount.<br />Your order is currently on-hold.<br />Kindly contact us for more information regarding your order and payment status.';
							$message_type = 'notice';

							//Add Customer Order Note
		                    $order->add_order_note($message.'<br />GTPay Transaction ID: '.$MerchantRef, 1);

		                    //Add Admin Order Note
		                    $order->add_order_note('Look into this order. <br />This order is currently on hold.<br />Reason: Amount paid is less than the total order amount.<br />Amount Paid was &#8358; '.$Amount.' while the total order amount is &#8358; '.$order_total.'<br />Voguepay Transaction ID: '.$MerchantRef);

							// Reduce stock levels
							$order->reduce_order_stock();

							// Empty cart
							wc_empty_cart();	
}
else
{
//if( $order->status == 'processing' ) 
/*{
	$order->add_order_note('Payment Via GTpay<br />Transaction ID: '.$MerchantRef);

	                   //Add customer order note
	$order->add_order_note('Payment Received.<br />Your order is currently being processed.<br />We will be shipping your order to you soon.<br />GTPay Transaction ID: '.$MerchantRef, 1);

					// Reduce stock levels
			$order->reduce_order_stock();

								// Empty cart
								wc_empty_cart();

								$message = 'Thank you for shopping with us.<br />Your transaction was successful, payment was received.<br />Your order is currently being processed.';
								$message_type = 'success';
			                }	*/

if( $order->has_downloadable_item() ) {

			                		//Update order status
									$order->update_status( 'completed', 'Payment received, your order is now complete.' );

				                    //Add admin order note
				                    $order->add_order_note('Payment Via GTPay Payment Gateway<br />Transaction ID: '.$MerchantRef);

				                    //Add customer order note
				 					$order->add_order_note('Payment Received.<br />Your order is now complete.<br />GTPay Transaction ID: '.$MerchantRef, 1);

									$message = 'Thank you for shopping with us.<br />Your transaction was successful, payment was received.<br />Your order is now complete.';
									$message_type = 'success';

			                	}
							
							
							
								else
								{
									//Update order status
									$order->update_status( 'processing', 'Payment received, your order is currently being processed.' );

									//Add admin order noote
				                    $order->add_order_note('Payment Via GTPay Show This Payment Gateway<br />Transaction ID: '.$MerchantRef);

				                    //Add customer order note
				 					$order->add_order_note('Payment Received.<br />Your order is currently being processed.<br />We will be shipping your order to you soon.<br />GTPay Transaction ID: '.$MerchantRef, 1);

									$message = 'Thank you for shopping with us.<br />Your transaction was successful, payment was received.<br />Your order is currently being processed.';
									$message_type = 'success';
								}
								// Reduce stock levels
								$order->reduce_order_stock();

								// Empty cart
								wc_empty_cart();
								
					
	
}

} //
 $gtpay_message = array(
	                	'message'	=> $message,
	                	'message_type' => $message_type
	                );

					if ( version_compare( WOOCOMMERCE_VERSION, "2.2" ) >= 0 ) {
						add_post_meta( $order_id, '_gtpay_tranx_id', $txdid, true );
					}

					update_post_meta( $order_id, '_gtpay_message', $gtpay_message );

                    die( 'IPN Processed OK. Payment Successfully' );

}

else
{
	            	$message = 	'Thank you for shopping with us. <br />However, the transaction wasn\'t successful, payment wasn\'t received.';
					$message_type = 'error';

					//Add Customer Order Note
                   	$order->add_order_note($message.'<br />GTPay Transaction ID: '.$txdid, 1);

                    //Add Admin Order Note
                  	$order->add_order_note($message.'<br />GTPay Transaction ID: '.$txdid);

	                //Update the order status
					$order->update_status('failed', '');

	                $gtpay_message = array(
	                	'message'	=> $message,
	                	'message_type' => $message_type
	                );

					update_post_meta( $order_id, '_gtpay_message', $gtpay_message );

					if ( version_compare( WOOCOMMERCE_VERSION, "2.2" ) >= 0 ) {
						add_post_meta( $order_id, '_gtpay_tranx_id', $txdid, true );
					}

                    die( 'IPN Processed OK. Payment Failed' );
	            
}
}
else {

            	$message = 	'Thank you for shopping with us. <br />However, the transaction wasn\'t successful, payment wasn\'t received.';
				$message_type = 'error';

                $gtpay_message = array(
                	'message'	=> $message,
                	'message_type' => $message_type
                );

				update_post_meta( $order_id, '_gtpay_message', $gtpay_message );

                die( 'IPN Processed OK' );
			}
// end of gtpay response

	}
}

function wc_gtpay_message() {

		$order_id 		= absint( get_query_var( 'order-received' ) );
		$order 			= wc_get_order( $order_id );
		$payment_method = $order->payment_method;

		if( is_order_received_page() &&  ( 'gtpay_gateway' == $payment_method ) ){

			$gtpay_message 	= get_post_meta( $order_id, '_gtpay_message', true );

			if( ! empty( $gtpay_message ) ){

				$message 			= $gtpay_message['message'];
				$message_type 		= $gtpay_message['message_type'];

				delete_post_meta( $order_id, '_gtpay_message' );

				wc_add_notice( $message, $message_type );
			}
		}
	}
	add_action( 'wp', 'wc_gtpay_message' );


	function wc_add_gtpay_gateway($methods) {
		$methods[] = 'WC_GTpay_Gateway';
		return $methods;
	}

	add_filter( 'woocommerce_payment_gateways', 'wc_add_gtpay_gateway' );
	
	if ( version_compare( WOOCOMMERCE_VERSION, "2.1" ) <= 0 ) {

		/**
		* Add NGN as a currency in WC
		**/
		add_filter( 'woocommerce_currencies', 'add_my_currency' );

		if( ! function_exists( 'add_my_currency' )){
			function add_my_currency( $currencies ) {
			     $currencies['NGN'] = __( 'Naira', 'woocommerce' );
			     return $currencies;
			}
		}

		/**
		* Enable the naira currency symbol in WC
		**/
		add_filter('woocommerce_currency_symbol', 'add_my_currency_symbol', 10, 2);

		if( ! function_exists( 'add_my_currency_symbol' ) ){
			function add_my_currency_symbol( $currency_symbol, $currency ) {
			     switch( $currency ) {
			          case 'NGN': $currency_symbol = '&#8358; '; break;
			     }
			     return $currency_symbol;
			}
		}
	}

   if ( version_compare( WOOCOMMERCE_VERSION, "2.1" ) <= 0 ) {

		add_filter('plugin_action_links', 'gtpay_plugin_action_links', 10, 2);

		function gtpay_plugin_action_links($links, $file) {
		    static $this_plugin;

		    if (!$this_plugin) {
		        $this_plugin = plugin_basename(__FILE__);
		    }

		    if ($file == $this_plugin) {
	        $settings_link = '<a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=woocommerce_settings&tab=payment_gateways&section=WC_GTpay_Gateway">Settings</a>';
		        array_unshift($links, $settings_link);
		    }
		    return $links;
		}
	}
	
    else{
		add_filter('plugin_action_links', 'gtpay_plugin_action_links', 10, 2);

		function gtpay_plugin_action_links($links, $file) {
		    static $this_plugin;

		    if (!$this_plugin) {
		        $this_plugin = plugin_basename(__FILE__);
		    }

		    if ($file == $this_plugin) {
		        $settings_link = '<a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=wc_gtpay_gateway">Settings</a>';
		        array_unshift($links, $settings_link);
		    }
		    return $links;
		}
	}	

}


?>