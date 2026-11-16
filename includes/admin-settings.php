<?php

/**
 * Admin hook callbacks for the MDS Bandcamp Integration plugin.
 *
 * @package MDS_Bandcamp_Integration
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register the Bandcamp meta box.
 *
 * @param object $post Post being processed.
 * @return void
 */
function mds_bandcamp_register_meta_box($post): void
{
    MdsBandcampAdmin::registerMetaBox($post);
}

/**
 * Render the Bandcamp meta box.
 *
 * @param object $post Post being processed.
 * @return void
 */
function mds_bandcamp_meta_box_render($post): void
{
    MdsBandcampAdmin::renderMetaBox($post);
}

/**
 * Save the Bandcamp shortcode.
 *
 * @param int   $post_id   Post ID.
 * @param array $post_data Submitted post data.
 * @return void
 */
function mds_bandcamp_save_meta($post_id, $post_data): void
{
    MdsBandcampAdmin::saveMeta($post_id, $post_data);
}

add_action('mds_rm_register_custom_meta_boxes', 'mds_bandcamp_register_meta_box');
add_action('mds_rm_save_custom_meta', 'mds_bandcamp_save_meta', 10, 2);
