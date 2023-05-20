<?php
/*
	Plugin Name: Disqus Recent Comments
	Plugin URI: https://go.gioxx.org/disqus-recent-comments
	Description: Show the latest comments from Disqus (using API 3.0 and JSON).
	Author: Gioxx
	Version: 0.3
	Author URI: https://gioxx.org
	License: GPL3
*/

/*
	Credits:
	https://help.disqus.com/en/articles/1717320-widgets
	https://disqus.com/api/docs/
	https://disqus.com/api/docs/forums/listPosts/
	https://github.com/TaltonFiggins/disqus-recent-comments/blob/master/disqus-recent-comments.php
	https://wordpress.stackexchange.com/a/112578
	https://wordpress.stackexchange.com/a/20034
	https://stackoverflow.com/a/19164186
	https://stackoverflow.com/a/10142695
*/

defined( 'ABSPATH' ) || exit;

/*	Registro sorgente aggiornamento plugin e collegamento a pagina di dettaglio (nell'area installazione plugin di WordPress)
	  Credits: https://rudrastyh.com/wordpress/self-hosted-plugin-update.html
*/
if ( !class_exists('gwplgUpdateChecker_drc') ) {
	class gwplgUpdateChecker_drc{
		public $plugin_slug;
		public $version;
		public $cache_key;
		public $cache_allowed;

		public function __construct() {
			$this->plugin_slug = plugin_basename( __DIR__ );
			$this->version = '0.3';
			$this->cache_key = 'customwidgets_updater';
			$this->cache_allowed = true;

			add_filter( 'plugins_api', array( $this, 'info' ), 20, 3 );
			add_filter( 'site_transient_update_plugins', array( $this, 'update' ) );
			add_action( 'upgrader_process_complete', array( $this, 'purge' ), 10, 2 );
		}

		public function request() {
			$remote = get_transient( $this->cache_key );
			if( false === $remote || ! $this->cache_allowed ) {
				$remote = wp_remote_get(
					'https://gioxx.github.io/disqus-recent-comments/plg-disqusrecentcomments.json',
					array(
						'timeout' => 10,
						'headers' => array(
							'Accept' => 'application/json'
						)
					)
				);

				if(
					is_wp_error( $remote )
					|| 200 !== wp_remote_retrieve_response_code( $remote )
					|| empty( wp_remote_retrieve_body( $remote ) )
				) {
					return false;
				}

				set_transient( $this->cache_key, $remote, DAY_IN_SECONDS );

			}
			$remote = json_decode( wp_remote_retrieve_body( $remote ) );
			return $remote;
		}


		function info( $res, $action, $args ) {
			// do nothing if you're not getting plugin information right now
			if( 'plugin_information' !== $action ) {
				return $res;
			}

			// do nothing if it is not our plugin
			if( $this->plugin_slug !== $args->slug ) {
				return $res;
			}

			// get updates
			$remote = $this->request();

			if( ! $remote ) {
				return $res;
			}

			$res = new stdClass();
			$res->name = $remote->name;
			$res->slug = $remote->slug;
			$res->version = $remote->version;
			$res->tested = $remote->tested;
			$res->requires = $remote->requires;
			$res->author = $remote->author;
			$res->author_profile = $remote->author_profile;
			$res->download_link = $remote->download_url;
			$res->trunk = $remote->download_url;
			$res->requires_php = $remote->requires_php;
			$res->last_updated = $remote->last_updated;
			$res->sections = array(
				'description' => $remote->sections->description,
				'installation' => $remote->sections->installation,
				'changelog' => $remote->sections->changelog
			);

			if( ! empty( $remote->banners ) ) {
				$res->banners = array(
					'low' => $remote->banners->low,
					'high' => $remote->banners->high
				);
			}

			return $res;
		}

		public function update( $transient ) {
			if ( empty($transient->checked ) ) {
				return $transient;
			}
			$remote = $this->request();

			if(
				$remote
				&& version_compare( $this->version, $remote->version, '<' )
				&& version_compare( $remote->requires, get_bloginfo( 'version' ), '<=' )
				&& version_compare( $remote->requires_php, PHP_VERSION, '<' )
			) {
				$res = new stdClass();
				$res->slug = $this->plugin_slug;
				$res->plugin = plugin_basename( __FILE__ ); // example: misha-update-plugin/misha-update-plugin.php
				$res->new_version = $remote->version;
				$res->tested = $remote->tested;
				$res->package = $remote->download_url;

				$transient->response[ $res->plugin ] = $res;
	    	}
			return $transient;
		}

		public function purge( $upgrader, $options ) {
			if (
				$this->cache_allowed
				&& 'update' === $options['action']
				&& 'plugin' === $options[ 'type' ]
			) {
				// just clean the cache when new plugin version is installed
				delete_transient( $this->cache_key );
			}
		}

	}
	new gwplgUpdateChecker_drc();
}

