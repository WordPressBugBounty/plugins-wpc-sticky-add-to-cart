<?php
/*
Plugin Name: WPC Sticky Add To Cart for WooCommerce
Plugin URI: https://wpclever.net/
Description: WPC Sticky Add To Cart brings about a nicer, customer-friendly sticky add-to-cart bar for your site.
Author: WPClever
Author URI: https://wpclever.net
Text Domain: wpc-sticky-add-to-cart
Domain Path: /languages/
Requires Plugins: woocommerce
Version: 2.1.8
Requires at least: 5.9
WC requires at least: 3.0
WC tested up to: 11.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

defined( 'ABSPATH' ) || exit;

! defined( 'WPCSB_VERSION' ) && define( 'WPCSB_VERSION', '2.1.8' );
! defined( 'WPCSB_LITE' ) && define( 'WPCSB_LITE', __FILE__ );
! defined( 'WPCSB_FILE' ) && define( 'WPCSB_FILE', __FILE__ );
! defined( 'WPCSB_URI' ) && define( 'WPCSB_URI', plugin_dir_url( __FILE__ ) );
! defined( 'WPCSB_DIR' ) && define( 'WPCSB_DIR', plugin_dir_path( __FILE__ ) );
! defined( 'WPCSB_REVIEWS' ) && define( 'WPCSB_REVIEWS', 'https://wordpress.org/support/plugin/wpc-sticky-add-to-cart/reviews/' );
! defined( 'WPCSB_CHANGELOG' ) && define( 'WPCSB_CHANGELOG', 'https://wordpress.org/plugins/wpc-sticky-add-to-cart/#developers' );
! defined( 'WPCSB_DISCUSSION' ) && define( 'WPCSB_DISCUSSION', 'https://wordpress.org/support/plugin/wpc-sticky-add-to-cart' );

// WPC Core
require_once __DIR__ . '/includes/wpc-core/wpc-core.php';
wpc_core_register( [
        'file'    => __FILE__,
        'version' => WPCSB_VERSION,
        'prefix'  => 'wpcsb',
] );

if ( ! function_exists( 'wpcsb_init' ) ) {
    add_action( 'plugins_loaded', 'wpcsb_init', 11 );

    function wpcsb_init() {
        if ( ! function_exists( 'WC' ) || ! version_compare( WC()->version, '3.0', '>=' ) ) {
            add_action( 'admin_notices', 'wpcsb_notice_wc' );

            return null;
        }

        if ( ! class_exists( 'WPCleverWpcsb' ) && class_exists( 'WC_Product' ) ) {
            class WPCleverWpcsb {
                protected static $settings = [];
                protected static $localization = [];
                protected static $instance = null;

                public static function instance() {
                    if ( is_null( self::$instance ) ) {
                        self::$instance = new self();
                    }

                    return self::$instance;
                }

                function __construct() {
                    self::$settings     = (array) get_option( 'wpcsb_settings', [] );
                    self::$localization = (array) get_option( 'wpcsb_localization', [] );

                    // init

                    // settings
                    add_action( 'admin_init', [ $this, 'register_settings' ] );
                    add_filter( 'pre_update_option', [ $this, 'last_saved' ], 10, 2 );
                    add_action( 'admin_menu', [ $this, 'admin_menu' ] );

                    // scripts
                    add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
                    add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );

                    // link
                    add_filter( 'plugin_action_links', [ $this, 'action_links' ], 10, 2 );
                    add_filter( 'plugin_row_meta', [ $this, 'row_meta' ], 10, 2 );

                    // add-to-cart form
                    add_action( 'woocommerce_before_add_to_cart_button', [ $this, 'add_to_cart_button' ] );

                    // footer
                    add_action( 'wp_footer', [ $this, 'footer' ] );

                    // WPC Smart Messages
                    add_filter( 'wpcsm_locations', [ $this, 'wpcsm_locations' ] );
                }
                public static function get_settings() {
                    return apply_filters( 'wpcsb_get_settings', self::$settings );
                }

                public static function get_setting( $name, $default = false ) {
                    if ( ! empty( self::$settings ) && isset( self::$settings[ $name ] ) ) {
                        $setting = self::$settings[ $name ];
                    } else {
                        $setting = get_option( 'wpcsb_' . $name, $default );
                    }

                    return apply_filters( 'wpcsb_get_setting', $setting, $name, $default );
                }

                public static function localization( $key = '', $default = '' ) {
                    $str = '';

                    if ( ! empty( $key ) && ! empty( self::$localization[ $key ] ) ) {
                        $str = self::$localization[ $key ];
                    } elseif ( ! empty( $default ) ) {
                        $str = $default;
                    }

                    return apply_filters( 'wpcsb_localization_' . $key, $str );
                }

                public static function sanitize_array( $arr ) {
                    foreach ( (array) $arr as $k => $v ) {
                        if ( is_array( $v ) ) {
                            $arr[ $k ] = self::sanitize_array( $v );
                        } else {
                            $arr[ $k ] = sanitize_post_field( 'post_content', $v, 0, 'db' );
                        }
                    }

                    return $arr;
                }

                function register_settings() {
                    // settings
                    register_setting( 'wpcsb_settings', 'wpcsb_settings', [
                            'type'              => 'array',
                            'sanitize_callback' => [ $this, 'sanitize_array' ],
                    ] );

                    // localization
                    register_setting( 'wpcsb_localization', 'wpcsb_localization', [
                            'type'              => 'array',
                            'sanitize_callback' => [ $this, 'sanitize_array' ],
                    ] );
                }

                function last_saved( $value, $option ) {
                    if ( $option == 'wpcsb_settings' ) {
                        $value['_last_saved']    = current_time( 'timestamp' );
                        $value['_last_saved_by'] = get_current_user_id();
                    }

                    return $value;
                }

                function admin_menu() {
                    add_submenu_page( 'wpclever', esc_html__( 'WPC Sticky Add To Cart', 'wpc-sticky-add-to-cart' ), esc_html__( 'Sticky Add To Cart', 'wpc-sticky-add-to-cart' ), 'manage_options', 'wpclever-wpcsb', [
                            $this,
                            'admin_menu_content'
                    ] );
                }

                function admin_menu_content() {
                    add_thickbox();
                    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                    $active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ?? '' ) ) : 'settings';
                    ?>
                    <div class="wpclever_settings_page wrap">
                        <div class="wpclever_settings_page_header">
                            <a class="wpclever_settings_page_header_logo" href="https://wpclever.net/"
                               target="_blank" title="Visit wpclever.net"></a>
                            <div class="wpclever_settings_page_header_text">
                                <div class="wpclever_settings_page_title"><?php echo esc_html__( 'WPC Sticky Add To Cart', 'wpc-sticky-add-to-cart' ) . ' ' . esc_html( WPCSB_VERSION ); ?></div>
                                <div class="wpclever_settings_page_desc about-text">
                                    <p>
                                        <?php echo wp_kses( sprintf( /* translators: stars */ __( 'Thank you for using our plugin! If you are satisfied, please reward it a full five-star %s rating.', 'wpc-sticky-add-to-cart' ), '<span style="color:#ffb900">&#9733;&#9733;&#9733;&#9733;&#9733;</span>' ), [ 'span' => [ 'style' => [] ] ] ); ?>
                                        <br/>
                                        <a href="<?php echo esc_url( WPCSB_REVIEWS ); ?>"
                                           target="_blank"><?php esc_html_e( 'Reviews', 'wpc-sticky-add-to-cart' ); ?></a>
                                        |
                                        <a href="<?php echo esc_url( WPCSB_CHANGELOG ); ?>"
                                           target="_blank"><?php esc_html_e( 'Changelog', 'wpc-sticky-add-to-cart' ); ?></a>
                                        |
                                        <a href="<?php echo esc_url( WPCSB_DISCUSSION ); ?>"
                                           target="_blank"><?php esc_html_e( 'Discussion', 'wpc-sticky-add-to-cart' ); ?></a>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <h2></h2>
                        <?php
                        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                        if ( isset( $_GET['settings-updated'] ) && 'true' === sanitize_text_field( wp_unslash( $_GET['settings-updated'] ) ) ) { ?>
                            <div class="notice notice-success is-dismissible">
                                <p><?php esc_html_e( 'Settings updated.', 'wpc-sticky-add-to-cart' ); ?></p>
                            </div>
                        <?php } ?>
                        <div class="wpclever_settings_page_nav">
                            <h2 class="nav-tab-wrapper">
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-wpcsb&tab=settings' ) ); ?>"
                                   class="<?php echo esc_attr( $active_tab === 'settings' ? 'nav-tab nav-tab-active' : 'nav-tab' ); ?>">
                                    <?php esc_html_e( 'Settings', 'wpc-sticky-add-to-cart' ); ?>
                                </a>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-wpcsb&tab=localization' ) ); ?>"
                                   class="<?php echo esc_attr( $active_tab === 'localization' ? 'nav-tab nav-tab-active' : 'nav-tab' ); ?>">
                                    <?php esc_html_e( 'Localization', 'wpc-sticky-add-to-cart' ); ?>
                                </a>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpclever-kit' ) ); ?>"
                                   class="nav-tab">
                                    <?php esc_html_e( 'Essential Kit', 'wpc-sticky-add-to-cart' ); ?>
                                </a>
                            </h2>
                        </div>
                        <div class="wpclever_settings_page_content">
                            <?php if ( $active_tab === 'settings' ) {
                                $position           = self::get_setting( 'position', 'bottom' );
                                $offset_top         = self::get_setting( 'offset_top', 0 );
                                $offset_bottom      = self::get_setting( 'offset_bottom', 0 );
                                $show_price         = self::get_setting( 'show_price', 'yes' );
                                $show_quantity      = self::get_setting( 'show_quantity', 'yes' );
                                $variations_form    = self::get_setting( 'variations_form', 'no' );
                                $show_compare       = self::get_setting( 'show_compare', 'yes' );
                                $show_quick_view    = self::get_setting( 'show_quick_view', 'yes' );
                                $show_wishlist      = self::get_setting( 'show_wishlist', 'yes' );
                                $show_buy_now       = self::get_setting( 'show_buy_now', 'yes' );
                                $hide_unpurchasable = self::get_setting( 'hide_unpurchasable', 'no' );
                                $hide_types         = self::get_setting( 'hide_types', [] );
                                ?>
                                <form method="post" action="options.php">
                                    <table class="form-table wpcsb-settings">
                                        <tr class="heading">
                                            <th colspan="2">
                                                <?php esc_html_e( 'General', 'wpc-sticky-add-to-cart' ); ?>
                                            </th>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Position', 'wpc-sticky-add-to-cart' ); ?></th>
                                            <td>
                                                <label> <select name="wpcsb_settings[position]">
                                                        <option value="top" <?php selected( $position, 'top' ); ?>><?php esc_html_e( 'Top', 'wpc-sticky-add-to-cart' ); ?></option>
                                                        <option value="bottom" <?php selected( $position, 'bottom' ); ?>><?php esc_html_e( 'Bottom', 'wpc-sticky-add-to-cart' ); ?></option>
                                                    </select> </label>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Offset top', 'wpc-sticky-add-to-cart' ); ?></th>
                                            <td>
                                                <label>
                                                    <input type="number" min="0" step="1" class="small-text"
                                                           name="wpcsb_settings[offset_top]"
                                                           value="<?php echo esc_attr( $offset_top ); ?>"/>
                                                </label> (px)
                                                <span class="description"><?php esc_html_e( 'Distance from the top to start showing the sticky bar.', 'wpc-sticky-add-to-cart' ); ?></span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Offset bottom', 'wpc-sticky-add-to-cart' ); ?></th>
                                            <td>
                                                <label>
                                                    <input type="number" min="0" step="1" class="small-text"
                                                           name="wpcsb_settings[offset_bottom]"
                                                           value="<?php echo esc_attr( $offset_bottom ); ?>"/>
                                                </label> (px)
                                                <span class="description"><?php esc_html_e( 'Distance from the bottom to hiding the sticky bar.', 'wpc-sticky-add-to-cart' ); ?></span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Show price', 'wpc-sticky-add-to-cart' ); ?></th>
                                            <td>
                                                <label> <select name="wpcsb_settings[show_price]">
                                                        <option value="yes" <?php selected( $show_price, 'yes' ); ?>><?php esc_html_e( 'Separately (default)', 'wpc-sticky-add-to-cart' ); ?></option>
                                                        <option value="on_atc" <?php selected( $show_price, 'on_atc' ); ?>><?php esc_html_e( 'On add-to-cart button', 'wpc-sticky-add-to-cart' ); ?></option>
                                                        <option value="no" <?php selected( $show_price, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-sticky-add-to-cart' ); ?></option>
                                                    </select> </label>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Show quantity', 'wpc-sticky-add-to-cart' ); ?></th>
                                            <td>
                                                <label> <select name="wpcsb_settings[show_quantity]">
                                                        <option value="yes" <?php selected( $show_quantity, 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpc-sticky-add-to-cart' ); ?></option>
                                                        <option value="no" <?php selected( $show_quantity, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-sticky-add-to-cart' ); ?></option>
                                                    </select> </label>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Variations form', 'wpc-sticky-add-to-cart' ); ?></th>
                                            <td>
                                                <label> <select name="wpcsb_settings[variations_form]">
                                                        <option value="yes" <?php selected( $variations_form, 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpc-sticky-add-to-cart' ); ?></option>
                                                        <option value="no" <?php selected( $variations_form, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-sticky-add-to-cart' ); ?></option>
                                                    </select> </label>
                                                <span class="description"><?php esc_html_e( 'Use variations form for variable products.', 'wpc-sticky-add-to-cart' ); ?></span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Show QUICK VIEW button', 'wpc-sticky-add-to-cart' ); ?></th>
                                            <td>
                                                <label> <select name="wpcsb_settings[show_quick_view]">
                                                        <option value="yes" <?php selected( $show_quick_view, 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpc-sticky-add-to-cart' ); ?></option>
                                                        <option value="no" <?php selected( $show_quick_view, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-sticky-add-to-cart' ); ?></option>
                                                    </select> </label> <span class="description">If yes, please install <a
                                                            href="<?php echo esc_url( admin_url( 'plugin-install.php?tab=plugin-information&plugin=woo-smart-quick-view&TB_iframe=true&width=800&height=550' ) ); ?>"
                                                            class="thickbox" title="WPC Smart Quick View">WPC Smart Quick View</a> to make it work.</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Show COMPARE button', 'wpc-sticky-add-to-cart' ); ?></th>
                                            <td>
                                                <label> <select name="wpcsb_settings[show_compare]">
                                                        <option value="yes" <?php selected( $show_compare, 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpc-sticky-add-to-cart' ); ?></option>
                                                        <option value="no" <?php selected( $show_compare, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-sticky-add-to-cart' ); ?></option>
                                                    </select> </label> <span class="description">If yes, please install <a
                                                            href="<?php echo esc_url( admin_url( 'plugin-install.php?tab=plugin-information&plugin=woo-smart-compare&TB_iframe=true&width=800&height=550' ) ); ?>"
                                                            class="thickbox"
                                                            title="WPC Smart Compare">WPC Smart Compare</a> to make it work.</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Show WISHLIST button', 'wpc-sticky-add-to-cart' ); ?></th>
                                            <td>
                                                <label> <select name="wpcsb_settings[show_wishlist]">
                                                        <option value="yes" <?php selected( $show_wishlist, 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpc-sticky-add-to-cart' ); ?></option>
                                                        <option value="no" <?php selected( $show_wishlist, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-sticky-add-to-cart' ); ?></option>
                                                    </select> </label> <span class="description">If yes, please install <a
                                                            href="<?php echo esc_url( admin_url( 'plugin-install.php?tab=plugin-information&plugin=wpc-sticky-add-to-cart&TB_iframe=true&width=800&height=550' ) ); ?>"
                                                            class="thickbox" title="WPC Smart Wishlist">WPC Smart Wishlist</a> to make it work.</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Show BUY NOW button', 'wpc-sticky-add-to-cart' ); ?></th>
                                            <td>
                                                <label> <select name="wpcsb_settings[show_buy_now]">
                                                        <option value="yes" <?php selected( $show_buy_now, 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpc-sticky-add-to-cart' ); ?></option>
                                                        <option value="no" <?php selected( $show_buy_now, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-sticky-add-to-cart' ); ?></option>
                                                    </select> </label> <span class="description">If yes, please install <a
                                                            href="<?php echo esc_url( admin_url( 'plugin-install.php?tab=plugin-information&plugin=wpc-buy-now-button&TB_iframe=true&width=800&height=550' ) ); ?>"
                                                            class="thickbox" title="WPC Buy Now Button">WPC Buy Now Button</a> to make it work.</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Disable for unpurchasable products', 'wpc-sticky-add-to-cart' ); ?></th>
                                            <td>
                                                <label> <select name="wpcsb_settings[hide_unpurchasable]">
                                                        <option value="yes" <?php selected( $hide_unpurchasable, 'yes' ); ?>><?php esc_html_e( 'Yes', 'wpc-sticky-add-to-cart' ); ?></option>
                                                        <option value="no" <?php selected( $hide_unpurchasable, 'no' ); ?>><?php esc_html_e( 'No', 'wpc-sticky-add-to-cart' ); ?></option>
                                                    </select> </label>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th scope="row"><?php esc_html_e( 'Disable for product types', 'wpc-sticky-add-to-cart' ); ?></th>
                                            <td>
                                                <?php
                                                if ( ( $types = wc_get_product_types() ) && ! empty( $types ) ) {
                                                    ?>
                                                    <select class="wpcsb_product_types"
                                                            name="wpcsb_settings[hide_types][]" multiple
                                                            data-placeholder="<?php echo esc_attr__( 'Select product types', 'wpc-sticky-add-to-cart' ); ?>">
                                                        <?php foreach ( $types as $key => $name ) { ?>
                                                            <option value="<?php echo esc_attr( $key ); ?>" <?php selected( in_array( $key, $hide_types, true ) ); ?>><?php echo esc_html( $name ); ?></option>
                                                        <?php } ?>
                                                    </select>
                                                    <?php
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                        <tr class="submit">
                                            <th colspan="2">
                                                <div class="wpclever_submit">
                                                    <?php
                                                    settings_fields( 'wpcsb_settings' );
                                                    submit_button( '', 'primary', 'submit', false );

                                                    if ( function_exists( 'wpc_last_saved' ) ) {
                                                        wpc_last_saved( self::get_settings() );
                                                    }
                                                    ?>
                                                </div>
                                                <a style="display: none;" class="wpclever_export"
                                                   data-key="wpcsb_settings"
                                                   data-name="settings"
                                                   href="#"><?php esc_html_e( 'import / export', 'wpc-sticky-add-to-cart' ); ?></a>
                                            </th>
                                        </tr>
                                    </table>
                                </form>
                            <?php } elseif ( $active_tab === 'localization' ) { ?>
                                <form method="post" action="options.php">
                                    <table class="form-table">
                                        <tr class="heading">
                                            <th scope="row"><?php esc_html_e( 'General', 'wpc-sticky-add-to-cart' ); ?></th>
                                            <td>
                                                <?php esc_html_e( 'Leave blank to use the default text and its equivalent translation in multiple languages.', 'wpc-sticky-add-to-cart' ); ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><?php esc_html_e( 'Add to cart', 'wpc-sticky-add-to-cart' ); ?></th>
                                            <td>
                                                <label>
                                                    <input type="text" name="wpcsb_localization[add_to_cart]"
                                                           value="<?php echo esc_attr( self::localization( 'add_to_cart' ) ); ?>"
                                                           placeholder="<?php esc_attr_e( 'Add to cart', 'wpc-sticky-add-to-cart' ); ?>"/>
                                                </label>
                                            </td>
                                        </tr>
                                        <tr class="submit">
                                            <th colspan="2">
                                                <?php settings_fields( 'wpcsb_localization' ); ?><?php submit_button(); ?>
                                            </th>
                                        </tr>
                                    </table>
                                </form>
                            <?php } ?>
                        </div><!-- /.wpclever_settings_page_content -->
                        <div class="wpclever_settings_page_suggestion">
                            <div class="wpclever_settings_page_suggestion_label">
                                <span class="dashicons dashicons-yes-alt"></span> Suggestion
                            </div>
                            <div class="wpclever_settings_page_suggestion_content">
                                <div>
                                    To display custom engaging real-time messages on any wished positions, please
                                    install
                                    <a href="https://wordpress.org/plugins/wpc-smart-messages/" target="_blank">WPC
                                        Smart Messages</a> plugin. It's free!
                                </div>
                                <div>
                                    Wanna save your precious time working on variations? Try our brand-new free plugin
                                    <a href="https://wordpress.org/plugins/wpc-variation-bulk-editor/" target="_blank">WPC
                                        Variation Bulk Editor</a> and
                                    <a href="https://wordpress.org/plugins/wpc-variation-duplicator/" target="_blank">WPC
                                        Variation Duplicator</a>.
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php
                }

                function enqueue_scripts() {
                    wp_enqueue_style( 'wpcsb-frontend', WPCSB_URI . 'assets/css/frontend.css', [], WPCSB_VERSION );
                    wp_enqueue_script( 'wpcsb-frontend', WPCSB_URI . 'assets/js/frontend.js', [ 'jquery' ], WPCSB_VERSION, true );
                    wp_localize_script( 'wpcsb-frontend', 'wpcsb_vars', [
                                    'offset_top'    => apply_filters( 'wpcsb_offset_top', self::get_setting( 'offset_top', 0 ) ),
                                    'offset_bottom' => apply_filters( 'wpcsb_offset_bottom', self::get_setting( 'offset_bottom', 0 ) ),
                            ]
                    );
                }

                function admin_enqueue_scripts( $hook ) {
                    wp_enqueue_style( 'wpcsb-backend', WPCSB_URI . 'assets/css/backend.css', [ 'woocommerce_admin_styles' ], WPCSB_VERSION );
                    wp_enqueue_script( 'wpcsb-backend', WPCSB_URI . 'assets/js/backend.js', [
                            'jquery',
                            'selectWoo',
                    ], WPCSB_VERSION, true );
                    wp_localize_script( 'wpcsb-backend', 'wpcsb_vars', [
                            'placeholder' => esc_html__( 'Select product types', 'wpc-sticky-add-to-cart' ),
                    ] );
                }

                function action_links( $links, $file ) {
                    static $plugin;

                    if ( ! isset( $plugin ) ) {
                        $plugin = plugin_basename( __FILE__ );
                    }

                    if ( $plugin === $file ) {
                        $settings = '<a href="' . esc_url( admin_url( 'admin.php?page=wpclever-wpcsb&tab=settings' ) ) . '">' . esc_html__( 'Settings', 'wpc-sticky-add-to-cart' ) . '</a>';
                        array_unshift( $links, $settings );
                    }

                    return (array) $links;
                }

                function row_meta( $links, $file ) {
                    static $plugin;

                    if ( ! isset( $plugin ) ) {
                        $plugin = plugin_basename( __FILE__ );
                    }

                    if ( $plugin === $file ) {
                        $row_meta = [
                                'support' => '<a href="' . esc_url( WPCSB_DISCUSSION ) . '" target="_blank">' . esc_html__( 'Community support', 'wpc-sticky-add-to-cart' ) . '</a>',
                        ];

                        return array_merge( $links, $row_meta );
                    }

                    return (array) $links;
                }

                function add_to_cart_button() {
                    global $product;

                    if ( $product ) {
                        ?>
                        <span class="wpcsb-id wpcsb-id-<?php echo esc_attr( $product->get_id() ); ?>"
                              data-product_id="<?php echo esc_attr( $product->get_id() ); ?>"></span>
                        <?php
                    }
                }

                function footer() {
                    $custom_product_id = apply_filters( 'wpcsb_custom_product_id', false );

                    if ( ! is_singular( 'product' ) && ! $custom_product_id ) {
                        return;
                    }

                    global $product;
                    $global_product = $product;
                    $product_id     = 0;

                    if ( $custom_product_id ) {
                        $product_id = $custom_product_id;
                        $product    = wc_get_product( $product_id );
                    } elseif ( $product && is_a( $product, 'WC_Product' ) ) {
                        $product_id = $product->get_id();
                    }

                    if ( ! $product_id || ! $product || ! is_a( $product, 'WC_Product' ) ) {
                        return;
                    }

                    if ( ( $hide_types = self::get_setting( 'hide_types', [] ) ) && is_array( $hide_types ) && ! empty( $hide_types ) ) {
                        foreach ( $hide_types as $product_type ) {
                            if ( $product->is_type( $product_type ) ) {
                                return;
                            }
                        }
                    }

                    if ( ( self::get_setting( 'hide_unpurchasable', 'no' ) === 'yes' ) && ( ! $product->is_in_stock() || ! $product->is_purchasable() ) ) {
                        return;
                    }

                    $position      = self::get_setting( 'position', 'bottom' );
                    $wrapper_class = 'wpcsb-wrapper wpcsb-wrapper-' . $position;
                    ?>
                    <div class="<?php echo esc_attr( $wrapper_class ); ?>">
                        <?php do_action( 'wpcsb_before_container', $product ); ?>
                        <div class="wpcsb-container">
                            <?php do_action( 'wpcsb_before_product', $product ); ?>
                            <div class="wpcsb-product">
                                <div class="wpcsb-product-info">
                                    <?php do_action( 'wpcsb_before_product_info', $product ); ?>
                                    <div class="wpcsb-product-image">
                                        <?php do_action( 'wpcsb_before_product_image', $product ); ?>
                                        <div class="wpcsb-image-ori">
                                            <?php echo wp_kses_post( apply_filters( 'wpcsb_product_image', $product->get_image(), $product ) ); ?>
                                        </div>
                                        <div class="wpcsb-image-new"></div>
                                        <?php do_action( 'wpcsb_after_product_image', $product ); ?>
                                    </div>
                                    <div class="wpcsb-product-data">
                                        <?php do_action( 'wpcsb_before_product_data', $product ); ?>
                                        <div class="wpcsb-product-name">
                                            <?php
                                            do_action( 'wpcsb_before_product_name', $product );
                                            echo esc_html( apply_filters( 'wpcsb_product_name', $product->get_name(), $product ) );
                                            do_action( 'wpcsb_after_product_name', $product );
                                            ?>
                                        </div>
                                        <?php
                                        $show_compare    = self::get_setting( 'show_compare', 'yes' ) === 'yes' && class_exists( 'WPCleverWoosc' );
                                        $show_quick_view = self::get_setting( 'show_quick_view', 'yes' ) === 'yes' && class_exists( 'WPCleverWoosq' );
                                        $show_wishlist   = self::get_setting( 'show_wishlist', 'yes' ) === 'yes' && class_exists( 'WPCleverWoosw' );

                                        if ( $show_compare || $show_quick_view || $show_wishlist ) {
                                            ?>
                                            <div class="wpcsb-product-btn">
                                                <?php
                                                if ( $show_quick_view ) {
                                                    echo wp_kses_post( do_shortcode( '[woosq]' ) );
                                                }

                                                if ( $show_compare ) {
                                                    echo wp_kses_post( do_shortcode( '[woosc]' ) );
                                                }

                                                if ( $show_wishlist ) {
                                                    echo wp_kses_post( do_shortcode( '[woosw]' ) );
                                                }
                                                ?>
                                            </div>
                                            <?php
                                        }

                                        if ( self::get_setting( 'show_price', 'yes' ) === 'yes' ) {
                                            ?>
                                            <div class="wpcsb-product-price">
                                                <?php do_action( 'wpcsb_before_product_price', $product ); ?>
                                                <div class="wpcsb-price-ori">
                                                    <?php echo wp_kses_post( apply_filters( 'wpcsb_product_price', $product->get_price_html(), $product ) ); ?>
                                                </div>
                                                <div class="wpcsb-price-new"></div>
                                                <?php do_action( 'wpcsb_after_product_price', $product ); ?>
                                            </div>
                                            <?php
                                        }

                                        do_action( 'wpcsb_after_product_data', $product ); ?>
                                    </div>
                                    <?php do_action( 'wpcsb_after_product_info', $product ); ?>
                                </div>
                                <div class="wpcsb-product-action">
                                    <?php do_action( 'wpcsb_before_product_action', $product );

                                    if ( $product->is_type( 'variable' ) && ( self::get_setting( 'variations_form', 'no' ) === 'yes' ) ) {
                                        woocommerce_template_single_add_to_cart();
                                    } else {
                                        if ( $product->is_in_stock() && $product->is_purchasable() ) { ?>
                                            <div class="wpcsb-add-to-cart"
                                                 data-product_id="<?php echo esc_attr( $product_id ); ?>">
                                                <?php
                                                do_action( 'wpcsb_before_add_to_cart_form', $product );

                                                if ( ( self::get_setting( 'show_quantity', 'yes' ) === 'yes' ) && ! $product->is_type( 'grouped' ) && ! $product->is_type( 'woosg' ) ) { ?>
                                                    <div class="wpcsb-quantity">
                                                        <?php
                                                        do_action( 'wpcsb_before_quantity_input', $product );

                                                        woocommerce_quantity_input( [
                                                                'classes'    => [
                                                                        'input-text',
                                                                        'qty',
                                                                        'text',
                                                                        'wpcsb-qty',
                                                                        'wpcsb-qty-' . $product_id
                                                                ],
                                                                'input_name' => 'wpcsb_qty'
                                                        ], $product );

                                                        do_action( 'wpcsb_after_quantity_input', $product );
                                                        ?>
                                                    </div>
                                                <?php } ?>

                                                <div class="wpcsb-atc">
                                                    <?php
                                                    do_action( 'wpcsb_before_add_to_cart_button', $product );

                                                    if ( self::get_setting( 'show_buy_now', 'yes' ) === 'yes' && class_exists( 'WPCleverWpcbn' ) ) {
                                                        echo wp_kses_post( do_shortcode( '[wpcbn_btn_single]' ) );
                                                    }
                                                    ?>
                                                    <button type="button" class="wpcsb-btn button alt">
                                                        <span><?php echo esc_html( self::localization( 'add_to_cart', esc_html__( 'Add to cart', 'wpc-sticky-add-to-cart' ) ) ); ?></span>
                                                        <?php
                                                        if ( self::get_setting( 'show_price', 'yes' ) === 'on_atc' ) {
                                                            ?>
                                                            <span class="wpcsb-product-price">
                                                                <?php do_action( 'wpcsb_before_product_price', $product ); ?>
                                                                <span class="wpcsb-price-ori">
                                                                    <?php echo wp_kses_post( apply_filters( 'wpcsb_product_price', $product->get_price_html(), $product ) ); ?>
                                                                </span>
                                                                <span class="wpcsb-price-new"></span>
                                                                <?php do_action( 'wpcsb_after_product_price', $product ); ?>
                                                            </span>
                                                            <?php
                                                        }
                                                        ?>
                                                    </button>
                                                    <?php do_action( 'wpcsb_after_add_to_cart_button', $product ); ?>
                                                </div>

                                                <?php do_action( 'wpcsb_after_add_to_cart_form', $product ); ?>
                                            </div>
                                        <?php }
                                    }

                                    do_action( 'wpcsb_after_product_action', $product ); ?>
                                </div>
                            </div>
                            <?php do_action( 'wpcsb_after_product', $product ); ?>
                        </div>
                        <?php do_action( 'wpcsb_after_container', $product ); ?>
                    </div>
                    <?php
                    $product = $global_product;
                }

                function wpcsm_locations( $locations ) {
                    $locations['WPC Sticky Add To Cart'] = [
                            'wpcsb_before_container'     => esc_html__( 'Before container', 'wpc-sticky-add-to-cart' ),
                            'wpcsb_after_container'      => esc_html__( 'After container', 'wpc-sticky-add-to-cart' ),
                            'wpcsb_before_product'       => esc_html__( 'Before product', 'wpc-sticky-add-to-cart' ),
                            'wpcsb_after_product'        => esc_html__( 'After product', 'wpc-sticky-add-to-cart' ),
                            'wpcsb_before_product_info'  => esc_html__( 'Before product info', 'wpc-sticky-add-to-cart' ),
                            'wpcsb_after_product_info'   => esc_html__( 'After product info', 'wpc-sticky-add-to-cart' ),
                            'wpcsb_before_product_image' => esc_html__( 'Before product image', 'wpc-sticky-add-to-cart' ),
                            'wpcsb_after_product_image'  => esc_html__( 'After product image', 'wpc-sticky-add-to-cart' ),
                            'wpcsb_before_product_data'  => esc_html__( 'Before product data', 'wpc-sticky-add-to-cart' ),
                            'wpcsb_after_product_data'   => esc_html__( 'After product data', 'wpc-sticky-add-to-cart' ),
                            'wpcsb_before_product_name'  => esc_html__( 'Before product name', 'wpc-sticky-add-to-cart' ),
                            'wpcsb_after_product_name'   => esc_html__( 'After product name', 'wpc-sticky-add-to-cart' ),
                            'wpcsb_before_product_price' => esc_html__( 'Before product price', 'wpc-sticky-add-to-cart' ),
                            'wpcsb_after_product_price'  => esc_html__( 'After product price', 'wpc-sticky-add-to-cart' ),
                    ];

                    return $locations;
                }
            }

            return WPCleverWpcsb::instance();
        }

        return null;
    }
}

if ( ! function_exists( 'wpcsb_notice_wc' ) ) {
    function wpcsb_notice_wc() {
        ?>
        <div class="error">
            <p><strong>WPC Sticky Add To Cart</strong> requires WooCommerce version 3.0 or greater.</p>
        </div>
        <?php
    }
}
