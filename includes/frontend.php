<?php

/**
 * Frontend hook callbacks for the MDS Bandcamp Integration plugin.
 *
 * @package MDS_Bandcamp_Integration
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render the Bandcamp player on the frontend.
 *
 * @param int $post_id Post ID.
 * @return void
 */
function mds_bandcamp_frontend_render($post_id): void
{
    MdsBandcampFrontend::render($post_id);
}

add_action('mds_rm_after_release_meta', 'mds_bandcamp_frontend_render');
