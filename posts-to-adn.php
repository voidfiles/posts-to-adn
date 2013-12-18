<?php
/*
Plugin Name: Posts to ADN
Plugin URI: http://wordpress.org/plugins/posts-to-adn/
Description: Automatically posts your new blog articles to your App.net account.
Author: Maxime VALETTE
Author URI: http://maxime.sh
Version: 1.6.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

define( 'PTADN_SLUG', 'ptadn' );
define( 'PTADN_DEBUG', false );
add_action( 'admin_menu', 'ptadn_config_page' );

function ptadn_config_page() {

	if ( function_exists( 'add_submenu_page' ) ) {

		add_submenu_page(
			'options-general.php',
			'Posts to ADN',
			'Posts to ADN',
			'manage_options', PTADN_SLUG, 'ptadn_conf'
		);

	}

}

// API Calls using the WP Http API
function ptadn_api_call( $url, $params = array(), $type = 'GET', $jsonContent = null ) {

	$options = ptadn_get_options();
	$json    = new stdClass;
	$data    = null;
	$request = new WP_Http;

	if ( 'GET' === $type ) {

		$params['access_token'] = $options['ptadn_token'];

		$qs = http_build_query( $params, '', '&' );

		$result = $request->request(
			'https://alpha-api.app.net/stream/0/'.$url.'?'.$qs,
			array(
			  'user-agent' => 'Posts to ADN/1.6.4 (http://wordpress.org/plugins/posts-to-adn/)',
			)
		);

		if ( is_array( $result ) && isset( $result['response']['code'] ) && 200 === $result['response']['code'] ) {

			$json = json_decode( $result['body'] );

		}
	} elseif ( 'POST' == $type ) {

		if ( ! empty( $jsonContent ) ) {

			$result = $request->request(
				'https://alpha-api.app.net/stream/0/'.$url,
				array(
					'user-agent' => 'Posts to ADN/1.6.4 (http://wordpress.org/plugins/posts-to-adn/)',
					'method' => 'POST',
					'body' => $jsonContent,
					'headers' => array(
						'Authorization' => 'Bearer '.$options['ptadn_token'],
						'Content-type' => 'application/json',
					)
				)
			);

		} else {

			$result = $request->request(
				'https://alpha-api.app.net/stream/0/'.$url,
				array(
					'user-agent' => 'Posts to ADN/1.6.4 (http://wordpress.org/plugins/posts-to-adn/)',
					'method' => 'POST',
					'body' => $params,
					'headers' => array(
						'Authorization' => 'Bearer '.$options['ptadn_token'],
					)
				)
			);

		}

		if ( is_array( $result ) && isset( $result['response']['code'] ) && 200 === $result['response']['code'] ) {

			$json = json_decode( $result['body'] );

		}

	} elseif ( 'UPLOAD' == $type ) {

		$boundary = uniqid();

		$headers = array(
			'Authorization' => 'Bearer '.$options['ptadn_token'],
			'Content-type' => 'multipart/form-data; boundary=' . $boundary,
		);

		$payload = '';

		foreach ( $params as $name => $value ) {

			if ( preg_match( '/^@([^;]+);type=(.+)$/', $value, $r ) ) {

				$getRequest  = new WP_Http;

				$getResult = $getRequest->request( $r[1] );
				$getData = (string) $getResult['body'];

				if ( isset( $getData ) && ! empty( $getData ) ) {

					$payload .= '--' . $boundary;
					$payload .= "\r\n";
					$payload .= 'Content-Disposition: form-data; name="' . $name . '"; filename="' . basename( $r[1] ) . '"' . "\r\n";
					$payload .= 'Content-Type: ' . $r[2] . "\r\n";
					$payload .= "\r\n";
					$payload .= $getData;
					$payload .= "\r\n";

				}

			} else {

				$payload .= '--' . $boundary;
				$payload .= "\r\n";
				$payload .= 'Content-Disposition: form-data; name="' . $name . '"' . "\r\n\r\n";
				$payload .= $value;
				$payload .= "\r\n";

			}

		}

		$payload .= '--' . $boundary . '--';

		$result = $request->request(
			'https://alpha-api.app.net/stream/0/'.$url,
			array(
				'user-agent' => 'Posts to ADN/1.6.4 (http://wordpress.org/plugins/posts-to-adn/)',
				'method' => 'POST',
				'body' => $payload,
				'headers' => $headers
			)
		);

		if ( is_array( $result ) && isset( $result['response']['code'] ) && 200 === $result['response']['code'] ) {

			$json = json_decode( $result['body'] );

		}

	}

	if ( isset( $json->meta->error_message ) &&
	! empty( $json->meta->error_message ) ) {

		$options['ptadn_error'] = $json->meta->error_message;

		update_option( 'ptadn', $options );

	}

	if ( PTADN_DEBUG && isset( $result ) && is_array( $result ) && isset( $result['body'] ) ) {

		error_log( 'API: '.$result['body'] );

	}

	return $json;

}

// PtADN Settings tabs
function ptadn_conf_tabs( $current = 'post' ) {

	$tabs = array(
		'post' => 'Post Format',
		'settings' => 'Settings',
		'broadcast' => 'Broadcast',
	);

	$cron = _get_cron_array();

	foreach ( $cron as $timestamp => $cronhooks ) {
		foreach ( (array) $cronhooks as $hook => $events ) {
			if ( $hook != 'ptadn_event' ) {
				unset( $cron[$timestamp][$hook] );
				continue;
			}
			foreach ( (array) $events as $key => $event ) {
				$cron[ $timestamp ][ $hook ][ $key ][ 'date' ] = date_i18n( 'Y/m/d \a\t g:ia', $timestamp + ( get_option( 'gmt_offset' ) * 3600 ), 1 );
			}
		}
		if ( count( $cron[$timestamp] ) == 0 ) {
			unset( $cron[$timestamp] );
		}
	}

	if ( count( $cron ) > 0 ) {

		$tabs['schedule'] = 'Schedule';

	}

	echo '<div id="icon-themes" class="icon32"><br /></div>';

	echo '<h2 class="nav-tab-wrapper">';

	foreach ( $tabs as $tab => $name ) {

		$class = ( $tab == $current ) ? ' nav-tab-active' : '';
		$url = add_query_arg( array(
			'page' => PTADN_SLUG,
			'tab' => $tab
		), admin_url( 'options-general.php' ) );
		echo "<a class='nav-tab" . esc_attr( $class ) . "' href='" . $url . "'>" . esc_html( $name ) . '</a>';

	}

	echo '</h2>';

}

function ptadn_get_subscribe_code_for_id( $id, $size = 11 , $width = 144) {
	$height = $size * 2;
	return '<a href=\'http://alpha.app.net/intent/subscribe/?channel_id='.$id.'\' class=\'adn-button\' target=\'_blank\' data-type=\'subscribe\' data-width="'. $width .'" data-height="'. $height .'" data-size="'. $size .'" data-channel-id="'.$id.'">Subscribe on App.net</a><script>(function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0];if(!d.getElementById(id)){js=d.createElement(s);js.id=id;js.src=\'//d2zh9g63fcvyrq.cloudfront.net/adn.js\';fjs.parentNode.insertBefore(js,fjs);}}(document, \'script\', \'adn-button-js\'));</script>';
}

function get_broadcast_channels() {
	$broadcast_channels = get_transient( 'broadcast_channels' );

	if ( empty( $broadcast_channels ) ){

		$json = ptadn_api_call( 'channels/search', array(
			'channel_types' => 'net.app.core.broadcast',
			'order' => 'id',
			'count' => 200,
			'include_annotations' => 1,
			'include_channel_annotations' => 1,
			'is_editable' => 1,
		));

		if ( $json->meta->code == 200 ) {
			if ( count( $json->data ) != 0 ) {
				$broadcast_channels = array();

				foreach ( $json->data as $channel ) {

					$broadcast_channels[$channel->id] = array(
						'readers' => count( $channel->readers->user_ids )
					);

					foreach ( $channel->annotations as $annotation ) {

						if ( $annotation->type == 'net.app.core.broadcast.metadata' ) {

							$broadcast_channels[$channel->id]['title'] = $annotation->value->title;
							$broadcast_channels[$channel->id]['description'] = $annotation->value->description;

						}
					}
				}

				set_transient('broadcast_channels', $broadcast_channels, 60 * 5 );  // Lets cache these for 5 minutes
			}
		}

	}
	return $broadcast_channels;
}

function get_broadcast_channel($channel_id) {
	$cache_string = 'broadcast_channels_' . $channel_id;

	$broadcast_channel = get_transient( $cache_string );

	if ( empty( $broadcast_channel ) ){
		$json = ptadn_api_call( 'channels/' . $channel_id, array( 'include_annotations' => 1) );
		if ( $json->meta->code == 200 ) {

			$broadcast_channel = $json->data;

		  	set_transient($cache_string, $broadcast_channel, 60 * 60 * 24 );  // Lets cache these for 24 hours
		}
	}

	return $broadcast_channel;
}


class PTADN_Subscribe_Widget extends WP_Widget {

	function __construct() {
		$widget_ops = array( 'description' => __('Adds a subscribe button for an App.net Broadcast channel.') );
		parent::__construct( 'channel_id', __('App.net Subscribe Button'), $widget_ops );
	}

	function widget($args, $instance) {
		extract($args);
		echo $before_widget;
		echo $before_title . 'Subscribe' . $after_title;

		$channel_def = json_decode(base64_decode($instance['channel_id']));
		echo '<p>Subscribe to the ' . htmlspecialchars($channel_def->title) . ' Broadcast channel to get real-time push notifications for free.</p>';
		echo ptadn_get_subscribe_code_for_id($channel_def->id);
		echo $after_widget;
	}

	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance['channel_id'] = $new_instance['channel_id'];
		return $instance;
	}

	function form( $instance ) {
		$instance = wp_parse_args( (array) $instance, array( 'channel_id' => '' ) );
		$channel_id = isset( $instance['channel_id'] ) ? $instance['channel_id'] : '';
		// Get channels
		$broadcast_channels = get_broadcast_channels();

		// If no menus exists, direct the user to go and create some.
		if ( empty($broadcast_channels) ) {
			echo '<p>'. sprintf( __('You haven\'t created any broadcast channels yet. <a href="%s">Create some</a>.'), admin_url('options-general.php?page=ptadn&tab=broadcast') ) .'</p>';
			return;
		}
		?>
		<p>
			<label for="<?php echo $this->get_field_id('channel_id'); ?>"><?php _e('Select Menu:'); ?></label>
			<select id="<?php echo $this->get_field_id('channel_id'); ?>" name="<?php echo $this->get_field_name('channel_id'); ?>">
		<?php
			foreach ( $broadcast_channels as $id => $channel ) {
				$channel_def = base64_encode(json_encode(array("id" => $id, "title" => $channel['title'])));
				echo '<option value="' . $channel_def . '"'
					. selected( $channel_id, $channel_def, false )
					. '>'. $channel['title'] . '</option>';
			}
		?>
			</select>
		</p>
		<?php
	}
}

add_action('widgets_init', function () {
	register_widget( 'PTADN_Subscribe_Widget' );
});

// PtADN Settings
function ptadn_conf() {

	$options = ptadn_get_options();

	$updated = false;

	if ( isset( $_GET['clear_error'] ) && $_GET['clear_error'] == '1' ) {

		$options['ptadn_error'] = null;

		update_option( 'ptadn', $options );

		$updated = true;

	}

	if ( isset( $_GET['delete_schedule'] ) ) {

		$cron = _get_cron_array();

		foreach ( $cron as $timestamp => $cronhooks ) {
			foreach ( (array) $cronhooks as $events ) {
				foreach ( (array) $events as $key => $event ) {

					if ( $_GET['delete_schedule'] == $key ) {

						wp_unschedule_event( $timestamp, 'ptadn_event', $event['args'] );

					}
				}
			}
		}

		$updated = true;

	}

	if ( isset( $_GET['bitly_token'] ) && ! empty( $_GET['bitly_token'] ) ) {

		if ( $_GET['bitly_token'] == 'reset' ) {

			$options['ptadn_bitly_token'] = null;
			$options['ptadn_bitly_login'] = null;

		} else {

			$options['ptadn_bitly_token'] = $_GET['bitly_token'];
			$options['ptadn_bitly_login'] = $_GET['bitly_login'];

			$options['ptadn_yourls_url']  = null;
			$options['ptadn_yourls_user'] = null;
			$options['ptadn_yourls_pass'] = null;

		}

		update_option( 'ptadn', $options );

		$updated = true;

	}

	if ( isset( $_GET['token'] ) && ! empty( $_GET['token'] ) ) {

		if ( $_GET['token'] == 'reset' ) {

			$options['ptadn_token'] = null;

		}

		update_option( 'ptadn', $options );

		$updated = true;

	}

	if ( isset( $_POST['auth-token'] ) && ! empty( $_POST['ptadn_token'] ) ) {

		$options['ptadn_token'] = $_POST['ptadn_token'];

		update_option( 'ptadn', $options );

		$updated = true;

	}

	$tab = ( isset( $_GET['tab'] ) ) ? $_GET['tab'] : 'post';

	if ( isset( $_POST['submit'] ) ) {

		check_admin_referer( 'ptadn', 'ptadn-admin' );

		if ( $tab == 'post' ) {

			if ( isset( $_POST['ptadn_text'] ) ) {

				$ptadn_text = sanitize_text_field( $_POST['ptadn_text'] );

			} else {

				$ptadn_text = '{title} {link}';

			}

			$options['ptadn_text'] = $ptadn_text;

		} elseif ( $tab == 'settings' ) {

			if ( isset( $_POST['ptadn_thumbnail'] ) ) {

				$ptadn_thumbnail = sanitize_text_field( $_POST['ptadn_thumbnail'] );

			} else {

				$ptadn_thumbnail = 0;

			}

			if ( isset( $_POST['ptadn_disabled'] ) ) {

				$ptadn_disabled = sanitize_text_field( $_POST['ptadn_disabled'] );

			} else {

				$ptadn_disabled = 0;

			}

			if ( isset( $_POST['ptadn_length'] ) ) {

				$ptadn_length = (int) $_POST['ptadn_length'];

			} else {

				$ptadn_length = 100;

			}

			if ( isset( $_POST['ptadn_antiflood'] ) ) {

				$ptadn_antiflood = sanitize_text_field( $_POST['ptadn_antiflood'] );

			} else {

				$ptadn_antiflood = 300;

			}

			if ( is_numeric( $_POST['ptadn_delay_days'] ) && is_numeric( $_POST['ptadn_delay_hours'] ) && is_numeric( $_POST['ptadn_delay_minutes'] ) ) {

				$ptadn_delay = $_POST['ptadn_delay_days'] * 86400 + $_POST['ptadn_delay_hours'] * 3600 + $_POST['ptadn_delay_minutes'] * 60;

			} else {

				$ptadn_delay = 0;

			}

			if ( is_array( $_POST['ptadn_types'] ) ) {

				$ptadn_types = $_POST['ptadn_types'];

			} else {

				$ptadn_types = array();

			}

			if ( ! empty( $_POST['ptadn_yourls_url'] ) && ! empty( $_POST['ptadn_yourls_user'] ) && ! empty( $_POST['ptadn_yourls_pass'] ) ) {

				$ptadn_yourls_url  = sanitize_text_field( $_POST['ptadn_yourls_url'] );
				$ptadn_yourls_user = sanitize_text_field( $_POST['ptadn_yourls_user'] );
				$ptadn_yourls_pass = sanitize_text_field( $_POST['ptadn_yourls_pass'] );

				$options['ptadn_bitly_login'] = null;
				$options['ptadn_bitly_token'] = null;

			} else {

				$ptadn_yourls_url  = null;
				$ptadn_yourls_user = null;
				$ptadn_yourls_pass = null;

			}

			$options['ptadn_thumbnail']   = $ptadn_thumbnail;
			$options['ptadn_disabled']    = $ptadn_disabled;
			$options['ptadn_length']      = $ptadn_length;
			$options['ptadn_delay']       = $ptadn_delay;
			$options['ptadn_types']       = $ptadn_types;
			$options['ptadn_yourls_url']  = $ptadn_yourls_url;
			$options['ptadn_yourls_user'] = $ptadn_yourls_user;
			$options['ptadn_yourls_pass'] = $ptadn_yourls_pass;
			$options['ptadn_antiflood']   = $ptadn_antiflood;

		}

		update_option( 'ptadn', $options );

		$updated = true;

	} elseif ( isset( $_POST['create-channel'] ) ) {

		check_admin_referer( 'ptadn', 'ptadn-channel-create' );

		$jsonContent = array(
			'type' => 'net.app.core.broadcast',
			'readers' => array(
				'any_user' => true,
				'public' => true,
				'user_ids' => array()
			),
			'editors' => array(
				'any_user' => false,
				'public' => false,
				'users_ids' => array( $options['ptadn_id'] )
			),
			'annotations' => array(
				array(
					'type' => 'net.app.core.broadcast.metadata',
					'value' => array(
						'title' => $_POST['ptadn_title'],
						'description' => $_POST['ptadn_description'],
					)
				),
			)
		);

		ptadn_api_call( 'channels', array(), 'POST', json_encode( $jsonContent ) );
		delete_transient( 'broadcast_channels' );
		$updated = true;

	} elseif ( isset( $_POST['check-channels'] ) ) {

		check_admin_referer( 'ptadn', 'ptadn-channel' );

		$options['ptadn_channels'] = array();

		foreach ( $_POST['ptadn_channels'] as $id ) {

			$json = ptadn_api_call( 'channels/' . $id, array( 'include_annotations' => 1 ) );

			foreach ( $json->data->annotations as $annotation ) {

				if ( $annotation->type == 'net.app.core.broadcast.metadata' ) {

					$options['ptadn_channels'][$id] = $annotation->value->title;

				}
			}
		}

		update_option( 'ptadn', $options );
		$updated = true;

	}

	echo '<div class="wrap">';

	if ( $updated ) {

		echo '<div id="message" class="updated fade"><p>Settings updated.</p></div>';

	}

	$json = new stdClass;

	if ( $options['ptadn_token'] ) {

		$json = ptadn_api_call( 'users/me' );

		if ( isset( $json->error ) && is_array( $json->error ) && count( $json->error ) ) {

			echo '<div id="message" class="error"><p>';
			echo 'There was something wrong with your App.net authentication. Please retry.';
			echo '</p></div>';

			$options['ptadn_token'] = null;
			$options['ptadn_id']    = 0;

			update_option( 'ptadn', $options );

		} else {

			$options['ptadn_id'] = $json->data->id;

		}
	}

	echo '<h2>Posts to ADN Settings</h2>';

	if ( empty( $options['ptadn_token'] ) ) {

		$params = array(
			'redirect_uri' => admin_url( 'options-general.php?page=' . PTADN_SLUG )
		);

		$auth_url = 'http://maxime.sh/triggers/adn-token.php?'.http_build_query( $params );

		echo '<p>Connect or sign up a free App.net account:</p>';

		echo '<p><a href="' . esc_url( $auth_url ) . '" class="adn-button" data-type="authorize_v2" data-width="145" data-height="22" >Authorize with App.net</a><script>(function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0];if(!d.getElementById(id)){js=d.createElement(s);js.id=id;js.src=\'//d2zh9g63fcvyrq.cloudfront.net/adn.js\';fjs.parentNode.insertBefore(js,fjs);}}(document, \'script\', \'adn-button-js\'));</script></p>';

		echo '<p>Then fill the form below.</p>';

		echo '<form action="'.admin_url( 'options-general.php?page=' . PTADN_SLUG ).'&amp;tab='.$tab.'" method="post">';

		echo '<p><label for="token">ADN Token:</label> <input type="text" style="width: 350px;" name="ptadn_token" value="" /></p>';

		echo '<p class="submit" style="text-align: left">';
		wp_nonce_field( 'ptadn', 'ptadn-token-auth' );
		echo '<input type="submit" class="button-primary" name="auth-token" value="'.__( 'Save' ).' &raquo;" /></p></form>';

	} else {

		ptadn_conf_tabs( $tab );

		$delayDays = $delayHours = $delayMinutes = 0;

		if ( $options['ptadn_delay'] > 0 ) {

			$delayDays    = floor( $options['ptadn_delay'] / 86400 );
			$delayHours   = floor( ( $options['ptadn_delay'] - $delayDays * 86400 ) / 3600 );
			$delayMinutes = floor( ( $options['ptadn_delay'] - $delayDays * 86400 - $delayHours * 3600 ) / 60 );

		}

		echo '<div style="float: right; background: #ececec; padding: 10px 20px; margin-top: 15px;">';

		echo '<h3>Your account</h3>';

		echo '<p><img src="' . esc_url( $json->data->avatar_image->url ) . '" width="60" alt="Avatar"></p>';

		echo '<p>You are authenticated on App.net<br />with the username: '.$json->data->username.'</p>';
		echo '<p><a class="button-secondary" href="'.admin_url( 'options-general.php?page=' . PTADN_SLUG ).'&amp;token=reset">Disconnect from App.net</a></p>';

		echo '<h3>About the creator</h3>';

		echo '<p>Ping me on App.net: <a href="http://alpha.app.net/maxime" target="_blank">maxime</a></p>';

		echo '</div>';

		if ( in_array( $tab, array( 'post', 'settings' ) ) ) {

			$url = add_query_arg( array(
				'page' => PTADN_SLUG,
				'tab' => $tab,
			), admin_url( 'options-general.php' ) );
			echo '<form action="' . esc_url( $url ) . '" method="post">';

			if ( $tab == 'post' ) {

				echo '<h3><label for="ptadn_text">ADN Post Format</label></h3>';
				echo '<p><textarea id="ptadn_text" name="ptadn_text" style="width: 400px; resize: vertical; height: 100px;">' . esc_html( $options['ptadn_text'] ) . '</textarea></p>';

				echo '<h3>Variables</h3>';

				echo '<ul><li>{title} for the blog title<li>{link} for the permalink<li>{author} for the author<li>{excerpt} for the first words of your post<li>{tags} for the tags of your article (with a #)</ul>';

				echo '<p>You can also use {linkedTitle} instead of {title} and {link} in order to use the link entity feature of App.net.</p>';

			} elseif ( $tab == 'settings' ) {

				echo '<h3>Advanced Settings</h3>';

				echo '<p><input id="ptadn_thumbnail" name="ptadn_thumbnail" type="checkbox" value="1"';
				if ( $options['ptadn_thumbnail'] == 1 ) echo ' checked';
				echo ' /> <label for="ptadn_thumbnail">Also send the Featured Image for the post if there is one</label></p>';

				echo '<p><label for="ptadn_length">Excerpt length:</label> <input type="text" style="width: 50px; text-align: center;" name="ptadn_length" id="ptadn_length" value="' . esc_attr( $options['ptadn_length'] ) . '" /> characters.</p>';

				echo '<p>Send a post for these post types:</p>';

				$postTypes = get_post_types( array( 'public' => true ), 'names' );

				echo '<ul style="margin-left: 10px;">';

				foreach ( $postTypes as $postType ) {

					if ( in_array( $postType, array( 'attachment', 'nav_menu_item', 'revision' ) ) ) {
						continue;
					}

					echo '<li><input type="checkbox" name="ptadn_types[]" value="' . esc_attr( $postType )	. '" id="ptype_' . esc_attr( $postType ) . '"';

					if ( in_array( $postType, $options['ptadn_types'] ) ) {
						echo ' checked';
					}

					echo '> <label for="ptype_' . esc_attr( $postType ) . '">' . esc_html( $postType ) . '</label></li>';

				}

				echo '</ul>';

				echo '<p>Bit.ly URL shortening: ';

				if ( is_null( $options['ptadn_bitly_login'] ) ) {

					$params = array(
						'redirect_uri' => admin_url( 'options-general.php?page=' . PTADN_SLUG )
					);

					echo '<a href="http://maxime.sh/triggers/bitly.php?'.http_build_query( $params ).'">Connect your Bit.ly account</a> &rarr;</p>';

				} else {

					$url = add_query_arg( array(
						'page' => PTADN_SLUG,
						'bitly_token' => 'reset',
					), admin_url( 'options-general.php' ) );
					echo 'Currently connected with ' . esc_html( $options['ptadn_bitly_login'] ) . ' — <a href="' . esc_url( $url ) . '">Disconnect</a></p>';

				}

				echo '<p>YOURLS URL shortening:</p>';

				echo '<p style="margin-left: 25px;">URL (without /yourls-api.php): <input type="text" style="width: 250px;" name="ptadn_yourls_url" value="'.$options['ptadn_yourls_url'].'" /></p>';
				echo '<p style="margin-left: 25px;">Username: <input type="text" style="width: 150px;" name="ptadn_yourls_user" value="'.$options['ptadn_yourls_user'].'" /></p>';
				echo '<p style="margin-left: 25px;">Password: <input type="password" style="width: 150px;" name="ptadn_yourls_pass" value="'.$options['ptadn_yourls_pass'].'" /></p>';

				echo '<p><label>Delay the ADN post:</label> <input type="text" style="width: 50px; text-align: center;" name="ptadn_delay_days" value="'.$delayDays.'" /> days,';
				echo ' <input type="text" style="width: 50px; text-align: center;" name="ptadn_delay_hours" value="'.$delayHours.'" /> hours,';
				echo ' <input type="text" style="width: 50px; text-align: center;" name="ptadn_delay_minutes" value="'.$delayMinutes.'" /> minutes.</p>';

				echo '<p><label>Anti-flood protection:</label> <input type="text" style="width: 50px; text-align: center;" name="ptadn_antiflood" value="'.$options['ptadn_antiflood'].'" /> seconds.</p>';

				echo '<p><input id="ptadn_disabled" name="ptadn_disabled" type="checkbox" value="1"';
				if ( $options['ptadn_disabled'] == 1 ) echo ' checked';
				echo ' /> <label for="ptadn_disabled">Disable auto posting to App.net</label></p>';

			}

			echo '<p class="submit" style="text-align: left">';
			wp_nonce_field( 'ptadn', 'ptadn-admin' );
			echo '<input type="submit" class="button-primary" name="submit" value="'.__( 'Save' ).' &raquo;" /></p></form>';

		} elseif ( $tab == 'schedule' ) {

			$cron = _get_cron_array();

			foreach ( $cron as $timestamp => $cronhooks ) {
				foreach ( (array) $cronhooks as $hook => $events ) {
					if ( $hook != 'ptadn_event' ) {
						unset( $cron[$timestamp][$hook] );
						continue;
					}
					foreach ( (array) $events as $key => $event ) {
						$cron[ $timestamp ][ $hook ][ $key ][ 'date' ] = date_i18n( 'Y/m/d \a\t g:ia', $timestamp + ( get_option( 'gmt_offset' ) * 3600 ), 1 );
					}
				}
				if ( count( $cron[$timestamp] ) == 0 ) {
					unset( $cron[$timestamp] );
				}
			}

			echo '<h3>Scheduled ADN posts</h3>';

			echo '<ul>';

			foreach ( $cron as $cronhooks ) {
				foreach ( $cronhooks as $events ) {
					foreach ( $events as $key => $event ) {

						$cronPost = $event['args'][0];
						echo '<li><a href="'.$cronPost->guid.'" target="_blank">'.$cronPost->post_title.'</a>: Will be posted on '.$event['date'].' — <a href="'.admin_url( 'options-general.php?page=' . PTADN_SLUG ).'&delete_schedule='.$key.'">Delete</a></li>';

					}
				}
			}

			echo '</ul>';

		} elseif ( $tab == 'broadcast' ) {

			echo '<h3>Broadcast Channel</h3>';

			echo '<p>Broadcast Channel is a new kind of channel built by App.net.</p>';

			echo '<p>The goal is to help you broadcast important posts to your readers through a dedicated channel they can subscribe to.</p>';

			echo '<p>When you post a new article you want to broadcast, just check the corresponding channel on the right box.</p>';

			echo '<h3>Your channels</h3>';

			echo '<form action="'.admin_url( 'options-general.php?page=' . PTADN_SLUG ).'&amp;tab='.$tab.'" method="post">';
			$broadcast_channels = get_broadcast_channels();
			if ( count( $broadcast_channels ) == 0 ) {

				echo '<p>You don\'t own any broadcast channel.</p>';

			} else {

				echo '<ul>';

				foreach ( $broadcast_channels as $id => $channel ) {

					echo '<li style="background: #ececec; padding: 10px; margin-bottom: 10px; width: 650px;">';
					echo '<input type="checkbox" name="ptadn_channels[]" value="'.$id.'" id="channel-'.$id.'" style="margin-right: 15px;"';
					echo intval( isset( $options['ptadn_channels'][$id] ) ) === 1 ? ' checked="checked"' : '';
					echo '>';
					echo '<label for="channel-'.$id.'"><strong>' . htmlspecialchars($channel['title']) .'</strong> — ' .  htmlspecialchars($channel['description']) . ' — <a href="javascript:;" onclick="document.getElementById(\'code-'.$id.'\').style.display=\'block\';">Show subscribe button code</a></label><br />';

					echo '<input id="code-'.$id.'" type="text" style="display: none; margin-left: 25px; margin-top: 5px; width: 350px;" value="'.htmlspecialchars( ptadn_get_subscribe_code_for_id( $id ) ).'">';

					echo '</li>';

				}

				echo '</ul>';

				echo '<p class="submit" style="text-align: left">';
				wp_nonce_field( 'ptadn', 'ptadn-channel' );
				echo '<input type="submit" class="button-primary" name="check-channels" value="'.__( 'Save' ).' &raquo;" /></p></form>';

			}

			echo '<h3>Create a new channel</h3>';

			echo '<form action="'.admin_url( 'options-general.php?page=' . PTADN_SLUG ).'&amp;tab='.$tab.'" method="post">';

			echo '<p>Channel title: <input type="text" style="width: 250px;" name="ptadn_title" value="'.get_bloginfo( 'name' ).'" /></p>';

			echo '<p>Channel description: <input type="text" style="width: 450px;" name="ptadn_description" value="'.get_bloginfo( 'description' ).'" /></p>';

			echo '<p>You can specify another URL than your website if you want your readers to have a more specific page about the channel.</p>';

			echo '<p class="submit" style="text-align: left">';
			wp_nonce_field( 'ptadn', 'ptadn-channel-create' );
			echo '<input type="submit" class="button-primary" name="create-channel" value="'.__( 'Save' ).' &raquo;" /></p></form>';

		}
	}

}

// Upload a file using the WP Filesystem API
function ptadn_upload_file( $name, $data ) {

	$url = wp_nonce_url( 'options-general.php?page=' . PTADN_SLUG, 'ptadn-options' );

	if ( false === ( $creds = request_filesystem_credentials( $url ) ) ) {

		return false;

	} else {

		if ( ! WP_Filesystem( $creds ) ) {

			request_filesystem_credentials( $url );

		}

		$upload_dir = wp_upload_dir();
		$filename = trailingslashit( $upload_dir['path'] ) . $name;

		/** @var WP_Filesystem */
		global $wp_filesystem;

		$wp_filesystem->put_contents( $filename, $data, FS_CHMOD_FILE );

		return $filename;

	}

}

