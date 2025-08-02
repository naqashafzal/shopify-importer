<?php
/**
 * Plugin Name: Shopify to WooCommerce Importer
 * Plugin URI:  https://nullpk.com/
 * Description: A plugin to import products from a Shopify store to WooCommerce.
 * Version:     1.1.0
 * Author:      Naqash Afzal
 * Author URI:  https://github.com/naqashafzal/
 * License:     GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: shopify-importer
 * Domain Path: /languages
 */

// shopify-importer/shopify-importer.php


// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// =================================================================================
// Main Plugin Class (Loader)
// =================================================================================

final class Shopify_Importer_Plugin {
    private static $_instance = null;

    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    private function __construct() {
        $this->define_constants();
        $this->init_hooks();
    }

    private function define_constants() {
        define( 'STW_IMPORTER_VERSION', '1.4.1' );
    }

    private function init_hooks() {
        if ( is_admin() ) {
            new STW_Admin_Menu();
        }
        add_action( 'init', [ $this, 'register_brand_taxonomy' ], 20 );
    }

    public function register_brand_taxonomy() {
        if ( ! function_exists('wc_create_attribute') || ! is_admin() ) return;
        $attributes = wc_get_attribute_taxonomies();
        $attribute_slug = 'brand';
        $attribute_exists = false;
        foreach ($attributes as $attribute) {
            if ($attribute->attribute_name === $attribute_slug) {
                $attribute_exists = true;
                break;
            }
        }
        if (!$attribute_exists) {
            wc_create_attribute([
                'name'         => 'Brand',
                'slug'         => $attribute_slug,
                'type'         => 'select',
                'order_by'     => 'menu_order',
                'has_archives' => true,
            ]);
        }
    }
}

// =================================================================================
// Admin Menu and Page Class
// =================================================================================

