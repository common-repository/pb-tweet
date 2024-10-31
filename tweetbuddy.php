<?php
/**
 *
 * Plugin Name: PB-Tweet
 * Plugin URI: http://pluginbuddy.com/pb-tweet/
 * Description: Adds your latest tweet to a banner at the top of your website.
 * Version: 1.0.1
 * Author: Matt Danner
 * Author URI: http://pluginbuddy.com
 *
 * Installation:
 *
 * 1. Download and unzip the latest release zip file.
 * 2. If you use the WordPress plugin uploader to install this plugin skip to step 4.
 * 3. Upload the entire TweetBuddydirectory to your `/wp-content/plugins/` directory.
 * 4. Activate the plugin through the 'Plugins' menu in WordPress Administration.
 *
 * Usage:
 *
 * 1. Navigate to the new TweetBuddy menu in the Wordpress Administration Panel.
 * 2. Enter your Twitter ID. Remember - Do NOT include the '@' symbol.
 *
 */

if (!class_exists("PBTweetBuddy")) {
	class PBTweetBuddy {
		var $_version = '1.0.1';
		
		var $_var = 'pb-tweetbuddy';
		var $_name = 'PB-Tweet';
		var $_timeformat = '%b %e, %Y, %l:%i%p';	// mysql time format
		var $_timestamp = 'M j, Y, g:iA';			// php timestamp format
		var $_usedInputs = array();
		var $_pluginPath = '';
		var $_pluginRelativePath = '';
		var $_pluginURL = '';
		var $_selfLink = '';
		var $_defaults = array(
			'twitter_id' => '',
		);
		var $_options = array();
		
		/**
		 * PBTweetBuddy()
		 *
		 * Default Constructor
		 *
		 */
		function PBTweetBuddy() {
			$this->_pluginPath = dirname( __FILE__ );
			$this->_pluginRelativePath = ltrim( str_replace( '\\', '/', str_replace( rtrim( ABSPATH, '\\\/' ), '', $this->_pluginPath ) ), '\\\/' );
			$this->_pluginURL = get_option( 'siteurl' ) . '/' . $this->_pluginRelativePath;
			if ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] == 'on' ) {
				$this->_pluginURL = str_replace( 'http://', 'https://', $this->_pluginURL );
			}
			$this->_selfLink = array_shift( explode( '?', $_SERVER['REQUEST_URI'] ) ) . '?page=' . $this->_var;
			
			// Admin.
			if ( is_admin() ) {
				add_action('admin_menu', array(&$this, 'admin_menu')); // Add menu in admin.
				add_action('admin_init', array(&$this, 'init_admin' )); // Run on admin initialization.
				// When user activates plugin in plugin menu.
				register_activation_hook(__FILE__, array(&$this, '_activate'));
			} else { // Non-Admin.
				add_action('wp_print_styles', array(&$this, 'enqueue_style'));
				add_action('wp_head', array( &$this, 'pb_print_latest_tweet') );;
				
				$this->load();
				$username = $this->_options["twitter_id"];
				
				$tweet = get_transient('pb_tweetbuddy_' . $username);
				if (! empty($tweet))
					return $tweet;
				$prefix = "<b style='font-style:italic; vertical-align:top;'>@{$this->_options['twitter_id']}: </b>";
				$suffix = ""; 
				$feed = "http://search.twitter.com/search.atom?q=from%3A" . $username . "&rpp=1";
				$twitterFeed = file_get_contents($feed);
				$tweet = stripslashes($prefix) . $this->parse_feed($twitterFeed) . stripslashes($suffix);
				set_transient('pb_tweetbuddy_' . $username, $tweet, 300);
				return $tweet;
			}
		}
		
		function pb_print_latest_tweet() {
			$this->load();
			if ( ! empty( $this->_options["twitter_id"] ) ) {
				?>
				<div id="tweet-outer">
					<div id="tweet">
						<div id="the-tweet"><?php echo $this->PBTweetBuddy(); ?></div>
					</div>
				</div>
				<?php
			}
		}
		
		function parse_feed($feed) {
			$stepOne = explode("<content type=\"html\">", $feed);
			$stepTwo = explode("</content>", $stepOne[1]);
			$tweet = $stepTwo[0];
			$tweet = str_replace("&lt;", "<", $tweet);
			$tweet = str_replace("&gt;", ">", $tweet);
			$tweet = html_entity_decode($tweet);
			return $tweet;
		}
		
		/**
		 * PBTweetBuddy::_activate()
		 *
		 * Run on plugin activation.
		 *
		 */
		function activate() {
		}
		
		/**
		 * PBTweetBuddy::init_admin()
		 *
		 * Run on admin load.
		 *
		 */
		function init_admin() {
		}
		
		/**
		 * PBTweetBuddy::init_public()
		 *
		 * Run on on public load.
		 *
		 */
		function enqueue_style() {
			if ( ! empty( $this->_options["twitter_id"] ) ) {
				wp_enqueue_style( 'pb-tweet-style', $this->_pluginURL . '/css/tweet.css' );
			}
		}
		
		/**
		 * PBTweetBuddy::view_settings()
		 *
		 * Displays settings form and values for viewing & editing.
		 *
		 */
		function view_settings() {
			$this->load();
		
			if (!empty($_POST['save'])) {
				$this->_saveSettings();
			}
			
			// Load scripts and CSS used on this page.
			wp_enqueue_script( 'ithemes-tooltip-js', $this->_pluginURL . '/js/tooltip.js' );
			wp_print_scripts( 'ithemes-tooltip-js' );
			wp_enqueue_script( 'ithemes-'.$this->_var.'-admin-js', $this->_pluginURL . '/js/admin.js' );
			wp_print_scripts( 'ithemes-'.$this->_var.'-admin-js' );
			echo '<link rel="stylesheet" href="'.$this->_pluginURL . '/css/admin.css" type="text/css" media="all" />';
			
			echo '<div class="wrap">';
			?>
			
				<h2>pbTweet Settings</h2>
				Thank you for Downloading pbTweet. This plugin allows you to easily add a banner to the top of your site that contains your latest <br />tweet. Simply fill in the box below with your Twitter ID and the banner will appear on the front-end of your site.
				<?php $this->_usedInputs=array(); ?>
				<form method="post" action="<?php echo $this->_selfLink; ?>-settings">
					<table class="form-table">
						<tr>
							<td><label for="twitter_id">Twitter ID <a class="ithemes_tip" title=" - Enter your twitter ID here. Please do NOT include the '@' symbol">(?)</a>:</label></td>
							<td><?php $this->_addTextBox('twitter_id', array( 'size' => '45', 'maxlength' => '45', 'value' => $this->_options['twitter_id'] ) ); ?></td>
						</tr>
					</table>
					<p class="submit"><?php $this->_addSubmit( 'save', 'Save Settings' ); ?></p>
					<?php $this->_addUsedInputs(); ?>
					<?php wp_nonce_field( $this->_var . '-nonce' ); ?>
				</form>
				
			<?php
			echo '</div>';
			//Debugging Information
			if (!empty($_POST['reset_defaults'])) {
				$this->_options = $this->_defaults;
				$this->save();
				$this->_showStatusMessage( 'Plugin settings have been reset to defaults.' );
			}
			
			// Dustin's useful debugging code.
			echo '<div class="wrap">';
			echo '<h2>Debugging Information</h2>';
			echo '<textarea rows="7" cols="65">';
			echo 'Plugin Version = '.$this->_name.' '.$this->_version.' ('.$this->_var.')'."\n";
			echo 'WordPress Version = '.get_bloginfo("version")."\n";
			echo 'PHP Version = '.phpversion()."\n";
			global $wpdb;
			echo 'DB Version = '.$wpdb->db_version()."\n";
			echo "\n".serialize($this->_options);
			echo '</textarea><br /><br />';
			?>
			<?php
			echo '</div>';
		}
		
		// OPTIONS STORAGE //////////////////////
		
		function save() {
			add_option($this->_var, $this->_options, '', 'no'); // 'No' prevents autoload if we wont always need the data loaded.
			update_option($this->_var, $this->_options);
			return true;
		}
		
		
		function load() {
			$this->_options=get_option($this->_var);
			$options = array_merge( $this->_defaults, (array)$this->_options );

			if ( $options !== $this->_options ) {
				// Defaults existed that werent already in the options so we need to update their settings to include some new options.
				$this->_options = $options;
				$this->save();
			}

			return true;
		}
		
		// ADMIN MENU FUNCTIONS /////////////////
		
		/** admin_menu()
		 *
		 * Initialize menu for admin section.
		 *
		 */
		function admin_menu() {
			add_submenu_page( 'options-general.php', 'pbTweet', 'pbTweet', 'manage_options', 'pb-tweetbuddy-settings', array(&$this, 'view_settings') );
		}
		
		// Form Creator
		function _saveSettings() {
			check_admin_referer( $this->_var . '-nonce' );
			
			foreach ( (array) explode( ',', $_POST['used-inputs'] ) as $name ) {
				$is_array = ( preg_match( '/\[\]$/', $name ) ) ? true : false;
				
				$name = str_replace( '[]', '', $name );
				$var_name = preg_replace( '/^' . $this->_var . '-/', '', $name );
				
				if ( $is_array && empty( $_POST[$name] ) )
					$_POST[$name] = array();
				
				if ( isset( $_POST[$name] ) && ! is_array( $_POST[$name] ) )
					$this->_options[$var_name] = stripslashes( $_POST[$name] );
				else if ( isset( $_POST[$name] ) )
					$this->_options[$var_name] = $_POST[$name];
				else
					$this->_options[$var_name] = '';
			}
			
			$errorCount = 0;
			
			// ERROR CHECKING OF INPUT
			if ( $errorCount < 1 ) {
				if ( $this->save() )
					$this->_showStatusMessage( __( 'Settings updated', $this->_var ) );
				else
					$this->_showErrorMessage( __( 'Error while updating settings', $this->_var ) );
			}
			else {
				$this->_showErrorMessage( __ngettext( 'Please fix the input marked in red below.', 'Please fix the inputs marked in red below.', $errorCount ) );
			}
		}
		
		function _newForm() {
			$this->_usedInputs = array();
		}
		
		function _addSubmit( $var, $options = array(), $override_value = true ) {
			if ( ! is_array( $options ) )
				$options = array( 'value' => $options );
			
			$options['type'] = 'submit';
			$options['name'] = $var;
			$options['class'] = ( empty( $options['class'] ) ) ? 'button-primary' : $options['class'];
			$this->_addSimpleInput( $var, $options, $override_value );
		}
		
		function _addButton( $var, $options = array(), $override_value = true ) {
			if ( ! is_array( $options ) )
				$options = array( 'value' => $options );
			
			$options['type'] = 'button';
			$options['name'] = $var;
			$this->_addSimpleInput( $var, $options, $override_value );
		}
		
		function _addTextBox( $var, $options = array(), $override_value = false ) {
			if ( ! is_array( $options ) )
				$options = array( 'value' => $options );
			
			$options['type'] = 'text';
			$this->_addSimpleInput( $var, $options, $override_value );
		}
		
		function _addCheckBox( $var, $options = array(), $override_value = false ) {
			if ( ! is_array( $options ) )
				$options = array( 'value' => $options );
			
			$options['type'] = 'checkbox';
			$this->_addSimpleInput( $var, $options, $override_value );
		}
		
		function _addRadio( $var, $options = array(), $override_value = false ) {
			if ( ! is_array( $options ) )
				$options = array( 'value' => $options );
			
			$options['type'] = 'radio';
			$this->_addSimpleInput( $var, $options, $override_value );
		}
		
		function _addUsedInputs() {
			$options['type'] = 'hidden';
			$options['value'] = implode( ',', $this->_usedInputs );
			$options['name'] = 'used-inputs';
			$this->_addSimpleInput( 'used-inputs', $options, true );
		}
		
		function _addSimpleInput( $var, $options = false, $override_value = false ) {
			if ( empty( $options['type'] ) ) {
				echo "<!-- _addSimpleInput called without a type option set. -->\n";
				return false;
			}
			
			$scrublist['textarea']['value'] = true;
			$scrublist['file']['value'] = true;
			$scrublist['dropdown']['value'] = true;
			$defaults = array();
			$defaults['name'] = $this->_var . '-' . $var;
			$var = str_replace( '[]', '', $var );
			
			if ( 'checkbox' === $options['type'] )
				$defaults['class'] = $var;
			else
				$defaults['id'] = $var;
			
			$options = $this->_merge_defaults( $options, $defaults );
			
			if ( ( false === $override_value ) && isset( $this->_options[$var] ) ) {
				if ( 'checkbox' === $options['type'] ) {
					if ( $this->_options[$var] == $options['value'] )
						$options['checked'] = 'checked';
				}
				elseif ( 'dropdown' !== $options['type'] )
					$options['value'] = $this->_options[$var];
			}
			
			if ( ( preg_match( '/^' . $this->_var . '/', $options['name'] ) ) && ( ! in_array( $options['name'], $this->_usedInputs ) ) )
				$this->_usedInputs[] = $options['name'];
			
			$attributes = '';
			
			if ( false !== $options )
				foreach ( (array) $options as $name => $val )
					if ( ! is_array( $val ) && ( ! isset( $scrublist[$options['type']][$name] ) || ( true !== $scrublist[$options['type']][$name] ) ) )
						if ( ( 'submit' === $options['type'] ) || ( 'button' === $options['type'] ) )
							$attributes .= "$name=\"$val\" ";
						else
							$attributes .= "$name=\"" . htmlspecialchars( $val ) . '" ';
			
			if ( 'textarea' === $options['type'] )
				echo '<textarea ' . $attributes . '>' . $options['value'] . '</textarea>';
			elseif ( 'dropdown' === $options['type'] ) {
				echo "<select ".$class." $attributes>\n";
				foreach ( (array) $options['value'] as $val => $name ) {
				
					$selected = ( $this->_options[$var] == $val ) ? ' selected="selected"' : '';
					echo "<option value=\"$val\"$selected>$name</option>\n";
				}
				
				echo "</select>\n";
			}
			else
				echo '<input ' . $attributes . '/>';
		}
		
		function _merge_defaults( $values, $defaults, $force = false ) {
			if ( ! $this->_is_associative_array( $defaults ) ) {
				if ( ! isset( $values ) ) {
					return $defaults;
				}
				if ( false === $force ) {
					return $values;
				}
				if ( isset( $values ) || is_array( $values ) )
					return $values;
				return $defaults;
			}
			
			foreach ( (array) $defaults as $key => $val ) {
				if ( ! isset( $values[$key] ) ) {
					$values[$key] = null;
				}
				$values[$key] = $this->_merge_defaults($values[$key], $val, $force );
			}
			return $values;
		}
		
		function _is_associative_array( &$array ) {
			if ( ! is_array( $array ) || empty( $array ) ) {
				return false;
			}
			$next = 0;
			foreach ( $array as $k => $v ) {
				if ( $k !== $next++ ) {
					return true;
				}
			}
			return false;
		}
		
		// PUBLIC DISPLAY OF MESSAGES ////////////////////////
		
		function _showStatusMessage( $message ) {
			echo '<div id="message" class="updated fade"><p><strong>'.$message.'</strong></p></div>';
		}
		function _showErrorMessage( $message ) {
			echo '<div id="message" class="error"><p><strong>'.$message.'</strong></p></div>';
		}
		
		// SORTING FUNCTION(S) //////////////////////////////////
		
		function _sortGroupsByName( $a, $b ) {
			if ( $this->_options['groups'][$a]['name'] < $this->_options['groups'][$b]['name'] )
				return -1;
			
			return 1;
		}
		
	} // End class
	
	$PBTweetBuddy = new PBTweetBuddy(); // Create instance
}