// Delete a file using the WP Filesystem API
function ptadn_delete_file( $name ) {

	$url = wp_nonce_url( 'options-general.php?page=' . PTADN_SLUG, 'ptadn-options' );

	if ( false === ( $creds = request_filesystem_credentials( $url ) ) ) {

		return false;

	} else {

		if ( ! WP_Filesystem( $creds ) ) {

			request_filesystem_credentials( $url );

		}

		/** @var WP_Filesystem */
		global $wp_filesystem;

		$wp_filesystem->delete( $name );

		return true;

	}

}

// Posts to ADN when there is a new post
function ptadn_posts_to_adn( $postID, $force = false ) {

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE || wp_is_post_revision( $postID ) ) { return $postID; }
	if ( isset( $_POST['_inline_edit'] ) ) { return $postID; }

	$options = ptadn_get_options();

	if ( $options['ptadn_disabled'] == 1 ) { return $postID; }

	$post_info = ptadn_post_info( $postID );

	if ( ! in_array( $post_info['postType'], $options['ptadn_types'] ) ) { return $postID; }

	$new = 1;

	$channels = array();

	if ( isset( $_POST['ptadn_channels'] ) ) {

		$channels = $_POST['ptadn_channels'];

	}

	$customFieldChannels = get_post_custom_values( 'ptadn_channels', $post_info['postId'] );

	if ( isset( $customFieldChannels[0] ) ) {

		$channels = explode( ',', $customFieldChannels[0] );

	}

	if ( isset( $_POST['ptadn_disable_post'] ) && $_POST['ptadn_disable_post'] == '1' ) {

		$new = 0;

	}

	$customFieldDisable = get_post_custom_values( 'ptadn_disable_post', $post_info['postId'] );

	if ( isset( $customFieldDisable[0] ) && $customFieldDisable[0] == '1' ) {

		$new = 0;

	}

	if ( $options['ptadn_last_time'] > ( time() - $options['ptadn_antiflood'] ) ) {

		$new = 0;

	}

	if ( $new || $force || count( $channels ) > 0 ) {

		if ( $options['ptadn_delay'] > 0 && ! $force ) {

			wp_schedule_single_event( time() + $options['ptadn_delay'], 'ptadn_event', array( $postID, true ) );

			return $postID;

		}

		$url = $post_info['postLink'];

		if ( ! is_null( $options['ptadn_bitly_token'] ) ) {

			$request = new WP_Http;
			$json    = new stdClass;

			$params = array(
				'access_token' => $options['ptadn_bitly_token'],
				'longUrl' => $url,
			);

			$result = $request->request( 'https://api-ssl.bitly.com/v3/shorten?' . http_build_query( $params ) );

			if ( is_array( $result ) && isset( $result['response']['code'] ) && 200 === $result['response']['code'] ) {

				$json = json_decode( $result['body'] );

			}

			if ( is_numeric( $json->status_code ) && $json->status_code == 200 ) {
				$url = $json->data->url;
			}
		} elseif ( ! is_null( $options['ptadn_yourls_url'] ) ) {

			$request = new WP_Http;
			$json    = new stdClass;

			$params = array(
				'action' => 'shorturl',
				'username' => $options['ptadn_yourls_user'],
				'password' => $options['ptadn_yourls_pass'],
				'url' => $url,
				'format' => 'json',
			);

			$result = $request->request( $options['ptadn_yourls_url'] . '/yourls-api.php?' . http_build_query( $params ) );

			if ( is_array( $result ) && isset( $result['response']['code'] ) && 200 === $result['response']['code'] ) {

				$json = json_decode( $result['body'] );

			}

			if ( isset( $json->shorturl ) ) {
				$url = $json->shorturl;
			}
		}

		$customFieldText = get_post_custom_values( 'ptadn_textarea', $post_info['postId'] );

		if ( isset( $customFieldText[0] ) && ! empty( $customFieldText[0] ) ) {

			$text = $customFieldText[0];

		} else {

			$text = ( isset( $_POST['ptadn_textarea'] ) ) ? $_POST['ptadn_textarea'] : $options['ptadn_text'];

		}

		$excerpt = ( empty( $post_info['postExcerpt'] ) ) ? $post_info['postContent'] : $post_info['postExcerpt'];

		$text = str_replace(
			array( '{title}', '{link}', '{author}', '{excerpt}', '{tags}' ),
			array( $post_info['postTitle'], $url, $post_info['authorName'], ptadn_word_cut( $excerpt, $options['ptadn_length'] ), $post_info['postHashtags'] ),
			$text
		);

		$jsonContent = array(
			'text' => $text,
		);

		$pos = mb_strpos( $text, '{linkedTitle}', 0, 'UTF-8' );

		if ( $pos !== false ) {

			$text = str_replace( '{linkedTitle}', $post_info['postTitle'], $text );

			$jsonContent = array(
				'text' => $text,
				'entities' => array(
					'links' => array(
						array(
							'pos' => $pos,
							'len' => mb_strlen( $post_info['postTitle'], 'UTF-8' ),
							'url' => $url,
						),
					)
				)
			);

		}

		if ( $options['ptadn_thumbnail'] == '1' ) {

			$postImageId = get_post_thumbnail_id( $post_info['postId'] );

			if ( $postImageId ) {
				$thumbnail = wp_get_attachment_image_src( $postImageId, 'large', false );
				if ( $thumbnail ) {
					$src = $thumbnail[0];
				}
			}

			if ( isset( $src ) ) {

				preg_match( '/\.([a-z]+)$/i', $src, $r );
				$fileExt = strtolower( $r[1] );

				switch ( $fileExt ) {

					case 'jpg':
					case 'jpeg':
						$fileType = 'jpeg';
						break;

					case 'gif':
						$fileType = 'gif';
						break;

					case 'png':
						$fileType = 'png';
						break;

					default:
						$fileType = 'png';
						break;

				}

				$fileJson = ptadn_api_call(
					'files', array(
						'public' => true,
						'type' => 'com.maximevalette.posts_to_adn',
						'name' => basename( $src ),
						 'content' => '@' . $src . ';type=image/' . $fileType,
					), 'UPLOAD'
				);

				if ( is_string( $fileJson->data->id ) ) {

					$jsonContent['annotations'] = array(
						array(
							'type' => 'net.app.core.oembed',
							'value' => array(
								'+net.app.core.file' => array(
									'file_id' => $fileJson->data->id,
									'file_token' => $fileJson->data->file_token,
									'format' => 'oembed',
								)
							)
						),
						array(
							'type' => 'net.app.core.attachments',
							'value' => array(
								'+net.app.core.file_list' => array(
									array(
										'file_id' => $fileJson->data->id,
										'file_token' => $fileJson->data->file_token,
										'format' => 'metadata',
									),
								)
							)
						),
					);

				}

				// ptadn_delete_file( $path );
			}
		}

		if ( PTADN_DEBUG ) {

			if ( $new ) {

				error_log( 'New post: '.json_encode( $jsonContent ) );

			}

			foreach ( $channels as $channel ) {

				error_log( 'New post to '.$channel.': '.json_encode( $jsonContent ) );

			}
		} else {

			if ( $new ) {

				ptadn_api_call( 'posts?include_post_annotations=1', array(), 'POST', json_encode( $jsonContent ) );

			}

			foreach ( $channels as $channel ) {

				ptadn_api_call(
					'channels/'.$channel.'/messages?include_post_annotations=1',
					array(),
					'POST',
					json_encode(
						array(
							'text' => ptadn_word_cut( $excerpt, $options['ptadn_length'] ),
							'annotations' => array(
								array(
									'type' => 'net.app.core.broadcast.message.metadata',
									'value' => array(
										'subject' => $post_info['postTitle'],
									),
								),
								array(
									'type' => 'net.app.core.crosspost',
									'value' => array(
										'canonical_url' => $url,
									),
								),
							),
						)
					)
				);

			}
		}

		$options['ptadn_last_time'] = time();
		update_option( 'ptadn', $options );

		delete_post_meta( $post_info['postId'], 'ptadn_textarea' );
		delete_post_meta( $post_info['postId'], 'ptadn_disable_post' );
		delete_post_meta( $post_info['postId'], 'ptadn_channels' );

	}

	return $postID;

}