class STW_Admin_Menu {
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_post_stw_verify_user', [ $this, 'handle_verification_form' ] );
        add_action( 'wp_ajax_stw_fetch_products', [ $this, 'ajax_fetch_products' ] );
        add_action( 'wp_ajax_stw_import_selected_products', [ $this, 'ajax_import_selected_products' ] );
    }

    public function add_admin_menu() {
        add_menu_page( 'Shopify Importer', 'Shopify Importer', 'manage_options', 'shopify_importer', [ $this, 'admin_page_html' ], 'dashicons-download', 6);
    }
    
    public function register_settings() {
        register_setting( 'stw_importer_settings', 'stw_importer_options', [ $this, 'sanitize_settings' ] );
    }

    public function sanitize_settings( $input ) {
        $sanitized_input = [];
        $sanitized_input['url'] = isset($input['url']) ? sanitize_text_field($input['url']) : '';
        $sanitized_input['key'] = isset($input['key']) ? sanitize_text_field($input['key']) : '';
        if ( ! empty( $input['pass'] ) ) {
            $sanitized_input['pass'] = sanitize_text_field( $input['pass'] );
        } else {
            $options = get_option('stw_importer_options');
            $sanitized_input['pass'] = $options['pass'] ?? '';
        }
        return $sanitized_input;
    }

    public function handle_verification_form() {
        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'stw_verify_nonce' ) ) {
            wp_die('Security check failed.');
        }

        $user_email = isset($_POST['user_email']) ? sanitize_email($_POST['user_email']) : '';
        $user_website = isset($_POST['user_website']) ? esc_url_raw($_POST['user_website']) : '';
        $admin_email = 'admin@nullpk.com';

        if ( is_email($user_email) && !empty($user_website) ) {
            $subject = 'Shopify Importer Plugin Activated';
            $message = "A user has activated the Shopify to WooCommerce Importer plugin.\n\n";
            $message .= "User Email: " . $user_email . "\n";
            $message .= "User Website: " . $user_website . "\n";
            $message .= "Date: " . current_time('mysql') . "\n";
            $headers = ['From: WordPress <wordpress@' . parse_url(get_site_url(), PHP_URL_HOST) . '>'];
            
            wp_mail($admin_email, $subject, $message, $headers);
            
            update_option('stw_importer_verified', true);
        }

        wp_redirect(admin_url('admin.php?page=shopify_importer&verified=1'));
        exit;
    }

    public function admin_page_html() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        
        $is_verified = get_option('stw_importer_verified');

        if ( ! $is_verified ) {
            $this->render_verification_form();
            return;
        }

        $options = get_option('stw_importer_options');
        ?>
        <div class="wrap stw-importer-wrapper">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <p>First, save your Shopify API credentials. Then, fetch the list of available products to import.</p>
            
            <div id="poststuff">
                <div id="post-body" class="metabox-holder columns-2">
                    <!-- Main Content -->
                    <div id="post-body-content">
                        <div class="postbox">
                            <h2 class="hndle"><span>Importer Settings &amp; Controls</span></h2>
                            <div class="inside">
                                <form method="post" action="options.php">
                                    <?php settings_fields( 'stw_importer_settings' ); ?>
                                    <h3>API Settings</h3>
                                    <table class="form-table">
                                        <tr><th scope="row"><label for="stw_shopify_url">Shopify Store URL</label></th>
                                            <td><input type="text" id="stw_shopify_url" name="stw_importer_options[url]" value="<?php echo esc_attr( $options['url'] ?? '' ); ?>" size="50" placeholder="your-store.myshopify.com" required/></td></tr>
                                        <tr><th scope="row"><label for="stw_shopify_api_key">Shopify API Key</label></th>
                                            <td><input type="text" id="stw_shopify_api_key" name="stw_importer_options[key]" value="<?php echo esc_attr( $options['key'] ?? '' ); ?>" size="50" required/></td></tr>
                                        <tr><th scope="row"><label for="stw_shopify_api_password">Shopify API Password</label></th>
                                            <td><input type="password" id="stw_shopify_api_password" name="stw_importer_options[pass]" value="" size="50" placeholder="Enter new token to update"/><p class="description">Leave blank to keep the existing token.</p></td></tr>
                                    </table>
                                    <?php submit_button('Save Settings'); ?>
                                </form>
                                <hr>
                                <div id="stw-product-section">
                                    <h3>Import Products</h3>
                                    <?php if (!empty($options['key']) && !empty($options['pass'])): ?>
                                        <p>Your settings are saved. You can now fetch the list of products from your Shopify store.</p>
                                        <button id="stw-fetch-products" class="button button-secondary">Fetch Available Products</button>
                                        <div id="stw-product-list-container" style="display:none;">
                                            <h4>Available Products (<span id="product-count">0</span>)</h4>
                                            <p>Select the products you wish to import. Collections will be imported as categories.</p>
                                            <div id="stw-filter-controls" style="margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center;">
                                                <input type="text" id="stw-product-search" placeholder="Search by product name..." style="width: 300px;">
                                                <a href="#" id="stw-select-all-filtered">Select/Deselect All Filtered</a>
                                            </div>
                                            <table class="wp-list-table widefat fixed striped">
                                                <thead><tr><td id="cb" class="manage-column column-cb check-column"><input id="cb-select-all" type="checkbox"></td><th scope="col" class="manage-column">Product Name</th><th scope="col" class="manage-column">Vendor/Brand</th><th scope="col" class="manage-column">Tags</th></tr></thead>
                                                <tbody id="stw-product-list"></tbody>
                                            </table>
                                            <div id="stw-pagination-controls" style="margin-top: 15px; text-align: right;"></div><br>
                                            <button id="stw-import-selected" class="button button-primary">Import Selected Products (<span id="selected-count">0</span>)</button>
                                        </div>
                                    <?php else: ?><p>Please save your API settings above to enable product fetching.</p><?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div id="stw-progress-container" style="display:none;" class="postbox">
                            <h2 id="stw-import-status" class="hndle">Starting import...</h2>
                            <div class="inside">
                                <div id="stw-progress-bar-wrapper"><div id="stw-progress-bar">0%</div></div>
                                <ul id="stw-import-log"></ul>
                            </div>
                        </div>
                    </div> <!-- /post-body-content -->

                    <!-- Sidebar -->
                    <div id="postbox-container-1" class="postbox-container">
                        <div class="postbox">
                            <h2 class="hndle"><span>Plugin Credits & Support</span></h2>
                            <div class="inside">
                                <p><strong>Author:</strong> Naqash Afzal</p>
                                <p><strong>Website:</strong> <a href="https://nullpk.com" target="_blank" rel="noopener">nullpk.com</a></p>
                                <hr>
                                <p>If you find this plugin useful, please consider supporting its development.</p>
                                <form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_blank" id="stw-donate-form">
                                    <input type="hidden" name="cmd" value="_xclick">
                                    <input type="hidden" name="business" value="haintsblue@gmail.com">
                                    <input type="hidden" name="item_name" value="Support for Shopify to WooCommerce Importer">
                                    <input type="hidden" name="currency_code" value="USD">
                                    <input type="hidden" name="no_shipping" value="1">
                                    <div class="stw-donation-amounts">
                                        <button type="button" class="button button-secondary" data-amount="5">$5</button>
                                        <button type="button" class="button button-secondary" data-amount="10">$10</button>
                                        <button type="button" class="button button-secondary" data-amount="20">$20</button>
                                    </div>
                                    <div class="stw-custom-amount">
                                        <span>$</span>
                                        <input type="text" name="amount" id="stw-custom-amount-input" placeholder="Custom Amount" required>
                                    </div>
                                    <button type="submit" class="button button-primary">Support via PayPal</button>
                                </form>
                            </div>
                        </div>
                        <div class="postbox">
                            <h2 class="hndle"><span>How to Use</span></h2>
                            <div class="inside">
                                <p><strong>Step 1: Get API Credentials</strong></p>
                                <ol>
                                    <li>In your Shopify Admin, go to <strong>Settings → Apps and sales channels → Develop apps</strong>.</li>
                                    <li>Create a new app, go to the <strong>Configuration</strong> tab, and configure the **Admin API integration** scopes.</li>
                                    <li>Give it **`read_products`** permission.</li>
                                    <li>Go to the **API credentials** tab, install the app, and reveal the **Admin API access token** once to get your credentials.</li>
                                </ol>
                                <p><strong>Step 2: Save Settings</strong></p>
                                <p>Paste your Shopify URL, API Key, and API Password (the access token) into the settings panel on this page and click "Save Settings".</p>
                                <p><strong>Step 3: Import Products</strong></p>
                                <ol>
                                    <li>Click **"Fetch Available Products"**.</li>
                                    <li>Use the search and pagination to find the products you want to import.</li>
                                    <li>Check the boxes next to the desired products.</li>
                                    <li>Click **"Import Selected Products"** and wait for the process to complete.</li>
                                </ol>
                            </div>
                        </div>
                    </div> <!-- /postbox-container-1 -->
                </div> <!-- /post-body -->
                <br class="clear">
            </div> <!-- /poststuff -->
        </div>
        <?php 
        $this->add_inline_styles();
        $this->add_inline_js();
    }

    private function render_verification_form() {
        ?>
        <div class="wrap">
            <h1>Welcome to the Shopify Importer</h1>
            <div class="postbox">
                <div class="inside">
                    <p>To unlock the plugin settings, please provide your details below. This helps us understand our user base and improve the plugin.</p>
                    <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
                        <input type="hidden" name="action" value="stw_verify_user">
                        <?php wp_nonce_field( 'stw_verify_nonce' ); ?>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="user_email">Your Email</label></th>
                                <td><input type="email" id="user_email" name="user_email" class="regular-text" value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>" required></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="user_website">Your Website</label></th>
                                <td><input type="url" id="user_website" name="user_website" class="regular-text" value="<?php echo esc_url( get_site_url() ); ?>" required></td>
                            </tr>
                        </table>
                        <?php submit_button('Activate Plugin'); ?>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function ajax_fetch_products() {
        check_ajax_referer( 'stw_import_ajax_nonce', 'nonce' );
        $options = get_option('stw_importer_options');
        $products = []; $next_page_info = '';
        do {
            $response = STW_Api_Handler::get_products($options['url'], $options['key'], $options['pass'], 250, $next_page_info);
            if (is_wp_error($response)) { wp_send_json_error(['message' => $response->get_error_message()]); return; }
            $products = array_merge($products, $response['products']);
            $next_page_info = $response['next_page_info'];
        } while (!empty($next_page_info));
        wp_send_json_success(['products' => $products]);
    }

    public function ajax_import_selected_products() {
        check_ajax_referer( 'stw_import_ajax_nonce', 'nonce' );
        $product_data = json_decode(stripslashes($_POST['product_data']), true);
        if (json_last_error() !== JSON_ERROR_NONE) { wp_send_json_error(['message' => 'Invalid product data.']); return; }
        
        $result = STW_Importer::create_wc_product($product_data);

        if ( is_wp_error( $result ) ) { wp_send_json_error([ 'name' => $product_data['title'], 'message' => $result->get_error_message() ]); } 
        else { wp_send_json_success([ 'name' => $product_data['title'], 'message' => $result['message'] ]); }
    }
    
    private function add_inline_styles() { /* CSS for admin page */ ?>
        <style type="text/css">
            .stw-importer-wrapper .form-table { margin-bottom: 20px; }
            #stw-progress-container { display: none; margin-top: 20px; }
            #stw-progress-bar-wrapper { width: 100%; background-color: #f0f0f1; border: 1px solid #ddd; border-radius: 4px; height: 28px; margin-bottom: 10px; overflow: hidden; }
            #stw-progress-bar { width: 0%; height: 100%; background-color: #007cba; text-align: center; line-height: 28px; color: white; transition: width 0.4s ease; }
            #stw-import-log { max-height: 300px; overflow-y: auto; background-color: #fafafa; border: 1px solid #ddd; padding: 10px; list-style-position: inside; }
            #stw-import-log li { padding: 2px 0; border-bottom: 1px solid #eee; }
            #stw-import-log li.success { color: #228b22; } #stw-import-log li.error { color: #dc3232; }
            #stw-import-status { font-weight: bold; margin-bottom: 10px; } #stw-product-list-container { margin-top: 20px; }
            #stw-pagination-controls button { cursor: pointer; margin: 0 5px; }
            #stw-pagination-controls .current-page { font-weight: bold; background-color: #f0f0f1; border: 1px solid #ddd; padding: 5px 10px; display: inline-block; vertical-align: middle; }
            .stw-donation-amounts button { margin-right: 5px; }
            .stw-custom-amount { margin: 10px 0; }
            .stw-custom-amount span { display: inline-block; vertical-align: middle; padding-right: 2px; }
            .stw-custom-amount input { width: 120px; vertical-align: middle; }
        </style>
    <?php }

    private function add_inline_js() { /* JavaScript for admin page */ ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                const fetchBtn = $('#stw-fetch-products'), productListContainer = $('#stw-product-list-container'), productListBody = $('#stw-product-list');
                const importBtn = $('#stw-import-selected'), productCountSpan = $('#product-count'), progressBar = $('#stw-progress-bar');
                const progressContainer = $('#stw-progress-container'), importLog = $('#stw-import-log'), importStatus = $('#stw-import-status');
                const searchInput = $('#stw-product-search'), paginationControls = $('#stw-pagination-controls'), selectAllFilteredBtn = $('#stw-select-all-filtered');
                const selectedCountSpan = $('#selected-count');

                let allProductsData = [], currentPage = 1, productsPerPage = 20;
                let selectedProductIds = new Set();

                function updateSelectedCount() { selectedCountSpan.text(selectedProductIds.size); }

                fetchBtn.on('click', function() {
                    $(this).text('Fetching...').prop('disabled', true);
                    $.post(ajaxurl, { action: 'stw_fetch_products', nonce: '<?php echo wp_create_nonce("stw_import_ajax_nonce"); ?>' }, function(response) {
                        fetchBtn.text('Fetch Available Products').prop('disabled', false);
                        if (response.success) {
                            allProductsData = response.data.products; currentPage = 1; selectedProductIds.clear(); updateSelectedCount(); renderProductList(); productListContainer.show();
                        } else { alert('Error: ' + response.data.message); }
                    });
                });

                searchInput.on('keyup', function() { currentPage = 1; renderProductList(); });
                paginationControls.on('click', 'button', function(e) { e.preventDefault(); const newPage = $(this).data('page'); if (newPage) { currentPage = parseInt(newPage); renderProductList(); } });

                function renderProductList() {
                    const searchTerm = searchInput.val().toLowerCase();
                    const filteredProducts = allProductsData.filter(p => p.title.toLowerCase().includes(searchTerm));
                    productCountSpan.text(filteredProducts.length); productListBody.empty(); paginationControls.empty();
                    if (filteredProducts.length === 0) { productListBody.append('<tr><td colspan="4">No products found.</td></tr>'); return; }
                    const totalPages = Math.ceil(filteredProducts.length / productsPerPage);
                    const startIndex = (currentPage - 1) * productsPerPage;
                    const productsToShow = filteredProducts.slice(startIndex, startIndex + productsPerPage);
                    productsToShow.forEach(function(p) {
                        const isChecked = selectedProductIds.has(String(p.id));
                        productListBody.append(`<tr><th scope="row" class="check-column"><input type="checkbox" class="stw-product-checkbox" name="products[]" value="${p.id}" ${isChecked ? 'checked' : ''}></th><td>${p.title}</td><td>${p.vendor || 'N/A'}</td><td>${p.tags || 'N/A'}</td></tr>`);
                    });
                    if (totalPages > 1) {
                        if (currentPage > 1) { paginationControls.append(`<button data-page="${currentPage - 1}" class="button">&laquo; Previous</button>`); }
                        paginationControls.append(`<span class="current-page">Page ${currentPage} of ${totalPages}</span>`);
                        if (currentPage < totalPages) { paginationControls.append(`<button data-page="${currentPage + 1}" class="button">Next &raquo;</button>`); }
                    }
                }

                selectAllFilteredBtn.on('click', function(e) {
                    e.preventDefault();
                    const filteredProducts = allProductsData.filter(p => p.title.toLowerCase().includes(searchInput.val().toLowerCase()));
                    const filteredIds = filteredProducts.map(p => String(p.id));
                    const allSelected = filteredIds.length > 0 && filteredIds.every(id => selectedProductIds.has(id));
                    if (allSelected) { filteredIds.forEach(id => selectedProductIds.delete(id)); } else { filteredIds.forEach(id => selectedProductIds.add(id)); }
                    updateSelectedCount(); renderProductList();
                });

                $('#cb-select-all').on('click', function() {
                    const visibleCheckboxes = productListBody.find('input.stw-product-checkbox');
                    const isChecked = this.checked;
                    visibleCheckboxes.each(function() {
                        const id = $(this).val();
                        if (isChecked) { selectedProductIds.add(id); } else { selectedProductIds.delete(id); }
                        $(this).prop('checked', isChecked);
                    });
                    updateSelectedCount();
                });

                productListBody.on('click', 'input.stw-product-checkbox', function() {
                    const id = $(this).val();
                    if (this.checked) { selectedProductIds.add(id); } else { selectedProductIds.delete(id); }
                    updateSelectedCount();
                });

                importBtn.on('click', function() {
                    const selectedIds = Array.from(selectedProductIds);
                    if (selectedIds.length === 0) { alert('Please select at least one product to import.'); return; }
                    const productsToImport = allProductsData.filter(p => selectedIds.includes(String(p.id)));
                    let processedCount = 0; const totalToImport = productsToImport.length;
                    progressContainer.show(); importLog.html(''); progressBar.css('width', '0%').text('0%');
                    importStatus.text(`Starting import of ${totalToImport} products...`);
                    $(this).prop('disabled', true); fetchBtn.prop('disabled', true);

                    function importNextProduct() {
                        if (productsToImport.length === 0) { importStatus.text('Import complete!'); importBtn.prop('disabled', false); fetchBtn.prop('disabled', false); return; }
                        const productData = productsToImport.shift();
                        $.post(ajaxurl, { action: 'stw_import_selected_products', nonce: '<?php echo wp_create_nonce("stw_import_ajax_nonce"); ?>', product_data: JSON.stringify(productData)
                        }, function(response) {
                            processedCount++;
                            const percentage = Math.round((processedCount / totalToImport) * 100);
                            progressBar.css('width', percentage + '%').text(percentage + '%');
                            importStatus.text(`Importing... (${processedCount} / ${totalToImport})`);
                            if (response.success) { importLog.prepend(`<li class="success"><strong>${response.data.name}</strong>: ${response.data.message}</li>`); } 
                            else { importLog.prepend(`<li class="error"><strong>${response.data.name}</strong>: Skipped (${response.data.message})</li>`); }
                            importNextProduct();
                        }).fail(function() {
                            processedCount++; importLog.prepend(`<li class="error">A server error occurred while importing <strong>${productData.title}</strong>. Trying next...</li>`);
                            importNextProduct();
                        });
                    }
                    importNextProduct();
                });

                $('.stw-donation-amounts button').on('click', function() {
                    const amount = $(this).data('amount');
                    $('#stw-custom-amount-input').val(amount);
                });
            });
        </script>
    <?php }
}