add_filter( 'plugin_row_meta', function( $links_array, $plugin_file_name, $plugin_data, $status ) {
	if( strpos( $plugin_file_name, basename( __FILE__ ) ) ) {
		$links_array[] = sprintf(
			'<a href="%s" class="thickbox open-plugin-details-modal">%s</a>',
			add_query_arg(
				array(
					'tab' => 'plugin-information',
					'plugin' => plugin_basename( __DIR__ ),
					'TB_iframe' => true,
					'width' => 772,
					'height' => 788
				),
				admin_url( 'plugin-install.php' )
			),
			__( 'View details' )
		);
	}
	return $links_array;
}, 25, 4 );

/*	Registro icona personalizzata del plugin (credits: ChatGPT!)
*/
function customdrc_plugin_icon() {
    $plugin_dir = plugin_dir_url(__FILE__);
    $icon_url   = $plugin_dir . 'assets/icon-128x128.png';

    $plugin_data = get_plugin_data(__FILE__);
    $plugin_slug = sanitize_title($plugin_data['Name']);

    ?>
    <style>
        #<?php echo $plugin_slug; ?> .dashicons-admin-generic:before {
            content: "\f108";
            background-image: url(<?php echo $icon_url; ?>);
            background-repeat: no-repeat;
            background-position: center center;
            background-size: 16px;
            display: inline-block;
            vertical-align: top;
            height: 16px;
            width: 16px;
        }
    </style>
    <?php
}
add_action('admin_head-update-core.php', 'customdrc_plugin_icon');

class wdg_DsqRcnCmmJSON extends WP_Widget {
	public function __construct() {
		parent::__construct(
	 		'DisqusRecentComments_Widget', // Base ID
			'(GX) Disqus Recent Comments (JSON)', // Name
			array( 'description' => __( 'Show the latest comments from Disqus', 'text_domain' ), ) // Args
		);
	}

	public function widget( $args, $instance ) {
		extract( $args );
		$title = apply_filters( 'widget_title', $instance['title'] );
		$jsonsource = $instance[ 'jsonsource' ];
		$cmnts_load = $instance[ 'cmnts_load' ];
		$avatar = $instance[ 'avatar' ] ? 'true' : 'false';

		echo $before_widget;
		if ( ! empty( $title ) )
		echo '<div class="header_widget">
		        <h2>'.$title.'</h2>
		      </div>';

		$request = wp_safe_remote_get($jsonsource);
		if( is_wp_error( $request ) ) {
			return false; // Bail early
		}
		$body = wp_remote_retrieve_body( $request );
		$data = json_decode( $body );

		if( ! empty( $data ) ) {
			$limit = 0;
			foreach( $data->response as $response ) {
				$limit++;
				$post_author = $response->author->name;
				$post_author_profileUrl = $response->author->profileUrl;
				$post_author_avatar = $response->author->avatar->small->cache;
				$post_thread_link = $response->thread->link;
				$post_thread_commentlink = $response->url;
				$post_thread_commentdate_Y = substr($response->createdAt,0,4);
				$post_thread_commentdate_M = substr($response->createdAt,5,2);
				$post_thread_commentdate_D = substr($response->createdAt,8,2);
				$post_thread_commentdate_H = substr($response->createdAt,11,5);

				//Checking the length of the comment and trimming it if it's too long
				$post_message  = strip_tags($response->message);
				if(strlen($post_message)>120){
					$post_message = substr($post_message, 0 , 120);
					$post_message = substr($post_message, 0 , strripos($post_message, ' ')).' ...';
				}

				//Same for the title
				$post_thread_title = $response->thread->clean_title;
				if(strlen($post_thread_title)>60){$post_thread_title = substr($post_thread_title, 0 , 60);
				$post_thread_title = substr($post_thread_title, 0 , strripos($post_thread_title, ' ')).' ...';}

				echo '<p>';
				if( 'on' == $instance[ 'avatar' ] ) : ?>
					<div class="about-us-avatar">
						<?php echo '<img src="'.$post_author_avatar.'" style="float: right; padding-left: 5px;" />'; ?>
					</div>
				<?php endif;
				echo '	<strong><a href="'.$post_author_profileUrl.'">'.$post_author.'</a></strong> '.$post_message.'<br />
						<small><a href="'.$post_thread_link.'">'.$post_thread_title.'</a> · <a href="'.$post_thread_commentlink.'">'.$post_thread_commentdate_D.'/'.$post_thread_commentdate_M.'/'.$post_thread_commentdate_Y.' '.$post_thread_commentdate_H.'</a></small>
						</p>';

				if ( $limit == $cmnts_load ) break;
			}
		}
		echo $after_widget;
	}