// Save posts meta
function ptadn_save_posts_meta( $postID ) {

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE || wp_is_post_revision( $postID ) ) { return $postID; }
	if ( isset( $_POST['_inline_edit'] ) ) { return $postID; }

	// $options = ptadn_get_options();

	if ( isset( $_POST['ptadn_textarea'] ) ) {

		if ( ! add_post_meta( $postID, 'ptadn_textarea', $_POST['ptadn_textarea'], true ) ) {

			update_post_meta( $postID, 'ptadn_textarea', $_POST['ptadn_textarea'] );

		}
	}

	if ( isset( $_POST['ptadn_channels'] ) ) {

		$channels = implode( ',', $_POST['ptadn_channels'] );

		if ( ! add_post_meta( $postID, 'ptadn_channels', $channels, true ) ) {

			update_post_meta( $postID, 'ptadn_channels', $channels );

		}
	}

	if ( isset( $_POST['ptadn_publish_now'] ) ) {

		ptadn_posts_to_adn( $postID, 'force' );

	} elseif ( isset( $_POST['ptadn_disable_post'] ) ) {

		if ( ! add_post_meta( $postID, 'ptadn_disable_post', $_POST['ptadn_disable_post'], true ) ) {

			update_post_meta( $postID, 'ptadn_disable_post', $_POST['ptadn_disable_post'] );

		}
	}

	return $postID;

}

