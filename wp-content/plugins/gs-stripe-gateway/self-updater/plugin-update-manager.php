<?php
defined( 'ABSPATH' ) || exit;

if( ! class_exists( 'PluginUpdateManager' ) ) {

class PluginUpdateManager extends SelfUpdatingPlugin {

public function __construct($main_plugin_file) {
    //parent::__construct($main_plugin_file);

    // Add an admin page for checking updates manually
    add_action('admin_menu', array($this, 'add_admin_menu'));  
    // Ajax handler for manual update checks
    add_action('wp_ajax_check_for_manual_updates', array($this, 'ajax_check_updates'));
    // שמירת ההגדרות בבסיס הנתונים
    add_action('admin_post_plugin_save_settings', array($this,'save_plugin_settings'));
    
}

    /**
     * Ajax handler for manual update checks.
     */
    public function ajax_check_updates() {
        check_ajax_referer('check-for-updates-nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'You do not have permission to do this.'));
            return;
        }
        
        // Force WordPress to check for updates
        delete_site_transient('update_plugins');
        wp_update_plugins();
        
        // Make sure plugin data is loaded
        $this->load_plugin_data();
        
        // Get update data from remote server
        $this->get_remote_update_data();
        
        if ($this->is_update_available()) {
            wp_send_json_success(array(
                'message' => sprintf(
                    'Update available! Version %s is available. Your current version is %s.',
                    esc_html($this->update_data->version),
                    esc_html($this->version)
                ),
                'update_available' => true
            ));
        } else {
            wp_send_json_success(array(
                'message' => 'You are using the latest version.',
                'update_available' => false
            ));
        }
    }
    
/**
* Add admin menu for checking updates manually.
*/
public function add_admin_menu() {
    $plugin_dir = $this->plugin_dir;
    add_submenu_page(
        'plugins.php',
        'Check for Updates',
        'Update Manager',
        'edit_posts',
        $plugin_dir,
        array($this, 'admin_page')
    );
}

    /**
     * Display the admin page.
     */
    public function admin_page() {
        
        // Make sure plugin data is loaded
        $plugin_data = $this->load_plugin_data();
        $plugin_version = $this->version;
        $plugin_dir = $this->plugin_dir;
        
        $all_plugins = get_plugins();
        $saved_key = get_option('plugin_update_key', '');
        $saved_webhook = get_option('plugin_update_webhook', '');
        $saved_update_op_switcher = get_option('plugin_update_op_switcher', '');
        $saved_logs_op_switcher = get_option('plugin_logs_op_switcher', '');
        ?>

        <div class="wrap">
        <h1>ניהול עדכוני תוספים</h1>
        
        <!-- טופס שמירת נתוני API -->
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <input type="hidden" name="action" value="plugin_save_settings">
            <?php wp_nonce_field('plugin_save_settings_action', 'plugin_save_settings_nonce'); ?>
            
            <h2>הגדרות API</h2>
            <table class="form-table">
                <tr>
                    <th><label for="update_key">מפתח</label></th>
                    <td><input type="text" name="update_key" id="update_key" value="<?php echo esc_attr($saved_key); ?>"></td>
                </tr>
                <tr>
                    <th><label for="update_webhook">וובהוק</label></th>
                    <td><input type="text" name="update_webhook" id="update_webhook" value="<?php echo esc_attr($saved_webhook); ?>"></td>
                </tr>
                <tr>
                    <th><label for="update_op_switcher">ניהול עידכונים</label></th>
                    <input type="hidden" name="update_op_switcher" value="0"> <!-- ערך ברירת מחדל -->
                    <td><input type="checkbox" name="update_op_switcher" id="update_op_switcher" value="1" <?php checked($saved_update_op_switcher, '1'); ?>"></td>
                </tr>
                <tr>
                    <th><label for="logs_op_switcher">ניהול לוגים</label></th>
                    <input type="hidden" name="logs_op_switcher" value="0"> <!-- ערך ברירת מחדל -->
                    <td><input type="checkbox" name="logs_op_switcher" id="logs_op_switcher" value="1" <?php checked($saved_logs_op_switcher, '1'); ?>"></td>
                </tr>
            </table>
            
            <p><button type="submit" class="button button-secondary">שמור הגדרות</button></p>
        </form>

        <hr>
        
        <!-- טופס שליחת עדכון -->
        <form method="post" action=<?php echo admin_url('admin-post.php?page='.$plugin_dir); ?> style="display: <?php echo ($saved_update_op_switcher == '1') ? 'block' : 'none'; ?>">
            <input type="hidden" name="action" value="plugin_update_action">
            <?php wp_nonce_field('plugin_update_action', 'plugin_update_nonce'); ?>
            
            <h2>בחר תוסף</h2>
            <ul>
                <?php foreach ($all_plugins as $plugin_file => $plugin_data) :
                    if (strpos(dirname($plugin_file), 'gs-') !== 0) continue; // סינון פלאגינים שמתחילים ב-GS
                ?>
                    <li>
                        <label>
                            <input type="radio" name="selected_plugin" value="<?php echo esc_attr($plugin_file); ?>">
                            <?php echo esc_html($plugin_data['Name']); ?>
                        </label>
                    </li>
                <?php endforeach; ?>
            </ul>

                <h2>פרטי עדכון</h2>
                <table class="form-table">
                    <tr>
                        <th><label for="update_version">גרסה</label></th>
                        <td><input type="text" name="update_version" id="update_version" placeholder="<?php echo esc_attr($plugin_version); ?>" required></td>
                    </tr>
                    <tr>
                        <th><label for="update_text">לוג שינויים</label></th>
                        <td><textarea name="update_text" id="update_text" required></textarea></td>
                    </tr>
                </table>
                
                <p><button type="submit" class="button button-primary">עדכן ושלח</button></p>
            </form>
        </div>
        
        <div class="wrap" style="display: <?php echo ($saved_update_op_switcher == '1') ? 'block' : 'none'; ?>">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <p>Current version: <?php echo esc_html($this->version); ?></p>
            <p>
                <button id="check-for-updates" class="button button-primary">Check for Updates</button>
                <span id="update-message"></span>
            </p>
            <script type="text/javascript">
                jQuery(document).ready(function($) {
                    $('#check-for-updates').on('click', function() {
                        var button = $(this);
                        button.prop('disabled', true);
                        $('#update-message').text('Checking for updates...');
                        
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'check_for_manual_updates',
                                nonce: '<?php echo wp_create_nonce('check-for-updates-nonce'); ?>'
                            },
                            success: function(response) {
                                if (response.success) {
                                    $('#update-message').html(response.data.message);
                                    if (response.data.update_available) {
                                        $('#update-message').append(' <a href="<?php echo admin_url('plugins.php'); ?>">Go to Plugins</a>');
                                    }
                                } else {
                                    $('#update-message').text('Error checking for updates.');
                                }
                                button.prop('disabled', false);
                            },
                            error: function() {
                                $('#update-message').text('Error checking for updates.');
                                button.prop('disabled', false);
                            }
                        });
                    });
                });
            </script>
        </div>
        <?php
    }

