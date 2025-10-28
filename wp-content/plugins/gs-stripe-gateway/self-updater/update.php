<?php
defined( 'ABSPATH' ) || exit;

if( ! class_exists( 'SelfUpdatingPlugin' ) ) {

class SelfUpdatingPlugin {
    protected  $plugin_slug;
    protected  $version;
    protected  $plugin_name;
    protected  $plugin_dir; 
    protected  $plugin_file;
    protected  $logs_op_switcher;
    private    $update_url;
    private    $update_data;
	private    $website_url;
    private    $server_url;
    private    $update_file_name;
    
    /**
     * Initialize the plugin.
     */
    public function __construct($main_plugin_file) {
        // Store the main plugin file path
        
        $saved_logs_op_switcher = get_option('plugin_logs_op_switcher', '');
        $this->logs_op_switcher = $saved_logs_op_switcher;
        $this->plugin_file = $main_plugin_file;
        $this->plugin_slug = plugin_basename($this->plugin_file);
        
        
        // We need to manually set these values initially because get_plugin_data() 
        // might not be available during plugin load
        $this->website_url		= get_site_url();
        $this->version          = '1.0.2';  // Match with the value in the plugin header
        $this->plugin_name      = 'GS Agent Helper';  // Match with the value in the plugin header
        $this->server_url       = 'https://plugins.gsolution.pro';
        $this->plugin_dir       =  dirname($this->plugin_slug);     
        $this->update_file_name = 'update.json';

        
        // Set the URL where the update JSON file is located - update this to your actual server URL
        $this->update_url = $this->server_url .'/'. $this->plugin_dir .'/'. $this->update_file_name;

        // Load the plugin data on admin_init when all admin functions are available
        add_action('admin_init', array($this, 'load_plugin_data'));
        add_filter('plugins_api', array($this, 'plugin_info'), 10, 3);
        add_filter('upgrader_post_install', array($this, 'after_update'), 10, 3);        
        // Hook into the update process
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_updates'));
		// extra links
		add_filter( 'plugin_action_links_'.$this->plugin_slug,  array($this,'update_action_links' ));

        //add_action('admin_init', array($this, 'error_log'));
        

        $this->plugin_update_manager();
        $this->update_ver_creation();
        $this->error_log();
        
    }

    

    public function error_log (){
        
        if($this->logs_op_switcher == '1'){
            error_log('Subscription processing error: ');
            ini_set('display_errors', 1);
            ini_set('display_startup_errors', 1);
            error_reporting(E_ALL);
            ini_set('log_errors', 'On'); 
            error_reporting(E_ALL | E_STRICT);
            ini_set('error_log', __DIR__.'/error.log');

            return add_action('admin_notices', array($this,'custom_admin_notice'));
        }
        
    }

    public function custom_admin_notice() {
        ?>
        <div class="custom-notice notice is-dismissible">
            <p><strong>😊 מצב שגיאות מופעל עבור הפלאגין<p>
        </div>
        <style>
            .custom-notice {
                background: #f5f7f9;
                border-left: 4px solid red;
                padding: 15px;
                border-radius: 6px;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            }
            .custom-notice p {
                font-size: 14px;
                color: #333;
            }
            .custom-notice .button-secondary {
                color: #555;
                text-decoration: none;
                margin-right: 10px;
            }
            .custom-notice .button-primary {
                background: #1e73be;
                color: #fff;
                padding: 6px 12px;
                border-radius: 4px;
                text-decoration: none;
                font-weight: bold;
            }
        </style>
        <?php
    }
	
	/**
     * Load the plugin extra functionality files.
    */
    public function update_action_links( $links ) {
        $website_url = $this->website_url;
        $new_link = "<a class='thickbox open-plugin-details-modal' href='$website_url/wp-admin/plugin-install.php?tab=plugin-information&plugin=$this->plugin_dir&section=changelog&TB_iframe=true&width=772&height=367'>".__('אודות הפלאגין',$this->plugin_dir)."</a>";
    
        // הימנע מהוספה כפולה
        if (!in_array($new_link, $links)) {
            $links[] = $new_link;
        }
    
        return $links;
    }
    

    /**
     * Load the plugin extra functionality files.
    */
    public function plugin_update_manager(){
        return require_once plugin_dir_path(__FILE__) . 'plugin-update-manager.php';
    }

     /**
     * Load the plugin extra functionality files.
    */
    public function update_ver_creation(){
        return require_once plugin_dir_path(__FILE__) . 'push-update-to-server.php';
    }


    /**
    * Load the plugin data when in admin area.
    */
    public function load_plugin_data() {

       
        if (!function_exists('get_plugin_data')) {
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }

        if($this->plugin_file == null){
            $plugin_data = get_plugin_data(PLUGIN_PATH);
        }else{
            $plugin_data = get_plugin_data($this->plugin_file);
        }
            
        $this->version = $plugin_data['Version'];
        $this->plugin_name = $plugin_data['Name'];

        
    }


    /**
     * Check for updates when WordPress checks for plugin updates.
     */
    public function check_for_updates($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        // Load plugin data if we're in the admin area
        if (is_admin() && function_exists('get_plugin_data')) {
            $this->load_plugin_data();
        }

        // Get update data from remote server
        $this->get_remote_update_data();
        
        // If there's an update, add it to the transient
        if ($this->is_update_available()) {
            $transient->response[$this->plugin_slug] = $this->format_update_data();
        }

        return $transient;
    }
    

    /**
     * Get plugin information for the WordPress updates screen.
     */
    public function plugin_info($result, $action, $args) {
        // Check if this is our plugin
        if ($action !== 'plugin_information' || !isset($args->slug) || $args->slug !== dirname($this->plugin_slug)) {
            return $result;
        }

        // Get update data from remote server
        $this->get_remote_update_data();
        
        if (!empty($this->update_data)) {
            $result = new stdClass();
            $result->name = $this->plugin_name;
            $result->slug = dirname($this->plugin_slug);
            $result->version = $this->update_data->version;
            $result->requires = $this->update_data->requires;
            $result->requires_php = $this->update_data->requires_php;
            $result->tested = $this->update_data->tested;
            $result->download_link = $this->update_data->download_url;
            $result->trunk = $this->update_data->download_url;
            $result->last_updated = $this->update_data->last_updated;
            $result->sections = array(
                'description' => $this->update_data->sections->description,
                'changelog' => $this->update_data->sections->changelog,
            );
            if (!empty($this->update_data->sections->installation)) {
                $result->sections['installation'] = $this->update_data->sections->installation;
            }
            if (isset($this->update_data->banners)) {
                $result->banners = array(
                    'low' => $this->update_data->banners->low,
                    'high' => $this->update_data->banners->high,
                );
            }
        }

        return $result;
    }

    /**
     * After the update is complete, maybe do some cleanup.
     */
    public function after_update($response, $hook_extra, $result) {
        if (isset($hook_extra['plugin']) && $hook_extra['plugin'] === $this->plugin_slug) {
            // Perform any post-update operations here
            // For example, you might need to update options or run database migrations
        }
        return $result;
    }

    /**
     * Get update data from the remote server.
     */
    protected function get_remote_update_data() {
        // Check if we already have the data
        if (!empty($this->update_data)) {
            return;
        }

        // Get the remote update data
        $response = wp_remote_get($this->update_url, array(
            'timeout' => 10,
            'headers' => array(
                'Accept' => 'application/json'
            )
        ));

        // If there's an error, return
        if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
            return;
        }

        // Get the response body and decode it
        $data = json_decode(wp_remote_retrieve_body($response));
        
        // Store the update data
        if (is_object($data) && isset($data->version)) {
            $this->update_data = $data;
        }
    }

    /**
     * Check if an update is available.
     */
    protected function is_update_available() {
        if (empty($this->update_data)) {
            return false;
        }

        // Compare versions
        return version_compare($this->version, $this->update_data->version, '<');
    }

    /**
     * Format the update data for WordPress.
     */
    private function format_update_data() {
        $update_data = new stdClass();
        $update_data->id = isset($this->update_data->id) ? $this->update_data->id : '';
        $update_data->slug = dirname($this->plugin_slug);
        $update_data->plugin = $this->plugin_slug;
        $update_data->new_version = $this->update_data->version;
        $update_data->url = isset($this->update_data->url) ? $this->update_data->url : '';
        $update_data->package = $this->update_data->download_url;
        
        if (isset($this->update_data->icons)) {
            $update_data->icons = (array) $this->update_data->icons;
        } else {
            $update_data->icons = array();
        }
        
        if (isset($this->update_data->banners)) {
            $update_data->banners = (array) $this->update_data->banners;
        } else {
            $update_data->banners = array();
        }
        
        if (isset($this->update_data->banners_rtl)) {
            $update_data->banners_rtl = (array) $this->update_data->banners_rtl;
        } else {
            $update_data->banners_rtl = array();
        }
        
        $update_data->tested = isset($this->update_data->tested) ? $this->update_data->tested : '';
        $update_data->requires_php = isset($this->update_data->requires_php) ? $this->update_data->requires_php : '';
        $update_data->compatibility = isset($this->update_data->compatibility) ? (object) $this->update_data->compatibility : new stdClass();
        
        return $update_data;
    }
    
}

} // End class exists check