<?php
// shopify-importer/includes/class-stw-api-handler.php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * STW_Api_Handler Class.
 *
 * Handles communication with the Shopify API.
 */
class STW_Api_Handler {

    /**
     * Get products from Shopify.
     */
    public static function get_products( $store_url, $api_key, $api_password ) {
        $api_url = "https://{$api_key}:{$api_password}@{$store_url}/admin/api/2023-10/products.json";

        $response = wp_remote_get( $api_url, [ 'timeout' => 120 ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( isset( $data['products'] ) && is_array( $data['products'] ) ) {
            return $data['products'];
        }

        return new WP_Error( 'api_error', 'Invalid response from Shopify API.' );
    }
}
