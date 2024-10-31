<?php
/*
Plugin Name: Pesapal Pay
Description: A quick way to integrate pesapal to your website to handle the payment process. All you need to do is set up what parameters to capture from the form and the plugin will do the rest
Version: 3.2.2
Author: rixeo
Author URI: http://thebunch.co.ke/
Plugin URI: http://dev.thebunch.co.ke/wordpress/pesapal-pay/
*/

class PesaPal_Pay{
	
	/**
	 * Uniquely identify plugin version
	 * Bust caches based on this value
	 *
	 * @since 2.0
	 *
	 * @var string
	 */
	var $version = '3.2.2';
	
	/**
	 * Plugin Directory
	 *
	 * @since 2.0
	 *
	 * @var string
	 */
	var $plugin_dir	= '';
	
	/**
	 * Plugin URL
	 *
	 * @since 2.0
	 *
	 * @var string
	 */
	var $plugin_url= '';
	
	/**
	 * Plugin File
	 *
	 * @since 2.0
	 *
	 * @var string
	 */
	var $plugin_file= '';
	
	/**
	 * Post URL for PesaPal
	 *
	 * @since 2.0
	 *
	 * @var string
	 */
	var $post_url = 'https://www.pesapal.com/api/PostPesapalDirectOrderV4';
		
		
	/**
	 * Status Request URL for PesaPal
	 *
	 * @since 2.0
	 *
	 * @var string
	 */
	var $status_request = 'https://www.pesapal.com/api/querypaymentstatus';
	
	/**
	 * Form Elements
	 *
	 * @since 3.0
	 *
	 * @var array
	 */
	var $form_elements = array('Text' => 'text', 'Text Area' => 'textarea', 'Check Box' => 'checkbox', 'Paragraph' => 'paragraph');
	
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
		$this->init_vars();
		add_action('init', array(&$this, 'install'));
		//custom post type
		add_action('init', array(&$this, 'custom_post_type'));

		$this->init();
		
		//manage orders page
		add_filter( 'manage_pesapal_pay_posts_columns', array( &$this, 'manage_orders_columns' ) );
		add_action( 'manage_pesapal_pay_posts_custom_column', array( &$this, 'manage_orders_custom_columns' ), 10, 2 );
		add_filter( 'manage_edit-pesapal_pay_sortable_columns', array( &$this,'manage_orders_sortable_columns') );
		add_filter('views_edit-pesapal_pay', array( &$this,'post_status_lables'), 10, 1);
		add_filter('bulk_actions-edit-pesapal_pay',array( &$this, 'manage_bulk_actions' ));
		
		//Meta Boxes
		add_action('add_meta_boxes', array(&$this,'set_up_meta_box'));
		add_action('save_post', array(&$this,'meta_save'));
		
		
		//Create and register Admin menu
		add_action("admin_menu", array(&$this, 'admin_menu' ));
		
		
		//Content filter
		add_filter( 'the_content', array(&$this, 'content_filter' ) );
		
		//Script for Ajax
		add_action( 'admin_print_scripts', array(&$this,'admin_inline_js') );
		
		//Ajax for order status
		add_action('wp_ajax_pesapal_change_order_status', array(&$this, 'change_order_status') );
		
		
		//Ajax actions to save transaction
		add_action( 'wp_ajax_nopriv_pesapal_save_transaction', array(&$this,'save_transaction'));
		add_action( 'wp_ajax_pesapal_save_transaction', array(&$this,'save_transaction'));
		
		//IPN Return
		add_action( 'wp_ajax_nopriv_pesapalpay_ipn_return', array(&$this,'ipn_return'));
		add_action( 'wp_ajax_pesapalpay_ipn_return', array(&$this,'ipn_return'));
		
