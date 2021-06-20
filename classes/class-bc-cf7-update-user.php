<?php

if(!class_exists('BC_CF7_Update_User')){
    final class BC_CF7_Update_User {

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
            add_filter('bc_cf7_redirect_hidden_fields', [$this, 'bc_cf7_redirect_hidden_fields']);
            add_filter('bc_cf7_storage_hidden_fields', [$this, 'bc_cf7_storage_hidden_fields']);
            add_filter('bc_cf7_storage_user_id', [$this, 'bc_cf7_storage_user_id']);
            add_filter('do_shortcode_tag', [$this, 'do_shortcode_tag'], 10, 4);
            add_filter('shortcode_atts_wpcf7', [$this, 'shortcode_atts_wpcf7'], 10, 3);
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    	private function output($user_id, $attr, $content, $tag){
            $original_user_id = get_current_user_id();
            wp_set_current_user($user_id);
            $output = wpcf7_contact_form_tag_func($attr, $content, $tag);
            wp_set_current_user($original_user_id);
            return $output;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        private function sanitize_user_id($user_id){
            $user = false;
            if(is_numeric($user_id)){
                $user = get_userdata($user_id);
            } else {
                if('current' === $user_id and is_user_logged_in()){
                    $user = wp_get_current_user();
                }
            }
            if(!$user){
                return 0;
            }
            return $user->ID;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
    	//
    	// public
    	//
    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function bc_cf7_redirect_hidden_fields($hidden_fields){
            $contact_form = wpcf7_get_current_contact_form();
            if($contact_form !== null){
                $user_id = $contact_form->shortcode_attr('bc_user_id');
                if(null !== $user_id){
                    $user_id = $this->sanitize_user_id($user_id);
                    if(0 !== $user_id){
                        $uniqid = get_user_meta($user_id, 'bc_uniqid', true);
    					if('' !== $uniqid){
    						$hidden_fields['bc_uniqid'] = $uniqid;
    					}
                    }
                }
            }
            return $hidden_fields;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function bc_cf7_storage_hidden_fields($hidden_fields){
            $contact_form = wpcf7_get_current_contact_form();
            if($contact_form !== null){
                $user_id = $contact_form->shortcode_attr('bc_user_id');
                if(null !== $user_id){
                    $user_id = $this->sanitize_user_id($user_id);
                    if(0 !== $user_id){
                        $hidden_fields['bc_user_id'] = $user_id;
                        $hidden_fields['bc_user_nonce'] = wp_create_nonce('bc_edit_user_' . $user_id);
                    }
                }
            }
            return $hidden_fields;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function bc_cf7_storage_user_id($contact_form){
            $submission = WPCF7_Submission::get_instance();
            if(null === $submission){
                return 0;
            }
            $user_id = $submission->get_posted_data('bc_user_id');
            if(null === $user_id){
                return 0;
            }
            $user_nonce = $submission->get_posted_data('bc_user_nonce');
            if(null === $user_nonce){
                return 0;
            }
            if(!wp_verify_nonce($user_nonce, 'bc_edit_user_' . $user_id)){
                return 0;
            }
            return (int) $user_id;
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
            $user_id = $contact_form->shortcode_attr('bc_user_id');
            if(null === $user_id){
                return $output;
            }
            $user_id = $this->sanitize_post_id($user_id);
            if(0 === $user_id){
                return '<div class="alert alert-danger" role="alert">' . __('Invalid user ID.') . '</div>';
            }
            if(!current_user_can('edit_user', $user_id)){
                $message = __('Sorry, you are not allowed to edit this user.');
                $message .=  ' ' . __('You need a higher level of permission.');
                return '<div class="alert alert-danger" role="alert">' . $message . '</div>';
			}
            if(isset($_GET['bc_referer'])){
                if(get_post_meta($user_id, 'bc_uniqid', true) === $_GET['bc_referer']){
                    $html_class = isset($attr['html_class']) ? trim($attr['html_class']) : '';
                    if('' === $html_class){
                        $html_class = 'bc-user-updated';
                    } else {
                        $html_class .= ' bc-user-updated';
                    }
                    $attr['html_class'] = $html_class;
                }
            }
            $content = isset($m[5]) ? $m[5] : null;
            $output = $this->output($user_id, $attr, $content, $tag);
            return $output;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function shortcode_atts_wpcf7($out, $pairs, $atts){
            if(isset($atts['bc_user_id'])){
                $out['bc_user_id'] = $atts['bc_user_id'];
            }
            return $out;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    }
}
