<?php
function wp_networker_schedule_settings_page() {
	if (!current_user_can('manage_options'))  {
		wp_die(__('You do not have sufficient permissions to access this page.'));
	}

	// Get all sites in the network
	$sites = get_sites();

	// If a site is selected
	if (isset($_POST['site_id'])) {
		$site_id = $_POST['site_id'];
		switch_to_blog($site_id);

		// If posts are selected to be scheduled
		if (isset($_POST['posts'])) {
			$selected_posts = $_POST['posts'];

			// Use provided schedule start time or default to the latest scheduled post time or tomorrow
			if (!empty($_POST['schedule_start_time'])) {
				$schedule_date = new DateTime($_POST['schedule_start_time'], new DateTimeZone(wp_timezone_string()));
			} else {
				// Get the latest post scheduled in future
				$args = array(
					'post_status' => 'future',
					'orderby' => 'date',
					'order' => 'DESC',
					'posts_per_page' => 1,
				);
				$latest_post = get_posts($args);

				if (!empty($latest_post)) {
					// Set the initial date to the day after the latest post scheduled
					$schedule_date = new DateTime($latest_post[0]->post_date, new DateTimeZone(wp_timezone_string()));
					$schedule_date->modify('+1 day');
				} else {
					// If no future post is scheduled yet, set the initial date to tomorrow
					$schedule_date = new DateTime('tomorrow', new DateTimeZone(wp_timezone_string()));
				}
			}

			// Use provided post interval or default to 1 day
			$interval = !empty($_POST['post_interval']) ? intval($_POST['post_interval']) : 1;

			foreach ($selected_posts as $post_id) {
				$post = get_post($post_id);
				$post->post_date = $schedule_date->format('Y-m-d H:i:s');
				$post->post_date_gmt = get_gmt_from_date($post->post_date);
				$post->edit_date = true; // Important: set edit_date to true to tell WordPress that we're editing the date
				$post->post_status = 'future';

				// Avoid default WP future post event
				remove_action('future_post', '_future_post_hook');
				wp_update_post($post);
				add_action('future_post', '_future_post_hook');

				// Add interval to the schedule date.
				$schedule_date->modify('+' . $interval . ' day');
			}

			echo '<div class="updated"><p>Posts scheduled successfully!</p></div>';
		}

		// Get all draft posts of the 'post' type from the selected site
		$args = array(
			'post_type' => 'post',
			'post_status' => 'draft',
			'posts_per_page' => -1,
		);
		$posts = get_posts($args);

		restore_current_blog();
	}

	echo '<div class="wrap">';
	echo '<h1>WP Networker Scheduler</h1>';

	echo '<form method="post">';

	echo '<table class="form-table">';
	echo '<tr><th>Select a Site:</th><td><select name="site_id">';
	foreach ($sites as $site) {
		echo '<option value="' . $site->blog_id . '"';
		if (isset($site_id) && $site_id == $site->blog_id) echo ' selected';
		echo '>' . get_blog_details($site->blog_id)->blogname . '</option>';
	}
	echo '</select></td></tr>';

	echo '<tr><th>Schedule Start Time:</th><td>';
	echo '<input type="datetime-local" name="schedule_start_time" value="' . current_time('Y-m-d\TH:i') . '" />';
	echo '</td></tr>';

	echo '<tr><th>Post Interval (days):</th><td>';
	echo '<input type="number" name="post_interval" min="1" value="1" />';
	echo '</td></tr>';

	if (isset($site_id)) {
		echo '<tr><th>Select Posts to Schedule:</th><td>';
		foreach ($posts as $post) {
			echo '<input type="checkbox" name="posts[]" value="' . $post->ID . '"> ' . $post->post_title . '<br>';
		}
		echo '</td></tr>';
	}

	echo '</table>';

	echo '<p class="submit">';
	echo '<input type="submit" class="button-primary" value="Save Changes" />';
	echo '</p>';

	echo '</form>';

	echo '</div>';
}
