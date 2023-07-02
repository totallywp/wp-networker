<?php

function render_search_posts_page(){
	if(isset($_POST['submit'])) {
		$blog_id = intval($_POST['blog_id']);
		$search_phrase = sanitize_text_field($_POST['search_phrase']);

		switch_to_blog($blog_id);

		$search_query = new WP_Query(array(
			's' => $search_phrase,
			'post_type' => 'post'
		));
		echo '<div class="wrap">';
		echo '<h1>Search Posts</h1>';
		echo '<h2>Search Results for "' . $search_phrase . '"</h2>';

		if($search_query->have_posts()) {
			echo '<ul>';
			while($search_query->have_posts()) {
				$search_query->the_post();
				echo '<li><a href="' . get_the_permalink() . '" target="_blank">' . get_the_title() . '</a></li>';
			}
			echo '</ul>';
		echo '</div>';

		} else {
			echo '<p>No results found.</p>';
		}

		restore_current_blog();
	} else {
		echo '<div class="wrap">';
		echo '<h1>Search Posts</h1>';
		echo '<form method="post">';
		echo '<label for="blog_id">Select Blog:</label>';
		echo '<select name="blog_id">';
		$sites = get_sites();
		foreach($sites as $site) {
			echo '<option value="' . $site->blog_id . '">' . get_blog_details($site->blog_id)->blogname . '</option>';
		}
		echo '</select><br />';
		echo '<label for="search_phrase">Search Phrase:</label>';
		echo '<input type="text" name="search_phrase" /><br />';
		echo '<input type="submit" name="submit" value="Search" />';
		echo '</form>';
		echo '</div>';

	}
}