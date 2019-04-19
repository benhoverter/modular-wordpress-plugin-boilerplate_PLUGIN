<?php

/**
* The file that defines the methods for automatic updates in WordPress
*
* A class definition that includes properties and methods used to update the plugin
* from within the WordPress Plugin area.
*
* @link       https://github.com/benhoverter/modular-wordpress-plugin-boilerplate
* @since      1.0.0
*
* @package    plugin-name
* @subpackage plugin-name/includes
*/

/**
* The plugin updater class.
*
*
* @since      1.0.0
* @package    plugin-name
* @subpackage plugin-name/includes
* @author     Your Name <email@example.com>
*/
class Plugin_Abbr_Updater {

    /**
    * The plugin slug.
    *
    * @since    1.0.0
    * @access   private
    * @var      string    $slug    The plugin slug.
    */
    private $slug;

    /**
    * The plugin metadata drawn from WordPress.
    *
    * @since    1.0.0
    * @access   private
    * @var      string    $plugin_data    The plugin metadata.
    */
    private $plugin_data;

    /**
    * The username for the GitHub repo.
    *
    * @since    1.0.0
    * @access   private
    * @var      string    $github_username    The username for the GitHub repo.
    */
    private $github_username;

    /**
    * The GitHub repo name.
    *
    * @since    1.0.0
    * @access   private
    * @var      string    $repo_name    The GitHub repo name.
    */
    private $repo_name;

    /**
    * The plugin file, passed in as __FILE__ in the main plugin-name.php script.
    *
    * @since    1.0.0
    * @access   private
    * @var      string    $plugin_file    The plugin file.
    */
    private $plugin_file;

    /**
    * The result of the GitHub API call.
    *
    * @since    1.0.0
    * @access   private
    * @var      string    $github_api_result    The result of the GitHub API call.
    */
    public $github_api_result;  // NOTE: TESTING ONLY!
    // private $github_api_result;


    /**
    * The access token for the GitHub repo.
    *
    * @since    1.0.0
    * @access   private
    * @var      string    $github_token    The access token for the GitHub repo.
    */
    private $github_token;

    /**
    * The access token for the GitHub repo.
    *
    * @since    1.0.0
    * @access   private
    * @var      boolean    $was_activated    The activated state of the plugin.
    */
    private $was_activated;