// Post info
function ptadn_post_info( $postID ) {

	$post = get_post( $postID );
	$tags = wp_get_post_tags( $post->ID );

	$values = array();

	$values['id']       = $postID;
	$values['postinfo'] = $post;
	$values['postId']   = $post->ID;
	$values['authId']   = $post->post_author;

	$info = get_userdata( $values['authId'] );
	$values['authorName'] = $info->display_name;

	$values['postDate']     = mysql2date( 'Y-m-d H:i:s', $post->post_date );
	$values['postModified'] = mysql2date( 'Y-m-d H:i:s', $post->post_modified );

	$thisPostTitle = stripcslashes( strip_tags( $post->post_title ) );
	if ( $thisPostTitle == '' ) {
		$thisPostTitle = stripcslashes( strip_tags( $_POST['title'] ) );
	}
	$values['postTitle'] = html_entity_decode( $thisPostTitle, ENT_COMPAT, get_option( 'blog_charset' ) );

	$values['postLink']  = get_permalink( $postID );
	$values['blogTitle'] = get_bloginfo( 'name' );

	$values['postStatus']  = $post->post_status;
	$values['postType']    = $post->post_type;
	$values['postContent'] = trim( html_entity_decode( htmlspecialchars_decode( strip_tags( $post->post_content ) ), ENT_COMPAT, get_option( 'blog_charset' ) ) );
	$values['postExcerpt'] = trim( html_entity_decode( htmlspecialchars_decode( strip_tags( $post->post_excerpt ) ), ENT_COMPAT, get_option( 'blog_charset' ) ) );

	$hashtags = array();

	foreach ( $tags as $tag ) {

		$hashtags[] = '#' . $tag->slug;

	}

	$values['postHashtags'] = implode( ' ', $hashtags );

	return $values;

}

