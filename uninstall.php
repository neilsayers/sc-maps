<?php

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Markers and the marker-type taxonomies are left in place on uninstall —
// they're the site's own location content, not plugin config, and wiping
// them on a WordPress "uninstall" click would be a nasty surprise. Only
// this plugin's own settings and its short-lived transients are removed.
delete_option('scm_settings');

delete_transient('scm_flush_rewrite_rules');
delete_transient('scm_activation_redirect');