    /**
    * Set the properties from params and add the filters that hook into our public methods.
    *
    *
    * @since    1.0.0
    */
  public function __construct( $plugin_file, $github_username, $repo_name /* , $github_token */ ) {

    add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'set_transient' ) );
    add_filter( 'plugins_api', array( $this, 'set_plugin_info' ), 10, 3 );
    add_filter( 'upgrader_pre_install', array( $this, 'handle_pre_install' ) );
    add_filter( 'upgrader_post_install', array( $this, 'handle_post_install' ) );

    $this->plugin_file     = $plugin_file; //   '/plugin-name/plugin-name.php'
    $this->github_username = $github_username;
    $this->repo_name       = $repo_name;
    // $this->github_token    = $github_token;

  }


  /**
  * Short Description. (use period)
  *
  * Long Description.
  *
  * @since    1.0.0
  */
  private function get_plugin_data() {
    $this->slug = plugin_basename( $this->plugin_file );

        // print_r( $this->slug );
        // print_r(WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . dirname( $this->slug ));


    $this->plugin_data = get_plugin_data( $this->plugin_file );

    // print_r( $this->plugin_data );

  }

  /**
  * Short Description. (use period)
  *
  * Long Description.
  *
  * @since    1.0.0
  */
  private function get_repo_release_info() {

    // Prevent a double-call on 'pre_set_site_transient_update_plugins' hook,
    // which WP calls twice:
    if( !empty( $this->github_api_result ) ) {
      return;
    }

    // Set the GitHub API URL:
    $url = "https://api.github.com/repos/" . $this->github_username . "/" . $this->repo_name . "/releases";


    // var_dump( $url );

    // Append the access token for the private repo:
    if( !empty( $this->github_token ) ) {
      $url = add_query_arg( array(
          'access_token' => $this->github_token,
        ), $url
      );
    }

    // Call the API:
    $this->github_api_result = wp_remote_retrieve_body(
      wp_remote_get( $url )
    );

    // Parse the response from JSON:
    // Suppress errors, just in case GitHub throws up.
    if( !empty( $this->github_api_result ) ) {
      $this->github_api_result = @json_decode( $this->github_api_result );
    }

    // Keep only the latest release, even if it's a pre-release:
    if( is_array( $this->github_api_result ) ) {
      $this->github_api_result = $this->github_api_result[0];
    }

  }

  /**
  * Provides info on the plugin update to WP.
  *
  * Called on 'pre_set_site_transient_update_plugins',
  * which WP calls when it checks for plugin updates.
  *
  * @since    1.0.0
  */
  public function set_transient( $transient ) {

    // If WP already checked for updates, don't re-check:
    if( empty( $transient->checked ) ) {
      // print_r( 'GOT HERE.' );
      return $transient;
    }


    // Get the plugin and release info:
    $this->get_plugin_data();
    $this->get_repo_release_info();

    // print_r( $this->github_api_result );

    $has_update = version_compare(
      $this->github_api_result->tag_name,
      $transient->checked[$this->slug]
    );
            //
            // print_r( $this->slug );
            // print_r( $this->plugin_data );


    if( $has_update ) {

      $zip_package = $this->github_api_result->zipball_url;

      // Append the access token, if it exists:
      if( !empty( $this->github_token ) ) {
        $zip_package = add_query_arg(
          array( "access_token" => $this->github_token ),
          $zip_package
        );
      }

      $obj = new stdClass();
      $obj->slug = $this->slug;
      $obj->new_version = $this->github_api_result->tag_name;
      $obj->url = $this->plugin_data['PluginURI'];
      $obj->package = $zip_package;

      $transient->response[$this->slug] = $obj;

    }

            // print_r( $transient );

    return $transient;

  }


  /**
  * Short Description. (use period)
  *
  * Long Description.
  *
  * @since    1.0.0
  */
  public function set_plugin_info( $false, $action, $response ) {
    $this->get_plugin_data();
    $this->get_repo_release_info();

    // If we aren't loading info, bail:
    if( empty( $response->slug ) || $action !== 'plugin_information' ) {
      return false;
    }

    // If we aren't loading info for this plugin, move along:
    if( $response->slug !== $this->slug ) {
      return $response;
    }

    // Add our plugin metadata to the $response:
    $response->last_updated = $this->github_api_result->published_at;
    $response->slug         = $this->slug;
    $response->plugin_name  = $this->plugin_data['Name'];
    $response->version      = $this->github_api_result->tag_name;
    $response->author       = $this->plugin_data['AuthorName'];
    $response->homepage     = $this->plugin_data['PluginURI'];

    // The ZIP file for the release:
    $download_link = $this->github_api_result->zipball_url;

    // Append the access token, if it exists:
    if( !empty( $this->github_token ) ) {
      $download_link = add_query_arg(
        array( "access_token" => $this->github_token ),
        $download_link
      );
    }

    $response->download_link = $download_link;

    /// TESTING:
    var_dump( $this->$response );
    ///


    return $response;
  }



  /**
  * Checks for activation before installation of a new version.
  *
  * Allows us to know whether or not to reactivate after installation.
  *
  * @since    1.0.0
  */
  public function handle_pre_install( $true, $args = [] ) {

    $this->get_plugin_data();

    $this->was_activated = is_plugin_active( $this->slug );

  }


  /**
  * Renames the extracted plugin dir to match the original.
  *
  * WordPress expects the same dir name after extraction, but GitHub appends
  * the version (tag_name) to the ZIP archive.  We need to rename it post-install.
  *
  * @since    1.0.0
  */
  public function handle_post_install( $true, $hook_extra = [], $result = [] ) {

    error_log( $result );

    // Get the plugin and see if it's active before we rename anything:
    // $this->get_plugin_data();
    // $was_activated = is_plugin_active( $this->slug );

    // GitHub plugin folder name for releases is 'reponame-tagname'.
    // We want the original folder name:
    global $wp_filesystem;
    $plugin_folder = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . dirname( $this->slug );
    $wp_filesystem->move( $result['destination'], $plugin_folder );
    $result['destination'] = $plugin_folder;

    // Re-activate if we need to:
    if( $this->was_activated ) {
      $activated = activate_plugin( $this->slug );
    }

    error_log( $result );

    return $result;
  }

}