// Action when a post is published
add_action( 'new_to_publish', 'ptadn_posts_to_adn' );
add_action( 'draft_to_publish', 'ptadn_posts_to_adn' );
add_action( 'auto-draft_to_publish', 'ptadn_posts_to_adn' );
add_action( 'pending_to_publish', 'ptadn_posts_to_adn' );
add_action( 'private_to_publish', 'ptadn_posts_to_adn' );
add_action( 'future_to_publish', 'ptadn_posts_to_adn' );
add_action( 'save_post', 'ptadn_save_posts_meta' );

add_action( 'ptadn_event', 'ptadn_posts_to_adn', 10, 2 );

// Admin notice
function ptadn_admin_notice() {

	$options = ptadn_get_options();

	if ( current_user_can( 'manage_options' ) ) {

		if ( empty( $options['ptadn_token'] ) && ! isset( $_GET['token'] ) && ! isset( $_POST['auth-token'] ) ) {

			echo '<div class="error"><p>Warning: Your App.net account is not properly configured in the Posts to ADN plugin. <a href="'.admin_url( 'options-general.php?page=' . PTADN_SLUG ).'">Update settings &rarr;</a></p></div>';

		} elseif ( ! isset( $_GET['token'] ) && $options['ptadn_files_scope'] === false ) {

			$json = ptadn_api_call( 'token' );

			if ( is_array( $json->data->scopes ) ) {

				if ( ! in_array( 'files', $json->data->scopes ) ) {

					echo '<div class="error"><p>Warning: You should disconnect and reconnect your App.net account to authorize the Files scope. <a href="'.admin_url( 'options-general.php?page=' . PTADN_SLUG ).'">Update settings &rarr;</a></p></div>';

				} else {

					$options['ptadn_files_scope'] = true;
					update_option( 'ptadn', $options );

				}
			}
		} elseif ( ! isset( $_GET['clear_error'] ) && ! empty( $options['ptadn_error'] ) ) {

			echo '<div class="error"><p>Warning: Your last App.net API call returned an error: '.$options['ptadn_error'].'. <a href="'.admin_url( 'options-general.php?page=' . PTADN_SLUG ).'&clear_error=1">Clear and go to settings &rarr;</a></p></div>';

		}
	}

}

