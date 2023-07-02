<?php
/**
* Plugin Name: WP Networker
* Plugin URI: https://totallywp.com/plugins/wp-networker
* Description: Transform your content creation process with WP Networker! This innovative tool uses AI to generate high-quality, unique blog content instantly, streamlining your blogging workflow and optimizing SEO. Take control of your content with WP Networker and blog smarter, not harder.
* Version: 1.0
* Author: TotallyWP
* Author URI: https://totallywp.com
* License: GPLv2 or later
* License URI: https://www.gnu.org/licenses/gpl-2.0.html
* Text Domain: wp-networker
* Domain Path: /languages
    
Disclaimer:    
WP Networker is a free software plugin provided "as-is" and without any expressed or implied warranties. This includes, but is not limited to, the implied warranties of merchantability and fitness for a particular purpose. In no event shall the authors or copyright holders be liable for any claim, damages, or other liability, whether in an action of contract, tort or otherwise, arising from, out of or in connection with the software or the use or other dealings in the software.
*/

if (!defined('ABSPATH')) {
    exit;
}

include plugin_dir_path( __FILE__ ) . 'includes/functions.php';
include plugin_dir_path( __FILE__ ) . 'includes/admin-settings.php';
include plugin_dir_path( __FILE__ ) . 'includes/scheduler.php';
include plugin_dir_path( __FILE__ ) . 'includes/keyword-link-replacement.php';
include plugin_dir_path( __FILE__ ) . 'includes/manage-comments.php';
include plugin_dir_path( __FILE__ ) . 'includes/search-posts.php';
include plugin_dir_path( __FILE__ ) . 'includes/duplicate-images.php';
include plugin_dir_path( __FILE__ ) . 'includes/internal-linking.php';

/*require_once( plugin_dir_path( __FILE__ ) . 'includes/updates.php' );

if (is_admin()) { 
    $updater = new WP_Networker_Updater('wp-networker', 'totallywp', 'wp-networker');
}*/

// Adds the custom Network Admin menus.
add_action( 'network_admin_menu', 'wp_ai_writer' );
function wp_ai_writer() {

    if ( is_multisite() && is_network_admin() ) {
        add_menu_page(
            'WP Networker',             // menu page title
            'WP Networker',             // menu title
            'manage_network',           // user capability required to access the menu (and its page)
            'wp-networker',             // menu slug that's used in the "page" query, e.g. ?page=menu-slug
            'aibg_network_admin_page',  // the function which outputs the page content
            'dashicons-welcome-write-blog', // menu icon (in this case, I'm using a Dashicons icon's CSS class)
            24                          // menu position; 24 would place the menu above the "Settings" menu
        );
    } else {
        add_menu_page(
            'WP Networker',             // menu page title
            'WP Networker',             // menu title
            'manage_options',           // user capability required to access the menu (and its page)
            'wp-networker',             // menu slug that's used in the "page" query, e.g. ?page=menu-slug
            'aibg_network_admin_page',  // the function which outputs the page content
            'dashicons-welcome-write-blog', // menu icon (in this case, I'm using a Dashicons icon's CSS class)
            24                          // menu position; 24 would place the menu above the "Settings" menu
        );
    }

    add_submenu_page( 'wp-networker',
        'Scheduler',
        'Scheduler',
        'manage_network',
        'wpn-scheduler',
        'wp_networker_schedule_settings_page'
    );
    
    add_submenu_page(
        'wp-networker',
        'Keyword Link Replacement',
        'Keyword Link Replacement',
        'manage_network_options',
        'keyword-link-replacement',
        'keyword_link_replacement_page'
    );

    add_submenu_page( 'wp-networker',
        'Manage Network Comments',
        'Comments',
        'manage_network',
        'manage-network-comments',
        'wp_networker_comments_page'
    );
    
    add_submenu_page(
        'wp-networker', 
        'Search Posts', 
        'Search Posts',
        'manage_network',
        'search-posts',
        'render_search_posts_page'
    );
    
    add_submenu_page(
        'wp-networker', 
        'Duplicate Images', 
        'Duplicate Images',
        'manage_network',
        'duplicate-images',
        'find_duplicate_images_page'
    );
    
    add_submenu_page(
        'wp-networker', 
        'Internal Linking', 
        'Internal Linking',
        'manage_network',
        'internal-linking',
        'render_internal_linking_page'
    );
        
    add_submenu_page( 'wp-networker',
        'API Settings',
        'API Settings',
        'manage_network',
        'api-settings',
        'aibg_network_api_settings_page'
    );
}