// =================================================================================
// API Handler Class
// =================================================================================

class STW_Api_Handler {
    public static function get_products($store_url, $api_key, $api_password, $limit = 50, $page_info = '') {
        $api_url = "https://{$api_key}:{$api_password}@{$store_url}/admin/api/2024-07/products.json?limit={$limit}&fields=id,title,body_html,vendor,tags,image,variants";
        if (!empty($page_info)) { $api_url .= "&page_info={$page_info}"; }
        $response = wp_remote_get($api_url, ['timeout' => 120]);
        if (is_wp_error($response)) { return $response; }
        $headers = wp_remote_retrieve_headers($response); $next_page_info = '';
        if (isset($headers['link']) && preg_match('/<[^>]+page_info=([^>]+)>; rel="next"/', $headers['link'], $matches)) { $next_page_info = $matches[1]; }
        $body = wp_remote_retrieve_body($response); $data = json_decode($body, true);
        if (isset($data['products'])) { return ['products' => $data['products'], 'next_page_info' => $next_page_info]; }
        return new WP_Error('api_error', 'Invalid response from Shopify API. Check credentials.');
    }

    public static function get_all_collections_for_product($product_id) {
        $options = get_option('stw_importer_options');
        if (empty($options['url'])) return new WP_Error('no_settings', 'API settings are not saved.');
        $all_collections = []; $endpoints = ['custom_collections', 'smart_collections'];
        foreach ($endpoints as $endpoint) {
            $api_url = "https://{$options['key']}:{$options['pass']}@{$options['url']}/admin/api/2024-07/{$endpoint}.json?product_id={$product_id}";
            $response = wp_remote_get($api_url, ['timeout' => 60]);
            if (!is_wp_error($response)) {
                $data = json_decode(wp_remote_retrieve_body($response), true);
                if (isset($data[$endpoint])) { $all_collections = array_merge($all_collections, wp_list_pluck($data[$endpoint], 'title')); }
            }
        }
        return array_unique($all_collections);
    }
}