// Admin notice
add_action( 'admin_notices', 'ptadn_admin_notice' );

// Options retriever
function ptadn_get_options() {

	$options = get_option( 'ptadn' );

	if ( ! isset( $options['ptadn_token'] ) ) $options['ptadn_token'] = null;
	if ( ! isset( $options['ptadn_id'] ) ) $options['ptadn_id'] = 0;
	if ( ! isset( $options['ptadn_disabled'] ) ) $options['ptadn_disabled'] = 0;
	if ( ! isset( $options['ptadn_text'] ) ) $options['ptadn_text'] = '{title} {link}';
	if ( ! isset( $options['ptadn_length'] ) ) $options['ptadn_length'] = 100;
	if ( ! isset( $options['ptadn_bitly_login'] ) ) $options['ptadn_bitly_login'] = null;
	if ( ! isset( $options['ptadn_bitly_token'] ) ) $options['ptadn_bitly_token'] = null;
	if ( ! isset( $options['ptadn_delay'] ) ) $options['ptadn_delay'] = 0;
	if ( ! isset( $options['ptadn_error'] ) ) $options['ptadn_error'] = null;

	if ( ! isset( $options['ptadn_files_scope'] ) ) $options['ptadn_files_scope'] = false;
	if ( ! isset( $options['ptadn_types'] ) ) $options['ptadn_types'] = array( 'post' );
	if ( ! isset( $options['ptadn_yourls_user'] ) ) $options['ptadn_yourls_user'] = null;
	if ( ! isset( $options['ptadn_yourls_pass'] ) ) $options['ptadn_yourls_pass'] = null;
	if ( ! isset( $options['ptadn_channels'] ) ) $options['ptadn_channels'] = array();

	if ( ! isset( $options['ptadn_last_time'] ) ) $options['ptadn_last_time'] = null;
	if ( ! isset( $options['ptadn_antiflood'] ) ) $options['ptadn_antiflood'] = 300;
	if ( ! isset( $options['ptadn_thumbnail'] ) ) $options['ptadn_thumbnail'] = null;

	if ( ! isset( $options['ptadn_yourls_url'] ) ) $options['ptadn_yourls_url'] = null;

	return $options;

}

