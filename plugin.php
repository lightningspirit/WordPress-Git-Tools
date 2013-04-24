<?php
/*
Plugin Name: Git Tools - Pull from git
Plugin URI: http://www.vcarvalho.com/
Version: 1.0
Text Domain: functions
Domain Path: /languages/
Author: lightningspirit
Author URI: http://profiles.wordpress.org/lightningspirit
Description: Give your staging environment a change with (automatic) git pulls
License: GPLv2
*/



// Checks if it is accessed from Wordpress' index.php
if ( ! function_exists( 'add_action' ) ) {
	die( 'I\'m just a plugin. I must not do anything when called directly!' );

}


/**
 * This is a good exemple on how to implement
 * an admin options or tools page with some inputs
 * 
 */


// Add plugin settings
function my_plugin_settings_link( $links, $file ) {
	
	if ( $file == 'git-tools/plugin.php' )
		$links['settings'] = sprintf( 
			'<a href="%s"> %s </a>', 
			admin_url( 'tools.php?page=git_tools' ), __( 'Settings', 'pfg' ) 
		);
	
	return $links;

}

add_filter( 'plugin_action_links', 'my_plugin_settings_link', 10, 2 );




// Create tools page in admin panel
function pfg_tools_page() {
	$page = add_management_page( __( 'Git Tools', 'pfg' ), __( 'Git Tools', 'pfg' ), 'manage_options', 'git_tools', 'pfg_git_tools_page' );
	add_action( "load-{$page}", 'pfg_git_tools_page_inputs' );	
	
	if ( isset( $_POST['pfg_git_repository_url'] ) )
		add_action( "load-options.php", 'pfg_git_tools_page_inputs' );
	
}

add_action( 'admin_menu', 'pfg_tools_page' );



// Add input options in page
function pfg_git_tools_page_inputs() {	
	
	if ( isset( $_GET['action'] ) && $_GET['action'] == 'update_environment' ) {
		add_settings_error( 'pfg_notices', 'git_return', nl2br( pfg_update_environment() ), 'updated' );
		//wp_redirect( admin_url( 'tools.php?page=git_tools' ) );
		
	}
	
	
	$fields = array(
		array( 
			'id' => 'repository-url',
			'name' => 'pfg_git_repository_url',
			'type' => 'text',
			'label' => __( 'Git Repository URI', 'pfg' ),
			'example' => __( 'Use HTTP url! Ex.: https://github.com:name/repository.git', 'pfg' ),
			'validate' => true
		),
		array( 
			'id' => 'username',
			'name' => 'pfg_git_repository_username',
			'type' => 'text',
			'label' => __( 'Git Repository Username', 'pfg' ),
			'example' => __( 'Your GitHub username', 'pfg' ),
			'validate' => false
		),
		array( 
			'id' => 'password',
			'name' => 'pfg_git_repository_password',
			'type' => 'password',
			'label' => __( 'Git Repository Password', 'pfg' ),
			'example' => __( 'Your GitHub password', 'pfg' ),
			'validate' => false
		),
		array( 
			'id' => 'relative-path',
			'name' => 'pfg_git_repository_path',
			'type' => 'text',
			'label' => __( 'Git Repository Local Path', 'pfg' ),
			'example' => __( 'Relative to WP root. Ex.: «/path-to-wp/wp-content». Default «/».', 'pfg' ),
			'validate' => false
		),
		array( 
			'id' => 'branch',
			'name' => 'pfg_git_repository_branch',
			'type' => 'text',
			'label' => __( 'Git Repository Branch', 'pfg' ),
			'example' => __( 'Empty means master', 'pfg' ),
			'validate' => false
		)
		
		
	);
	
	foreach ( $fields as $args ) {
		register_setting( 'git_tools_general', $args['name'], ( $args['validate'] ? 'pfg_validate_git_inputs' : 'strip_tags' ) );
		add_settings_field( $args['id'], $args['label'], 'pfg_git_tools_page_render_input', 'git_tools_general', 'git-options', $args );
		
	}
	
	add_settings_section( 'git-options', __( 'Git Settings', 'pfg' ), false, 'git_tools_general' );
	

}

// Add git option repository url
function pfg_git_tools_page_render_input( $args ) {
	$value = get_option( $args['name'] );
	?>
	<input id="<?php echo $args['id']; ?>" name="<?php echo $args['name']; ?>" type="<?php echo $args['type']; ?>" value="<?php echo $value; ?>" placeholder="<?php echo $args['example']; ?>" class="regular-text code" />
	<?php
}

// Validate option repository url input
function pfg_validate_git_inputs( $value ) {
	// Vamos analizar se o usuário introduzio um email válido.
	if ( empty( $value ) )
		return add_settings_error( 'pfg_notices', 'empty', esc_html__( 'Repository Settings cannot be empty', 'pfg' ), 'error' );
	
	else
		add_settings_error( 'pfg_notices', 'updated', esc_html__( 'Your settings were updated.', 'pfg' ), 'updated' );
	
	return $value;
	
}


// Git tools page
function pfg_git_tools_page() {	
	?>
	<div class="wrap">
    <?php screen_icon(); ?>
    <h2>
        <?php _e( 'Git Tools', 'pfg' ); ?>
        <a href="<?php echo admin_url( 'tools.php?page=git_tools&action=update_environment' ); ?>" class="add-new-h2">
        	<?php _e( 'Update environment', 'pfg' ); ?>
        </a>
    </h2>
    
   
	<form id="formulario" action="options.php" method="post">
		
		<?php settings_fields( 'git_tools_general' ); ?>
		
		<?php do_settings_sections( 'git_tools_general' ); ?>
		
		<?php submit_button(); ?>
		
	</form>
	
	<p class="description"><?php _e( "<strong>Important:</strong> Your environment must be configured with suExec or webserver's username must have write permition.", 'pfg' ); ?></p>
    
	
	</div>
	<?php
}

// Throw admin notice 
function pfg_admin_notices() {
	settings_errors( 'pfg_notices' );
	
}
add_action( 'admin_notices', 'pfg_admin_notices' );

/**
 * End of the example
 */


/**
 * Updates the environment
 * 
 * Use it for other hooks. It calls `git pull`
 * 
 * @return void
 * 
 * @since 1.0
 */
function pfg_update_environment() {
	set_time_limit( 120 );
	ignore_user_abort( true );
	
	// Build query
	$path = get_option( 'pfg_git_repository_path' );
	$username = get_option( 'pfg_git_repository_username' );
	$password = get_option( 'pfg_git_repository_password' );
	$uri = str_replace( array( 'http://', 'https://' ), '', get_option( 'pfg_git_repository_url' ) );
	$branch = get_option( 'pfg_git_repository_branch' );
	
	if ( $branch )
		$uri .= ' '.$branch;
	
	// Build command
	$command = sprintf( 'cd %1$s; git pull https://%2$s:%3$s@%4$s 2>&1', ABSPATH.$path, $username, $password, $uri );
	
	// Executes the pull
	$return = shell_exec( $command );
	
	return $return;
	
}