		add_action( 'wp_ajax_nopriv_pesapalpay_ipn_page_return', array(&$this,'ipn_page_return'));
		add_action( 'wp_ajax_pesapalpay_ipn_page_return', array(&$this,'ipn_page_return'));
		add_action( 'widgets_init', array(&$this,'register_widget') );
		add_filter( 'woocommerce_currencies', array( $this,'load_woo_currencies') );
		add_filter('woocommerce_currency_symbol', array( $this,'load_woo_currency_symbols'), 10, 2);
	}
	
	/**
	 * Initialise the variables we want to use
	 *
	 * @since 2.0
	 */
	function init_vars(){
		$this->plugin_file	 = __FILE__;
		$this->plugin_dir	 = plugin_dir_path( __FILE__ ) . 'pesapal_pay/';
		$this->plugin_url	 = plugin_dir_url( __FILE__ ) . 'pesapal_pay/';
		require_once( $this->plugin_dir.'lib/currencies.php' ); //Load the currencies
	}
	
	/**
	 * Install
	 *
	 * @since 2.0
	 */
	function install(){
		
		$old_settings	 = $this->get_options();
		$old_version	 = get_option( 'pesapal_pay_version' );
		
		//Default settings
		$default_settings = array(
								'customer_key' => '',
								'customer_secret' => '',
								'full_frame' => 'false',
								'currency' => 'KES',
								'form_invoice' => 'pesapal_pay_invoice',
								'form_email' => 'pesapal_pay_email',
								'form_cost' => 'pesapal_pay_cost',
								'form_function' => '',
								'thankyou_page' => '');
								
		$default_settings	 = apply_filters( 'pespal_default_settings', $default_settings );
		$settings			 = wp_parse_args( (array) $old_settings, $default_settings );
		update_option( 'pesapal_pay_setup', $settings );
		
		if ( empty( $old_version ) ) 
			$this->update_20();
			
		$this->set_form_options(); //Form options
								
		update_option( 'pesapal_pay_version', $this->version );
	}

	public function init() {
		require_once( $this->plugin_dir.'addons/pesapal_pay_shortcodes.php' ); //Load shortcodes
		require_once( $this->plugin_dir.'addons/pesapal_pay_donate_widget.php' ); //Load widget
		require_once( $this->plugin_dir.'addons/pesapal_pay_wp_travel.php' ); //Load WP travel

		add_action( 'plugins_loaded', array( &$this, 'load_plugins' ) ); //Load plugins
	}
	
	/**
	 * Load plugins
	 *
	 * @since 2.1
	 */
	function load_plugins(){
		require_once( $this->plugin_dir.'addons/pesapal_pay_woo.php' ); //Load WooCommerce
		$dir = $this->plugin_dir.'plugins/';
		if ( !is_dir( $dir ) )
			return;
		if ( ! $dh = opendir( $dir ) )
			return;
			
		while ( ( $plugin = readdir( $dh ) ) !== false ) {
			if ( substr( $plugin, -4 ) == '.php' )
				$plugins[] = $dir . $plugin;
		}
		closedir( $dh );
		if(!empty($plugins)){
		
			sort( $plugins );

			//include them suppressing errors
			foreach ($plugins as $file)
				@include_once( $file );
		}
	}
	
	/**
	 * Get Options
	 *
	 * @since 2.0
	 *
	 * @return Array
	 */
	function get_options(){
		return get_option( 'pesapal_pay_setup' );
	}
	
	/**
	 * Update Options
	 *
	 * @since 2.0
	 */
	function update_options($settings){
		update_option( 'pesapal_pay_setup', $settings );
	}
	
	function set_form_options(){
		$checkout_settings = get_option( 'pesapal_pay_checkout_settings' );
		if(empty($checkout_settings)){
			$default_options = array(
				array(
					'name' => 'First Name',
					'type' => 'text',
					'uname' => 'pesapal_pay_firstname',
					'initial' => '',
					'mandatory' => 'checked',
					'visible' => 'checked',
					'delete' => false
					),
				array(
					'name' => 'Last Name',
					'type' => 'text',
					'uname' => 'pesapal_pay_lastname',
					'initial' => '',
					'mandatory' => 'checked',
					'visible' => 'checked',
					'delete' => false
					),
				array(
					'name' => 'Email',
					'type' => 'text',
					'uname' => 'pesapal_pay_email',
					'initial' => '',
					'mandatory' => 'checked',
					'visible' => 'checked',
					'delete' => false
					),
				array(
					'name' => 'Amount',
					'type' => 'text',
					'uname' => 'pesapal_pay_amount',
					'initial' => __('Payment Amount'),
					'mandatory' => 'checked',
					'visible' => 'checked',
					'delete' => false
					)
			);
			update_option( 'pesapal_pay_checkout_settings', $default_options );
		}
	}
	/**
	 * Update from old version
	 *
	 * @since 2.0
	 */
	function update_20(){
		global $wpdb;
		$table_name = $wpdb->prefix."pesapal_pay";
		if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
			$sql = "SELECT * FROM {$table_name} ORDER BY `id`";
			$results = $wpdb->get_results($sql);
			if (is_array($results) && count($results) > 0) {
				foreach ($results as $result) {
					$post_status = '';
					switch($result->payment_status){
						case 'Pending':
							$post_status = 'order_pending';
							break;
						case 'Paid':
							$post_status = 'order_paid';
							break;
						case 'Canceled':
							$post_status = 'order_cancelled';
							break;
					}
					$post_data = array(
							'post_title' => $result->invoice,
							'post_content' => '',
							'post_status' => $post_status,
							'post_type' => 'pesapal_pay',
							'post_date' => $result->date
							);
					$new_post_id = wp_insert_post($post_data);
					if ($new_post_id) {
						$pesapal_pay_info = array();
						$pesapal_pay_info[ 0 ][ 'invoice' ] = $result->invoice;
						$pesapal_pay_info[ 0 ][ 'email' ] = $result->email;
						$pesapal_pay_info[ 0 ][ 'fname' ] = $result->firstname;
						$pesapal_pay_info[ 0 ][ 'lname' ] = $result->lastname;
						$pesapal_pay_info[ 0 ][ 'total' ] = $result->total;
                        update_post_meta($new_post_id, 'pesapal_pay_info', $pesapal_pay_info);
                    }
				}
			}
			$wpdb->query("DROP TABLE IF EXISTS $table_name");
		}
	}
	
	/**
	* Enqeue js on product settings screen
	*/
	function admin_script_settings() {
		wp_enqueue_script('jquery-ui-sortable');
	}

	/**
	* Enqeue css on product settings screen
	*/
	function admin_css_settings() {
		wp_enqueue_style('dp_jquery_ui', $this->plugin_url . 'resources/jquery-ui.min.css', false, $this->version);
		wp_enqueue_style('dp_jquery_ui_structure', $this->plugin_url . 'resources/jquery-ui.structure.min.css', false, $this->version);
		wp_enqueue_style('dp_jquery_ui_theme', $this->plugin_url . 'resources/jquery-ui.theme.min.css', false, $this->version);
	}
	
	/**
	 * Custom Post Type for transactions
	 *
	 * @since 2.0
	 */
	function custom_post_type(){

		// Register custom pesapal_pay post type
		register_post_type( 'pesapal_pay',
			array(
				'labels'		 => array(
					'name' => __( 'PesaPal Pay'),
					'singular_name'	 => __( 'Transaction'),
					'edit'			 => __( 'Edit'),
					'view_item'		 => __( 'View Transaction'),
					'search_items'	 => __( 'Search Transactions'),
					'not_found'		 => __( 'No Transactions Found')
				),
			'description'		 => __( 'PesaPal Pay Transactions'),
			'menu_icon' => 		 $this->plugin_url . 'images/pesapal_pay.png',
			'public' => true,
			'publicly_queryable' => true,
			'has_archive' => true,
			'show_ui' => true,
			'show_in_menu' => true, 
			'query_var' => true,
			'capability_type'	 => 'post',
			'hierarchical'		 => false,
			'rewrite'			 => false,
			'supports'			 => false,
			'capabilities' => array(
				'create_posts' => false, // Removes support for the "Add New" function
			  ),
			'map_meta_cap' => true
			)
		);
		
	
		
		//register custom post statuses for our orders
		register_post_status( 'order_pending', array(
			'label'			 => _x( 'Pending Orders', 'post'),
			'label_count'	 => _n_noop( 'Pending <span class="count">(%s)</span>', 'Pending <span class="count">(%s)</span>' ),
			'post_type'		 => 'pesapal_pay',
			'public'		 => true,
			'exclude_from_search' => false,
			'show_in_admin_all_list'    => false,
			'show_in_admin_status_list' => true
		) );
		register_post_status( 'order_paid', array(
			'label'			 => _x( 'Paid Orders', 'post'),
			'label_count'	 => _n_noop( 'Paid <span class="count">(%s)</span>', 'Paid <span class="count">(%s)</span>' ),
			'post_type'		 => 'pesapal_pay',
			'public'		 => true,
			'exclude_from_search' => false,
			'show_in_admin_all_list'    => false,
			'show_in_admin_status_list' => true
		) );
		register_post_status( 'order_cancelled', array(
			'label'			 => _x( 'Cancelled Orders', 'post'),
			'label_count'	 => _n_noop( 'Cancelled <span class="count">(%s)</span>', 'Cancelled <span class="count">(%s)</span>' ),
			'post_type'		 => 'pesapal_pay',
			'public'		 => true,
			'exclude_from_search' => false,
			'show_in_admin_all_list'    => false,
			'show_in_admin_status_list' => true
		) );
		register_post_status( 'trash', array(
			'label'						 => _x( 'Trash', 'post' ),
			'label_count'				 => _n_noop( 'Trash <span class="count">(%s)</span>', 'Trash <span class="count">(%s)</span>' ),
			'show_in_admin_status_list'	 => true,
			'post_type'					 => 'pesapal_pay',
			'public'					 => false
		) );
	}
	
	
	/**
	 * Show header lables
	 *
	 * @since 2.0
	 */
	function post_status_lables($views){
		$edit_url = admin_url( 'edit.php?post_type=pesapal_pay' );
		$views['order_pending'] = '<a href="'.$edit_url.'&post_status=order_pending">Pending</a>';
		$views['order_paid'] = '<a href="'.$edit_url.'&post_status=order_paid">Paid</a>';
		$views['order_cancelled'] = '<a href="'.$edit_url.'&post_status=order_cancelled">Cancelled</a>';
		return $views;
	}
	
	/**
	 * Add custom column header
	 *
	 * @since 2.0
	 */
	function manage_orders_columns( $old_columns ) {
		$columns[ 'cb' ] = '<input type="checkbox" />';
		$columns[ 'pp_orders_id' ]		 = __( 'Invoice');
		$columns[ 'pp_orders_email' ]	 = __( 'Email');
		$columns[ 'pp_orders_fname' ]	 = __( 'First Name');
		$columns[ 'pp_orders_lname' ]	 = __( 'Last Name');
		$columns[ 'pp_orders_total' ]	 = __( 'Total');
		$columns[ 'pp_orders_status' ]	 = __( 'Status');
		$columns[ 'pp_orders_date' ]	 = __( 'Order Date');

		return $columns;
	}
	
	/**
	 * Add custom column header data
	 *
	 * @since 2.0
	 */
	function manage_orders_custom_columns($column, $id){
		$post = get_post($id); 
		$meta = get_post_custom($id);
		
		
		//unserialize
		foreach ( $meta as $key => $val )
			$meta[ $key ]	 = array_map( 'maybe_unserialize', $val );
			
		switch ( $column ) {
			case "pp_orders_status":
				if ( $post->post_status == 'order_pending' )
					$text	 = __( 'Pending');
				else if ( $post->post_status == 'order_paid' )
					$text	 = __( 'Paid');
				else if ( $post->post_status == 'order_cancelled' )
					$text	 = __( 'Cancelled');
				else if ( $post->post_status == 'trash' )
					$text	 = __( 'Trashed');
				?>
				<span id="pesapal_order_status_<?php echo $id; ?>"><?php echo $text; ?></span>
				<div class="row-actions">
					<span class="edit">
						<a href="javascript:void(null);" onclick="pesapal_pay_status('<?php echo $id; ?>');"><?php _e("Change status"); ?></a>
					</span>
				</div>
				<?php
				break;
				
			case "pp_orders_date":
				$t_time	 = get_the_time( __( 'Y/m/d g:i:s A' ) );
				$m_time	 = $post->post_date;
				$time	 = get_post_time( 'G', true, $post );

				$time_diff = time() - $time;

				if ( $time_diff > 0 && $time_diff < 24 * 60 * 60 )
					$h_time	 = sprintf( __( '%s ago' ), human_time_diff( $time ) );
				else
					$h_time	 = mysql2date( __( 'Y/m/d' ), $m_time );
				echo '<abbr title="' . $t_time . '">' . $h_time . '</abbr>';
				break;
			case "pp_orders_id":
				$order_id	 = $meta[ "pesapal_pay_info" ][ 0 ][0][ 'invoice' ];
				?>
				<strong>
					<?php echo $order_id ?>
				</strong>
				<?php
				break;
			case "pp_orders_email":
				echo $meta[ "pesapal_pay_info" ][0][ 0 ][ 'email' ];
				break;
			case "pp_orders_fname":
				echo $meta[ "pesapal_pay_info" ][0][ 0 ][ 'fname' ];
				break;
			case "pp_orders_lname":
				echo $meta[ "pesapal_pay_info" ][0][ 0 ][ 'lname' ];
				break;
			case "pp_orders_total":
				echo $meta[ "pesapal_pay_info" ][0][ 0 ][ 'total' ];
				break;
		}
	}
	
	/**
	 * Make columns sortable
	 *
	 * @since 2.0
	 */
	function manage_orders_sortable_columns( $columns){
		$columns[ 'pp_orders_id' ]		 = 'title';
		$columns[ 'pp_orders_status' ]	 = 'post_status';
		$columns[ 'pp_orders_date' ]	 = 'date';
		return $columns;
	}
	
	/**
	 * Manage Bulk Actions
	 *
	 * @since 2.0
	 */
	function manage_bulk_actions($actions){
		unset( $actions['edit'] );
		return $actions;
	}
	
	/**
	 * Save Order
	 *
	 * @since 2.0
	 *
	 * @param String $status  - the transaction status
	 * @param String $invoice - the invoice id
	 * @param String $email   - the email
	 * @param String $fname   - the first name
	 * @param String $lname   - the last name
	 * @param Double $total   - the amount
	 *
	 */
	function save_order($status, $invoice,$email,$fname,$lname,$total, $user_info = array()){
		$post_data = array(
				'post_title' => $invoice,
				'post_content' => '',
				'post_status' => $status,
				'post_type' => 'pesapal_pay'
				);
		$new_post_id = wp_insert_post($post_data);
		if ($new_post_id) {
			$pesapal_pay_info = array();
			$pesapal_pay_info[ 0 ][ 'invoice' ] = $invoice;
			$pesapal_pay_info[ 0 ][ 'email' ] = $email;
			$pesapal_pay_info[ 0 ][ 'fname' ] = $fname;
			$pesapal_pay_info[ 0 ][ 'lname' ] = $lname;
			$pesapal_pay_info[ 0 ][ 'total' ] = $total;
			$pesapal_pay_info[ 0 ][ 'user_info' ] = $user_info;
			update_post_meta($new_post_id, 'pesapal_pay_info', $pesapal_pay_info);
			return $new_post_id;
		}
		return 0;
	}
	
	/**
	 * Change Order Status
	 *
	 * @since 2.0
	 *
	 * @return void
	 */
	function change_order_status(){
		$order_id = intval($_POST['id']);
		$order_status = 'Pending';
		$new_status = '';
		$post = get_page_by_title( $order_id, OBJECT, 'pesapal_pay' ); 
		if ($post) {
			$current_status = $post->post_status;
			if ($current_status === "order_pending") {
				$order_status = 'Paid';
				$new_status = 'order_paid';
			}else if ($current_status === "order_paid"){
				$order_status = 'Cancelled';
				$new_status = 'order_cancelled';
			}else if ($current_status === "order_cancelled"){
				$order_status = 'Pending';
				$new_status = 'order_pending';
			}else {
				$order_status = 'Cancelled';
				$new_status = 'order_paid';
			}
			
			$my_post = array(
				  'ID'           => $post->ID,
				  'post_status'  => $new_status
			);
			wp_update_post( $my_post );
		}
		
		die($order_status);
	}
	
	/**
	 * Inline JS
	 *
	 * @since 2.0
	 */
	function admin_inline_js(){
		?>
		<script type="text/javascript">
			function pesapal_pay_status(id){
				jQuery.ajax({
					type: "POST",
					url: '<?php echo admin_url('admin-ajax.php'); ?>',
					data: 'action=pesapal_change_order_status&id=' + id,
					success:function(msg){
						jQuery('span#pesapal_order_status_'+id).html(msg);
					}
				});
			}
		</script>
		<?php
	}
	
	/** 
	 * Load the OAuth.php file
	 *
	 * @since 2.1
	 */
	function load_pesapal_lib(){
		require_once($this->plugin_dir.'lib/OAuth.php'); //Load the PesaPal lib
	}
	
	/**
	 * Save Transaction
	 *
	 * @since 2.0
	 *
	 * @return void
	 */
	function save_transaction(){
		$checkout_settings = get_option( 'pesapal_pay_checkout_settings' );
		$this->load_pesapal_lib();
		
		$options = $this->get_options();
		$form_function = $options['form_function'];
		if(function_exists ($form_function)){
			call_user_func($form_function);
		}
		$firstname = '';
		$lastname = '';
		$form_invoice = $this->generate_order_id();
		$user_info = array();
		$pesapal_button = @$_REQUEST['pesapal_button'];
		if($pesapal_button){
			$form_invoice = $_REQUEST['pesapal_pay_invoice'];
			$form_email = $_REQUEST['pesapal_pay_email'];
			$form_cost = $_REQUEST['pesapal_pay_cost'];
			$firstname = $form_email;
			$lastname = $form_email;
		}else{
			$form_cost = $_REQUEST['pesapal_pay_amount'];
			$form_email = $_REQUEST['pesapal_pay_email'];
			$firstname = $_REQUEST['pesapal_pay_firstname'];
			$lastname = $_REQUEST['pesapal_pay_lastname'];
			$predef_keys = array("ppform", "action", "ajax","pesapal_pay_lastname","pesapal_pay_firstname","pesapal_pay_amount","pesapal_pay_email");
			foreach($_POST as $key => $value){
				if(!in_array($key, $predef_keys)){
					$array_position =  array_search($key, array_column($checkout_settings, 'uname')); //Search by the uname
					if($array_position !== FALSE ){
						$checkout_setting = $checkout_settings[$array_position];
						$checkout_setting['value'] = $value;
						$user_info[] = $checkout_setting;
					}
				}
			}
		}
		
		$form_cost = floatval($form_cost);
		
		$this->save_order('order_pending', $form_invoice,$form_email,$firstname,$lastname,$form_cost,$user_info); //Save Order
		
		
		$return_path = admin_url("admin-ajax.php?action=pesapalpay_ipn_page_return");
		
		$token = $params = NULL;
		$consumer_key = $options['customer_key'];
		$consumer_secret = $options['customer_secret'];
		$signature_method = new PesaPalOAuthSignatureMethod_HMAC_SHA1();
		
		//get form details
		$desc = 'Your Order No.: '.$form_invoice;
		$type = 'MERCHANT';
		$reference = $form_invoice;
		$first_name = $firstname;
		$fullnames = $firstname.' '.$lastname;
		$last_name = $lastname;
		$email = $form_email;
		$username = $email; //same as email
		$phonenumber = '';//leave blank
		$payment_method = '';//leave blank
		$code = '';//leave blank
		$currency = $options['currency'];
		
		$callback_url = $return_path; //redirect url, the page that will handle the response from pesapal.
		$post_xml = "<?xml version=\"1.0\" encoding=\"utf-8\"?><PesapalDirectOrderInfo xmlns:xsi=\"http://www.w3.org/2001/XMLSchemainstance\" xmlns:xsd=\"http://www.w3.org/2001/XMLSchema\" Amount=\"".$form_cost."\" Description=\"".$desc."\" Code=\"".$code."\" Currency=\"".$currency."\" Type=\"".$type."\" PaymentMethod=\"".$payment_method."\" Reference=\"".$reference."\" FirstName=\"".$first_name."\" LastName=\"".$last_name."\" Email=\"".$email."\" PhoneNumber=\"".$phonenumber."\" UserName=\"".$username."\" xmlns=\"http://www.pesapal.com\" />";
		$post_xml = htmlentities($post_xml);
		
		$consumer = new PesaPalOAuthConsumer($consumer_key, $consumer_secret);
		//post transaction to pesapal
		$pp_post_url = $this->post_url;
		$iframe_src = PesaPalOAuthRequest::from_consumer_and_token($consumer, $token, "GET", $pp_post_url, $params);
		$iframe_src->set_parameter("oauth_callback", $callback_url);
		$iframe_src->set_parameter("pesapal_request_data", $post_xml);
		$iframe_src->sign_request($signature_method, $consumer, $token);
		
		$output = '<iframe src="'.$iframe_src.'" width="100%" height="100%" style="overflow:hidden;overflow-x:hidden;overflow-y:hidden;height:100%;width:100%;position:absolute;top:0px;left:0px;right:0px;bottom:0px" scrolling="no" frameBorder="0" >';
		$output .= '</iframe>';
		echo $output;
		exit();
	}
	
	/**
	 * Save Transaction passing variables
	 *
	 * @since 2.1
	 *
	 * @param String $firstname  - the names
	 * @param String $form_email - the email
	 * @param String $form_cost  - the cost
	 *
	 */
	function save_transaction_with_values($firstname,$form_email,$form_cost){
	
		$this->load_pesapal_lib();
		
		$options = $this->get_options();
		$form_function = $options['form_function'];
		if(function_exists ($form_function)){
			call_user_func($form_function);
		}
		$lastname = $firstname;
		$form_invoice = $this->generate_order_id();
		
		$form_cost = floatval($form_cost);
		
		$this->save_order('order_pending', $form_invoice,$form_email,$firstname,$lastname,$form_cost); //Save Order
		
		
		$return_path = admin_url("admin-ajax.php?action=pesapalpay_ipn_page_return");
		
		$token = $params = NULL;
		$consumer_key = $options['customer_key'];
		$consumer_secret = $options['customer_secret'];
		$signature_method = new PesaPalOAuthSignatureMethod_HMAC_SHA1();
		
		//get form details
		$desc = 'Your Order No.: '.$form_invoice;
		$type = 'MERCHANT';
		$reference = $form_invoice;
		$first_name = $firstname;
		$fullnames = $firstname.' '.$lastname;
		$last_name = $lastname;
		$email = $form_email;
		$username = $email; //same as email
		$phonenumber = '';//leave blank
		$payment_method = '';//leave blank
		$code = '';//leave blank
		$currency = $options['currency'];
		
		$callback_url = $return_path; //redirect url, the page that will handle the response from pesapal.
		$post_xml = "<?xml version=\"1.0\" encoding=\"utf-8\"?><PesapalDirectOrderInfo xmlns:xsi=\"http://www.w3.org/2001/XMLSchemainstance\" xmlns:xsd=\"http://www.w3.org/2001/XMLSchema\" Amount=\"".$form_cost."\" Description=\"".$desc."\" Code=\"".$code."\" Currency=\"".$currency."\" Type=\"".$type."\" PaymentMethod=\"".$payment_method."\" Reference=\"".$reference."\" FirstName=\"".$first_name."\" LastName=\"".$last_name."\" Email=\"".$email."\" PhoneNumber=\"".$phonenumber."\" UserName=\"".$username."\" xmlns=\"http://www.pesapal.com\" />";
		$post_xml = htmlentities($post_xml);
		
		$consumer = new PesaPalOAuthConsumer($consumer_key, $consumer_secret);
		//post transaction to pesapal
		$pp_post_url = $this->post_url;
		$iframe_src = PesaPalOAuthRequest::from_consumer_and_token($consumer, $token, "GET", $pp_post_url, $params);
		$iframe_src->set_parameter("oauth_callback", $callback_url);
		$iframe_src->set_parameter("pesapal_request_data", $post_xml);
		$iframe_src->sign_request($signature_method, $consumer, $token);
		
		$output = '<iframe src="'.$iframe_src.'" width="100%" height="100%" style="overflow:hidden;overflow-x:hidden;overflow-y:hidden;height:100%;width:100%;position:absolute;top:0px;left:0px;right:0px;bottom:0px"  scrolling="no" frameBorder="0" >';
		$output .= '</iframe>';
		echo $output;
		exit();
	}
	
	
	function save_transaction_with_values_return( $firstname,$form_email,$form_cost,$return_path, $reference, $currency = false ){
	
		$this->load_pesapal_lib();
		
		$options = $this->get_options();
		$form_function = $options['form_function'];
		if(function_exists ($form_function)){
			call_user_func($form_function);
		}
		$lastname = $firstname;
		$form_invoice = $this->generate_order_id();
		
		$form_cost = floatval($form_cost);
		
		$new_post_id = $this->save_order('order_pending', $form_invoice,$form_email,$firstname,$lastname,$form_cost); //Save Order
				
		if ( $new_post_id ) {
			update_post_meta( $new_post_id, '_pesapal_pay_external_reference', $reference );
		}
		
		do_action( 'pesapal_pay_after_transaction_saved', $form_invoice );
		$token = $params = NULL;
		$consumer_key = $options['customer_key'];
		$consumer_secret = $options['customer_secret'];
		$signature_method = new PesaPalOAuthSignatureMethod_HMAC_SHA1();
		
		//get form details
		$desc = 'Your Order No.: '.$form_invoice;
		$type = 'MERCHANT';
		$reference = $form_invoice;
		$first_name = $firstname;
		$fullnames = $firstname.' '.$lastname;
		$last_name = $lastname;
		$email = $form_email;
		$username = $email; //same as email
		$phonenumber = '';//leave blank
		$payment_method = '';//leave blank
		$code = '';//leave blank
		$currency = !$currency ? $options['currency'] : $currency;
		
		$callback_url = $return_path; //redirect url, the page that will handle the response from pesapal.
		$post_xml = "<?xml version=\"1.0\" encoding=\"utf-8\"?><PesapalDirectOrderInfo xmlns:xsi=\"http://www.w3.org/2001/XMLSchemainstance\" xmlns:xsd=\"http://www.w3.org/2001/XMLSchema\" Amount=\"".$form_cost."\" Description=\"".$desc."\" Code=\"".$code."\" Currency=\"".$currency."\" Type=\"".$type."\" PaymentMethod=\"".$payment_method."\" Reference=\"".$reference."\" FirstName=\"".$first_name."\" LastName=\"".$last_name."\" Email=\"".$email."\" PhoneNumber=\"".$phonenumber."\" UserName=\"".$username."\" xmlns=\"http://www.pesapal.com\" />";
		$post_xml = htmlentities($post_xml);
		
		$consumer = new PesaPalOAuthConsumer($consumer_key, $consumer_secret);
		//post transaction to pesapal
		$pp_post_url = $this->post_url;
		$iframe_src = PesaPalOAuthRequest::from_consumer_and_token($consumer, $token, "GET", $pp_post_url, $params);
		$iframe_src->set_parameter("oauth_callback", $callback_url);
		$iframe_src->set_parameter("pesapal_request_data", $post_xml);
		$iframe_src->sign_request($signature_method, $consumer, $token);
		
		$output = '<iframe src="'.$iframe_src.'" width="100%" height="100%" style="overflow:hidden;overflow-x:hidden;overflow-y:hidden;height:100%;width:100%;position:absolute;top:0px;left:0px;right:0px;bottom:0px"  scrolling="no" frameBorder="0" >';
		$output .= '</iframe>';
		echo $output;
		exit();
	}
	
	/**
	 * IPN Return
	 *
	 * @since 2.0
	 *
	 * @return String
	 */
	function ipn_return(){
		$this->load_pesapal_lib();
		
		$options = $this->get_options();
		
		$consumer_key = $options['customer_key'];
		$consumer_secret = $options['customer_secret'];
		
		$transaction_tracking_id = $_REQUEST['pesapal_transaction_tracking_id'];
		$payment_notification = $_REQUEST['pesapal_notification_type'];
		$invoice = $_REQUEST['pesapal_merchant_reference'];
		$statusrequestAPI = $this->status_request;
		$message = array('message' => 'No transaction tracking id present');
		if(!empty($transaction_tracking_id) && $payment_notification=="CHANGE"){
			$token = $params = NULL;
			$consumer = new PesaPalOAuthConsumer($consumer_key, $consumer_secret);
			$signature_method = new PesaPalOAuthSignatureMethod_HMAC_SHA1();

			//get transaction status
			$request_status = PesaPalOAuthRequest::from_consumer_and_token($consumer, $token, "GET", $statusrequestAPI, $params);
			$request_status->set_parameter("pesapal_merchant_reference", $pesapal_merchant_reference);
			$request_status->set_parameter("pesapal_transaction_tracking_id",$invoice);
			$request_status->sign_request($signature_method, $consumer, $token);
			
			$request = wp_remote_get( $request_status );
			if ( is_wp_error( $request) ) {
				$response = __( 'PENDING' );
			} else {
				$response = wp_remote_retrieve_body( $request );
			}

			switch ($response) {
				case 'PENDING':
					$updated_status = 'order_pending';
					break;
				case 'COMPLETED':
					$updated_status = 'order_paid';
					break;
				case 'FAILED':
					$updated_status = 'order_cancelled';
					break;
				default:
					$updated_status = 'order_cancelled';
					break;
			}
			$page = $this->get_transaction($invoice);
			if($page){
				$my_post = array(
					  'ID'           => $page->ID,
					  'post_status'  => $updated_status
				 );
				 wp_update_post( $my_post );

				$reference = get_post_meta( $page->ID, '_pesapal_pay_external_reference', true );
				if ( $reference ) {
					do_action( 'pesapal_pay_process_success_ipn_transaction', $updated_status, $reference );
				}
			}
			do_action( 'pesapal_pay_after_ipn', $updated_status, $invoice );
		}
		
		$resp="pesapal_notification_type=$payment_notification&pesapal_transaction_tracking_id=$transaction_tracking_id&pesapal_merchant_reference=$invoice";
		ob_start();
		echo $resp;
		ob_flush();
		exit();
	}
	
	
	/** 
	 * Thank you page IPN
	 *
	 */
	function ipn_page_return(){
		$this->load_pesapal_lib();
		
		$options = $this->get_options();
		
		$consumer_key = $options['customer_key'];
		$consumer_secret = $options['customer_secret'];
		
		$transaction_tracking_id = $_REQUEST['pesapal_transaction_tracking_id'];
		$payment_notification = $_REQUEST['pesapal_notification_type'];
		$invoice = $_REQUEST['pesapal_merchant_reference'];
		$statusrequestAPI = $this->status_request;
		if(!empty($transaction_tracking_id)){
			$token = $params = NULL;
			$consumer = new PesaPalOAuthConsumer($consumer_key, $consumer_secret);
			$signature_method = new PesaPalOAuthSignatureMethod_HMAC_SHA1();

			//get transaction status
			$request_status = PesaPalOAuthRequest::from_consumer_and_token($consumer, $token, "GET", $statusrequestAPI, $params);
			$request_status->set_parameter("pesapal_merchant_reference", $pesapal_merchant_reference);
			$request_status->set_parameter("pesapal_transaction_tracking_id",$invoice);
			$request_status->sign_request($signature_method, $consumer, $token);
			
			$request = wp_remote_get( $request_status );
			if ( is_wp_error( $server_response) ) {
				$response = __( 'PENDING' );
			} else {
				$response 	= wp_remote_retrieve_body( $response );
			}
			switch ($response) {
				case 'PENDING':
					$updated_status = 'order_pending';
					break;
				case 'COMPLETED':
					$updated_status = 'order_paid';
					break;
				case 'FAILED':
					$updated_status = 'order_cancelled';
					break;
				default:
					$updated_status = 'order_cancelled';
					break;
			}
			$page = $this->get_transaction($invoice);
			if($page){
				$my_post = array(
					  'ID'           => $page->ID,
					  'post_status'  => $updated_status
				 );
				 wp_update_post( $my_post );
			}
			
		}
		
		$return_path = get_page_link($options['thankyou_page']);
		$check_return_path = explode('?', $return_path);
		if (count($check_return_path) > 1) {
			$return_path .= '&id=' . $form_invoice;
		} else {
			$return_path .= '?id=' . $form_invoice;
		}
		
		wp_redirect($return_path); 
		exit; 
	}


	/** 
	 * Thank you page IPN
	 *
	 */
	function payment_process(){
		$updated_status = 'order_pending';
		$this->load_pesapal_lib();
		
		$options = $this->get_options();
		
		$consumer_key = $options['customer_key'];
		$consumer_secret = $options['customer_secret'];
		
		$transaction_tracking_id = $_REQUEST['pesapal_transaction_tracking_id'];
		$payment_notification = $_REQUEST['pesapal_notification_type'];
		$invoice = $_REQUEST['pesapal_merchant_reference'];
		$statusrequestAPI = $this->status_request;
		if(!empty($transaction_tracking_id)){
			$token = $params = NULL;
			$consumer = new PesaPalOAuthConsumer($consumer_key, $consumer_secret);
			$signature_method = new PesaPalOAuthSignatureMethod_HMAC_SHA1();

			//get transaction status
			$request_status = PesaPalOAuthRequest::from_consumer_and_token($consumer, $token, "GET", $statusrequestAPI, $params);
			$request_status->set_parameter("pesapal_merchant_reference", $pesapal_merchant_reference);
			$request_status->set_parameter("pesapal_transaction_tracking_id",$invoice);
			$request_status->sign_request($signature_method, $consumer, $token);
			
			$request = wp_remote_get( $request_status );
			if ( is_wp_error( $server_response) ) {
				$response = __( 'PENDING' );
			} else {
				$response 	= wp_remote_retrieve_body( $response );
			}
			switch ($response) {
				case 'PENDING':
					$updated_status = 'order_pending';
					break;
				case 'COMPLETED':
					$updated_status = 'order_paid';
					break;
				case 'FAILED':
					$updated_status = 'order_cancelled';
					break;
				default:
					$updated_status = 'order_cancelled';
					break;
			}
			$page = $this->get_transaction($invoice);
			if($page){
				$my_post = array(
					  'ID'           => $page->ID,
					  'post_status'  => $updated_status
				 );
				 wp_update_post( $my_post );
			}
			
		}
		return $updated_status;
	}
	
	/**
	 * Get Transaction 
	 *
	 * @since 2.0
	 *
	 * @param String $invoice - the invoice
	 *
	 * @return OBJECT
	 */
	function get_transaction($invoice){
		return get_page_by_title($invoice);
	}
		
	/**
	 * Create Meta Box 
	 *
	 * @since 2.0
	 *
	 * @return void
	 */
	function set_up_meta_box(){
		$post_types = array( 'post', 'page');
		foreach( $post_types as $post_type) {
			add_meta_box( 
				'pesapalpay_sectionid',
				__( 'PesaPal Pay Options'),
				array(&$this, 'post_inner_custom_box'),
				$post_type,'side', 'high'
			);
		}
	}
	
	/**
	 * The Meta Box
	 *
	 */
	function post_inner_custom_box($post){
		wp_nonce_field( plugin_basename( __FILE__ ), 'pp_pay_noncename' );
		$post_id = $post->ID;
		$content_price = get_post_meta($post_id, 'pp_pay_price', true);
		$button_name = get_post_meta($post_id, 'pp_pay_button_name', true);
		$pp_pay_button_sc = get_post_meta($post_id, 'pp_pay_button_sc', true);
		?>
		<div class="inside">
			<p>
				<label><?php _e('Price:');?> :</label>
				<input type="text" value="<?php echo $content_price; ?>" name="pp_pay_price" id="pp_pay_price">
			</p>
			<p>
				<label><?php _e('Unique button name:');?> :</label>
				<input type="text" value="<?php echo $button_name; ?>" name="pp_pay_button_name" id="pp_pay_button_name">
			</p>
			<p>
				<label><input type="checkbox" value="checked" name="pp_pay_button_sc" <?php echo ($pp_pay_button_sc == 'checked') ? "checked='checked'": ""; ?> /> <?php _e('Automatically add button shortcode');?></label>
			</p>
		</div>
		<?php
	}
	
	/**
	 * Save Meta box values
	 *
	 * @since 2.0
	 *
	 * @return void
	 */
	function meta_save($post_id){
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) 
			return;
			
		if ( !wp_verify_nonce(@$_POST['pp_pay_noncename'], plugin_basename( __FILE__ ) ) )
			return;
		// Check permissions
		if ( 'dg_product' == @$_POST['post_type'] ) {
			if ( !current_user_can( 'edit_page', $post_id ) )
				return;
		}
		else{
			if ( !current_user_can( 'edit_post', $post_id ) )
				return;
		}
		// for pp_pay_price
		if (NULL == @$_POST['pp_pay_price']) {
			//do nothing
		} else {
			$pp_pay_price = $_POST['pp_pay_price'];
			update_post_meta($post_id, 'pp_pay_price', $pp_pay_price);
		}
		
		// for pp_pay_button_name
		if (NULL == @$_POST['pp_pay_button_name']) {
			//do nothing
		} else {
			$pp_pay_button_name = $_POST['pp_pay_button_name'];
			update_post_meta($post_id, 'pp_pay_button_name', $pp_pay_button_name);
		}
		
		// for pp_pay_button_sc
		if (NULL == @$_POST['pp_pay_button_sc']) {
			update_post_meta($post_id, 'pp_pay_button_sc', "");
		} else {
			$pp_pay_button_sc = @$_POST['pp_pay_button_sc'];
			update_post_meta($post_id, 'pp_pay_button_sc', $pp_pay_button_sc);
		}
	}
	
	/**
	 * Content Filter 
	 *
	 * @since 2.0
	 *
	 * @return String content
	 */
	function content_filter($content){
		$post_id = $GLOBALS['post']->ID;
		$content_price = get_post_meta($post_id, 'pp_pay_price', true);
		$button_name = get_post_meta($post_id, 'pp_pay_button_name', true);
		$pp_pay_button_sc = get_post_meta($post_id, 'pp_pay_button_sc', true);
		if(!empty($pp_pay_button_sc)){
			if($pp_pay_button_sc === 'checked'){
				if(empty($button_name)){
					$button_name = 'Buy Using Pesapal';
				}
				if(empty($content_price)){
					$content_price = '10';
				}
				$content = $content.do_shortcode("[pesapal_pay_button button_name='$button_name' amount='$content_price' use_options='true']");
			}
		}
		
		return $content;
	}
	
	/**
	 * Create Settings 
	 *
	 * @since 2.0
	 *
	 * @return void
	 */
	function admin_menu(){
		$page = add_submenu_page('edit.php?post_type=pesapal_pay', __('Settings'), __('Settings'), 'edit_others_posts', 'settings', array(&$this,'admin_settings_page'));
		add_action('admin_print_scripts-' . $page, array(&$this, 'admin_script_settings'));
		add_action('admin_print_styles-' . $page, array(&$this, 'admin_css_settings'));
	}
	
	/** 
	 * The admin header tabs
	 *
	 */
	function admin_tabs($current = 'settings'){
		$tabs = array( 'settings' => __('Settings'), 'forms' => __('Dynamic Form'), 'shortcodes' => __('Shortcodes')); 
		$links = array();
		echo '<div id="icon-themes" class="icon32"><br></div>';
		echo '<h2 class="nav-tab-wrapper">';
		foreach( $tabs as $tab => $name ){
			$class = ( $tab == $current ) ? ' nav-tab-active' : '';
			echo "<a class='nav-tab$class' href='".admin_url('edit.php?post_type=pesapal_pay&page=settings')."&tab=$tab'>$name</a>";
		}
		echo '</h2>';
	}
	
	/**
	 * Admin settings page 
	 *
	 * @since 2.0
	 *
	 * @return void
	 */
	function admin_settings_page(){
		global $pagenow;
		if ( $pagenow == 'edit.php' && $_GET['post_type'] == 'pesapal_pay' && $_GET['page'] == 'settings'){ 
		?>
		<div class="wrap">
			<h2><?php _e("PesaPal Pay Settings"); ?></h2>
			<?php
				if ( 'true' == esc_attr( @$_GET['updated'] ) ) echo '<div class="updated" ><p>Settings updated.</p></div>';
				
				if ( isset ( $_GET['tab'] ) ) $this->admin_tabs($_GET['tab']); else $this->admin_tabs();
			?>
			<div id="poststuff">
				<?php
				$tab = 'settings';
				if ( isset ( $_GET['tab'] ) ) $tab = $_GET['tab'];
				switch ( $tab ){
					case 'settings' :
						$this->general_settings();
					break;
					
					case 'shortcodes' :
						$this->shortcodes();
					break;
					
					case 'forms' :
						$this->forms();
					break;
					
					default :
						$this->general_settings();
					break;
				}
				?>
			</div>
		</div>
		<?php
		}else{
			echo __('Invalid access');
		}
	}
	
	/** 
	 * Admin general settings
	 * 
	 */
	function general_settings(){
		if(@$_POST['pesapal_settings']){
			$required_fields = array(
									'customer_key' => '',
									'customer_secret' => '',
									'full_frame' => '',
									'currency' => '',
									'form_invoice' => '',
									'form_email' => '',
									'form_cost' => '',
									'form_function' => '',
									'thankyou_page' => '');
			$required_fields['customer_key'] = $_POST['customer_key'];
			$required_fields['customer_secret'] = $_POST['customer_secret'];
			$required_fields['currency'] = $_POST['currency'];
			$required_fields['full_frame'] = $_POST['full_frame'];
			$required_fields['form_invoice'] = $_POST['form_invoice'];
			$required_fields['form_email'] = $_POST['form_email'];
			$required_fields['form_cost'] = $_POST['form_cost'];
			$required_fields['form_function'] = $_POST['form_function'];
			$required_fields['thankyou_page'] = $_POST['thankyou_page'];
			$this->update_options($required_fields);
			?>
			<div id="message" class="updated fade">
				<h3><?php _e('Settings Updated'); ?></h3>
			</div>
			<?php
		}
		$options = $this->get_options();

		?>
		<form method="POST" action="">
			<table class="widefat">
				<tr>
					<th scope="row"><?php _e('PesaPal Checkout') ?></th>
					<td>
						<p>
							<?php _e('PesaPal requires Full names and email/phone number. To handle APN return requests, please set the url '); ?>
							<strong><?php echo admin_url("admin-ajax.php?action=pesapalpay_ipn_return"); ?></strong>
							<?php _e(' on your <a href="https://www.pesapal.com/merchantdashboard" target="_blank">pesapal</a> account settings'); ?>
						</p>
						
					</td>
				</tr>
				<tr>
					<th scope="row"><?php _e('PesaPal Merchant Credentials'); ?></th>
					<td>
						<p>
							<label><?php _e('Customer Key') ?><br />
							  <input value="<?php echo $options['customer_key']; ?>" size="30" name="customer_key" type="text" />
							</label>
						</p>
						<p>
							<label><?php _e('Customer Secret') ?><br />
								 <input value="<?php echo $options['customer_secret']; ?>" size="30" name="customer_secret" type="text" />
							</label>
						</p>
						<p>
							<label><?php _e('Currency'); ?><br />
								<select name="currency">
									<?php 
									foreach ($this->currencies as $key => $value ) {
										$cont_selected = '';
										if ($options['currency'] == $key) {
											$cont_selected = 'selected="selected"';
										}
										$option = '<option value="' .$key. '" '.$cont_selected.'>';
										$option .= $value[0];
										$option .= '</option>';
										echo $option;
									}
									?>
								</select>
							</label>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php _e('PesaPal Form Settings. These are the names of the fields to be used by the gateway'); ?></th>
					<td>
						<p>
							<label><?php _e('Function to be called before payment'); ?> ( <?php _e('your php function that is called before processing payment'); ?> ) <br />
								<input value="<?php echo $options['form_function']; ?>" size="30" name="form_function" type="text" />
							</label>
						</p>
						<p>
							<label><?php _e('Thank You page'); ?><br />
								<select name="thankyou_page">
									<?php 
									$pages = get_pages(); 
									foreach ( $pages as $pagg ) {
										$cont_selected = '';
										if (intval($options['thankyou_page']) === $pagg->ID) {
											$cont_selected = 'selected="selected"';
										}
										$option = '<option value="' .$pagg->ID. '" '.$cont_selected.'>';
										$option .= $pagg->post_title;
										$option .= '</option>';
										echo $option;
									}
									?>
								</select>
							</label>
						</p>
						<p>
							<label><?php _e('Load Payment page in entire page'); ?> (<?php _e('This will replace your entire page content with the PesaPal payment form'); ?>)<br />
								<select name="full_frame">
									<option value="false" <?php selected( $options['full_frame'], 'false'); ?>><?php _e('No'); ?></option>
									<option value="true" <?php selected( $options['full_frame'], 'true' ); ?>><?php _e('Yes'); ?></option>
								</select>
							</label>
						</p>
					</td>
				</tr>
			</table>
			<p class="submit">
				<input class='button-primary' type='submit' name='pesapal_settings' value='<?php _e('Save Settings'); ?>'/><br/>
			</p>
		</form>
		<?php
	}
	
	/** 
	 * Shortcodes to use
	 * 
	 */
	function shortcodes(){
		?>
		<table class="widefat">
			<tr>
				<th scope="row"><?php _e('Payment Form'); ?></th>
				<td>
					<p>
						[pesapal_pay_payment_form button_name='Buy Using Pesapal' amount='10'] 
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php _e('Payment Button'); ?></th>
				<td>
					<p>
						[pesapal_pay_button button_name='Buy Using Pesapal' amount='10' use_options='false'] <br/>
						The use_options variable set to false will tell the shortcode to get parameters from a previos page. To use the button on a page, put [pesapal_pay_button button_name='Buy Using Pesapal' amount='10' layout='flat']
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php _e('Donate Form'); ?></th>
				<td>
					<p>
						[pesapal_donate] 
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php _e('Verify transaction on thank you page'); ?></th>
				<td>
					<p>
						[pesapal_verify_transaction] Download URL HERE [/pesapal_verify_transaction]
					</p>
				</td>
			</tr>
		</table>
		<?php
	}
	
	/**
	 * Dynamic forms
	 *
	 * @since 3.0
	 *
	 */
	function forms(){
		if ( isset( $_POST[ 'pesapal_pay_checkout_settings' ] ) ) {
			update_option( 'pesapal_pay_checkout_settings', $_POST[ 'ppay' ]);
			echo '<div class="updated fade"><p>' . __( 'Checkout settings saved.') . '</p></div>';
		}
		$pesapal_pay_checkout_settings = get_option( 'pesapal_pay_checkout_settings' );
		$count = 0;
		?>
		<h2><?php _e("Checkout Settings","dp-lang");?></h2>
		<div id="dpsc_checkout">
			<div class="dpsc_checkout_fields">
				<form method="POST" action="<?php echo esc_url($_SERVER['REQUEST_URI']); ?>">
					<?php wp_nonce_field('pesapal_pay_checkout_settings','pesapal_pay_checkout_noncename'); ?>
					<input type="hidden" name="sort_order" id="sort_order" value=""/>
					<p class="submit">
						<a class="button button-secondary" onclick="add_checkout_element()"><?php _e('Add Field'); ?></a>&nbsp;&nbsp;&nbsp;<input class='button button-primary' type='submit' name='pesapal_pay_checkout_settings' value='<?php _e('Save Options'); ?>'/><br/>
					</p>
					<table width="100%" border="0" class="widefat">
						<thead>
							<tr>
								<th width="1%" align="left" scope="col"></th>
								<th width="20%" align="left" scope="col"><?php _e('Name'); ?></th>
								<th width="10%" align="left" scope="col"><?php _e('Type'); ?></th>
								<th width="10%" align="left" scope="col"><?php _e('Unique Name'); ?></th>
								<th width="39%" align="left" scope="col"><?php _e('Place Holder'); ?></th>
								<th width="10%" align="left" scope="col"><?php _e('Mandatory'); ?></th>
								<th width="10%" align="left" scope="col"><?php _e('Visible'); ?></th>
								<th width="1%" align="left" scope="col"></th>
							</tr>
						</thead>

						<tfoot>
							<tr>
								<th align="left" scope="col"></th>
								<th align="left" scope="col"><?php _e('Name'); ?></th>
								<th align="left" scope="col"><?php _e('Type'); ?></th>
								<th align="left" scope="col"><?php _e('Unique Name'); ?></th>
								<th align="left" scope="col"><?php _e('Place Holder'); ?></th>
								<th align="left" scope="col"><?php _e('Mandatory'); ?></th>
								<th align="left" scope="col"><?php _e('Visible'); ?></th>
								<th align="left" scope="col"></th>
							</tr>
						</tfoot>
						<tbody class='sort-checkout ui-sortable'>
							<?php
							if (is_array($pesapal_pay_checkout_settings) && count($pesapal_pay_checkout_settings) > 0) {

								foreach ($pesapal_pay_checkout_settings as $checkout_row) {
									?>
									<tr id="<?php echo $checkout_row['name']; ?>">
										<td><span style="cursor:move" class="dashicons dashicons-sort"></span></td>
										<?php if(!isset($checkout_row['delete'])) { ?>
										<td><input type="text" name="ppay[<?php echo $count; ?>][name]" value="<?php echo $checkout_row['name']; ?>"/></td>
										<td>
											<select name="ppay[<?php echo $count; ?>][type]">
												<?php
													foreach ($dukapress->form_elements as $forms => $form) {
														$cont_selected = '';
														if ($checkout_row['type'] === $form) {
															$cont_selected = 'selected="selected"';
														}
														?>
														<option value="<?php echo $form; ?>" <?php echo $cont_selected; ?> ><?php _e($forms); ?></option>
														<?php
													}
												?>
											</select>
										</td>
										<td><input type="text" name="ppay[<?php echo $count; ?>][uname]" value="<?php echo $checkout_row['uname']; ?>" /></td>
										<td><input type="text" name="ppay[<?php echo $count; ?>][initial]" value="<?php echo @$checkout_row['initial']; ?>" style="width:100%"/></td>
										<?php } else { ?>
										<td><input type="hidden" name="ppay[<?php echo $count; ?>][name]" value="<?php echo $checkout_row['name']; ?>" /><?php _e($checkout_row['name']); ?></td>
										<td><input type="hidden" name="ppay[<?php echo $count; ?>][type]" value="<?php echo $checkout_row['type']; ?>" /><?php _e($checkout_row['type']); ?></td>
										<td><input type="hidden" name="ppay[<?php echo $count; ?>][uname]" value="<?php echo $checkout_row['uname']; ?>" /><?php _e($checkout_row['uname']); ?></td>
										<td><input type="hidden" name="ppay[<?php echo $count; ?>][initial]" value="<?php echo $checkout_row['initial']; ?>" /><?php _e($checkout_row['initial']); ?></td>
										<?php } ?>
										<?php if(!isset($checkout_row['delete'])) {?>
										<td><input type="checkbox" value="checked" name="ppay[<?php echo $count; ?>][mandatory]" <?php echo ($checkout_row['mandatory'] == 'checked') ? "checked='checked'": ""; ?> /></td>
										<td><input type="checkbox" value="checked" name="ppay[<?php echo $count; ?>][visible]" <?php echo ($checkout_row['visible'] == 'checked') ? "checked='checked'": ""; ?> /></td>
										<?php } else { ?>
										<td><?php _e('Mandatory'); ?><input type="hidden" value="checked" name="ppay[<?php echo $count; ?>][mandatory]" <?php echo ($checkout_row['mandatory'] == 'checked') ? "checked='checked'": ""; ?> /></td>
										<td><?php _e('Visible'); ?><input type="hidden" value="checked" name="ppay[<?php echo $count; ?>][visible]" <?php echo ($checkout_row['visible'] == 'checked') ? "checked='checked'": ""; ?> /></td>
										<?php } ?>
										<?php if(!isset($checkout_row['delete'])) {?>
											<td><span style="cursor:pointer" class="dashicons dashicons-no-alt" onclick="delete_checkout_element(this)"></span></td>
										<?php } else { ?>
											<td><input type="hidden" name="ppay[<?php echo $count; ?>][delete]" value="false" /></td>
										<?php } ?>
									</tr>
									<?php
									$count++;
								}
							}
							?>

						</tbody>
					</table>
					<p class="submit">
						<a class="button button-secondary" onclick="add_checkout_element()"><?php _e('Add Field'); ?></a>&nbsp;&nbsp;&nbsp;<input class='button button-primary' type='submit' name='pesapal_pay_checkout_settings' value='<?php _e('Save Options'); ?>'/><br/>
					</p>
				</form>
				<input type="hidden" id="ppay_item_count" value="<?php echo $count; ?>" />
				<div style="display:none" class="ppay_append_row">
					<script type="ppay_checkout_row">
						<tr class="ui-sortable-handle">
							<td><span style="cursor:move" class="dashicons dashicons-sort"></span></td>
							<td><input type="text" name="ppay[CURRENTCOUNT][name]" value=""/></td>
							<td>
								<select name="ppay[CURRENTCOUNT][type]">
									<?php
										foreach ($this->form_elements as $forms => $form) {
											?>
											<option value="<?php echo $form; ?>"><?php _e($forms); ?></option>
											<?php
										}
									?>
								</select>
							</td>
							<td><input type="text" name="ppay[CURRENTCOUNT][uname]" value="" /></td>
							<td><input type="text" name="ppay[CURRENTCOUNT][initial]" value="" style="width:100%"/></td>
							<td><input type="checkbox" value="checked" name="ppay[CURRENTCOUNT][manadatory]" /></td>
							<td><input type="checkbox" value="checked" name="ppay[CURRENTCOUNT][visible]" /></td>
							<td><span style="cursor:pointer" class="dashicons dashicons-no-alt" onclick="delete_checkout_element(this)"></span></td>
						</tr>
					</script>
				</div>
				<script type="text/javascript">
					jQuery(document).ready(function() {
						var idsInOrder = [];
						jQuery("tbody.sort-checkout").sortable({
							update: function( event, ui ) {
								idsInOrder = [];
								jQuery('tbody.sort-checkout tr').each(function() {
									idsInOrder.push(jQuery(this).attr('id'));
								});
								jQuery('#sort_order').val(idsInOrder);
							}
						});
					});
					function add_checkout_element(){
						var cc_current = jQuery('#ppay_item_count').val();
						var newRow = jQuery('div.ppay_append_row script[type="ppay_checkout_row"]').clone();
						newRow.attr('id',cc_current);
						newRow = newRow.html().replace(/CURRENTCOUNT/g,cc_current);
						jQuery('tbody.sort-checkout').append(newRow);
						cc_current++;
						jQuery('#ppay_item_count').val(cc_current);
					}

					function delete_checkout_element(elem){
						jQuery(elem).parent().parent().remove();
						
					}
				</script>
			</div>
		</div>
		<?php
	}
	
	/**
	 * Generate the form based on the users inputs
	 *
	 *
	 * @param String $layout - The layout. flat or fluid
	 * @param String $default_amount - The default amount to be used. If blank if shows an input
	 * @param String $currency - The currency in the settings
	 * @since 3.0
	 *
	 * @return String $content - The form
	 */
	function generate_checkout_form($layout,$default_amount = "", $currency = ""){
		$content = "";
		$pesapal_pay_checkout_settings = get_option( 'pesapal_pay_checkout_settings' );
		if (is_array($pesapal_pay_checkout_settings) && count($pesapal_pay_checkout_settings) > 0) {
			$elem = ($layout == 'flat') ? "table": "div";
			$elem_row = ($layout == 'flat') ? "tr": "div";
			$elem_column = ($layout == 'flat') ? "td": "div";
			$content .= '<'.$elem.' class="ppay_checkout_table">';
			$curr_str = '';
			foreach ($pesapal_pay_checkout_settings as $checkout_row) {
				if($checkout_row['visible'] == 'checked'){
					$form_class = '';
					$required_attr = '';
					if($checkout_row['mandatory'] == 'checked'){
						$form_class = 'required';
						$required_attr = 'required="required"';
					}
					$input_elem = '';
					$input_type = $checkout_row['type'];
					if(!empty($default_amount) && $checkout_row['uname'] == 'pesapal_pay_amount'){
						
						if(!empty($currency)){
							$curr_str = ' ('.$currency.')';
						}
						$input_elem = '<input type="hidden" class="'.$form_class.' '.$checkout_row['uname'].'_input" name="'.$checkout_row['uname'].'" '.$required_attr.' value="'.$default_amount.'" id="'.$checkout_row['uname'].'" /> '.$default_amount.$curr_str;
					}else{
						switch($input_type){
							case 'text' :
								$input_elem = '<input type="text" class="'.$form_class.' '.$checkout_row['uname'].'_input" name="'.$checkout_row['uname'].'" '.$required_attr.' placeholder="'.$checkout_row['initial'].'" id="'.$checkout_row['uname'].'" />';
								break;
							case 'textarea' :
								$input_elem = '<textarea class="'.$form_class.' '.$checkout_row['uname'].'_input" name="'.$checkout_row['uname'].'" id="'.$checkout_row['uname'].'" '.$required_attr.' placeholder="'.$checkout_row['initial'].'"></textarea>';
								break;
							case 'checkbox' :
								$input_elem = '<input type="checkbox" class="'.$form_class.' '.$checkout_row['uname'].'_input" name="'.$checkout_row['uname'].'" id="'.$checkout_row['uname'].'" '.$required_attr.'/>';
								break;
							case 'paragraph' :
								$input_elem = '<p class="'.$form_class.' '.$checkout_row['uname'].'_input">'.$checkout_row['initial'].'</p>';
								break;
							case 'select' :
								$input_elem = '<select class="'.$form_class.' '.$checkout_row['uname'].'_input">';
								$input_elem .= '<option value="">'.$checkout_row['initial'].'</option>';
								foreach ($dukapress->get_countries() as $country_code => $country_name) {
									$input_elem .= '<option value="' . $country_code . '">' . $country_name . '</option>';
								}
								$input_elem .= '</select>';
								break;
						}
					}
					$content .= "<$elem_row class='dpsc_row'>";
					$content .= "<$elem_column class='ppay_column'>";
					$content .= '<label for="'.$checkout_row['uname'].'" class="'.$checkout_row['uname'].'">'.$checkout_row['name'].'</label>';
					$content .= "</$elem_column>";
					$content .= "<$elem_column class='ppay_column'>";
					$content .= $input_elem;
					$content .= "</$elem_column>";
					$content .= "</$elem_row>";
				}

			}
			$content .= "</$elem>";
		}else{
			$content = __('Plugin not set up correctly');
		}
		return $content;
	}
	
	/**
	 * Genrate Order ID 
	 *
	 * @since 2.0
	 * @update 2.3
	 *
	 * @return String
	 */
	function generate_order_id() {
		return md5(uniqid(rand(), true));
	}

	function register_widget() {
		return register_widget( "Pesapal_Pay_widget" );
	}

	function load_woo_currencies( $currencies ) {
		if ( !isset( $currencies['KES'] ) ||!isset( $currencies['KSH'] ) ) {
			$currencies['KES'] = __( 'Kenyan Shilling', 'woocommerce' );
			$currencies['TZA'] = __( 'Tanzanian Shilling', 'woocommerce' );
			$currencies['UGX'] = __( 'Ugandan Shilling', 'woocommerce' );
			return $currencies;
		}
	}

	function load_woo_currency_symbols( $currency_symbol, $currency ) {
		switch( $currency ) {
			case 'KES': 
				$currency_symbol = 'KShs'; 
			break;
			case 'TZA': 
				$currency_symbol = 'TZs'; 
			break;
			case 'UGX': 
				$currency_symbol = 'UShs'; 
			break;
		}
		return $currency_symbol;
	}
}


/**
 * Global function
 *
 * @since 1.0.0
 */
function pesapal_pay() {
	return PesaPal_Pay::instance();
}

//Set plugin globals
$GLOBALS['pesapal_pay'] = pesapal_pay();
?>