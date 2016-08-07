<?php
/**
 * Add Ajax Handler - this is the function that handles the submission of the form.
 * @since    1.0.0
 * @version  1.0.1
 */

function epm_mailchimp_submit_to_list() {

	global $epm_options;

	//get data from our ajax() call
	$epm_list_id = sanitize_text_field($_POST['epm_list_id']);
	if(epm_get_option('display_name_fields')):
	$epm_name = sanitize_text_field($_POST['epm_firstname']);
	$epm_lastname = sanitize_text_field($_POST['epm_lastname']);
	endif;
	$epm_email = sanitize_email($_POST['epm_email']);
	$epm_enable_validation = apply_filters( 'epm_filter_validation', 'enabled' ); //filter to disable/enable default validation messages
	$epm_enable_success = apply_filters( 'epm_filter_success', 'enabled' ); //filter to disable/enable default success messages
	$errors = array();

	//show error if fields are empty and validation is enabled
	if($epm_enable_validation == 'enabled') {
		// first name and last name not filled and name fields are enabled
		if(empty($epm_name) && epm_get_option('display_name_fields')) {
			$errors[] = __('Please fill in first name and last name fields.'.$epm_options['display_name_fields'],'easy-peasy-mailchimp');
		}
		// if email is not a valid email
		if(!is_email( $epm_email )) {
			$errors[] = __('Please add a correct email address.'.$epm_options['display_name_fields'],'easy-peasy-mailchimp');
		}
	}

	//show success if enabled and form is correctly filled
	if($epm_enable_success == 'enabled' && empty($errors)) {

		$MailChimp = new \Drewm\MailChimp( $epm_options['mailchimp_api_key'] );
		$result = $MailChimp->call('lists/subscribe', array(
			'id'                => $epm_options['mailchimp_list_id'],
			'email'             => array('email'=> $epm_email),
			'merge_vars'        => (epm_get_option('display_name_fields') ? array('FNAME'=>$epm_name, 'LNAME'=>$epm_lastname) : array()),
			'double_optin'      => (epm_get_option('enable_double_optin') ? true : false),
			'update_existing'   => true,
			'replace_interests' => false,
			'send_welcome'      => (epm_get_option('send_welcome_message') ? true : false),
		));

		// check if the result from MailChimp is clean
		if ($result) {
			echo '<div class="epm-message epm-success message success">';
			printf('<p>%s</p>', __('Thank you for signing up to the newsletter.','easy-peasy-mailchimp'));
			if (epm_get_option('enable_double_optin')) {
				printf('<p>%s</p>', __('Please check your email.', 'easy-peasy-mailchimp'));
			}
			echo '</div>';
		} else {
			$errors[] = __('There has been an error with MailChimp. Please contact the administrator of this website.', 'easy-peasy-mailchimp');
		}
	}

	// If there are errors output them to the user
	if (!empty($errors)) {
		echo '<div class="epm-message epm-error message error">';
		foreach ($errors as $error) {
			printf('<p>%s</p>', $error);
		}
		echo '</div>';
	}

	// Return String
	die();

}
add_action('wp_ajax_epm_mailchimp_submit_to_list', 'epm_mailchimp_submit_to_list');
add_action('wp_ajax_nopriv_epm_mailchimp_submit_to_list', 'epm_mailchimp_submit_to_list');

/**
 * Add js ajax script to footer.
 * @since    1.0.0
 * @since 1.0.6 wait for jquery to be loaded
 */
function epm_mailchimp_footer_js() {
	if ( wp_script_is( 'jquery', 'done' ) ) :
		?>
<script>
	jQuery(document).ready(function($) {
		$('.epm-sign-up-form').on('submit', function(e) {
			e.preventDefault();

			//get form values
			var epm_form = $(this);
			var epm_list_id = $(epm_form).parent().find('#epm_list_id').val();
			var epm_firstname = $(epm_form).parent().find('#epm-first-name').val();
			var epm_lastname = $(epm_form).parent().find('#epm-last-name').val();
			var epm_email = $(epm_form).parent().find('#epm-email').val();

			//change submit button text
			var submit_wait_text = $(this).data('wait-text');
			var submit_orig_text = $(this).val();
			$(this).val(submit_wait_text);

			$.ajax({
				type: 'POST',
				context: this,
				url: "<?php echo admin_url('admin-ajax.php');?>",
				data: {
					action: 'epm_mailchimp_submit_to_list',
					epm_list_id: epm_list_id,
					epm_firstname: epm_firstname,
					epm_lastname: epm_lastname,
					epm_email: epm_email
				},
				success: function(data, textStatus, XMLHttpRequest){
					var epm_ajax_response = $(data);
					$(epm_form).parent().find('.epm-message').remove(); // remove existing messages on re-submission
					$(epm_form).parent().prepend(epm_ajax_response);
					$(epm_form).val(submit_orig_text); // restore submit button text
					<?php do_action('epm_jquery_ajax_success_event');?>
				},
				error: function(XMLHttpRequest, textStatus, errorThrown){
					alert(<?php __('Something Went Wrong!', 'easy-peasy-mailchimp'); ?>);
				}
			});
			return false;

		});
	});
</script>
<?php
	endif;
}
add_action('wp_footer','epm_mailchimp_footer_js');