// =================================================================================
// Product Importer Class
// =================================================================================

class STW_Importer {
    public static function create_wc_product($product_data) {
        if (!function_exists('wc_get_product_id_by_sku')) { return new WP_Error('woocommerce_not_active', 'WooCommerce is not active.'); }
        $sku = 'shopify_' . $product_data['id'];
        if (wc_get_product_id_by_sku($sku)) { return new WP_Error('product_exists', 'Product already exists.'); }

        $product = (count($product_data['variants']) > 1) ? new WC_Product_Variable() : new WC_Product_Simple();
        $product->set_name($product_data['title']);
        $product->set_description($product_data['body_html']);
        $product->set_sku($sku);
        if ($product->is_type('simple')) { $product->set_regular_price($product_data['variants'][0]['price']); }

        if (!empty($product_data['image'])) {
            $image_id = self::upload_image_from_url($product_data['image']['src']);
            if (!is_wp_error($image_id)) { $product->set_image_id($image_id); }
        }

        $collection_names = STW_Api_Handler::get_all_collections_for_product($product_data['id']);
        $category_ids = [];
        if (!is_wp_error($collection_names) && !empty($collection_names)) {
            foreach ($collection_names as $name) {
                $term = term_exists($name, 'product_cat');
                if (0 === $term || null === $term) { $term = wp_insert_term($name, 'product_cat'); }
                if (!is_wp_error($term)) { $category_ids[] = $term['term_id']; }
            }
            if(!empty($category_ids)) { $product->set_category_ids($category_ids); }
        }

        if (!empty($product_data['tags'])) {
            $tag_ids = self::get_term_ids_for_taxonomy($product_data['tags'], 'product_tag');
            if(!empty($tag_ids)) { $product->set_tag_ids($tag_ids); }
        }
        
        if (!empty($product_data['vendor'])) {
            $brand_name = $product_data['vendor'];
            $brand_taxonomy = 'pa_brand';
            $term_id = self::get_term_ids_for_taxonomy([$brand_name], $brand_taxonomy)[0] ?? 0;
            if ($term_id) {
                $attribute = new WC_Product_Attribute();
                $attribute->set_id(wc_attribute_taxonomy_id_by_name($brand_taxonomy));
                $attribute->set_name($brand_taxonomy);
                $attribute->set_options([$term_id]);
                $attribute->set_visible(true);
                $product->set_attributes([$attribute]);
            }
        }
        
        $product_id = $product->save();
        if ($product->is_type('variable') && $product_id) { self::create_wc_variations($product, $product_data); }
        
        $log_message = 'Imported successfully.';
        if(!empty($collection_names)){ $log_message .= ' Categories: ' . implode(', ', $collection_names); }
        return ['product_id' => $product_id, 'message' => $log_message];
    }

