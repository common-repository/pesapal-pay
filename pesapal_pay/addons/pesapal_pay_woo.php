<?php

if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) :

	pesapal_pay()->load_pesapal_lib();

	class PesaPal_Pay_Woo extends WC_Payment_Gateway {
		function __construct(){
				
			//Settings
			$this->id 			= 'pesapal';
			$this->method_title = 'Pesapal Pay';
			$this->has_fields 	= false;
			$this->testmode 	= ($this->get_option('testmode') === 'yes') ? true : false;
			$this->debug 		= $this->get_option( 'debug' );
			$this->title 		= $this->get_option('title');
			$this->description 	= $this->get_option('description', 'Add PesaPal to your site' );
			
			//Set up logging
			if ( 'yes' == $this->debug ) {
				if ( class_exists('WC_Logger') ) {
					$this->log = new WC_Logger();
				} else {
					$this->log = $woocommerce->logger();
				}
			}
			$settings  	= pesapal_pay()->get_options();
			$api  		= 'https://www.pesapal.com/';
			$this->consumer_key 	= $settings['customer_key'];
			$this->consumer_secret 	= $settings['customer_secret'];
			
			
			//OAuth Signatures
			$this->consumer 			= new PesaPalOAuthConsumer($this->consumer_key, $this->consumer_secret);
			$this->signature_method  	= new PesaPalOAuthSignatureMethod_HMAC_SHA1();
			$this->token 				= $this->params = NULL;
			
			//PesaPal End Points
			$this->gatewayURL 						= $api.'api/PostPesapalDirectOrderV4';
			$this->QueryPaymentStatus 				= $api.'API/QueryPaymentStatus';
			$this->QueryPaymentStatusByMerchantRef  = $api.'API/QueryPaymentStatusByMerchantRef';
			$this->querypaymentdetails 				= $api.'API/querypaymentdetails';
			
			//IPN URL
			$this->notify_url   					= str_replace( 'https:', 'http:', add_query_arg( 'wc-api', 'WC_Pesapal_Gateway', home_url( '/' ) ) );
			
			$this->init_form_fields();
			$this->init_settings();
			
			if (is_admin()){
				add_action('woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			}
			
			add_action('woocommerce_receipt_'.$this->id, array(&$this, 'payment_page'));
			add_action('woocommerce_api_wc_'.$this->id.'_gateway', array( $this, 'ipn_response' ) );
			add_action($this->id.'_process_valid_ipn_request', array($this, 'process_valid_ipn_request'));
			
		}
		
		
		function init_form_fields() {
			$this->form_fields = array(
				'enabled' => array(
					'title' => __( 'Enable/Disable', 'woothemes' ),
					'type' => 'checkbox',
					'label' => __( 'Enable Pesapal Payment', 'woothemes' ),
					'default' => 'no'
				),
				'title' => array(
					'title' => __( 'Title', 'woothemes' ),
					'type' => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'woothemes' ),
					'default' => __( 'Pesapal Payment', 'woothemes' )
				),
				'description' => array(
					'title' => __( 'Description', 'woocommerce' ),
					'type' => 'textarea',
					'description' => __( 'This is the description which the user sees during checkout.', 'woocommerce' ),
					'default' => __("Payment via Pesapal Gateway, you can pay by either credit/debit card or use mobile payment option such as Mpesa.", 'woocommerce')
				),
				
				'debug' => array(
					'title' => __( 'Debug Log', 'woocommerce' ),
					'type' => 'checkbox',
					'label' => __( 'Enable logging', 'woocommerce' ),
					'default' => 'no',
					'description' => sprintf( __( 'Log PesaPal events, such as IPN requests, inside <code>woocommerce/logs/pesapal-%s.txt</code>', 'woocommerce' ), sanitize_file_name( wp_hash( 'pesapal' ) ) ),
				)
				
				
			);
		}
		
		public function admin_options() {
			$page_url = admin_url( 'edit.php?post_type=pesapal_pay&page=settings' );
			?>
			<h3><?php _e('Pesapal', 'woothemes'); ?></h3>
			<p>
				<?php _e('PesaPal requires Full names and email/phone number. To handle APN return requests, please set the url '); ?>
				<strong><?php echo $this->notify_url; ?></strong>
				<?php _e(' in your <a href="https://www.pesapal.com/merchantdashboard" target="_blank">PesaPal</a> account settings'); ?>
			</p>
			<p>
				<?php echo sprintf( __( 'Your PesaPal settings can be found %shere%s ' ), '<a href="'.$page_url.'">', '</a>' ); ?>
			</p>
			<?php
		}
		
		
		function process_payment( $order_id ) {
			global $woocommerce;
		
			$order = wc_get_order( $order_id );
			
			if($order->get_status() === 'completed'){
				//Redirect to payment page
				return array(
					'result'    => 'success',
					'redirect'  => $this->get_return_url( $order )
				);
			}else{
				return array(
					'result'    => 'success',
					'redirect'  => $order->get_checkout_payment_url(true)
				);
			}
		}
		
		
		//Create Payment Page
		function payment_page($order_id){
			if(isset($_REQUEST['pesapal_merchant_reference'])){
				$order = wc_get_order( $order_id );
				$pesapalMerchantReference = $_REQUEST['pesapal_merchant_reference'];
				$pesapalTrackingId = $_REQUEST['pesapal_transaction_tracking_id'];
				
				$transactionDetails	= $this->getTransactionDetails($pesapalMerchantReference,$pesapalTrackingId);
				
				add_post_meta( $order_id, '_order_pesapal_transaction_tracking_id', $transactionDetails['pesapal_transaction_tracking_id']);
				add_post_meta( $order_id, '_order_thebunchke_pesapalment_method', $transactionDetails['payment_method']);
				
				add_post_meta( $order_id, '_order_payment_method', $transactionDetails['payment_method']);
				
				if($transactionDetails['status'] === 'COMPLETED'){
					$order->update_status('wc-completed', 'order_note'); 
					$order->payment_complete();
				}else{
					$order->update_status('wc-processing', 'Payment accepted, awaiting confirmation'); 
				}
				return array(
					'result'   => 'success',
					'redirect' => $this->get_return_url( $order )
				);
				
			}else{
				$url = $this->create_url($order_id);
				?>
				<div class="pesapal_container" style="position:relative;">
					<img class="pesapal_loading_preloader" src="<?php echo THEBUNCHKE_PESAPAL_WOO_PLUGIN_URL; ?>/assets/img/loader.gif" alt="loading" style="position:absolute;"/>
					<iframe class="pesapal_loading_frame" src="<?php echo $url; ?>" width="100%" height="700px"  scrolling="yes" frameBorder="0">
						<p><?php _e('Browser unable to load iFrame', 'woothemes'); ?></p>
					</iframe>
				</div>
				<script>
					jQuery(document).ready(function () {
						jQuery('.pesapal_loading_frame').on('load', function () {
							jQuery('.pesapal_loading_preloader').hide();
						});
					});
				</script>
				<?php
			}
		}
		
		function thankyou_page($order_id) {

			if(isset($_REQUEST['pesapal_transaction_tracking_id'])){
				$order = wc_get_order( $order_id );
				$pesapalMerchantReference = $_REQUEST['pesapal_merchant_reference'];
				$pesapalTrackingId = $_REQUEST['pesapal_transaction_tracking_id'];
				
				$transactionDetails	= $this->getTransactionDetails($pesapalMerchantReference,$pesapalTrackingId);
				
				$order->add_order_note( __('Payment accepted, awaiting confirmation.', 'woothemes') );
				add_post_meta( $order_id, '_order_pesapal_transaction_tracking_id', $transactionDetails['pesapal_transaction_tracking_id']);
				add_post_meta( $order_id, '_order_thebunchke_pesapalment_method', $transactionDetails['payment_method']);
				
				add_post_meta( $order_id, '_order_payment_method', $transactionDetails['payment_method']);
				
				if($transactionDetails['status'] === 'COMPLETED'){
					$order->update_status('wc-completed', 'order_note'); 
					$order->payment_complete();
				}else{
					$order->update_status('wc-processing', 'Payment accepted, awaiting confirmation'); 
				}
			}
			WC()->cart->empty_cart();
		}
		
		
		/**
		 * Create iframe URL
		 *
		 */
		function create_url($order_id){
			$order = wc_get_order( $order_id );
			$order_xml = $this->pesapal_xml($order,$order_id);
			$callback_url = $this->get_return_url( $order ); //add_query_arg('key', $order->order_key, add_query_arg('order', $order_id, $this->get_return_url( $order )));
			//$callback_url = add_query_arg('key', $order->order_key, add_query_arg('order', $order_id, $order->get_checkout_order_received_url()));
			
			$url = PesaPalOAuthRequest::from_consumer_and_token($this->consumer, $this->token, "GET", $this->gatewayURL, $this->params);
			$url->set_parameter("oauth_callback", $callback_url);
			$url->set_parameter("pesapal_request_data", $order_xml);
			$url->sign_request($this->signature_method, $this->consumer, $this->token);
			return $url;
		}
		
		/**
		 * Generate PesaPal XML
		 */
		function pesapal_xml($order,$order_id) {
			$pesapal_args['total'] = $order->get_total();
			$pesapal_args['reference'] = $order_id;
			$pesapal_args['first_name'] = $order->get_billing_first_name();
			$pesapal_args['last_name'] = $order->get_billing_last_name();
			$pesapal_args['email'] = $order->get_billing_email();
			$pesapal_args['phone'] = $order->get_billing_phone();
		  
		 
		  
			$xml = "<?xml version=\"1.0\" encoding=\"utf-8\"?>
				<PesapalDirectOrderInfo xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xmlns:xsd=\"http://www.w3.org/2001/XMLSchema\"
				Amount=\"" . $pesapal_args['total'] . "\"
				Description=\"Order from " . bloginfo('name') . ".\"
				Type=\"MERCHANT\"
				Reference=\"" . $pesapal_args['reference'] . "\"
				FirstName=\"" . $pesapal_args['first_name'] . "\"
				LastName=\"" . $pesapal_args['last_name'] . "\"
				Email=\"" . $pesapal_args['email'] . "\"
				PhoneNumber=\"" . $pesapal_args['phone'] . "\"
				Currency=\"" . get_woocommerce_currency() . "\"
				xmlns=\"http://www.pesapal.com\" />";
		  
			return htmlentities($xml);
		}
		
		
		function status_request($transaction_id, $merchant_ref){
			$request_status = PesaPalOAuthRequest::from_consumer_and_token($this->consumer, $this->token, "GET", $this->gatewayURL, $this->params);
			$request_status->set_parameter("pesapal_merchant_reference", $merchant_ref);
			$request_status->set_parameter("pesapal_transaction_tracking_id", $transaction_id);
			$request_status->sign_request($this->signature_method, $this->consumer, $this->token);
		  
			return $this->checkTransactionStatus($merchant_ref);
		}
		
		
		/**
		 * Check Transaction status
		 *
		 * @return PENDING/FAILED/INVALID
		 **/
		function checkTransactionStatus($pesapalMerchantReference,$pesapalTrackingId = NULL){
			if($pesapalTrackingId)
				$queryURL = $this->QueryPaymentStatus;
			else
				$queryURL = $this->QueryPaymentStatusByMerchantRef;
	  
			//get transaction status
			$request_status = PesaPalOAuthRequest::from_consumer_and_token(
								$this->consumer, 
								$this->token, 
								"GET", 
								$queryURL, 
								$this->params
							  );
	
			$request_status->set_parameter("pesapal_merchant_reference", $pesapalMerchantReference);
	
			if($pesapalTrackingId)
				$request_status->set_parameter("pesapal_transaction_tracking_id",$pesapalTrackingId);
	  
			$request_status->sign_request($this->signature_method, $this->consumer, $this->token);
  
			return $this->curlRequest($request_status);
		}

		/**
		 * Check Transaction status
		 *
		 * @return PENDING/FAILED/INVALID
		**/
		function getTransactionDetails($pesapalMerchantReference,$pesapalTrackingId){

			$request_status = PesaPalOAuthRequest::from_consumer_and_token(
								$this->consumer, 
								$this->token, 
								"GET", 
								$this->querypaymentdetails, 
								$this->params
							  );
	
			$request_status->set_parameter("pesapal_merchant_reference", $pesapalMerchantReference);
			$request_status->set_parameter("pesapal_transaction_tracking_id",$pesapalTrackingId);
			$request_status->sign_request($this->signature_method, $this->consumer, $this->token);
  
			$responseData = $this->curlRequest($request_status);
		  
			$pesapalResponse = explode(",", $responseData);
			$pesapalResponseArray=array('pesapal_transaction_tracking_id'=>$pesapalResponse[0],
								'payment_method'=>$pesapalResponse[1],
								'status'=>$pesapalResponse[2],
								'pesapal_merchant_reference'=>$pesapalResponse[3]
							  );
							 
			return $pesapalResponseArray;
		}

		/**
		 * Check Transaction status
		 *
		 * @return ARRAY
		 **/
		function curlRequest($request_status){
			
			$response = wp_remote_get( $request_status );
			if ( is_wp_error( $response) ) {
				return __( 'An Error Occurred' );
			} else {
				$resp_body 	= wp_remote_retrieve_body( $response );
				return $resp_body;
			}

		}

		/**
		 * IPN Response
		 *
		 * @return null
		 **/
		function ipn_response(){
			$pesapalTrackingId 		= '';
			$pesapalNotification 		= '';
			$pesapalMerchantReference	= '';

			if(isset($_REQUEST['pesapal_merchant_reference']))
				$pesapalMerchantReference = $_REQUEST['pesapal_merchant_reference'];
				  
			if(isset($_REQUEST['pesapal_transaction_tracking_id']))
				$pesapalTrackingId = $_REQUEST['pesapal_transaction_tracking_id'];
				  
			if(isset($_REQUEST['pesapal_notification_type']))
				$pesapalNotification=$_REQUEST['pesapal_notification_type'];

			$transactionDetails	= $this->getTransactionDetails($pesapalMerchantReference,$pesapalTrackingId);
			$order 				= wc_get_order( $pesapalMerchantReference );
			if ( $order ) {
				// We are here so lets check status and do actions
				switch ( $transactionDetails['status'] ) {
					case 'COMPLETED' :
					case 'PENDING' :

						// Check order not already completed
						if ( $order->get_status() == 'completed' ) {
							if ( 'yes' == $this->debug )
								$this->log->add( 'pesapal', 'Aborting, Order #' . $order->id . ' is already complete.' );
							exit;
						}

						if ( $transactionDetails['status'] == 'COMPLETED' ) {
							$order->add_order_note( __( 'IPN payment completed', 'woocommerce' ) );
							$order->payment_complete();
						} else {
							$order->update_status( 'on-hold', sprintf( __( 'Payment pending: %s', 'woocommerce' ), 'Waiting PesaPal confirmation' ) );
						}

						if ( 'yes' == $this->debug )
							$this->log->add( 'pesapal', 'Payment complete.' );

						break;
					case 'INVALID' :
					case 'FAILED' :
						// Order failed
						$order->update_status( 'failed', sprintf( __( 'Payment %s via IPN.', 'woocommerce' ), strtolower( $transactionDetails['status'] ) ) );
						break;

					default :
						// No action
						break;
				}
			}

			if ( $pesapalNotification =="CHANGE" && $transactionDetails['status'] != "PENDING" ){    
				$resp = "pesapal_notification_type=$pesapalNotification".		
						"&pesapal_transaction_tracking_id=$pesapalTrackingId".
						"&pesapal_merchant_reference=$pesapalMerchantReference";
									
				ob_start();
				echo $resp;
				ob_flush();
				exit;
			}      
		}
	}

	add_filter('woocommerce_payment_gateways', 'pesapal_pay_add_woo_gateway_class' );
	function pesapal_pay_add_woo_gateway_class( $methods ) {
		$methods[] = 'PesaPal_Pay_Woo'; 
		return $methods;
	}
endif;
?>