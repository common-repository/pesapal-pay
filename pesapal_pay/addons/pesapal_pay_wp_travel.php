<?php
class PesaPal_Pay_Wp_travel {

	/**
	* @var The single instance of the class
	* @since 26
	*/
	protected static $_instance = null;


	//Get instance
	public static function instance() {
	  	if ( is_null( self::$_instance ) ) {
		 	self::$_instance = new self();
	  	}
	  	return self::$_instance;
	}

	function __construct() {
		add_filter( 'wp_travel_payment_gateway_lists', array(  $this, 'gateway_list' ) );
		add_action( 'wp_travel_payment_gateway_fields', array( $this, 'init_fields' ) );
		add_filter( 'wp_travel_before_save_settings', array(  $this, 'save_settings' ) );
		add_action( 'wp_travel_after_frontend_booking_save', array( $this, 'process' ) );
		add_action( 'wp_travel_before_content_start', array( $this, 'after_payment' )  );
		add_action( 'pesapal_pay_process_success_ipn_transaction', array( $this, 'after_ipn' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	function gateway_list( $gateway ) {
		$gateway['pesapal'] = __( 'PesaPal' );
		return $gateway;
	}

	function init_fields( $settings ) {
		$payment_option_pesapal = ( isset( $settings['settings']['payment_option_pesapal'] ) ) ? $settings['settings']['payment_option_pesapal'] : '';
		?>
		<h3 class="wp-travel-tab-content-title"><?php esc_html_e( 'PesaPal Pay' )?></h3>
		<table class="form-table">
			<tr>
				<th><label for="payment_option_pesapal"><?php esc_html_e( 'Enable PesaPal' ) ?></label></th>
				<td>
					<span class="show-in-frontend checkbox-default-design">
					<label data-on="ON" data-off="OFF">
						<input type="checkbox" value="yes" <?php checked( 'yes', $payment_option_pesapal ) ?> name="payment_option_pesapal" id="payment_option_pesapal"/>
						<span class="switch">
					</span>

					</label>
				</span>
					<p class="description"><?php esc_html_e( 'Check to enable PesaPal gateway' ) ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	function save_settings( $settings ) {
		$payment_option_pesapal = ( isset( $_POST['payment_option_pesapal'] ) && '' !== $_POST['payment_option_pesapal'] ) ? $_POST['payment_option_pesapal'] : '';
		$settings['payment_option_pesapal'] = $payment_option_pesapal;
		return $settings;
	}

	function process( $booking_id ) {
		if ( ! $booking_id ) {
			return;
		}

		do_action( 'wt_before_payment_process', $booking_id );

		// Check if pesapal is selected.
		if ( ! isset( $_POST['wp_travel_payment_gateway'] ) || 'pesapal' !== $_POST['wp_travel_payment_gateway'] ) {
			return;
		}
		// Check if Booking with payment is selected.
		if ( ! isset( $_POST['wp_travel_booking_option'] ) || 'booking_with_payment' !== $_POST['wp_travel_booking_option'] ) {
			return;
		}

		global $wt_cart;
		$items = $wt_cart->getItems();
		
		if ( ! $items ) {
			return false;
		}
		
		$itinery_id    	= isset( $_POST['wp_travel_post_id'] ) ? $_POST['wp_travel_post_id']          : 0;
		$currency_code 	= ( isset( $settings['currency'] ) ) ? $settings['currency']                  : '';
		$current_url   	= get_permalink( $itinery_id );
		$current_url   	= apply_filters( 'wp_travel_thankyou_page_url', $current_url, $booking_id );
		$current_url	= add_query_arg( array( 'booking_id' => $booking_id, 'pp_booked' => true ), $current_url );
		$cart_amounts  	= $wt_cart->get_total();
		$total 			= $cart_amounts['sub_total'];
		$payment_id 	= get_post_meta( $booking_id, 'wp_travel_payment_id', true );
		update_post_meta( $payment_id, 'wp_travel_payment_amount',$total );
		if ( isset( $_POST['wp_travel_fname'] ) || isset( $_POST['wp_travel_email'] ) ) { // Booking using old booking form
			$first_name = $_POST['wp_travel_fname'];
			$last_name 	= $_POST['wp_travel_lname'];
			$email 		= $_POST['wp_travel_email'];
		} else {
			$first_name = $_POST['wp_travel_fname_traveller'];
			$last_name 	= $_POST['wp_travel_lname_traveller'];
			$email 		= $_POST['wp_travel_email_traveller'];
			
			reset( $first_name );
			$first_key = key( $first_name );
		
			$first_name = isset( $first_name[ $first_key ] ) && isset( $first_name[ $first_key ][0] ) ? $first_name[ $first_key ][0] : '';
			$last_name = isset( $last_name[ $first_key ] ) && isset( $last_name[ $first_key ][0] ) ? $last_name[ $first_key ][0] : '';
			$email = isset( $email[ $first_key ] ) && isset( $email[ $first_key ][0] ) ? $email[ $first_key ][0] : '';
		}

		$settings = wp_travel_get_settings();
		$currency_code = ( isset( $settings['currency'] ) ) ? $settings['currency'] : '';
		pesapal_pay()->save_transaction_with_values_return( $first_name . ' ' . $last_name ,$email,$total, $current_url, $booking_id, $currency_code );
		exit();
	}


	function after_payment() {
		if ( isset( $_GET['pp_booked'] ) && 1 == $_GET['pp_booked'] ) {
			$updated_status = pesapal_pay()->payment_process();
			$url = remove_query_arg( 'pp_booked' );
			$url = add_query_arg( array( 'booked' => true ), $url );
			if ( $updated_status == 'order_paid' ) {
				$booking_id 	=  $_GET['booking_id'];
				update_post_meta( $booking_id, 'wp_travel_booking_status', 'booked' );
				$payment_id = get_post_meta( $booking_id, 'wp_travel_payment_id', true );
				update_post_meta( $payment_id, 'wp_travel_payment_status', 'paid' );

				do_action( 'wp_travel_after_successful_payment', $booking_id );
			}
			wp_redirect( $url );
			exit;
		}
	}

	function after_ipn( $updated_status, $booking_id ) {
		if ( $updated_status == 'order_paid' ) {
			update_post_meta( $booking_id, 'wp_travel_booking_status', 'booked' );
			$payment_id = get_post_meta( $booking_id, 'wp_travel_payment_id', true );
			update_post_meta( $payment_id, 'wp_travel_payment_status', 'paid' );
		}
	}

	function enqueue_scripts() {
		wp_register_script(
			'pp-wp-travel',
			pesapal_pay()->plugin_url . 'resources/wp-travel.js',
			array( 'jquery' ), '1'
		);
		wp_enqueue_script( 'pp-wp-travel' );
	}
}
PesaPal_Pay_Wp_travel::instance();
?>