function aibg_network_admin_page() {
    ?>
<div class="wrap">
        <h1>WP Networker</h1>
        <form id="aibg_form" method="post">
            <input type="hidden" name="aibg_action" value="generate_blog_posts">
            <?php wp_nonce_field('aibg_generate_blog_posts'); ?>
    
            <h2>Select Subsite</h2>
            <select name="subsite_id">
                <?php
                $sites = get_sites();
                foreach ($sites as $site) {
                    echo '<option value="' . $site->blog_id . '">' . $site->blogname . '</option>';
                }
                ?>
            </select>
    
            <h2>Category</h2>
            <select name="category" id="category_select">
                <?php
                $categories = get_categories(array('hide_empty' => 0));
                foreach ($categories as $category) {
                    echo '<option value="' . $category->term_id . '">' . $category->name . '</option>';
                }
                ?>
            </select>
    
            <h2>Blog Post Titles</h2>
            <textarea name="post_titles" placeholder="Enter blog post titles, one per line" rows="20" cols="100"></textarea>
            
            <h2>Choose AI API:</h2>
            <?php 
            $openai_api_key = get_site_option('openai_api_key', '');
            $claude_api_key = get_site_option('claude_api_key', '');
            
            if (!empty($openai_api_key)) { ?>
                <label>
                    <input type="radio" id="openai" name="ai-api" value="openai" checked> OpenAI
                </label>
            <?php }
            if (!empty($claude_api_key)) { ?>
                <label>
                    <input type="radio" id="claude" name="ai-api" value="claude" <?php if(empty($openai_api_key)) echo 'checked'; ?>> Claude
                </label>
            <?php } ?>
    
            <h2>Choose Image API:</h2>
            <?php 
            $pexels_api_key = get_site_option('pexels_api_key', '');
            $pixabay_api_key = get_site_option('pixabay_api_key', '');
            
            if (!empty($pexels_api_key)) { ?>
                <label>
                    <input type="radio" id="pexels" name="image-api" value="pexels" checked> Pexels
                </label>
            <?php }
            if (!empty($pixabay_api_key)) { ?>
                <label>
                    <input type="radio" id="pixabay" name="image-api" value="pixabay" <?php if(empty($pexels_api_key)) echo 'checked'; ?>> Pixabay
                </label>
            <?php } ?> 
    
            <p>
                <button id="aibg_generate_button" class="button button-primary">Generate Blog Posts</button>
            </p>
            
            <div id="aibg_progress_container" style="display:none; margin-top: 10px;">
                <div id="aibg_progress_bar" style="background-color: blue; width: 0; height: 20px;"></div>
                <p id="aibg_progress_text"></p>
            </div> 
        </form>
    </div>

    <script>
    var ajaxurl = '<?php echo admin_url( 'admin-ajax.php'); ?>';
    document.addEventListener('DOMContentLoaded', function() {
        var generateButton = document.getElementById('aibg_generate_button');
        var progressBar = document.getElementById('aibg_progress_bar');
        var progressContainer = document.getElementById('aibg_progress_container');
        var progressText = document.getElementById('aibg_progress_text');
        var form = document.getElementById('aibg_form');
    
        generateButton.addEventListener('click', function(event) {
            event.preventDefault();
    
            progressBar.style.width = '0%';
            progressText.textContent = 'Writing the first post... Do not close this window...';
    
            var formData = new FormData(form);
            formData.append('action', 'aibg_generate_blog_posts');
            var titles = formData.get('post_titles').split('\n');
            var category = form.querySelector('[name="category"]').value; // Get the selected category value
            formData.delete('post_titles'); // Remove the old post_titles from formData
            titles.forEach(function(title) {
                formData.append('post_titles[]', title.trim()); // Add each title as a separate value in formData
            });
            formData.append('category', category); // Add the category to formData
    
            var completed = 0;
    
            function processTitle(retryCount = 0) {
                if (completed >= titles.length) {
                    progressText.textContent = 'All posts generated! Please, create some more :)';
            
                    // Clear blog title textarea
                    document.querySelector('textarea[name="post_titles"]').value = '';
            
                    // Hide progress container
                    setTimeout(() => {
                        progressContainer.style.display = 'none';
                    }, 3000);
            
                    return;
                }
            
                progressContainer.style.display = 'block';
                var title = titles[completed];
                formData.set('post_titles[]', title);
            
                fetch(ajaxurl, {
                    method: 'POST',
                    body: formData
                })
                .then(function(response) {
                    console.log(response); // Log the response to the console
                    if (!response.ok) {
                        if ((response.status === 429 || response.status === 503) && retryCount < 5) { 
                            // if it's a rate limit error or service unavailable, and we've tried less than 5 times
                            var waitTime = Math.pow(2, retryCount) * 1000; // exponential backoff, wait for 2^retryCount seconds
                            console.log('Waiting for ' + waitTime/1000 + ' seconds due to error ' + response.status); // log wait time
                            setTimeout(() => {
                                processTitle(retryCount + 1); // retry after waiting
                            }, waitTime);
                        } else {
                            throw new Error('processTitle - Network response was not ok');
                        }
                    } else {
                        return response.json();
                    }
                })
                .then(function(data) {
                    if (data && data.error && data.error.includes("API response does not contain the expected data") && retryCount < 5) {
                        var waitTime = Math.pow(2, retryCount) * 1000; // exponential backoff, wait for 2^retryCount seconds
                        console.log('Waiting for ' + waitTime/1000 + ' seconds due to error: ' + data.error); // log wait time
                        setTimeout(() => {
                            processTitle(retryCount + 1); // retry after waiting
                        }, waitTime);
                    } else if (data && data.error) {
                        throw new Error(data.error);
                    } else {
                        completed++;
                        var progress = Math.floor((completed / titles.length) * 100);
                        progressBar.style.width = progress + '%';
                        progressText.textContent = 'Completed ' + completed + ' of ' + titles.length + ' posts, hang tight...';
            
                        processTitle();
                    }
                })
                .catch(function(error) {
                    console.log('Error: ' + error.message); // log the error message
                    progressText.textContent = 'Error: ' + error.message;
                });
            }
   
    
            processTitle();
        });
        
        function updateCategories(subsiteId) {
            fetch(ajaxurl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'aibg_get_categories',
                        subsite_id: subsiteId,
                    }),
                })
                .then(function(response) {
                    if (!response.ok) {
                        throw new Error('updateCategories - Network response was not ok: ' + response.status);
                    }
                    return response.json();
                })
                .then(function(categories) {
                    var categorySelect = document.getElementById('category_select');
                    categorySelect.innerHTML = '';
                    categories.forEach(function(category) {
                        var option = document.createElement('option');
                        option.value = category.term_id;
                        option.textContent = category.name;
                        categorySelect.appendChild(option);
                    });
                })
                .catch(function(error) {
                    console.error('Error fetching categories:', error);
                });
        }
        
        var subsiteSelect = document.getElementsByName('subsite_id')[0];
        subsiteSelect.addEventListener('change', function() {
            updateCategories(this.value);
        });
    });
    </script>
    <?php
}

