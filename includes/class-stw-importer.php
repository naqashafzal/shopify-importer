<?php
// shopify-importer/includes/class-stw-importer.php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * STW_Importer Class.
 *
 * Handles the logic of creating WooCommerce products.
 */
class STW_Importer {

    /**
     * Create a WooCommerce product from Shopify product data.
     */
    public static function create_wc_product( $product_data ) {
        if ( ! function_exists( 'wc_get_product_id_by_sku' ) ) {
            return new WP_Error( 'woocommerce_not_active', 'WooCommerce is not active.' );
        }

        $product_id = wc_get_product_id_by_sku( 'shopify_' . $product_data['id'] );

        if ( $product_id ) {
            // For now, we skip existing products.
            // In the future, you could add update logic here.
            return;
        }

        $product_type = count( $product_data['variants'] ) > 1 ? 'variable' : 'simple';
        $classname    = 'WC_Product_' . ucfirst( $product_type );
        $product      = new $classname();

        $product->set_name( $product_data['title'] );
        $product->set_description( $product_data['body_html'] );
        $product->set_sku( 'shopify_' . $product_data['id'] );

        if ( 'simple' === $product_type ) {
            $product->set_regular_price( $product_data['variants'][0]['price'] );
        }

        if ( ! empty( $product_data['image'] ) ) {
            $image_id = self::upload_image_from_url( $product_data['image']['src'] );
            if ( ! is_wp_error( $image_id ) ) {
                $product->set_image_id( $image_id );
            }
        }

        $product_id = $product->save();

        if ( 'variable' === $product_type && $product_id ) {
            self::create_wc_variations( $product_id, $product_data );
        }
        
        return $product_id;
    }

    /**
     * Create variations for a variable product.
     */
    public static function create_wc_variations( $product_id, $product_data ) {
        $product = wc_get_product( $product_id );
        $attributes = [];

        foreach ( $product_data['options'] as $index => $option ) {
            $attribute = new WC_Product_Attribute();
            $attribute->set_name( $option['name'] );
            $attribute->set_options( $option['values'] );
            $attribute->set_position( $index );
            $attribute->set_visible( true );
            $attribute->set_variation( true );
            $attributes[] = $attribute;
        }
        $product->set_attributes( $attributes );
        $product->save();

        foreach ( $product_data['variants'] as $variant_data ) {
            $variation = new WC_Product_Variation();
            $variation->set_parent_id( $product_id );
            
            $variation_attributes = [];
            foreach($product_data['options'] as $index => $option){
                $option_index = 'option' . ($index + 1);
                $variation_attributes[sanitize_title($option['name'])] = $variant_data[$option_index];
            }

            $variation->set_attributes( $variation_attributes );
            $variation->set_regular_price( $variant_data['price'] );
            $variation->set_sku( 'shopify_' . $variant_data['id'] );
            $variation->save();
        }
    }

    /**
     * Upload an image from a URL.
     */
    private static function upload_image_from_url( $image_url ) {
        require_once( ABSPATH . 'wp-admin/includes/media.php' );
        require_once( ABSPATH . 'wp-admin/includes/file.php' );
        require_once( ABSPATH . 'wp-admin/includes/image.php' );

        $attachment_id = media_sideload_image( $image_url, 0, null, 'id' );

        return $attachment_id;
    }
}
