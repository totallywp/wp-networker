<?php
function wp_networker_comments_page() {
	echo '<div class="wrap">';
	echo '<h1>Manage Network Comments</h1>';

	$commentsTable = new WP_Networker_Comments_Table();
	$commentsTable->prepare_items();
	$commentsTable->display();

	echo '</div>';
}

if (!class_exists('WP_List_Table')) {
	require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class WP_Networker_Comments_Table extends WP_List_Table {

	public function prepare_items() {
		$columns = $this->get_columns();
		$hidden = $this->get_hidden_columns();
		$sortable = $this->get_sortable_columns();

		$this->process_bulk_action();
		$this->items = $this->get_comments();
		$this->_column_headers = array($columns, $hidden, $sortable);
	}

	public function get_columns() {
		return array(
			'cb' => '<input type="checkbox" />',
			'subsite' => 'Subsite',  // New 'Blog' column
			'author' => 'Author',
			'comment' => 'Comment',
			'in_response_to' => 'In Response To',
			'submitted_on' => 'Submitted On',
			'status' => 'Status',  // Add status column
		);
	}

	
	public function get_hidden_columns() {
		return array();
	}

	public function column_default($item, $column_name) {
		switch ($column_name) {
			case 'subsite':
			case 'author':
			case 'comment':
			case 'in_response_to':
			case 'submitted_on':
			case 'status':
				return $item[$column_name];
			default:
				return print_r($item, true);
		}
	}

	public function column_cb($item) {
		return sprintf('<input type="checkbox" name="comment[]" value="%s" />', $item['ID']);
	}

	public function get_bulk_actions() {
		return array(
			'delete' => 'Delete',
		);
	}

	private function get_comments() {
		$comments = [];
		$sites = get_sites();
	
		foreach ($sites as $site) {
			switch_to_blog($site->blog_id);
	
			// Only fetch comments
			$args = array('type' => 'comment');
			$site_comments = get_comments($args);
	
			foreach ($site_comments as $comment) {
				$comments[] = array(
					'ID' => $comment->comment_ID,
					'subsite' => get_option('blogname'),  // Get the blog name
					'author' => $comment->comment_author,
					'comment' => '<a href="' . get_comment_link($comment) . '" target="_blank">' . $comment->comment_content . '</a>',
					'in_response_to' => '<a href="' . get_permalink($comment->comment_post_ID) . '" target="_blank">' . get_the_title($comment->comment_post_ID) . '</a>',
					'submitted_on' => $comment->comment_date,
					'status' => wp_get_comment_status($comment->comment_ID),
					'actions' => $this->column_actions($comment),
				);
			}
	
			restore_current_blog();
		}
	
		usort($comments, function($a, $b) {
			return strtotime($b['submitted_on']) - strtotime($a['submitted_on']);
		});
	
		return $comments;
	}
}