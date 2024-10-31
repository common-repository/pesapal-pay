<?php
/**
 * Pesapal Donate Widget
 */

if(!class_exists('Pesapal_Pay_widget')) {
	class Pesapal_Pay_widget extends WP_Widget {
		
		function __construct(){
			$widget_ops = array( 'classname' => 'pesapal_pay_widget', 'description' => __('Pesapal Donate Widget') ); // Widget Settings
			$control_ops = array( 'id_base' => 'pesapal_pay_widget' ); // Widget Control Settings
			parent::__construct( 'pesapal_pay_widget', __('Pesapal Donate'), $widget_ops, $control_ops ); //
		}
		
		
		function widget($args, $instance) {
			extract($args);
			$title = empty( $instance['title'] ) ? '' : __($instance['title']);
			$optional_text = empty( $instance['optional_text'] ) ? '' : __($instance['optional_text']);
			echo $before_widget;
			echo $before_title.$title.$after_title;
			echo pesapal_pay_donate($optional_text);
			echo $after_widget;
		}
		
		/**
		 * Update widget
		 */
		function update($new_instance, $old_instance) {
			$instance = $old_instance;
			$instance['title'] = strip_tags( $new_instance['title'] );
			$instance['optional_text'] = strip_tags( $new_instance['optional_text'] );
			return $instance;
		}
		
		function form($instance) {
			$title 	= apply_filters('widget_title', @$instance['title']); // Widget Title
			$optional_text 	= apply_filters('optional_text', @$instance['title']); // Optional Text
			?>
			<p>
				<label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Widget Title:');?></label>
				<input type="text" value="<?php echo $title; ?>" name="<?php echo $this->get_field_name('title'); ?>" id="<?php echo $this->get_field_id('title'); ?>" class="widefat" />
			</p>
			<p>
				<label for="<?php echo $this->get_field_id('optional_text'); ?>"><?php _e('Optional Donation Text:');?></label>
				<textarea name="<?php echo $this->get_field_name('optional_text'); ?>" id="<?php echo $this->get_field_id('optional_text'); ?>"><?php echo $optional_text; ?></textarea>
			</p>
			<?php
		}
	}
}
?>