/**
 * שמירת ההגדרות בבסיס הנתונים
 */

public function save_plugin_settings() {
        
        if (!current_user_can('manage_options')) {
            wp_die('אין לך הרשאה לבצע פעולה זו.');
        }

        if (!isset($_POST['plugin_save_settings_nonce']) || !wp_verify_nonce($_POST['plugin_save_settings_nonce'], 'plugin_save_settings_action')) {
            wp_die('אימות נכשל.');
        }

        // שמירת הערכים באופציות וורדפרס
        if (isset($_POST['update_key'])) {
            update_option('plugin_update_key', sanitize_text_field($_POST['update_key']));
        }

        if (isset($_POST['update_webhook'])) {
            update_option('plugin_update_webhook', sanitize_text_field($_POST['update_webhook']));
        }

        if (isset($_POST['update_op_switcher'])) {
            // שמירת checkbox: 1 אם מסומן, 0 אם לא מסומן
            $update_op_switcher = isset($_POST['update_op_switcher']) && $_POST['update_op_switcher'] == '1' ? '1' : '0';
            update_option('plugin_update_op_switcher', $update_op_switcher);
        }

        if (isset($_POST['logs_op_switcher'])) {
            // שמירת checkbox: 1 אם מסומן, 0 אם לא מסומן
            $update_op_switcher = isset($_POST['logs_op_switcher']) && $_POST['logs_op_switcher'] == '1' ? '1' : '0';
            update_option('plugin_logs_op_switcher', $update_op_switcher);
        }

        // חזרה לדף ההגדרות עם הודעה
        $plugin_dir = $this->plugin_dir;
        wp_redirect(admin_url("plugins.php?page=$plugin_dir&settings-updated=true"));
        exit;
    }

}

function find_main_plugin_file() {
    if (!function_exists('get_plugins')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    // מקבל את רשימת כל התוספים
    $plugins = get_plugins();
    $plugin_dir = basename(dirname(__FILE__, 2)); // קבלת שם תיקיית התוסף

    foreach ($plugins as $plugin_file => $plugin_data) {
        // בדיקה אם קובץ התוסף נמצא בתיקייה הראשית של התוסף (ולא בסאב-תיקייה)
        if (strpos($plugin_file, $plugin_dir . '/') === 0) {
            return WP_PLUGIN_DIR . '/' . $plugin_file;
        }
    }

    return __FILE__; // fallback למקרה שלא נמצא
}

new PluginUpdateManager(find_main_plugin_file());
}


