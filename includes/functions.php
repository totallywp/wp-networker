<?php
function fetch_image_from_pexels($query) {
	$pexels_api_key = get_site_option('pexels_api_key', '');
	$api_key = $pexels_api_key;
	$url = "https://api.pexels.com/v1/search?query=" . urlencode($query);

	$response = wp_remote_get($url, [
		'headers' => [
			'Authorization' => $api_key
		],
		'timeout' => 180
	]);

	if (is_wp_error($response)) {
		return false;
	}

	$body = json_decode(wp_remote_retrieve_body($response), true);

	if (isset($body['photos'][0])) {
		$photo = $body['photos'][0];
		return [
			'src' => $photo['src']['medium'],
			'photographer' => $photo['photographer'],
			'url' => $photo['url']
		];
	}

	return false;
}

function fetch_image_from_pixabay($query) {
	$pixabay_api_key = get_site_option('pixabay_api_key', '');
	$api_key = $pixabay_api_key;
	$url = "https://pixabay.com/api/?key=" . $api_key . "&q=" . urlencode($query);

	$response = wp_remote_get($url, [
		'timeout' => 180
	]);

	if (is_wp_error($response)) {
		return false;
	}

	$body = json_decode(wp_remote_retrieve_body($response), true);

	if (isset($body['hits'][0])) {
		$hit = $body['hits'][0];
		return [
			'src' => $hit['webformatURL'],
			'photographer' => $hit['user'],
			'url' => "https://pixabay.com/users/" . $hit['user'] . "-" . $hit['user_id'] . "/"
		];
	}

	return false;
}

