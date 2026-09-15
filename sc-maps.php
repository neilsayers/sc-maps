<?php

/**
 * Plugin Name:       SC Maps
 * Plugin URI:        https://screencandy.co.uk
 * Description:       A site-agnostic map embed — Leaflet + OpenStreetMap, no API key, no Google. A [sc_map] shortcode and a matching PHP function, styleable per site.
 * Version:           0.3.1
 * Requires at least: 6.6
 * Requires PHP:      8.1
 * Author:            Neil Sayers
 * Author URI:        https://screencandy.co.uk
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       sc-maps
 */

namespace SCMaps;

if (! defined('ABSPATH')) {
    exit;
}

define('SCM_VERSION', '0.3.1');
define('SCM_FILE', __FILE__);
define('SCM_PATH', \plugin_dir_path(__FILE__));
define('SCM_URL', \plugin_dir_url(__FILE__));

/**
 * Minimal PSR-4-style autoloader so this plugin has zero build step
 * or Composer dependency — it just needs to be copied into any site's
 * wp-content/plugins and activated. Same shape as SC Events Manager's
 * and SC Room Bookings' own autoloaders, deliberately — but this
 * plugin shares no code or assets with either at runtime. Each is
 * independently activatable/deactivatable; a site can run any one,
 * any two, or all three without any of them needing another to be
 * present. Leaflet itself is vendored separately here rather than
 * pointed at SC Events Manager's own copy of it, for exactly that
 * reason — see assets/vendor/leaflet/.
 */
\spl_autoload_register(function (string $class): void {
    $prefix = __NAMESPACE__.'\\';

    if (! \str_starts_with($class, $prefix)) {
        return;
    }

    $relative = \substr($class, \strlen($prefix));
    $path = SCM_PATH.'src/'.\str_replace('\\', '/', $relative).'.php';

    if (\is_file($path)) {
        require $path;
    }
});

\register_activation_hook(__FILE__, [Setup\Activator::class, 'activate']);
\register_deactivation_hook(__FILE__, [Setup\Activator::class, 'deactivate']);

require SCM_PATH.'src/Frontend/template-functions.php';

Plugin::instance()->boot();
