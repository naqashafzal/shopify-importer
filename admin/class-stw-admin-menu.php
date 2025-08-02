<?php
// shopify-importer/admin/class-stw-admin-menu.php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * STW_Admin_Menu Class.
 *
 * Sets up the admin menu and page.
 */
class STW_Admin_Menu {

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_styles' ] );
        add_action( 'admin_post_stw_import_products', [ $this, 'handle_import_form' ] );
    }

    /**
     * Add the admin menu item.
     */
    public function add_admin_menu() {
        add_menu_page(
            'Shopify Importer',
            'Shopify Importer',
            'manage_options',
            'shopify_importer',
            [ $this, 'admin_page_html' ],
            'dashicons-download',
            6
        );
    }

    /**
     * Enqueue admin styles.
     */
    public function enqueue_styles( $hook ) {
        if ( 'toplevel_page_shopify_importer' !== $hook ) {
            return;
        }
        wp_enqueue_style( 'stw-admin-style', STW_IMPORTER_URL . 'assets/css/admin-style.css', [], STW_IMPORTER_VERSION );
    }

    /**
     * Display the admin page HTML.
     */
    public function admin_page_html() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <div class="wrap stw-importer-wrapper">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <p>Enter your Shopify store details below to import products into WooCommerce.</p>
            
            <?php
            if ( isset( $_GET['import_status'] ) ) {
                if ( $_GET['import_status'] === 'success' ) {
                    echo '<div class="notice notice-success is-dismissible"><p>Products imported successfully!</p></div>';
                } elseif ( $_GET['import_status'] === 'error' ) {
                    echo '<div class="notice notice-error is-dismissible"><p>There was an error during the import. Please check your settings and try again.</p></div>';
                }
            }
            ?>

            <form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="POST">
                <input type="hidden" name="action" value="stw_import_products">
                <?php wp_nonce_field( 'stw_import_products_nonce' ); ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><label for="stw_shopify_url">Shopify Store URL</label></th>
                        <td><input type="text" id="stw_shopify_url" name="stw_shopify_url" value="" size="50" placeholder="your-store.myshopify.com" required/></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="stw_shopify_api_key">Shopify API Key</label></th>
                        <td><input type="text" id="stw_shopify_api_key" name="stw_shopify_api_key" value="" size="50" required/></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="stw_shopify_api_password">Shopify API Password</label></th>
                        <td><input type="password" id="stw_shopify_api_password" name="stw_shopify_api_password" value="" size="50" required/></td>
                    </tr>
                </table>
                <?php submit_button( 'Import Products' ); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Handle the import form submission.
     */
    public function handle_import_form() {
        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'stw_import_products_nonce' ) ) {
            wp_die( 'Security check failed.' );
        }

        $store_url    = sanitize_text_field( $_POST['stw_shopify_url'] );
        $api_key      = sanitize_text_field( $_POST['stw_shopify_api_key'] );
        $api_password = sanitize_text_field( $_POST['stw_shopify_api_password'] );

        $products = STW_Api_Handler::get_products( $store_url, $api_key, $api_password );

        if ( is_wp_error( $products ) ) {
            wp_redirect( admin_url( 'admin.php?page=shopify_importer&import_status=error' ) );
            exit;
        }

        foreach ( $products as $product_data ) {
            STW_Importer::create_wc_product( $product_data );
        }

        wp_redirect( admin_url( 'admin.php?page=shopify_importer&import_status=success' ) );
        exit;
    }
}
