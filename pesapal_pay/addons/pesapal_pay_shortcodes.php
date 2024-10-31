<?php
/**
 * Payment form
 *
 */
add_shortcode('pesapal_pay_payment_form', 'pesapal_pay_payment_form');
function pesapal_pay_payment_form($atts){
	global $pesapal_pay;
	extract(shortcode_atts(array(
				'button_name' => 'Buy Using Pesapal',
				'amount' => '10',
				'layout' => 'fluid'), $atts));
	$options = $pesapal_pay->get_options();
	$output = '<form id="pesapal_checkout" class="pesapal_payment_form">
				<input type="hidden" name="ppform" id="ppform" value="ppform"/>
				<input type="hidden" name="ajax" value="true" />
				<input type="hidden" name="action" value="pesapal_save_transaction"/>
				<input type="hidden" name="ppamount" value="'.$amount.'"/>
				<fieldset>';
	$output .= $pesapal_pay->generate_checkout_form($layout,$amount, $options['currency']);			
	$output .=	'</fieldset>	 	 
			</form>
			<button name="pespal_pay" id="pespal_pay_btn">'.$button_name.'</button>';
	
	$output .= '<script type="text/javascript">';
	$output .= 'jQuery(document).ready(function(){';
	$output .= 'jQuery("#pespal_pay_btn").click(function(){';
	$output .= 'jQuery("#pespal_pay_btn").val("Processing......");';
	$output .= 'jQuery.ajax({';
	$output .= 'type: "POST",';
	$output .= 'data: jQuery("#pesapal_checkout").serialize(),';
	$output .= 'url: "'.admin_url('admin-ajax.php').'",';
	$output .= 'success:function(data){';
	if($options['full_frame'] === 'true'){
		$output .= 'jQuery("body").css("height","100%");';
		$output .= 'jQuery("body").html(data)';
	}else{
		$output .= 'jQuery("#pesapal_checkout").parent().html(data)';
	}
	$output .= '}';
	$output .= '})';
	$output .= '});';
	$output .= '});';
	$output .= '</script>';
	return $output;
}

/**
 * Shortcode
 */
add_shortcode('pesapal_pay_button', 'pesapal_pay_button');
function pesapal_pay_button($atts){
	global $pesapal_pay;
	$invoice = $pesapal_pay->generate_order_id();
	$user_email = get_bloginfo( 'admin_email' );
	extract(shortcode_atts(array(
				'button_name' => 'Pay Using Pesapal',
				'amount' => '10',
				'use_options' => 'false'), $atts));
	$options = $pesapal_pay->get_options();
	$formid= mt_rand();
	
	$output = '<form id="pesapal_checkout_'.$formid.'">
				<input type="hidden" name="pesapal_pay_invoice" value="'.$invoice.'"/>
				<input type="hidden" name="pesapal_pay_email" value="'.$user_email.'"/>
				<input type="hidden" name="pesapal_pay_cost" value="'.$amount.'"/>
				<input type="hidden" name="pesapal_button" value="1" />
				<input type="hidden" name="ajax" value="true" />
				<input type="hidden" name="action" value="pesapal_save_transaction"/>
				</form>
				<button name="pespal_pay_'.$formid.'" id="pespal_pay_btn_'.$formid.'" class="pesapal_btn">'.$button_name.'</button>';
					
	
	$output .= '<script type="text/javascript">';
	$output .= 'jQuery(document).ready(function(){';
	$output .= 'jQuery("#pespal_pay_btn_'.$formid.'").click(function(){';
	$output .= 'jQuery("#pespal_pay_btn_'.$formid.'").val("Processing......");';
	$output .= 'jQuery.ajax({';
	$output .= 'type: "POST",';
	$output .= 'data: jQuery("#pesapal_checkout_'.$formid.'").serialize(),';
	$output .= 'url: "'.admin_url('admin-ajax.php').'",';
	$output .= 'success:function(data){';
	if($options['full_frame'] === 'true'){
		$output .= 'jQuery("body").css("height","100%");';
		$output .= 'jQuery("body").html(data)';
	}else{
		$output .= 'jQuery("#pesapal_checkout_'.$formid.'").parent().parent().html(data)';
	}
	$output .= '}';
	$output .= '})';
	$output .= '});';
	$output .= '});';
	$output .= '</script>';
	
	return $output;
}
 


//PesaPal Donate Shortcode
add_shortcode('pesapal_donate', 'pesapal_pay_donate');

/**
 * Generate PesaPal Donate box
 */
function pesapal_pay_donate($text){
	global $pesapal_pay;
	$options = $pesapal_pay->get_options();
	$content = '<form id="pesapal_donate_widget">';
	
	$content .= '<div class="pesapal_pay_widget_table">';
	$content .= '<fieldset>';
	if(!empty($text)){
		$content .= '<div class="control-group">';
		$content .= $text;
		$content .= '</div>';
	}
	$content .= $pesapal_pay->generate_checkout_form('fluid','', $options['currency']);	
	
	$content .= '</fieldset>';
	$content .= '</div>';
	$content .= '<input type="hidden" name="ajax" value="true" />';
	$content .= '<input type="hidden" name="action" value="pesapal_save_transaction"/>';
	$content .= '</form>';
	$content .= '<button name="pespal_pay_donate" class="pesapal_btn" id="pespal_pay_donate">'.__("Donate Using PesaPal").'</button>';
	$content .= '<script type="text/javascript">';
	$content .= 'jQuery(document).ready(function(){';
	$content .= 'jQuery("#pespal_pay_donate").click(function(){';
	$content .= 'jQuery("#pespal_pay_donate").val("Processing......");';
	$content .= 'jQuery.ajax({';
	$content .= 'type: "POST",';
	$content .= 'data: jQuery("#pesapal_donate_widget").serialize(),';
	$content .= 'url: "'.admin_url('admin-ajax.php').'",';
	$content .= 'success:function(data){';
	if($options['full_frame'] === 'true'){
		$output .= 'jQuery("body").css("height","100%");';
		$content .= 'jQuery("body").html(data)';
	}else{
		$content .= 'jQuery("#pesapal_donate_widget").parent().html(data)';
	}
	$content .= '}';
	$content .= '})';
	$content .= '});';
	$content .= '});';
	$content .= '</script>';
	return $content;
}
	
/**
 * Verify a transaction is paid for. This is to secure the page content
 *
 */
add_shortcode('pesapal_verify_transaction', 'pesapal_verify_transaction');
function pesapal_verify_transaction($atts, $content = null){
	global $pesapal_pay;
	$transactionid = $_REQUEST['id']; //Get the id of the invoice
	$transaction = $pesapal_pay->get_transaction($transactionid);
	if ($transaction->post_status == 'order_paid' ){
		return $content;
	}else{
		return "Transaction not yet verified";
	}
}
?>