    private static function create_wc_variations($product, $product_data) {
        $attributes = $product->get_attributes();
        foreach ($product_data['options'] as $option) {
            $attribute = new WC_Product_Attribute();
            $attribute->set_name($option['name']);
            $attribute->set_options($option['values']);
            $attribute->set_visible(true);
            $attribute->set_variation(true);
            $attributes[sanitize_title($option['name'])] = $attribute;
        }
        $product->set_attributes(array_values($attributes));
        $product->save();

        foreach ($product_data['variants'] as $variant_data) {
            $variation = new WC_Product_Variation();
            $variation->set_parent_id($product->get_id());
            $var_attributes = [];
            foreach($product_data['options'] as $index => $option){
                $var_attributes[sanitize_title($option['name'])] = $variant_data['option' . ($index + 1)];
            }
            $variation->set_attributes($var_attributes);
            $variation->set_regular_price($variant_data['price']);
            $variation->set_sku('shopify_' . $variant_data['id']);
            $variation->save();
        }
    }
    
    private static function get_term_ids_for_taxonomy($terms, $taxonomy) {
        $term_ids = [];
        if (is_string($terms)) { $terms = explode(',', $terms); }
        foreach ($terms as $term_name) {
            $term_name = trim($term_name);
            $term = term_exists($term_name, $taxonomy);
            if (0 === $term || null === $term) { $term = wp_insert_term($term_name, $taxonomy); }
            if (!is_wp_error($term)) { $term_ids[] = $term['term_id']; }
        }
        return $term_ids;
    }

    private static function upload_image_from_url($image_url) {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        return media_sideload_image($image_url, 0, null, 'id');
    }
}

// Let's go!
Shopify_Importer_Plugin::instance();
