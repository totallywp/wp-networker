<?php

function keyword_link_replacement_page() {
	if (isset($_POST['keyword_link_replacement'])) {
		check_admin_referer('keyword_link_replacement_options');
		update_site_option('keyword_link_replacement', $_POST['keyword_link_replacement']);
		echo '<div id="message" class="updated fade"><p><strong>Options saved.</strong></p></div>';
	}

	$options = get_site_option('keyword_link_replacement', '');

	echo '<div class="wrap">';
	echo '<h2>Keyword Link Replacement</h2>';
	echo '<form method="post" action="">';

	if (function_exists('wp_nonce_field')) {
		wp_nonce_field('keyword_link_replacement_options');
	}

	echo '<table class="form-table">';
	echo '<tr valign="top">';
	echo '<th scope="row">Keywords and Links</th>';
	echo '<td><textarea name="keyword_link_replacement" rows="10" cols="50" class="large-text code">' . esc_textarea($options) . '</textarea>';
	echo '<p class="description">Enter each keyword and link pair on a new line in the format "keyword,http://link.url".</p>';
	echo '</td></tr>';
	echo '</table>';

	echo '<p class="submit"><input type="submit" name="Submit" class="button-primary" value="Save Changes" /></p>';
	echo '</form></div>';
}

function replace_keywords_with_links($content) {
	$options = get_site_option('keyword_link_replacement', '');
	$replacements = [];
	$lines = explode("\n", $options);
	$contentAltered = false;

	foreach ($lines as $line) {
		list($keyword, $link) = explode(',', $line, 2);
		$replacements[$keyword] = '<a href="' . esc_url($link) . '">' . esc_html($keyword) . '</a>';
	}

	foreach($replacements as $search => $replace) {
		$pos = strpos($content, $search);
		if($pos !== false) {
			$content = substr_replace($content, $replace, $pos, strlen($search));
			$contentAltered = true;
		}
	}

	if($contentAltered) {
		$content .= '<p><em>This post may contain affiliate links, where I may receive a small commission if you purchase something through following the link at no cost to you.</em></p>';
	}

	return $content;
}
add_filter('the_content', 'replace_keywords_with_links');