	public function update( $new_instance, $old_instance ) {
		$instance = array();
		$instance['title'] = strip_tags( $new_instance['title'] );
		$instance['jsonsource'] = $new_instance['jsonsource'];
		$instance['cmnts_load'] = $new_instance['cmnts_load'];
		$instance['avatar'] = $new_instance['avatar'];
		return $instance;
	}

	public function form( $instance ) {
		if ( isset( $instance[ 'title' ] ) ) { $title = $instance[ 'title' ]; }
		else { $title = __( '', 'text_domain' ); }
		if ( isset( $instance[ 'jsonsource' ] ) ) { $jsonsource = $instance[ 'jsonsource' ];	}
		else { $jsonsource = __( '', 'text_domain' );	}
		if ( isset( $instance[ 'cmnts_load' ] ) ) { $cmnts_load = $instance[ 'cmnts_load' ];	}
		else { $cmnts_load = __( '', 'text_domain' );	}
		?>
		<p>
			<!-- Widget Title -->
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
		</p>
		<p>
			<!-- JSON Source -->
			<label for="<?php echo $this->get_field_id( 'jsonsource' ); ?>"><?php _e( 'JSON URL<br />(example: https://disqus.com/api/3.0/forums/listPosts.json?api_key=PUBLICAPIKEY&forum=gioxx&related=thread). Get your API Key from <a href="https://disqus.com/api/applications/" target="_blank">disqus.com/api/applications</a>:' ); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'jsonsource' ); ?>" name="<?php echo $this->get_field_name( 'jsonsource' ); ?>" type="text" value="<?php echo esc_attr( $jsonsource ); ?>" />
		</p>
		<p>
			<!-- How many comments do you want? -->
			<label for="<?php echo $this->get_field_id('cmnts_load'); ?>"><?php _e( 'Comments to load:' ); ?></label>
			<select class='widefat' id="<?php echo $this->get_field_id('cmnts_load'); ?>" name="<?php echo $this->get_field_name('cmnts_load'); ?>">
				<?php
					for ($maxcomments=1; $maxcomments<=24; $maxcomments++) { ?>
						<option <?php selected( $instance['cmnts_load'], $maxcomments); ?> value="<?php echo $maxcomments; ?>"><?php echo $maxcomments; ?></option>
				<?php } ?>
			</select>
		</p>
		<p>
			<!-- Avatar -->
	    	<input class="checkbox" type="checkbox" <?php checked( $instance[ 'avatar' ], 'on' ); ?> id="<?php echo $this->get_field_id( 'avatar' ); ?>" name="<?php echo $this->get_field_name( 'avatar' ); ?>" />
			<label for="<?php echo $this->get_field_id( 'avatar' ); ?>"><?php _e( 'Show avatars' ); ?></label>
		</p>
		<?php
	}

} // Disqus Recent Comments JSON - The End

// Register widget
function src_load_DsqsRecentsWidget() { register_widget( 'wdg_DsqRcnCmmJSON' ); }
add_action( 'widgets_init', 'src_load_DsqsRecentsWidget' );
