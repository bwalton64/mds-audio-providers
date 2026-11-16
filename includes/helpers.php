<?php

/**
 * Public dependency helpers for the MDS Bandcamp Integration plugin.
 *
 * @package MDS_Bandcamp_Integration
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Check whether MDS Release Manager is active.
 *
 * @return bool
 */
function mds_bandcamp_is_mds_active(): bool
{
    return MdsBandcampDependencies::isMdsActive();
}

/**
 * Check whether Blocks for Bandcamp is active.
 *
 * @return bool
 */
function mds_bandcamp_is_bandcamp_active(): bool
{
    return MdsBandcampDependencies::isBandcampActive();
}