function aibg_form_submission_handler() {
	if (isset($_POST['aibg_action']) && $_POST['aibg_action'] === 'generate_blog_posts') {
		if (!check_admin_referer('aibg_generate_blog_posts')) {
			wp_die('Nonce verification failed.');
		}

		//header('Content-Type: application/json');
		$response = ['error' => null];

		$subsite_id = intval($_POST['subsite_id']);
		$post_titles = array_map('sanitize_text_field', $_POST['post_titles']);
		$category = intval($_POST['category']); // Get the selected category value

		// Get the selected API from POST data
		$selected_api = $_POST['ai-api'];
		
		switch_to_blog($subsite_id);
		
		foreach ($post_titles as $title) {
			$messages = [
				[
					'role' => 'user',
					'content' => 'Write a blog post about ' . $title . '. Write this blog post in an informal, first-person style. Do not include the post title in the main article, nor any other output as it is directly published. Output in HTML. NEVER mention you are an AI.'
				]
			];
		
			$openai_api_key = get_site_option('openai_api_key', '');

			if ($selected_api === 'openai') {
				$response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
					'headers' => [
						'Content-Type' => 'application/json',
						'Authorization' => 'Bearer ' . $openai_api_key
					],
					'body' => json_encode([
						'model' => 'gpt-3.5-turbo',
						'messages' => $messages,
						'temperature' => 0.7,
						'top_p' => 1,
					]),
					'timeout' => 180
				]);
			} elseif ($selected_api === 'claude') {
				$claude_api_key = get_site_option('claude_api_key', '');

				$apiKey = $claude_api_key; // Replace with your Claude API Key
				$data = [
					'prompt' => $messages[0]['content'],
					'model' => 'claude-v1', // Use appropriate model
					'max_tokens_to_sample' => 1200, // Adjust as needed
					'temperature' => 0.7,
					// ... any other parameters Claude API supports
				];
		
				$response = wp_remote_post('https://api.anthropic.com/v1/complete', [
					'headers' => [
						'Content-Type' => 'application/json',
						'x-api-key' => $apiKey,
					],
					'body' => json_encode($data),
					'timeout' => 180
				]);
			}
			
			if (is_wp_error($response)) {
				$response['error'] = 'API request error: ' . $response->get_error_message();
				echo json_encode($response);
				exit;
			}

			$response_body = json_decode(wp_remote_retrieve_body($response), true);

			if ($selected_api === 'openai' && (!isset($response_body['choices']) || !isset($response_body['choices'][0]['message']['content']))) {
				$response['error'] = 'API response does not contain the expected data: ' . json_encode($response);
				echo json_encode($response);
				exit;
			} elseif ($selected_api === 'claude' && !isset($response_body['completion'])) {
				$response['error'] = 'API response does not contain the expected data: ' . json_encode($response);
				echo json_encode($response);
				exit;
			}

			if ($selected_api === 'openai') {
				$content = $response_body['choices'][0]['message']['content'];
			} else if ($selected_api === 'claude') {
				//$content = $response_body['completion'] . ' + ' . json_encode($response_body);
				$content = $response_body['completion'];
			}
						

			// Extract tags from content
			$messages = [
				[
					'role' => 'user',
					'content' => 'Extract 5 keywords from this text: ' . $content
				]
			];
		
			$response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
				'headers' => [
					'Content-Type' => 'application/json',
					'Authorization' => 'Bearer ' . $openai_api_key
				],
				'body' => json_encode([
					'model' => 'gpt-3.5-turbo',
					'messages' => $messages,
					'temperature' => 0.7,
					'top_p' => 1,
					'max_tokens' => 50
				]),
				'timeout' => 60
			]);
		
			if (is_wp_error($response)) {
				$response['error'] = 'API request error: ' . $response->get_error_message();
				echo json_encode($response);
				exit;
			}
		
			$response_body = json_decode(wp_remote_retrieve_body($response), true);
		
			if (!isset($response_body['choices']) || !isset($response_body['choices'][0]['message']['content'])) {
				$response['error'] = 'API response does not contain the expected data';
				echo json_encode($response);
				exit;
			}
		
			$tags = $response_body['choices'][0]['message']['content'];

			// Remove numbers and line breaks from the response
			$tags = preg_replace('/\d+\.\s*/', '', $tags);
			$tags = str_replace(["\n", "\r"], ',', $tags);
			
			// Remove any unnecessary spaces and convert to an array
			$tags = explode(',', $tags);
			$tags = array_map('trim', $tags);
			
			
			
			// Fetch image for the blog post
			
			$first_tag = $tags[0];
			if ($_POST['image-api'] == 'pexels') {
				$image_data = fetch_image_from_pexels($first_tag);
			} else {
				$image_data = fetch_image_from_pixabay($first_tag);
			}

			if ($image_data) {
				$paragraphs = explode("\n", $content);
				if (count($paragraphs) >= 6) {
					// Download the image to the Media Library
					require_once(ABSPATH . 'wp-admin/includes/file.php');
					require_once(ABSPATH . 'wp-admin/includes/media.php');
					require_once(ABSPATH . 'wp-admin/includes/image.php');
			
					$local_image_url = media_sideload_image($image_data['src'], 0, $first_tag, 'src');
			
					// Check for download errors
					if (!is_wp_error($local_image_url)) {
						$image_caption = "Photo by <a href='{$image_data['url']}' target='_blank'>{$image_data['photographer']}</a>";
						$image_html = "<center><figure><img src='{$local_image_url}' alt='{$first_tag}' /><figcaption>{$image_caption}</figcaption></figure></center>";
						array_splice($paragraphs, 6, 0, $image_html);
						$content = implode("\n", $paragraphs);
					}
				}
			}


			$post_data = [
				'post_title' => $title,
				'post_content' => $content,
				'post_status' => 'draft',
				'post_author' => get_current_user_id(),
				'post_type' => 'post',
				'post_category' => array($category), // Set the selected category
				'tags_input' => $tags // Set the extracted tags
			];
		
			wp_insert_post($post_data);
		
				}
		
				restore_current_blog();
				echo json_encode($response);
				exit;
			}
}
// Replace the existing 'admin_init' action with these two lines
add_action('wp_ajax_nopriv_aibg_generate_blog_posts', 'aibg_form_submission_handler');
add_action('wp_ajax_aibg_generate_blog_posts', 'aibg_form_submission_handler');

function aibg_get_categories() {
	$subsite_id = intval($_POST['subsite_id']);

	switch_to_blog($subsite_id);
	$categories = get_categories(array('hide_empty' => 0));
	restore_current_blog();

	wp_send_json($categories);
}
add_action('wp_ajax_aibg_get_categories', 'aibg_get_categories');