// Word cutting function
function ptadn_word_cut( $string, $max_length ) {

	if ( strlen( $string ) <= $max_length ) return $string;

	$string = mb_substr( $string, 0, $max_length );
	$pos    = mb_strrpos( $string, ' ' );

	if ( $pos === false ) return mb_substr( $string, 0, $max_length ).'…';
	return mb_substr( $string, 0, $pos ).'…';

}

// Meta box
function ptadn_meta_box( $post, $data ) {

	global $post;

	$options = ptadn_get_options();

	wp_nonce_field( 'ptadn', 'ptadn-meta', false, true );

	$customFieldText    = get_post_custom_values( 'ptadn_textarea', $post->ID );
	$customFieldDisable = get_post_custom_values( 'ptadn_disable_post', $post->ID );

	$customFieldChannels = get_post_custom_values( 'ptadn_channels', $post->ID );
	$customFieldChannels = explode( ',', $customFieldChannels[0] );

	$textarea = ( isset( $customFieldText[0] ) ) ? $customFieldText[0] : $options['ptadn_text'];
	$disable  = $customFieldDisable[0];

	echo '<p style="margin-bottom: 0;"><textarea style="width: 100%; height: 60px; resize: vertical;';
	echo sanitize_text_field( $disable ) == '1' ? ' opacity: 0.5;" disabled="disabled' : null;
	echo '" name="ptadn_textarea" id="ptadn_textarea">'.$textarea.'</textarea></p>';

	foreach ( $options['ptadn_channels'] as $id => $channel ) {

		echo '<p style="margin-top: 0.5em;"><input type="checkbox" name="ptadn_channels[]" id="channel-'.$id.'" value="'.$id.'" ';
		echo intval( in_array( $id, $customFieldChannels ) ) === 1 ? 'checked' : null;
		echo ' />';

		echo ' <label for="channel-'.$id.'">Send an App.net Alert to <strong>'.$channel.'</strong></label></p>';

	}

	echo '<input type="hidden" name="ptadn_disable_post" id="ptadn_disable_post" value="';
	echo sanitize_text_field( $disable ) == '1' ? '1' : '0';
	echo '">';

	echo '<p style="margin-top: 0.5em;"><input type="checkbox" name="ptadn_enable_post" id="ptadn_enable_post" value="1" onChange="var pdp = document.getElementById(\'ptadn_disable_post\'); if (document.getElementById(\'ptadn_enable_post\').checked) { pdp.value = \'0\'; } else { pdp.value = \'1\'; }" ';
	echo sanitize_text_field( $disable ) == '1' ? null : 'checked';
	echo ' />';

	echo ' <label for="ptadn_enable_post">Send an App.net Post</label></p>';

	if ( $data['args']['oldPost'] ) {

		echo '<p style="text-align: center; margin-bottom: 20px;"><input type="submit" name="ptadn_publish_now" value="Publish the post on ADN" class="button"></p>';

	}

	echo '<p style="text-align: right;"><a href="'.admin_url( 'options-general.php?page=' . PTADN_SLUG ).'">Go to Posts to ADN settings</a> &rarr;</p>';

}

