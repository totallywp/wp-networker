<?php

// submenu page render function
function aibg_network_api_settings_page() {
	// check user capabilities
	if (!current_user_can('manage_network')) {
		return;
	}

	// check if the form has been submitted
	if (isset($_POST['submit'])) {
		// update API keys
		update_site_option('openai_api_key', sanitize_text_field($_POST['openai_api_key']));
		update_site_option('claude_api_key', sanitize_text_field($_POST['claude_api_key']));
		update_site_option('pexels_api_key', sanitize_text_field($_POST['pexels_api_key']));
		update_site_option('pixabay_api_key', sanitize_text_field($_POST['pixabay_api_key'])); // Added this line
		echo '<div id="message" class="updated notice is-dismissible"><p>API keys updated successfully.</p></div>';
	}

	// retrieve stored API keys
	$openai_api_key = get_site_option('openai_api_key', '');
	$claude_api_key = get_site_option('claude_api_key', '');
	$pexels_api_key = get_site_option('pexels_api_key', '');
	$pixabay_api_key = get_site_option('pixabay_api_key', ''); // Added this line
	?>
	<div class="wrap">
		<h1><?= esc_html(get_admin_page_title()); ?></h1>
		<form method="post">
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="openai_api_key">OpenAI API Key</label></th>
					<td><input name="openai_api_key" type="password" id="openai_api_key" value="<?= esc_attr($openai_api_key); ?>" class="regular-text"></td>
				</tr>
				<tr>
					<th scope="row"><label for="claude_api_key">Claude API Key</label></th>
					<td><input name="claude_api_key" type="password" id="claude_api_key" value="<?= esc_attr($claude_api_key); ?>" class="regular-text"></td>
				</tr>
				<tr>
					<th scope="row"><label for="pexels_api_key">Pexels API Key</label></th>
					<td><input name="pexels_api_key" type="password" id="pexels_api_key" value="<?= esc_attr($pexels_api_key); ?>" class="regular-text"></td>
				</tr>
				<tr> <!-- Added this new table row -->
					<th scope="row"><label for="pixabay_api_key">Pixabay API Key</label></th>
					<td><input name="pixabay_api_key" type="password" id="pixabay_api_key" value="<?= esc_attr($pixabay_api_key); ?>" class="regular-text"></td>
				</tr>
			</table>
			<?php submit_button('Save API Keys'); ?>
		</form>
	</div>
	<?php
}