jQuery(function($) {
	$('input[name=wp_travel_payment_gateway]').on('click', function(){
		var $elem = $(this).val();
		if($elem == 'pesapal'){
			if($("#wp-travel-book-now").is(':hidden')){
				$("#wp-travel-book-now").show();
				$(".paypal-button-label-checkout").remove();
			}
		}
	});
	
});