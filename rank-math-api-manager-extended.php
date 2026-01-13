<?php
/**
 * Plugin Name: Rank Math API Manager Extended v1.4
 * Description: Manages the update of Rank Math metadata (SEO Title, SEO Description, Canonical URL) via a dedicated REST API endpoint for WordPress posts and WooCommerce products.
 * Version: 1.4
 * Author: Phil - https://inforeole.fr
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Rank_Math_API_Manager_Extended {

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_api_routes']);
    }

    /**
     * Registers the REST API route to update Rank Math meta fields.
     */
    public function register_api_routes() {
        register_rest_route( 'rank-math-api/v1', '/update-meta', [
            'methods'             => 'POST',
            'callback'            => [$this, 'update_rank_math_meta'],
            'permission_callback' => [$this, 'check_route_permission'],
            'args'                => [
                'post_id' => [
                    'required'          => true,
                    'validate_callback' => function( $param ) {
                        $post = get_post( (int) $param );
                        if ( ! $post ) {
                            return false;
                        }
                        $allowed_post_types = class_exists('WooCommerce') ? ['post', 'product'] : ['post'];
                        return in_array($post->post_type, $allowed_post_types, true);
                    },
                    'sanitize_callback' => 'absint',
                ],
                'rank_math_title' => [
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'rank_math_description' => [
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'rank_math_canonical_url' => [
                    'type'              => 'string',
                    'sanitize_callback' => 'esc_url_raw',
                ],
            ],
        ] );
    }

    /**
     * Updates the Rank Math meta fields for a specific post.
     *
     * @param WP_REST_Request $request The REST API request instance.
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error on failure.
     */
    public function update_rank_math_meta( WP_REST_Request $request ) {
        $post_id = $request->get_param('post_id');
        
        // Secondary, more specific permission check.
        if ( ! current_user_can('edit_post', $post_id) ) {
            return new WP_Error(
                'rest_forbidden',
                'You do not have permission to edit this post.',
                ['status' => 403]
            );
        }

        $fields  = ['rank_math_title', 'rank_math_description', 'rank_math_canonical_url'];
        $results = [];
        $updated = false;

        foreach ( $fields as $field ) {
            if ( $request->has_param( $field ) ) {
                $value = $request->get_param( $field );
                $current_value = get_post_meta($post_id, $field, true);

                if ($current_value === $value) {
                     $results[$field] = 'unchanged';
                } else {
                    $update_status = update_post_meta( $post_id, $field, $value );
                    if ($update_status) {
                        $results[$field] = 'updated';
                        $updated = true;
                    } else {
                        // This case is rare but could indicate a DB error or other failure.
                        $results[$field] = 'failed'; 
                    }
                }
            }
        }

        if ( ! $updated && empty($results) ) {
            return new WP_Error(
                'no_fields_provided',
                'No Rank Math fields were provided for update.',
                ['status' => 400]
            );
        }

        return new WP_REST_Response( $results, 200 );
    }

    /**
     * Checks if the current user has permission to access the REST API route.
     *
     * @return bool
     */
    public function check_route_permission() {
        return current_user_can( 'edit_posts' );
    }
}

new Rank_Math_API_Manager_Extended();