// Meta box loading
function ptadn_meta( $type, $context ) {

	global $post;

	$options = ptadn_get_options();

	$screen = get_current_screen();

	if ( $context == 'side' && in_array( $type, array_keys( $options['ptadn_types'] ) ) ) {

		if ( $screen->action == 'add' || in_array( $post->post_status, array( 'draft', 'future', 'auto-draft', 'pending' ) ) ) {

			add_meta_box( 'ptadn', 'Posts to ADN', 'ptadn_meta_box', $type, 'side', 'default', array( 'oldPost' => false ) );

		} else {

			add_meta_box( 'ptadn', 'Posts to ADN', 'ptadn_meta_box', $type, 'side', 'default', array( 'oldPost' => true ) );

		}
	}

}

add_action( 'do_meta_boxes', 'ptadn_meta', 20, 2 );

// [channel-subscribe channel_id="1"]
function adn_channel_display_func( $atts ) {
	$channel_id = $atts['channel_id'];
	$broadcast_channel = get_broadcast_channel($channel_id);
	if (!$broadcast_channel) {
		return 'Channel doesn\'t exsist';
	}

	$channel_title = '';
	$channel_description = '';
	$channel_icon = '';
	$freq = '';
	$url = '';
	foreach ( $broadcast_channel->annotations as $annotation ) {
		if ( $annotation->type == 'net.app.core.broadcast.metadata' ) {

			$channel_title = $annotation->value->title;
			$channel_description = $annotation->value->description;

		}

		if ( $annotation->type == 'net.app.core.broadcast.icon' && property_exists($annotation, 'value')) {
			$channel_icon = $annotation->value->url;
		}

		if ( $annotation->type == 'net.app.core.broadcast.freq' && property_exists($annotation, 'value')) {
			$freq = $annotation->value->avg_freq;
		}

		if ( $annotation->type == 'net.app.core.fallback_url' && property_exists($annotation, 'value')) {
			$url = $annotation->value->url;
		}

	}
	if ($channel_icon == '') {
		$channel_icon = $broadcast_channel->owner->avatar_image->url;
	}
	$channel_img = '';
	if ($channel_icon != '') {
		$channel_img = '<img src="' . $channel_icon . '" width=80 height=80>';
	}
	ob_start();
	?>
	<div class='adn-broadcast-channel'>
		<div class='adn-channel-display'>
			<?PHP if ($channel_icon) { ?>
			<img src='<?PHP echo $channel_icon; ?>' width='100' height='100' align='left'>
			<?PHP } ?>
			<p>
				<a href='<?PHP echo $url; ?>'><?PHP echo $channel_title; ?></a><br>
				<small><?PHP echo $freq; ?></small><br>
				<?PHP echo $channel_description; ?>
			</p>
		</div>
		<div class='adn-channel-subscribe-display'>
			<span>Never miss important news again</span>
			<?PHP echo ptadn_get_subscribe_code_for_id($channel_id, 14, 182) ?>
		</div>
	</div>
	<?
	return ob_get_clean();
}

add_shortcode( 'adn-channel', 'adn_channel_display_func' );

function register_style () {
    wp_register_style( 'posts_to_adn_style', plugins_url('/style.css', __FILE__), false, '1.0.0', 'all');
}

add_action('init', 'register_style');

function enqueue_style () {
   wp_enqueue_style( 'posts_to_adn_style' );
}

add_action('wp_enqueue_scripts', 'enqueue_style');
