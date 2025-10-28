<?php
defined( 'ABSPATH' ) || exit;

if( ! class_exists( 'PushUpdateToServer' ) ) {

    class PushUpdateToServer {
        private $plugin_file;
        private $plugin_dir;
        private $zip_file;
        private $update_webhook;

        public function __construct() {
            add_action('admin_post_plugin_update_action', [$this, 'pushPluginToServer']);
        }

        public function setPluginData($plugin_file, $update_webhook) {
            $this->plugin_file = $plugin_file;
            $this->plugin_dir = WP_PLUGIN_DIR . '/' . dirname($plugin_file);
            $this->zip_file = WP_PLUGIN_DIR . '/' . dirname($plugin_file) . '.zip';
            $this->update_webhook = $update_webhook;
        }

        public function updatePluginVersion($new_version) {
            $plugin_path = WP_PLUGIN_DIR . '/' . $this->plugin_file;
            
            if (!file_exists($plugin_path)) {
                return false;
            }

            $plugin_data = file_get_contents($plugin_path);
            $plugin_data = preg_replace('/Version:\s*[0-9.]+/', 'Version: ' . esc_html($new_version), $plugin_data);
            
            return file_put_contents($plugin_path, $plugin_data) !== false;
        }

        public function updatePluginUpdateFile($update_text) {
            $update_text  = $update_text . "\n" . ($_POST['update_text'] ?? '');
            $update_file = plugin_dir_path(__DIR__) . "readme.txt";

            if (!file_exists(dirname($update_file))) {
                mkdir(dirname($update_file), 0755, true);
            }

            if (!file_exists($update_file)) {
                file_put_contents($update_file, "<?php\n// Update Log\n");
            }

            if (!is_writable($update_file)) {
                error_log("❌ קובץ העדכון אינו ניתן לכתיבה: " . $update_file);
                return false;
            }

            return file_put_contents($update_file, "\n// " . esc_html($update_text), FILE_APPEND) !== false;
        }

        public function createPluginZip() {
            $zip = new ZipArchive();
            
            if ($zip->open($this->zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
                return false;
            }

            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->plugin_dir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            );

            $plugin_folder_name = basename($this->plugin_dir); // מקבל את שם התיקייה של הפלאגין

            foreach ($files as $file) {
                if (!$file->isDir()) {
                    $file_path = $file->getRealPath();
                    $relative_path = substr($file_path, strlen($this->plugin_dir) + 1);
                    $zip->addFile($file_path, $plugin_folder_name . '/' . $relative_path);
                }
            }

            return $zip->close() && file_exists($this->zip_file);
        }

        public function pushPluginToServer($update_text) {
            if (!isset($_POST['plugin_update_nonce']) || !wp_verify_nonce($_POST['plugin_update_nonce'], 'plugin_update_action')) {
                wp_die('אימות נכשל');
            }

            $selected_plugin = sanitize_text_field($_POST['selected_plugin'] ?? get_option('selected_plugin'));
            $update_webhook  = sanitize_text_field($_POST['update_webhook'] ?? get_option('plugin_update_webhook'));
            $update_version  = sanitize_text_field($_POST['update_version'] ?? '');

            if (!$selected_plugin) {
                wp_die('לא נבחר תוסף');
            }

            $this->setPluginData($selected_plugin, $update_webhook);

            if ($update_version && !$this->updatePluginVersion($update_version)) {
                wp_die('⚠ שגיאה: עדכון גרסת התוסף נכשל!');
            }
            
            if (!$this->updatePluginUpdateFile("עדכון גרסה ל- " . $update_version)) {
                wp_die('⚠ שגיאה: לא ניתן לעדכן את קובץ ה-update.txt');
            }

            if (!$this->createPluginZip()) {
                wp_die('⚠ שגיאה: לא ניתן היה ליצור קובץ ZIP');
            }

            if (!file_exists($this->zip_file)) {
                wp_die('⚠ שגיאה: קובץ ה-ZIP לא נוצר!');
            }

            $update_text  = $update_text . "\n" . ($_POST['update_text'] ?? '');

            $args = [
                'method'  => 'POST',
                'timeout' => 60,
                'body'    => json_encode([
                    'file'    => base64_encode(file_get_contents($this->zip_file)),
                    'filename' => basename($this->zip_file),
                    'plugin'  => basename($this->plugin_dir),
                    'version' => $update_version,
                    'comment' => $update_text
                ]),
                'headers' => ['Content-Type' => 'application/json']
            ];

            $response = wp_remote_post($update_webhook, $args);

            if (is_wp_error($response)) {
                wp_die('⚠ שגיאה בשליחת ZIP: ' . $response->get_error_message());
            } else {
                echo "✅ עדכון הושלם בהצלחה! תגובת הוובהוק: ", wp_remote_retrieve_body($response);
                exit;
            }
        }
    }

    new PushUpdateToServer();

}

