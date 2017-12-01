<?php
/** @var string $client_id */
/** @var string $client_secret */
/** @var string $access_token */
/** @var string $redirect_url */
/** @var string $authorization_url */
/** @var string $message */
?>

<?php if ( ! $client_id || ! $client_secret ) : ?>
	<p>You can obtain the Client ID and Client Secret by logging into <a href="https://www.instagram.com/developer/">Instagram's developer portal</a> and registering a new client. Copy the Redirect URL from the field below and paste it into your <strong>Valid Redirect URIs</strong> field in your Instagram API Client Settings. Once you've entered your ID and Secret, click <strong>Save Changes</strong> at the bottom of this page to proceed to the next step.</p>
	<table class="form-table">
		<tr>
			<th scope="row">
				<label for="tggr-instagram-redirect-url">Redirect URL</label>
			</th>
			<td>
				<input type="url" value="<?php echo esc_attr( $redirect_url ); ?>" id="tggr-instagram-redirect-url" class="regular-text" readonly />
			</td>
		</tr>
	</table>
<?php elseif ( ! $access_token ) : ?>
	<p>Click the button below to authorize Tagregator on Instagram and get your access token.</p>
	<p><a href="<?php echo esc_attr( $authorization_url ); ?>" class="button button-primary">Authorize</a></p>
<?php else : ?>
	<p><strong>Note:</strong> Sandbox Mode will retrieve your Instagram account's nine latest posts, ignoring any configured hashtags. Non-sandbox will retrieve the latest hashtags posts from all of Instagram, as long as there is permission for 'public_content' in your client.</p>
<?php endif; ?>

<?php if ( $message ) : ?>
	<div class="notice notice-error">
		<?php echo wpautop( wp_kses_post( $message ) ); ?>
	</div>
<?php endif; ?>
