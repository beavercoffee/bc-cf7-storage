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

    	private function upload_file($tmp = '', $post_id = 0){
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

    	private function upload_files($submission = null, $post_id = 0){
            $files = [];
            $uploaded_files = $submission->uploaded_files();
            if($uploaded_files){
                foreach($uploaded_files as $key => $value){
                    foreach((array) $value as $single){
                        $attachment_id = $this->upload_file($single, $post_id);
                        if(is_wp_error($attachment_id)){
                            return $attachment_id;
                        }
                        $files[] = [
                            'filename' => wp_basename($single),
                            'id' => $attachment_id,
                        ];
                    }
                }
            }
            return $files;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    	//
    	// public
    	//
    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function bc_cf7_storage_files($atts, $content = ''){
            $atts = shortcode_atts([
                'key' => '',
                'type' => 'post',
            ], $atts, 'bc_cf7_storage_files');
            $html = '';
            $key = $atts['key'];
            $type = $atts['type'];
            switch($type){
                case 'post':
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
                    break;
                case 'user':
                    $user_id = get_current_user_id();
                    if($user_id){
                        $files = get_user_meta($user_id, 'bc_' . $key . '_files', true);
                        if($files){
                            $html = [];
                            $files = wp_list_pluck($files, 'filename', 'id');
                            foreach($files as $id => $filename){
                                $html[] = '<a href="' . wp_get_attachment_url($id) . '" target="_blank">' . $filename . '</a>';
                            }
                            $html = __('Uploaded') . ': ' . implode(', ', $html);
                        }
                    }
                    break;
            }
            if(!$html){
                $html = __('No media items found.');
            }
            return $html;
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
                $loading = $contact_form->pref('bc_loading');
                if(null === $loading or '' === $loading){
                    $loading = __('Loading&hellip;');
                }
                $hidden_fields['bc_loading'] = $loading;
                $thank_you = $contact_form->pref('bc_thank_you');
                if(null === $thank_you or '' === $thank_you){
                    $thank_you = $contact_form->message('mail_sent_ok');
                }
                $hidden_fields['bc_thank_you'] = $thank_you;
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
            if(null !== $type){
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
            $user_id = apply_filters('bc_cf7_storage_user_id', 0, $contact_form);
            if($user_id){
                foreach($meta_data as $key => $value){
                    update_user_meta($user_id, $key, $value);
                }
                foreach($posted_data as $key => $value){
                    if(is_array($value)){
    					delete_user_meta($user_id, $key);
    					foreach($value as $single){
    						add_user_meta($user_id, $key, $single);
    					}
    				} else {
    					update_user_meta($user_id, $key, $value);
    				}
    			}
                $files = $this->upload_files($submission);
                if(is_wp_error($files)){
                    $submission->set_response($files->get_error_message());
                    $submission->set_status('aborted');
                    return;
                }
                update_user_meta($user_id, 'bc_' . $key . '_files', $files);
                do_action('bc_cf7_update_user', $user_id);
            } else {
                $post_id = apply_filters('bc_cf7_storage_post_id', 0, $contact_form);
                $update = false;
    			if($post_id){
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
                    update_post_meta($post_id, $key, $value);
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
                $files = $this->upload_files($submission, $post_id);
                if(is_wp_error($files)){
                    $submission->set_response($files->get_error_message());
                    $submission->set_status('aborted');
                    return;
                }
                update_post_meta($post_id, 'bc_' . $key . '_files', $files);
                if($update){
                    do_action('bc_cf7_update_post', $post_id);
    			} else {
    				do_action('bc_cf7_insert_post', $post_id);
    			}
            }
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    }
}
