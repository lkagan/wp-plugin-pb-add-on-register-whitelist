<?php
/**
 * Plugin Name: Profile Builder - Registration Whitelist Add-On
 * Version: 1.0.0
 * Description: Extends the functionality of Profile Builder by allowing you to restrict registration based on email domain.
 * Author: Superiocity, Larry Kagan
 * Author URI: http://www.superiocity.com
 * Plugin URI: http://www.superiocity.com
 */

namespace Superiocity;

class RegisterWhitelist
{
	/**
	 * Sets up hooks.
	 */
	public function __construct()
	{
		add_filter(
			'wppb_check_form_field_default-e-mail',
			array( $this, 'check_email_domain' ), 40, 4
		);
		
		add_action( 'admin_menu', array( $this, 'menu_item' ) );
    }


	/**
	 * Check if the person registering has an email address with a whitelisted domain.
	 *
	 * @param string $message
	 * @param array $posted_values
	 *
	 * @return string|void Error message if email address is not in the whitelist.
	 */
	public function check_email_domain( $message, $posted_values )
	{
		if ( empty( $_POST['email'] ) || ! is_email( $_POST['email'] ) ) {
			return;
		}

		$email     = $_POST['email'];
		$whitelist = get_option( 'wppb_whitelist' );
		$domain    = explode( '@', $email )[1];
		
		if ( ! in_array( $domain, $whitelist ) ) {
			if ( $message != '' ) {
				$message .= '<br>';
			}

			return $message .= __( 'Sorry, only email addresses from registered domains are allowed.', 'profile-builder' );
		}
	}


	/**
	 * Displays the admin pages and handles the form submission.
	 */
	public function admin_page()
	{
		$css_cache_bust = filemtime( __DIR__ . '/assets/css/style.css' );

		// Has the form been posted?
		if ( ! empty( $_POST['wppb_whitelist'] ) ) {
			// Check for correct nonce. End execution if someone is being sneaky.
			if ( ! check_admin_referer( 'wppb_whitelist' ) ) {
				exit;
			}

			$posted_whitelist = $whitelist = $_POST['wppb_whitelist'];
			$errors           = $this->get_whitelist_validation_errors();
			$message          = $this->get_message( $errors );

			// No validation errors, save the whitelist in the DB.
			if ( empty( $errors ) ) {
				$whitelist_array = preg_split( '/\s+/', $posted_whitelist );
				$whitelist_array = array_map( 'trim', $whitelist_array );
				update_option( 'wppb_whitelist', $whitelist_array );
			}
		} else { // Form not posted
			$db_whitelist = get_option( 'wppb_whitelist' );
			$whitelist = empty( $db_whitelist ) ? '' : join( PHP_EOL, $db_whitelist );
		}

		?>
		<link rel="stylesheet" href="<?= plugin_dir_url( __FILE__ ) ?>assets/css/style.css?<?= $css_cache_bust ?>">
		<div class="pbaorw-container">
			<h2>Registration Whitelist</h2>
			<p>
				Restrict front-end user registration based on the domain name of their email address.
				Be sure to set <em>"Email Confirmation" Activated</em> to <em>Yes</em> in the
				<a href="?page=profile-builder-general-settings">General Settings</a> page for this whitelist to have the greatest effect.
			</p>
			<?php if ( ! empty( $message ) ): ?>
			<div class="message <?= empty( $errors ) ? 'success' : 'error' ?>"><?= $message ?></div>
			<?php endif; ?>
			<form method="post">
				<label for="whitelist">Whitelist domains: <span class="field-instructions">one per line</span></label>
				<textarea name="wppb_whitelist"><?= $whitelist ?></textarea>
				<?php wp_nonce_field( 'wppb_whitelist' ); ?>
				<button type="submit" class="button-primary">Save whitelist</button>
			</form>
		</div>
		<?php
	}


	/**
	 * Validate admin form submission.
	 * 
	 * @return array Validation errors, if any.
	 */
	protected function get_whitelist_validation_errors() 
	{
		$errors       = [];
		$domain_regex = '/^([a-z\d]{1}[a-z\d-]*\.)+[a-z\d]{1,63}$/i';
		$whitelist    = preg_split( '/\s+/', $_POST['wppb_whitelist'] );
		$whitelist    = array_map( 'trim', $whitelist );

		foreach ( $whitelist as $domain ) {
			if ( ! preg_match( $domain_regex, $domain ) ) {
				$errors[] = '<em>' . esc_attr( $domain ) . '</em>' .
				            __( ' is not a valid domain.', 'profile-builder' );
			}
		}

		return $errors;
	}


	/**
	 * Build a success or error message after admin form post.
	 *
	 * @param $errors
	 *
	 * @return string
	 */
	protected function get_message( $errors ) 
	{
		if ( empty( $errors ) ) {
			return __( 'Successfully whitelisted domains.' );
		}

		$message = __( 'Please correct the following problems:' ) .
	               '<ul>';

		foreach ( $errors as $error ) {
			$message .= "<li>$error</li>\n";
		}

		return $message . "</ul>\n";
	}


	/**
	 * Setup the admin menu item.
	 */
	public function menu_item()
	{
		add_submenu_page(
			'profile-builder',
			__( 'Registration Whitelist', 'profile-builder' ),
			__( 'Registration Whitelist', 'profile-builder' ),
			'manage_options',
			'profile-builder-registration-whitelist',
			array( $this, 'admin_page' )
		);
	}
}

new RegisterWhitelist();
