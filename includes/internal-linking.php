<?php

if(!class_exists('WP_List_Table')){
	require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class Internal_Linking_Table extends WP_List_Table {
	public $data = array();

	function prepare_items() {
		$columns = $this->get_columns();
		$hidden = $this->get_hidden_columns();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array($columns, $hidden, $sortable);
		$this->items = $this->data;
	}

	function get_columns() {
		return array(
			'title' => 'Initial Post',
			'matching_post' => 'Matching Post',
			'matching_tags' => 'Matching Tags'
		);
	}

	function get_hidden_columns() {
		return array();
	}

	function column_default($item, $column_name) {
		return $item[$column_name];
	}

	function column_title($item) {
		return sprintf('<a href="%s" target="_blank">%s</a>', $item['link'], $item['title']);
	}

	function column_matching_post($item) {
		return sprintf('<a href="%s" target="_blank">%s</a>', $item['matching_post']['link'], $item['matching_post']['title']);
	}
}

function render_internal_linking_page(){
	echo '<h2>Related Posts across the Network</h2>';

	$sites = get_sites();
	$all_posts = array();

	foreach($sites as $site) {
		switch_to_blog($site->blog_id);

		// Query all posts
		$search_query = new WP_Query(array(
			'post_type' => 'post',
			'post_status' => 'publish',
			'posts_per_page' => -1,
		));

		while($search_query->have_posts()) {
			$search_query->the_post();
			$tags = wp_get_post_tags($search_query->post->ID, array('fields' => 'slugs'));
			$all_posts[] = array(
				'title' => get_the_title(),
				'link' => get_the_permalink(),
				'tags' => implode(', ', $tags)
			);
		}

		restore_current_blog();
	}

	// Now compare tags across all posts.
	$related_posts = array();
	foreach($all_posts as $key => $post) {
		foreach($all_posts as $inner_key => $inner_post) {
			if($post['link'] !== $inner_post['link']) {
				$matching_tags = array_intersect(explode(', ', $post['tags']), explode(', ', $inner_post['tags']));
				if(count($matching_tags) > 0) {
					$post['matching_tags'] = implode(', ', $matching_tags);
					$post['matching_post'] = array(
						'title' => $inner_post['title'],
						'link' => $inner_post['link'],
					);
					$related_posts[] = $post;
					break;
				}
			}
		}
	}

	$table = new Internal_Linking_Table();
	$table->data = $related_posts;
	$table->prepare_items();
	$table->display();
}