function network_stats_dashboard_widget() {
    wp_add_dashboard_widget(
        'network_stats_dashboard_widget',
        'Network Statistics',
        'network_stats_dashboard_widget_callback'
    );
}
add_action('wp_network_dashboard_setup', 'network_stats_dashboard_widget', 20);

function network_stats_dashboard_widget_callback() {
    $site_count = get_blog_count();
    $total_published_posts = 0;
    $total_scheduled_posts = 0;
    $total_draft_posts = 0;
    $total_categories = 0;

    $sites = get_sites();

    foreach ($sites as $site) {
        switch_to_blog($site->blog_id);

        $post_counts = wp_count_posts();
        $total_published_posts += $post_counts->publish;
        $total_scheduled_posts += $post_counts->future;
        $total_draft_posts += $post_counts->draft;

        $total_categories += wp_count_terms('category');

        restore_current_blog();
    }

    echo '<ul>';
    echo '<li>Number of Sites: ' . $site_count . '</li>';
    echo '<li>Total Published Posts: ' . $total_published_posts . '</li>';
    echo '<li>Total Scheduled Posts: ' . $total_scheduled_posts . '</li>';
    echo '<li>Total Draft Posts: ' . $total_draft_posts . '</li>';
    echo '<li>Total Categories: ' . $total_categories . '</li>';
    echo '</ul>';
}