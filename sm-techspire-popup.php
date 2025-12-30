<?php
/**
 * Plugin Name: SM TechSpire - Add to Cart & Buy Now Popup
 * Plugin URI: https://smtechspire.com
 * Description: Professional WooCommerce popup for Add to Cart and Buy Now with variation selection. Fully customizable with license activation system.
 * Version: 1.0.0
 * Author: SM TechSpire – IT
 * Author URI: https://smtechspire.com
 * Text Domain: sm-popup
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * WC requires at least: 3.0
 * WC tested up to: 8.0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'SM_POPUP_VERSION', '1.0.0' );
define( 'SM_POPUP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SM_POPUP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Main Plugin Class
 */
class SM_TechSpire_Popup {
    
    private static $instance = null;
    
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action( 'plugins_loaded', array( $this, 'init' ) );
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
    }
    
    public function init() {
        // Check if WooCommerce is active
        if ( ! class_exists( 'WooCommerce' ) ) {
            add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
            return;
        }
        
        // Check license activation
        if ( ! $this->is_license_valid() ) {
            add_action( 'admin_notices', array( $this, 'license_notice' ) );
            // Still allow admin to access settings
            if ( is_admin() ) {
                add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
                add_action( 'admin_init', array( $this, 'register_settings' ) );
            }
            return;
        }
        
        // Load plugin functionality
        $this->load_hooks();
    }
    
    private function load_hooks() {
        // Admin hooks
        if ( is_admin() ) {
            add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
            add_action( 'admin_init', array( $this, 'register_settings' ) );
            add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
        }
        
        // Frontend hooks
        remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );
        add_action( 'woocommerce_after_shop_loop_item', array( $this, 'custom_dual_buttons' ), 10 );
        add_action( 'wp_footer', array( $this, 'popup_html' ) );
        add_action( 'wp_footer', array( $this, 'popup_script' ) );
        add_action( 'wp_head', array( $this, 'custom_styles' ) );
        
        // AJAX hooks
        add_action( 'wp_ajax_get_product_variations', array( $this, 'get_variations_ajax' ) );
        add_action( 'wp_ajax_nopriv_get_product_variations', array( $this, 'get_variations_ajax' ) );
        add_action( 'wp_ajax_add_variation_to_cart', array( $this, 'add_to_cart_ajax' ) );
        add_action( 'wp_ajax_nopriv_add_variation_to_cart', array( $this, 'add_to_cart_ajax' ) );
        add_action( 'wp_ajax_get_cart_count', array( $this, 'get_cart_count_ajax' ) );
        add_action( 'wp_ajax_nopriv_get_cart_count', array( $this, 'get_cart_count_ajax' ) );
        add_action( 'wp_ajax_get_cart_fragments', array( $this, 'get_cart_fragments_ajax' ) );
        add_action( 'wp_ajax_nopriv_get_cart_fragments', array( $this, 'get_cart_fragments_ajax' ) );
        
        // Hide WooCommerce messages
        add_filter( 'wc_add_to_cart_message_html', '__return_false' );
        add_action( 'wp_head', array( $this, 'hide_wc_messages' ) );
    }
    
    // License validation
    private function is_license_valid() {
        $license_key = get_option( 'sm_popup_license_key', '' );
        $valid_keys = array(
            'SM-TECH-2024-PRO-12345',
            'SM-TECH-2024-PRO-67890',
            'SM-TECH-2024-PRO-DEMO1'
        );
        return in_array( $license_key, $valid_keys );
    }
    
    public function woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p><strong>SM TechSpire Popup:</strong> This plugin requires WooCommerce to be installed and activated.</p>
        </div>
        <?php
    }
    
    public function license_notice() {
        if ( get_current_screen()->id === 'toplevel_page_sm-popup-settings' ) {
            return; // Don't show on settings page
        }
        ?>
        <div class="notice notice-warning">
            <p><strong>SM TechSpire Popup:</strong> Please activate your license key in <a href="<?php echo admin_url( 'admin.php?page=sm-popup-settings' ); ?>">Settings</a> to use this plugin.</p>
        </div>
        <?php
    }
    
    // Admin menu
    public function add_admin_menu() {
        add_menu_page(
            'SM Popup Settings',
            'SM Popup',
            'manage_options',
            'sm-popup-settings',
            array( $this, 'settings_page' ),
            'dashicons-cart',
            56
        );
    }
    
    // Register settings
    public function register_settings() {
        // License settings
        register_setting( 'sm_popup_license', 'sm_popup_license_key', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field'
        ) );
        
        // Button settings
        register_setting( 'sm_popup_buttons', 'sm_popup_buy_now_text', array(
            'type' => 'string',
            'default' => 'Buy Now',
            'sanitize_callback' => 'sanitize_text_field'
        ) );
        
        register_setting( 'sm_popup_buttons', 'sm_popup_add_cart_text', array(
            'type' => 'string',
            'default' => 'কার্টে যোগ করুন',
            'sanitize_callback' => 'sanitize_text_field'
        ) );
        
        register_setting( 'sm_popup_buttons', 'sm_popup_buy_now_color', array(
            'type' => 'string',
            'default' => '#FF6B35',
            'sanitize_callback' => 'sanitize_hex_color'
        ) );
        
        register_setting( 'sm_popup_buttons', 'sm_popup_add_cart_color', array(
            'type' => 'string',
            'default' => '#28a745',
            'sanitize_callback' => 'sanitize_hex_color'
        ) );
        
        register_setting( 'sm_popup_buttons', 'sm_popup_button_height', array(
            'type' => 'integer',
            'default' => 45,
            'sanitize_callback' => 'absint'
        ) );
        
        register_setting( 'sm_popup_buttons', 'sm_popup_button_font_size', array(
            'type' => 'integer',
            'default' => 15,
            'sanitize_callback' => 'absint'
        ) );
        
        register_setting( 'sm_popup_buttons', 'sm_popup_button_radius', array(
            'type' => 'integer',
            'default' => 5,
            'sanitize_callback' => 'absint'
        ) );
        
        register_setting( 'sm_popup_buttons', 'sm_popup_buy_now_icon', array(
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field'
        ) );
        
        register_setting( 'sm_popup_buttons', 'sm_popup_add_cart_icon', array(
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field'
        ) );
        
        register_setting( 'sm_popup_buttons', 'sm_popup_icon_position', array(
            'type' => 'string',
            'default' => 'left',
            'sanitize_callback' => 'sanitize_text_field'
        ) );
    }
    
    // Admin scripts
    public function admin_scripts( $hook ) {
        if ( 'toplevel_page_sm-popup-settings' !== $hook ) {
            return;
        }
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'wp-color-picker' );
    }
    
    // Settings page HTML
    public function settings_page() {
        ?>
        <div class="wrap">
            <h1><span class="dashicons dashicons-cart" style="font-size: 30px; margin-right: 10px;"></span>SM TechSpire - Popup Settings</h1>
            
            <?php
            if ( isset( $_GET['settings-updated'] ) ) {
                ?>
                <div class="notice notice-success is-dismissible">
                    <p><strong>Settings saved successfully!</strong></p>
                </div>
                <?php
            }
            
            $active_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'license';
            ?>
            
            <h2 class="nav-tab-wrapper">
                <a href="?page=sm-popup-settings&tab=license" class="nav-tab <?php echo $active_tab === 'license' ? 'nav-tab-active' : ''; ?>">License</a>
                <a href="?page=sm-popup-settings&tab=buttons" class="nav-tab <?php echo $active_tab === 'buttons' ? 'nav-tab-active' : ''; ?>">Button Settings</a>
                <a href="?page=sm-popup-settings&tab=about" class="nav-tab <?php echo $active_tab === 'about' ? 'nav-tab-active' : ''; ?>">About</a>
            </h2>
            
            <?php if ( $active_tab === 'license' ) : ?>
                <form method="post" action="options.php">
                    <?php settings_fields( 'sm_popup_license' ); ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row">License Status</th>
                            <td>
                                <?php if ( $this->is_license_valid() ) : ?>
                                    <span style="color: green; font-weight: bold;">✓ Activated</span>
                                <?php else : ?>
                                    <span style="color: red; font-weight: bold;">✗ Not Activated</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="sm_popup_license_key">License Key</label></th>
                            <td>
                                <input type="text" id="sm_popup_license_key" name="sm_popup_license_key" 
                                       value="<?php echo esc_attr( get_option( 'sm_popup_license_key' ) ); ?>" 
                                       class="regular-text" placeholder="Enter your license key">
                                <p class="description">Enter the license key provided by SM TechSpire.</p>
                                <p class="description"><strong>Demo Keys:</strong> SM-TECH-2024-PRO-12345, SM-TECH-2024-PRO-67890</p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button( 'Save License' ); ?>
                </form>
                
            <?php elseif ( $active_tab === 'buttons' ) : ?>
                <?php if ( ! $this->is_license_valid() ) : ?>
                    <div class="notice notice-warning">
                        <p><strong>Please activate your license first to customize button settings.</strong></p>
                    </div>
                <?php else : ?>
                    <form method="post" action="options.php">
                        <?php settings_fields( 'sm_popup_buttons' ); ?>
                        
                        <h3>Button Text</h3>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="sm_popup_buy_now_text">Buy Now Button Text</label></th>
                                <td>
                                    <input type="text" id="sm_popup_buy_now_text" name="sm_popup_buy_now_text" 
                                           value="<?php echo esc_attr( get_option( 'sm_popup_buy_now_text', 'Buy Now' ) ); ?>" 
                                           class="regular-text">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="sm_popup_add_cart_text">Add to Cart Button Text</label></th>
                                <td>
                                    <input type="text" id="sm_popup_add_cart_text" name="sm_popup_add_cart_text" 
                                           value="<?php echo esc_attr( get_option( 'sm_popup_add_cart_text', 'কার্টে যোগ করুন' ) ); ?>" 
                                           class="regular-text">
                                </td>
                            </tr>
                        </table>
                        
                        <h3>Button Colors</h3>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="sm_popup_buy_now_color">Buy Now Button Color</label></th>
                                <td>
                                    <input type="text" id="sm_popup_buy_now_color" name="sm_popup_buy_now_color" 
                                           value="<?php echo esc_attr( get_option( 'sm_popup_buy_now_color', '#FF6B35' ) ); ?>" 
                                           class="color-picker">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="sm_popup_add_cart_color">Add to Cart Button Color</label></th>
                                <td>
                                    <input type="text" id="sm_popup_add_cart_color" name="sm_popup_add_cart_color" 
                                           value="<?php echo esc_attr( get_option( 'sm_popup_add_cart_color', '#28a745' ) ); ?>" 
                                           class="color-picker">
                                </td>
                            </tr>
                        </table>
                        
                        <h3>Button Dimensions</h3>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="sm_popup_button_height">Button Height (px)</label></th>
                                <td>
                                    <input type="number" id="sm_popup_button_height" name="sm_popup_button_height" 
                                           value="<?php echo esc_attr( get_option( 'sm_popup_button_height', 45 ) ); ?>" 
                                           min="5" max="100" class="small-text"> px
                                    <p class="description">Minimum: 5px, Maximum: 100px</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="sm_popup_button_font_size">Font Size (px)</label></th>
                                <td>
                                    <input type="number" id="sm_popup_button_font_size" name="sm_popup_button_font_size" 
                                           value="<?php echo esc_attr( get_option( 'sm_popup_button_font_size', 15 ) ); ?>" 
                                           min="8" max="30" class="small-text"> px
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="sm_popup_button_radius">Border Radius (px)</label></th>
                                <td>
                                    <input type="number" id="sm_popup_button_radius" name="sm_popup_button_radius" 
                                           value="<?php echo esc_attr( get_option( 'sm_popup_button_radius', 5 ) ); ?>" 
                                           min="0" max="50" class="small-text"> px
                                </td>
                            </tr>
                        </table>
                        
                        <h3>Button Icons (Optional) - ✨ Use Icons or Leave Empty</h3>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="sm_popup_buy_now_icon">Buy Now Button Icon</label></th>
                                <td>
                                    <input type="text" id="sm_popup_buy_now_icon" name="sm_popup_buy_now_icon" 
                                           value="<?php echo esc_attr( get_option( 'sm_popup_buy_now_icon', '' ) ); ?>" 
                                           class="regular-text" placeholder="Leave empty for no icon">
                                    <p class="description">
                                        <strong>Optional:</strong> Add emoji or icon (e.g., 🛒, 🛍️, ⚡, 🔥) 
                                        <br><strong>Leave empty if you don't want any icon.</strong>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="sm_popup_add_cart_icon">Add to Cart Button Icon</label></th>
                                <td>
                                    <input type="text" id="sm_popup_add_cart_icon" name="sm_popup_add_cart_icon" 
                                           value="<?php echo esc_attr( get_option( 'sm_popup_add_cart_icon', '' ) ); ?>" 
                                           class="regular-text" placeholder="Leave empty for no icon">
                                    <p class="description">
                                        <strong>Optional:</strong> Add emoji or icon (e.g., 🛒, 🛍️, ➕, ✓) 
                                        <br><strong>Leave empty if you don't want any icon.</strong>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="sm_popup_icon_position">Icon Position</label></th>
                                <td>
                                    <select id="sm_popup_icon_position" name="sm_popup_icon_position">
                                        <option value="left" <?php selected( get_option( 'sm_popup_icon_position', 'left' ), 'left' ); ?>>Left of Text</option>
                                        <option value="right" <?php selected( get_option( 'sm_popup_icon_position', 'left' ), 'right' ); ?>>Right of Text</option>
                                    </select>
                                    <p class="description">Only works if you add icons above</p>
                                </td>
                            </tr>
                        </table>
                        
                        <div style="background: #f0f0f1; padding: 15px; border-left: 4px solid #2271b1; margin: 20px 0;">
                            <h4 style="margin-top: 0;">💡 Icon Examples (Copy & Paste):</h4>
                            <p><strong>Shopping:</strong> 🛒 🛍️ 🏪 💳 💰 🎁</p>
                            <p><strong>Action:</strong> ⚡ 🔥 ✓ ➕ ▶️ ✨</p>
                            <p><strong>Arrow:</strong> → ➜ ➤ ⇒ ⮕ ▸</p>
                            <p style="margin-bottom: 0; color: #d63638;">
                                <strong>⚠️ Note:</strong> Icons are completely optional. 
                                <strong>If you don't want icons, just leave the fields empty!</strong>
                            </p>
                        </div>
                        
                        <?php submit_button( 'Save Button Settings' ); ?>
                    </form>
                    
                    <script>
                    jQuery(document).ready(function($) {
                        $('.color-picker').wpColorPicker();
                    });
                    </script>
                <?php endif; ?>
                
            <?php elseif ( $active_tab === 'about' ) : ?>
                <div style="max-width: 800px;">
                    <h2>About SM TechSpire - Add to Cart & Buy Now Popup</h2>
                    <p><strong>Version:</strong> <?php echo SM_POPUP_VERSION; ?></p>
                    <p><strong>Developer:</strong> SM TechSpire – IT</p>
                    
                    <h3>Features</h3>
                    <ul style="list-style: disc; margin-left: 20px;">
                        <li>✅ Professional popup for variable and simple products</li>
                        <li>✅ Buy Now button (direct checkout)</li>
                        <li>✅ Add to Cart with popup variation selection</li>
                        <li>✅ Real-time cart count update</li>
                        <li>✅ Fully customizable button text, colors, and dimensions</li>
                        <li>✅ Mobile responsive design</li>
                        <li>✅ License activation system</li>
                        <li>✅ Stock status indicator</li>
                        <li>✅ Quantity selector</li>
                        <li>✅ Bengali language support</li>
                    </ul>
                    
                    <h3>Support</h3>
                    <p>For support and custom development, contact: <strong>SM TechSpire – IT</strong></p>
                    
                    <h3>License Keys (Demo)</h3>
                    <ul style="list-style: none; background: #f5f5f5; padding: 15px; border-radius: 5px;">
                        <li>🔑 SM-TECH-2024-PRO-12345</li>
                        <li>🔑 SM-TECH-2024-PRO-67890</li>
                        <li>🔑 SM-TECH-2024-PRO-DEMO1</li>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    // Custom dual buttons
    public function custom_dual_buttons() {
        global $product;
        
        $product_id = $product->get_id();
        $product_type = $product->get_type();
        
        $buy_text = get_option( 'sm_popup_buy_now_text', 'Buy Now' );
        $cart_text = get_option( 'sm_popup_add_cart_text', 'কার্টে যোগ করুন' );
        $buy_color = get_option( 'sm_popup_buy_now_color', '#FF6B35' );
        $cart_color = get_option( 'sm_popup_add_cart_color', '#28a745' );
        $height = get_option( 'sm_popup_button_height', 45 );
        $font_size = get_option( 'sm_popup_button_font_size', 15 );
        $radius = get_option( 'sm_popup_button_radius', 5 );
        $buy_icon = trim( get_option( 'sm_popup_buy_now_icon', '' ) );
        $cart_icon = trim( get_option( 'sm_popup_add_cart_icon', '' ) );
        $icon_position = get_option( 'sm_popup_icon_position', 'left' );
        
        // Prepare button text with icons (only if icons are set)
        $buy_display = ! empty( $buy_icon ) ? 
            ( $icon_position === 'left' ? $buy_icon . ' ' . $buy_text : $buy_text . ' ' . $buy_icon ) : 
            $buy_text;
        $cart_display = ! empty( $cart_icon ) ? 
            ( $icon_position === 'left' ? $cart_icon . ' ' . $cart_text : $cart_text . ' ' . $cart_icon ) : 
            $cart_text;
        
        $button_style = "padding: {$height}px 20px; font-size: {$font_size}px; border-radius: {$radius}px; border: none; cursor: pointer; font-weight: bold; color: white; text-align: center; flex: 1; display: flex; align-items: center; justify-content: center; gap: 5px;";
        
        echo '<div class="sm-custom-button-wrapper" style="display: flex; gap: 10px; margin-top: 10px;">';
        
        if ( $product_type == 'variable' ) {
            echo '<button class="button sm-open-buy-now-popup" data-product-id="' . $product_id . '" style="' . $button_style . ' background: ' . $buy_color . ';">' . wp_kses_post( $buy_display ) . '</button>';
            echo '<button class="button sm-open-add-to-cart-popup" data-product-id="' . $product_id . '" style="' . $button_style . ' background: ' . $cart_color . ';">' . wp_kses_post( $cart_display ) . '</button>';
        } else {
            echo '<a href="' . wc_get_checkout_url() . '?add-to-cart=' . $product_id . '" class="button" style="' . $button_style . ' background: ' . $buy_color . '; text-decoration: none;">' . wp_kses_post( $buy_display ) . '</a>';
            echo '<a href="?add-to-cart=' . $product_id . '" data-quantity="1" class="button add_to_cart_button ajax_add_to_cart" data-product_id="' . $product_id . '" style="' . $button_style . ' background: ' . $cart_color . '; text-decoration: none;">' . wp_kses_post( $cart_display ) . '</a>';
        }
        
        echo '</div>';
    }
    
    // Popup HTML
    public function popup_html() {
        $cart_text = get_option( 'sm_popup_add_cart_text', 'কার্টে যোগ করুন' );
        ?>
        <div id="sm-variation-popup-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 9999; justify-content: center; align-items: center;">
            <div id="sm-variation-popup" style="background: white; padding: 30px; border-radius: 10px; max-width: 500px; width: 90%; position: relative; box-shadow: 0 5px 30px rgba(0,0,0,0.3); max-height: 90vh; overflow-y: auto;">
                <button id="sm-close-popup" style="position: absolute; top: 10px; right: 10px; background: #ff4444; color: white; border: none; width: 35px; height: 35px; border-radius: 50%; cursor: pointer; font-size: 24px; line-height: 1; font-weight: bold;">×</button>
                
                <h3 id="sm-popup-product-title" style="margin-top: 0; margin-bottom: 20px; color: #333; font-size: 20px;">Product Name</h3>
                
                <div id="sm-popup-variations-container" style="margin-bottom: 20px;"></div>
                
                <div style="display: flex; gap: 10px; align-items: center; margin-bottom: 20px;">
                    <label style="font-weight: bold; font-size: 16px;">পরিমাণ:</label>
                    <input type="number" id="sm-popup-quantity" value="1" min="1" style="width: 80px; padding: 10px; border: 2px solid #ddd; border-radius: 5px; font-size: 16px;">
                </div>
                
                <button id="sm-popup-action-button" style="background: #28a745; color: white; width: 100%; padding: 15px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; font-size: 18px;"><?php echo esc_html( $cart_text ); ?></button>
                
                <div id="sm-popup-message" style="margin-top: 15px; padding: 12px; border-radius: 5px; display: none; text-align: center; font-weight: bold;"></div>
            </div>
        </div>
        <?php
    }
    
    // Popup JavaScript
    public function popup_script() {
        $buy_color = get_option( 'sm_popup_buy_now_color', '#FF6B35' );
        $cart_color = get_option( 'sm_popup_add_cart_color', '#28a745' );
        $buy_text = get_option( 'sm_popup_buy_now_text', 'Buy Now' );
        $cart_text = get_option( 'sm_popup_add_cart_text', 'কার্টে যোগ করুন' );
        ?>
        <script>
        jQuery(document).ready(function($) {
            let currentProductId = 0;
            let variations = {};
            let actionType = 'cart';
            
            function updateCartCount() {
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: { action: 'get_cart_fragments' },
                    success: function(response) {
                        if (response && response.fragments) {
                            $.each(response.fragments, function(key, value) {
                                $(key).replaceWith(value);
                            });
                            $.ajax({
                                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                                type: 'POST',
                                data: { action: 'get_cart_count' },
                                success: function(res) {
                                    if (res.success) {
                                        $('.cart-contents-count, .cart-count, .header-cart-count, .count').text(res.data.count);
                                        if (res.data.count > 0) {
                                            $('.cart-contents-count').css('display', 'inline-block');
                                        }
                                    }
                                }
                            });
                            $(document.body).trigger('wc_fragments_refreshed');
                            $(document.body).trigger('wc_fragment_refresh');
                        }
                    }
                });
            }
            
            $('.sm-open-add-to-cart-popup').on('click', function(e) {
                e.preventDefault();
                actionType = 'cart';
                currentProductId = $(this).data('product-id');
                loadProductVariations('<?php echo esc_js( $cart_text ); ?>', '<?php echo esc_js( $cart_color ); ?>');
            });
            
            $('.sm-open-buy-now-popup').on('click', function(e) {
                e.preventDefault();
                actionType = 'buy';
                currentProductId = $(this).data('product-id');
                loadProductVariations('<?php echo esc_js( $buy_text ); ?>', '<?php echo esc_js( $buy_color ); ?>');
            });
            
            function loadProductVariations(buttonText, buttonColor) {
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: { action: 'get_product_variations', product_id: currentProductId },
                    success: function(response) {
                        if (response.success) {
                            $('#sm-popup-product-title').text(response.data.title);
                            variations = response.data.variations;
                            $('#sm-popup-action-button').text(buttonText).css('background', buttonColor);
                            
                            let html = '<div style="margin-bottom: 15px;"><label style="font-weight: bold; display: block; margin-bottom: 12px; font-size: 16px;">সাইজ নির্বাচন করুন:</label><div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px;">';
                            
                            $.each(variations, function(index, variation) {
                                let inStock = variation.is_in_stock;
                                let disabled = !inStock ? 'disabled' : '';
                                let style = inStock ? 'background: white; border: 2px solid <?php echo esc_js( $cart_color ); ?>; color: #333;' : 'background: #f5f5f5; border: 2px solid #ddd; color: #999; cursor: not-allowed;';
                                
                                html += '<button class="sm-variation-option" data-variation-id="' + variation.variation_id + '" ' + disabled + ' style="' + style + ' padding: 14px 10px; border-radius: 5px; cursor: pointer; font-weight: bold; transition: all 0.3s; font-size: 15px;">';
                                html += variation.attributes;
                                if (!inStock) html += '<br><small style="font-size: 11px;">(স্টক নেই)</small>';
                                html += '</button>';
                            });
                            
                            html += '</div></div>';
                            $('#sm-popup-variations-container').html(html);
                            $('#sm-variation-popup-overlay').css('display', 'flex');
                            $('#sm-popup-message').hide();
                        }
                    }
                });
            }
            
            $('#sm-close-popup, #sm-variation-popup-overlay').on('click', function(e) {
                if (e.target === this) {
                    $('#sm-variation-popup-overlay').hide();
                    $('#sm-popup-message').hide();
                }
            });
            
            $(document).on('click', '.sm-variation-option:not([disabled])', function() {
                $('.sm-variation-option').css({'background': 'white', 'border-color': '<?php echo esc_js( $cart_color ); ?>', 'color': '#333'});
                $(this).css({'background': '<?php echo esc_js( $cart_color ); ?>', 'color': 'white', 'border-color': '<?php echo esc_js( $cart_color ); ?>'});
            });
            
            $('#sm-popup-action-button').on('click', function() {
                let selectedVariation = $('.sm-variation-option').filter(function() {
                    return $(this).css('background-color') === 'rgb(40, 167, 69)' || $(this).css('background').includes('rgb(40, 167, 69)');
                }).data('variation-id');
                
                let quantity = $('#sm-popup-quantity').val();
                
                if (!selectedVariation) {
                    $('#sm-popup-message').css({'background': '#ff4444', 'color': 'white', 'display': 'block'}).text('⚠ অনুগ্রহ করে একটি সাইজ নির্বাচন করুন!');
                    return;
                }
                
                let originalText = $(this).text();
                $(this).text('অপেক্ষা করুন...').prop('disabled', true);
                
                if (actionType === 'cart') {
                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: { action: 'add_variation_to_cart', product_id: currentProductId, variation_id: selectedVariation, quantity: quantity },
                        success: function(response) {
                            if (response.success) {
                                $('#sm-popup-action-button').text(originalText).prop('disabled', false);
                                $('#sm-popup-message').css({'background': '#28a745', 'color': 'white', 'display': 'block'}).html('✓ সফলভাবে কার্টে যোগ হয়েছে!<br><small style="font-size: 13px;">পেজ রিফ্রেশ হচ্ছে...</small>');
                                setTimeout(function() { window.location.reload(); }, 1000);
                            } else {
                                $('#sm-popup-action-button').text(originalText).prop('disabled', false);
                                $('#sm-popup-message').css({'background': '#ff4444', 'color': 'white', 'display': 'block'}).text('⚠ কিছু সমস্যা হয়েছে!');
                            }
                        }
                    });
                } else {
                    let form = $('<form>', { 'method': 'POST', 'action': '<?php echo wc_get_checkout_url(); ?>' });
                    form.append($('<input>', {'type': 'hidden', 'name': 'add-to-cart', 'value': currentProductId}));
                    form.append($('<input>', {'type': 'hidden', 'name': 'variation_id', 'value': selectedVariation}));
                    form.append($('<input>', {'type': 'hidden', 'name': 'quantity', 'value': quantity}));
                    $('body').append(form);
                    form.submit();
                }
            });
        });
        </script>
        <?php
    }
    
    // Custom styles
    public function custom_styles() {
        ?>
        <style>
            .sm-variation-option:hover:not([disabled]) {
                transform: scale(1.05);
                box-shadow: 0 3px 10px rgba(40, 167, 69, 0.3);
            }
            #sm-popup-action-button:hover:not([disabled]) {
                opacity: 0.9;
                transform: translateY(-2px);
            }
            #sm-popup-action-button:disabled {
                opacity: 0.6;
                cursor: wait;
            }
            @media (max-width: 768px) {
                .sm-custom-button-wrapper {
                    flex-direction: column !important;
                    gap: 8px !important;
                }
                .sm-custom-button-wrapper .button,
                .sm-custom-button-wrapper a {
                    width: 100% !important;
                    flex: none !important;
                }
                #sm-variation-popup {
                    padding: 20px !important;
                    width: 95% !important;
                }
                .sm-variation-option {
                    font-size: 13px !important;
                    padding: 10px 8px !important;
                }
                #sm-popup-product-title {
                    font-size: 18px !important;
                }
                #sm-popup-action-button {
                    padding: 12px !important;
                    font-size: 16px !important;
                }
            }
        </style>
        <?php
    }
    
    public function hide_wc_messages() {
        ?>
        <style>
            .woocommerce-message, .woocommerce-info, div.woocommerce > .woocommerce-message, .woocommerce-notices-wrapper {
                display: none !important;
            }
        </style>
        <?php
    }
    
    // AJAX Handlers
    public function get_variations_ajax() {
        $product_id = intval( $_POST['product_id'] );
        $product = wc_get_product( $product_id );
        
        if ( ! $product || $product->get_type() !== 'variable' ) {
            wp_send_json_error();
        }
        
        $variations_data = array();
        $available_variations = $product->get_available_variations();
        
        foreach ( $available_variations as $variation ) {
            $variation_obj = wc_get_product( $variation['variation_id'] );
            $attributes = array();
            
            foreach ( $variation['attributes'] as $key => $value ) {
                $attributes[] = $value;
            }
            
            $variations_data[] = array(
                'variation_id' => $variation['variation_id'],
                'attributes' => implode( ' - ', $attributes ),
                'price' => $variation_obj->get_price_html(),
                'is_in_stock' => $variation['is_in_stock']
            );
        }
        
        wp_send_json_success( array(
            'title' => $product->get_name(),
            'variations' => $variations_data
        ) );
    }
    
    public function add_to_cart_ajax() {
        $product_id = intval( $_POST['product_id'] );
        $variation_id = intval( $_POST['variation_id'] );
        $quantity = intval( $_POST['quantity'] );
        
        $added = WC()->cart->add_to_cart( $product_id, $quantity, $variation_id );
        
        if ( $added ) {
            wp_send_json_success( array(
                'message' => 'Product added to cart',
                'cart_count' => WC()->cart->get_cart_contents_count()
            ) );
        } else {
            wp_send_json_error( array(
                'message' => 'Failed to add product to cart'
            ) );
        }
    }
    
    public function get_cart_count_ajax() {
        wp_send_json_success( array(
            'count' => WC()->cart->get_cart_contents_count()
        ) );
    }
    
    public function get_cart_fragments_ajax() {
        WC_AJAX::get_refreshed_fragments();
    }
    
    // Activation
    public function activate() {
        // Check if WooCommerce is active
        if ( ! class_exists( 'WooCommerce' ) ) {
            deactivate_plugins( plugin_basename( __FILE__ ) );
            wp_die( 'This plugin requires WooCommerce to be installed and activated. <a href="' . admin_url( 'plugins.php' ) . '">Go back</a>' );
        }
        
        // Set default options
        add_option( 'sm_popup_buy_now_text', 'Buy Now' );
        add_option( 'sm_popup_add_cart_text', 'কার্টে যোগ করুন' );
        add_option( 'sm_popup_buy_now_color', '#FF6B35' );
        add_option( 'sm_popup_add_cart_color', '#28a745' );
        add_option( 'sm_popup_button_height', 45 );
        add_option( 'sm_popup_button_font_size', 15 );
        add_option( 'sm_popup_button_radius', 5 );
        add_option( 'sm_popup_buy_now_icon', '' );
        add_option( 'sm_popup_add_cart_icon', '' );
        add_option( 'sm_popup_icon_position', 'left' );
    }
    
    public function deactivate() {
        // Cleanup if needed
    }
}

// Initialize the plugin
SM_TechSpire_Popup::get_instance();