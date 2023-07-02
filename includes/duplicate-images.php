<?php
function find_duplicate_images_menu() {
	add_submenu_page('tools.php', 'Find Duplicate Images', 'Find Duplicate Images', 'manage_options', 'find-duplicate-images', 'find_duplicate_images_page');
}
//add_action('admin_menu', 'find_duplicate_images_menu');

function find_duplicate_images_page() {
	$step = isset($_POST['step']) ? $_POST['step'] : 1;
	switch ($step) {
		case 1:
			find_duplicate_images_select_site();
			break;
		case 2:
			find_duplicate_images_show_duplicates();
			break;
		case 3:
			replace_and_delete_duplicates();
			break;
		default:
			echo 'Invalid step';
			break;
	}
}

function find_duplicate_images_select_site() {
	if ($_SERVER["REQUEST_METHOD"] == "POST") {
		$site_id = $_POST['site_id'];
	} else {
		$site_id = '';
	}

	echo '<div class="wrap">';
	echo '<h1>Select a Site</h1>';
	echo '<form method="POST">';
	echo '<input type="hidden" name="step" value="2">';
	echo '<select name="site_id">';
	$sites = get_sites();
	foreach ($sites as $site) {
		echo '<option value="' . esc_attr($site->blog_id) . '"' . selected($site->blog_id, $site_id, false) . '>' . esc_html($site->blogname) . '</option>';
	}
	echo '</select>';
	echo '<input type="submit" value="Select" class="button button-primary">';
	echo '</form>';
	echo '</div>';
}

function find_duplicate_images_show_duplicates() {
	if ($_SERVER["REQUEST_METHOD"] != "POST") {
		echo 'Invalid request';
		return;
	}

	$site_id = $_POST['site_id'];
	switch_to_blog($site_id);

	$query = new WP_Query(array(
		'post_type' => 'attachment',
		'post_mime_type' =>'image',
		'post_status' => 'inherit',
		'posts_per_page' => -1,
	));

	$images = array();
	while($query->have_posts()) {
		$query->the_post();
		$slug = get_post_field('post_name');
		$pattern = '/-(\d+)$/';
		if (preg_match($pattern, $slug, $matches)) {
			$original_slug = preg_replace($pattern, '', $slug);
			if (!isset($images[$original_slug])) {
				$images[$original_slug] = array();
			}
			$images[$original_slug][] = array(
				'ID' => get_the_ID(),
				'name' => $slug,
				'url' => wp_get_attachment_url(),
			);
		}
	}

	echo '<div class="wrap">';
	echo '<h1>Duplicate Images</h1>';
	echo '<form method="POST" action="">';
	echo '<input type="hidden" name="step" value="3">';
	echo '<input type="hidden" name="site_id" value="' . esc_attr($site_id) . '">';

	foreach ($images as $original_slug => $duplicates) {
		if (count($duplicates) > 1) {
			echo '<h2>' . esc_html($original_slug) . '</h2>';
			foreach ($duplicates as $duplicate) {
				echo '<input type="checkbox" name="delete_ids[]" value="' . esc_attr($duplicate['ID']) . '">';
				echo '<label>' . esc_html($duplicate['name']) . ' (' . esc_html($duplicate['url']) . ')</label><br>';
			}
		}
	}
	echo '<input type="submit" value="Delete Selected Duplicates" class="button button-primary">';
	echo '</form>';
	echo '</div>';

	restore_current_blog();
}

function replace_and_delete_duplicates() {
	if ($_SERVER["REQUEST_METHOD"] != "POST") {
		echo 'Invalid request';
		return;
	}

	$delete_ids = $_POST['delete_ids'];
	$site_id = $_POST['site_id'];
	switch_to_blog($site_id);

	// Loop through each post and replace the image URL.
	$query = new WP_Query(array('posts_per_page' => -1));
	while($query->have_posts()) {
		$query->the_post();
		$content = get_the_content();

		foreach ($delete_ids as $delete_id) {
			$duplicate_url = wp_get_attachment_url($delete_id);
			$original_slug = get_post_field('post_name', $delete_id);
			$original_slug = preg_replace('/-(\d+)$/', '', $original_slug);
			$original_id = attachment_url_to_postid($original_slug);
			$original_url = wp_get_attachment_url($original_id);

			$content = str_replace($duplicate_url, $original_url, $content);
		}

		// Update the post with the new content.
		wp_update_post(array(
			'ID' => get_the_ID(),
			'post_content' => $content,
		));
	}

	// Delete the duplicate images.
	foreach ($delete_ids as $delete_id) {
		wp_delete_attachment($delete_id, true);
	}

	restore_current_blog();

	echo 'Duplicates replaced and deleted successfully.';
}