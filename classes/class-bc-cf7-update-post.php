<?php

if(!class_exists('BC_CF7_Update_Post')){
    final class BC_CF7_Update_Post {

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
            add_filter('bc_cf7_storage_post_id', [$this, 'bc_cf7_storage_post_id']);
            add_filter('do_shortcode_tag', [$this, 'do_shortcode_tag'], 10, 4);
            add_filter('shortcode_atts_wpcf7', [$this, 'shortcode_atts_wpcf7'], 10, 3);
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
                $post_id = $contact_form->shortcode_attr('bc_post_id');
                if(null !== $post_id){
                    $post_id = $this->sanitize_post_id($post_id);
                    if(0 !== $post_id){
                        $hidden_fields['bc_post_id'] = $post_id;
                        $hidden_fields['bc_post_nonce'] = wp_create_nonce('bc_edit_post_' . $post_id);
                    }
                }
            }
            return $hidden_fields;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function bc_cf7_storage_post_id($contact_form){
            $submission = WPCF7_Submission::get_instance();
            if(null === $submission){
                return 0;
            }
            $post_id = $submission->get_posted_data('bc_post_id');
            if(null === $post_id){
                return 0;
            }
            $post_nonce = $submission->get_posted_data('bc_post_nonce');
            if(null === $post_nonce){
                return 0;
            }
            if(!wp_verify_nonce($post_nonce, 'bc_edit_post_' . $post_id)){
                return 0;
            }
            return (int) $post_id;
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
                        $html_class = 'bc-post-updated';
                    } else {
                        $html_class .= ' bc-post-updated';
                    }
                    $attr['html_class'] = $html_class;
                }
            }
            $content = isset($m[5]) ? $m[5] : null;
            $output = $this->output($post_id, $attr, $content, $tag);
            return $output;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function shortcode_atts_wpcf7($out, $pairs, $atts){
            if(isset($atts['bc_post_id'])){
                $out['bc_post_id'] = $atts['bc_post_id'];
            }
            return $out;
        }

    	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    }
}
