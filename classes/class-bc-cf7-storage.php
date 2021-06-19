<?php

if(!class_exists('BC_CF7_Storage')){
    final class BC_CF7_Storage {

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    	//
    	// private static
    	//
    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        private static $instance = null;

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    	//
    	// public static
    	//
    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    	public static function get_instance($file = ''){
            if(null === self::$instance){
                if(@is_file($file)){
                    self::$instance = new self($file);
                } else {
                    wp_die(__('File doesn&#8217;t exist?'));
                }
            }
            return self::$instance;
    	}

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    	//
    	// private
    	//
    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        private $file = '';

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    	private function __clone(){}

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    	private function __construct($file = ''){
            $this->file = $file;
            add_action('init', [$this, 'init']);
            add_action('wpcf7_enqueue_scripts', [$this, 'wpcf7_enqueue_scripts']);
            add_action('wpcf7_mail_sent', [$this, 'wpcf7_mail_sent']);
            add_filter('bc_cf7_redirect_hidden_fields', [$this, 'bc_cf7_redirect_hidden_fields']);
            add_filter('do_shortcode_tag', [$this, 'do_shortcode_tag'], 10, 4);
            add_filter('shortcode_atts_wpcf7', [$this, 'shortcode_atts_wpcf7'], 10, 3);
            add_filter('wpcf7_form_elements', 'do_shortcode');
            add_filter('wpcf7_form_hidden_fields', [$this, 'wpcf7_form_hidden_fields']);
            add_filter('wpcf7_verify_nonce', 'is_user_logged_in');
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    	private function copy($source = '', $destination = '', $overwrite = false, $mode = false){
            global $wp_filesystem;
            $fs = $this->filesystem();
            if(is_wp_error($fs)){
                return $fs;
            }
            if(!$wp_filesystem->copy($source, $destination, $overwrite)){
                return new WP_Error('files_not_writable', sprintf(__('The uploaded file could not be moved to %s.'), $destination));
            }
            return $destination;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    	private function filesystem(){
            global $wp_filesystem;
            if($wp_filesystem instanceof WP_Filesystem_Direct){
                return true;
            }
            if(!function_exists('get_filesystem_method')){
                require_once(ABSPATH . 'wp-admin/includes/file.php');
            }
            if('direct' !== get_filesystem_method()){
                return new WP_Error('fs_unavailable', __('Could not access filesystem.'));
            }
            if(!WP_Filesystem()){
                return new WP_Error('fs_error', __('Filesystem error.'));
            }
            return true;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    	private function output($post_id, $attr, $content, $tag){
            global $post;
            $post = get_post($post_id);
            setup_postdata($post);
            $output = wpcf7_contact_form_tag_func($attr, $content, $tag);
            wp_reset_postdata();
            return $output;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        private function post_type_labels($singular = '', $plural = '', $all = true){
            if(!$singular or !$plural){
                return [];
            }
            return [
                'name' => $plural,
                'singular_name' => $singular,
                'add_new' => 'Add New',
                'add_new_item' => 'Add New ' . $singular,
                'edit_item' => 'Edit ' . $singular,
                'new_item' => 'New ' . $singular,
                'view_item' => 'View ' . $singular,
                'view_items' => 'View ' . $plural,
                'search_items' => 'Search ' . $plural,
                'not_found' => 'No ' . strtolower($plural) . ' found.',
                'not_found_in_trash' => 'No ' . strtolower($plural) . ' found in Trash.',
                'parent_item_colon' => 'Parent ' . $singular . ':',
                'all_items' => ($all ? 'All ' : '') . $plural,
                'archives' => $singular . ' Archives',
                'attributes' => $singular . ' Attributes',
                'insert_into_item' => 'Insert into ' . strtolower($singular),
                'uploaded_to_this_item' => 'Uploaded to this ' . strtolower($singular),
                'featured_image' => 'Featured image',
                'set_featured_image' => 'Set featured image',
                'remove_featured_image' => 'Remove featured image',
                'use_featured_image' => 'Use as featured image',
                'filter_items_list' => 'Filter ' . strtolower($plural) . ' list',
                'items_list_navigation' => $plural . ' list navigation',
                'items_list' => $plural . ' list',
                'item_published' => $singular . ' published.',
                'item_published_privately' => $singular . ' published privately.',
                'item_reverted_to_draft' => $singular . ' reverted to draft.',
                'item_scheduled' => $singular . ' scheduled.',
                'item_updated' => $singular . ' updated.',
            ];
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    	private function sanitize_post_id($post_id){
            $post = null;
            if(is_numeric($post_id)){
                $post = get_post($post_id);
            } else {
                if('current' === $post_id){
                    $post = get_post();
                }
            }
            if(null === $post){
                return 0;
            }
            return $post->ID;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    	private function upload($tmp = '', $post_id = 0){
            global $wp_filesystem;
            $upload_dir = wp_upload_dir();
            $original_filename = wp_basename($tmp);
            $filename = wp_unique_filename($upload_dir['path'], $original_filename);
            $file = trailingslashit($upload_dir['path']) . $filename;
            $result = $this->copy($tmp, $file);
            if(is_wp_error($result)){
                return $result;
            }
            $filetype_and_ext = wp_check_filetype_and_ext($file, $filename);
            if(!$filetype_and_ext['type']){
                return new WP_Error('invalid_filetype', __('Sorry, this file type is not permitted for security reasons.'));
            }
            $attachment_id = wp_insert_attachment([
                'guid' => str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $file),
                'post_mime_type' => $filetype_and_ext['type'],
                'post_status' => 'inherit',
                'post_title' => preg_replace('/\.[^.]+$/', '', $original_filename),
            ], $file, $post_id, true);
            if(is_wp_error($attachment_id)){
                return $attachment_id;
            }
            $attachment = get_post($attachment_id);
            wp_raise_memory_limit('image');
            wp_maybe_generate_attachment_metadata($attachment);
            return $attachment_id;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    	//
    	// public
    	//
    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function bc_cf7_redirect_hidden_fields($hidden_fields){
            $contact_form = wpcf7_get_current_contact_form();
            if($contact_form !== null){
                $post_id = $contact_form->shortcode_attr('bc_post_id');
                if(null !== $post_id){
                    $post_id = $this->sanitize_post_id($post_id);
                    if(0 !== $post_id){
                        $uniqid = get_post_meta($post_id, 'bc_uniqid', true);
    					if($uniqid){
    						$hidden_fields['bc_uniqid'] = $uniqid;
    					}
                    }
                }
            }
            return $hidden_fields;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function bc_cf7_storage_files($atts, $content = ''){
            $atts = shortcode_atts([
                'key' => '',
            ], $atts, 'bc_cf7_storage_files');
            $html = '';
            $key = $atts['key'];
            $post_id = get_the_ID();
            if($post_id){
                $files = get_post_meta($post_id, 'bc_' . $key . '_files', true);
                if($files){
                    $html = [];
                    $files = wp_list_pluck($files, 'filename', 'id');
                    foreach($files as $id => $filename){
                        $html[] = '<a href="' . wp_get_attachment_url($id) . '" target="_blank">' . $filename . '</a>';
                    }
                    $html = __('Uploaded') . ': ' . implode(', ', $html);
                }
            }
            if(!$html){
                $html = __('No media items found.');
            }
            return $html;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function do_shortcode_tag($output, $tag, $attr, $m){
			if('contact-form-7' !== $tag){
                return $output;
            }
            $contact_form = wpcf7_get_current_contact_form();
            if(null === $contact_form){
                return $output;
            }
            $post_id = $contact_form->shortcode_attr('bc_post_id');
            if(null === $post_id){
                return $output;
            }
            $post_id = $this->sanitize_post_id($post_id);
            if(0 === $post_id){
                return '<div class="alert alert-danger" role="alert">' . __('Invalid post ID.') . '</div>';
            }
            if(!current_user_can('edit_post', $post_id)){
                if('post' === get_post_type($post_id)){
                    $message = __('Sorry, you are not allowed to edit this post.');
                } else {
                    $message = __('Sorry, you are not allowed to edit this item.');
                }
                $message .=  ' ' . __('You need a higher level of permission.');
                return '<div class="alert alert-danger" role="alert">' . $message . '</div>';
			} else {
				if('trash' === get_post_status($post_id)){
                    return '<div class="alert alert-danger" role="alert">' . __('You can&#8217;t edit this item because it is in the Trash. Please restore it and try again.') . '</div>';
				}
			}
            if(isset($_GET['bc_referer'])){
                if(get_post_meta($post_id, 'bc_uniqid', true) === $_GET['bc_referer']){
                    $html_class = isset($attr['html_class']) ? trim($attr['html_class']) : '';
                    if('' === $html_class){
                        $html_class = 'bc_updated';
                    } else {
                        $html_class .= ' bc_updated';
                    }
                    $attr['html_class'] = $html_class;
                }
            }
            $content = isset($m[5]) ? $m[5] : null;
            $output = $this->output($post_id, $attr, $content, $tag);
            return $output;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function init(){
            add_shortcode('bc_cf7_storage_files', [$this, 'bc_cf7_storage_files']);
            register_post_type('bc_cf7_submission', [
                'labels' => $this->post_type_labels('Submission', 'Submissions', false),
                'show_in_admin_bar' => false,
                'show_in_menu' => 'wpcf7',
                'show_ui' => true,
                'supports' => ['custom-fields', 'title'],
            ]);
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function shortcode_atts_wpcf7($out, $pairs, $atts){
            if(isset($atts['bc_post_id'])){
                $out['bc_post_id'] = $atts['bc_post_id'];
            }
            return $out;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function wpcf7_enqueue_scripts(){
            $src = plugin_dir_url($this->file) . 'assets/bc-cf7-storage.js';
            $ver = filemtime(plugin_dir_path($this->file) . 'assets/bc-cf7-storage.js');
            wp_enqueue_script('bc-cf7-storage', $src, ['contact-form-7'], $ver, true);
            wp_add_inline_script('bc-cf7-storage', 'bc_cf7_storage.init();');
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function wpcf7_form_hidden_fields($hidden_fields){
            $contact_form = wpcf7_get_current_contact_form();
            if($contact_form !== null){
                $post_id = $contact_form->shortcode_attr('bc_post_id');
                if(null !== $post_id){
                    $post_id = $this->sanitize_post_id($post_id);
                    if(0 !== $post_id){
                        $hidden_fields['bc_nonce'] = wp_create_nonce('bc_edit_post-' . $post_id);
                        $hidden_fields['bc_post_id'] = $post_id;
                    }
                }
                $message = $contact_form->pref('bc_storage_message');
                if(null === $message){
                    $message = $contact_form->message('mail_sent_ok');
                }
                $hidden_fields['bc_storage_message'] = $message;
            }
            $hidden_fields = apply_filters('bc_cf7_storage_hidden_fields', $hidden_fields);
            return $hidden_fields;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function wpcf7_mail_sent($contact_form){
            if($contact_form->is_true('do_not_store')){
                return;
            }
            $type = $contact_form->pref('bc_type');
            if(null === $type){
                return;
            }
            $submission = WPCF7_Submission::get_instance();
            if(null === $submission){
                return;
            }
            $posted_data = $submission->get_posted_data();
            if(!$posted_data){
                return;
            }
            $meta_data = [
                'bc_container_post_id' => $submission->get_meta('container_post_id'),
                'bc_current_user_id' => $submission->get_meta('current_user_id'),
                'bc_id' => $contact_form->id(),
                'bc_locale' => $contact_form->locale(),
                'bc_name' => $contact_form->name(),
                'bc_remote_ip' => $submission->get_meta('remote_ip'),
                'bc_remote_port' => $submission->get_meta('remote_port'),
                'bc_response' => $submission->get_response(),
                'bc_status' => $submission->get_status(),
                'bc_timestamp' => $submission->get_meta('timestamp'),
                'bc_title' => $contact_form->title(),
                'bc_unit_tag' => $submission->get_meta('unit_tag'),
                'bc_url' => $submission->get_meta('url'),
                'bc_user_agent' => $submission->get_meta('user_agent'),
            ];
            $post_id = $submission->get_posted_data('bc_post_id');
            $update = false;
			if(null !== $post_id){
                $nonce = $submission->get_posted_data('bc_nonce');
                if(null === $nonce){
                    $nonce = '';
                }
                if(!wp_verify_nonce($nonce, 'bc_edit_post-' . $post_id)){
    				$submission->set_response(__('Error while saving.'));
                    $submission->set_status('aborted');
                    return;
                }
                $update = true;
            } else {
                $post_id = wp_insert_post([
					'post_status' => 'private',
					'post_title' => sprintf('[contact-form-7 id="%1$d" title="%2$s"]', $contact_form->id(), $contact_form->title()),
					'post_type' => 'bc_cf7_submission',
				], true);
                if(is_wp_error($post_id)){
                    $submission->set_response($post_id->get_error_message());
                    $submission->set_status('aborted');
                    return;
                }
            }
            foreach($meta_data as $key => $value){
                add_post_meta($post_id, $key, $value);
            }
            foreach($posted_data as $key => $value){
                if(is_array($value)){
					delete_post_meta($post_id, $key);
					foreach($value as $single){
						add_post_meta($post_id, $key, $single);
					}
				} else {
					update_post_meta($post_id, $key, $value);
				}
			}
            $uploaded_files = $submission->uploaded_files();
            if($uploaded_files){
                foreach($uploaded_files as $key => $value){
                    $files = [];
                    foreach((array) $value as $single){
                        $attachment_id = $this->upload($single, $post_id);
                        if(is_wp_error($attachment_id)){
                            $submission->set_response($attachment_id->get_error_message());
                            $submission->set_status('aborted');
                            return;
                        }
                        $files[] = [
                            'filename' => wp_basename($single),
                            'id' => $attachment_id,
                        ];
                    }
                    update_post_meta($post_id, 'bc_' . $key . '_files', $files);
                }
			}
            if($update){
                do_action('bc_cf7_update_post', $post_id);
			} else {
				do_action('bc_cf7_insert_post', $post_id);
			}
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    }
}
