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
*/

if (!defined('ABSPATH')) {
    exit;
}

include plugin_dir_path( __FILE__ ) . 'includes/functions.php';
include plugin_dir_path( __FILE__ ) . 'includes/admin-settings.php';

// Adds the custom Network Admin menus.
add_action( 'network_admin_menu', 'wp_ai_writer' );
function wp_ai_writer() {

    add_menu_page( 'WP Networker',      // menu page title
        'WP Networker',          // menu title
        'manage_network',                // user capability required to access the menu (and its page)
        'wp-networker',                // menu slug to that's with the "page" query, e.g. ?page=menu-slug
        'aibg_network_admin_page', // the function which outputs the page content
        'dashicons-welcome-write-blog',         // menu icon (in this case, I'm using a Dashicons icon's CSS class)
        24                               // menu position; 24 would place the menu above the "Settings" menu
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
        <h1>AI Blog Generator</h1>
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
            <div id="aibg_input_fields">
                <div class="aibg_input_field">
                    <input type="text" name="post_titles[]" placeholder="Blog post title...">
                    <button type="button" class="aibg_remove_input">Remove</button>
                </div>
            </div>
            <button type="button" id="aibg_add_input">Add input field</button>
            
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
        var addButton = document.getElementById('aibg_add_input');
        var inputFields = document.getElementById('aibg_input_fields');
    
        addButton.addEventListener('click', function() {
            var newField = document.createElement('div');
            newField.className = 'aibg_input_field';
            newField.innerHTML = '<input type="text" name="post_titles[]" placeholder="Blog post title..."><button type="button" class="aibg_remove_input">Remove</button>';
            inputFields.appendChild(newField);
        });
    
        inputFields.addEventListener('click', function(event) {
            if (event.target.classList.contains('aibg_remove_input')) {
                inputFields.removeChild(event.target.parentNode);
            }
        });
    
        var subsiteSelect = document.getElementsByName('subsite_id')[0];
        updateCategories(subsiteSelect.value);
    
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
            var titles = formData.getAll('post_titles[]');
            var category = form.querySelector('[name="category"]').value; // Get the selected category value
            formData.append('category', category); // Add the category to formData
    
            var completed = 0;
    
            function processTitle() {
                if (completed >= titles.length) {
                    progressText.textContent = 'All posts generated! Please, create some more :)';
    
                    // Clear blog title inputs
                    document.querySelectorAll('input[name="post_titles[]"]').forEach(input => {
                        input.value = '';
                    });
    
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
                        throw new Error('processTitle - Network response was not ok');
                    }
                    return response.json();
                })
                .then(function(data) {
                    console.log(data); // Log the JSON data to the console
                    if (data.error) {
                        throw new Error(data.error);
                    }
    
                        completed++;
                        var progress = Math.floor((completed / titles.length) * 100);
                        progressBar.style.width = progress + '%';
                        progressText.textContent = 'Completed ' + completed + ' of ' + titles.length + ' posts, hang tight...';
    
                        processTitle();
                    })
                    .catch(function(error) {
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