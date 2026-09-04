=== SC Maps ===
Contributors: screencandy
Tags: map, leaflet, openstreetmap, locations
Requires at least: 6.6
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 0.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A site-agnostic map embed — Leaflet + OpenStreetMap, no API key, no Google. Guides you through naming your own
marker post types before anything is registered.

== Description ==

SC Maps is built to be dropped into any WordPress site as-is — no build step, no Composer install. Rather than
hard-coding a single "Location" post type, it's managed from one "Maps" settings screen where you create your own
marker types with their own singular/plural labels, colour, and icon (e.g. "Shop" / "Shops", "Point of interest" /
"Points of interest", "Theme park" / "Theme parks") — up to 6 by default. That cap is filterable, e.g.:

    add_filter('scm_max_marker_types', fn () => 8);

For each type, the underlying post type key and URL slug are locked in the moment it's created — renaming its
labels afterwards never orphans existing markers or breaks their links. Types can be deleted from the screen too,
but only after typing the type's plural label to confirm — doing so moves every one of its posts to Trash (a
normal 30-day recovery window, nothing is hard-deleted) and removes any custom taxonomies attached to it.

Each marker type can also have its own custom taxonomy (e.g. "Cuisine" for Restaurants), managed from a
"Taxonomies" screen under Maps — capped at 2 per marker type by default, also filterable:

    add_filter('scm_max_taxonomies_per_marker_type', fn () => 4);

Unlike marker types, taxonomies can be freely deleted — removing one just stops classifying posts, it doesn't touch
them.

Every marker post type gets a hand-written "Location" box above the content editor — a Leaflet + OpenStreetMap map
with address search (vendored locally, no runtime CDN dependency) for dropping a pin, plus lat/lng inputs kept in
sync with it. Everything else about a marker (name, photo, description) is just the post's own title/featured
image/content.

Configured marker types nest under one "Map Locations" admin menu instead of each getting its own top-level entry
— keeps the sidebar tidy as more types are added.

Marker data is available three ways, all backed by the same query so results always match whichever one you use:
scm_get_markers()/scm_render_map() (PHP), a public REST endpoint (GET /wp-json/scm/v1/markers), and a [sc_map]
shortcode. See Maps → Documentation for the full reference.

This plugin is deliberately independent of SC Events Manager and SC Room Bookings — no shared code or assets at
runtime, even though the underlying patterns (and the Leaflet location picker) are the same. Any one, two, or all
three can be activated or deactivated without affecting the others.

== Changelog ==

= 0.2.0 =
* Marker types now nest under a single "Map Locations" top-level admin menu instead of each type getting its own —
  keeps the admin sidebar tidy as more types are added. Each type still gets exactly one link there (its own list);
  "Add New" stays one click away, from inside a type's own list.
* scm_get_markers()/scm_render_map()/[sc_map] gained an "ids" parameter — plot exactly a given set of marker posts,
  for theme code that already knows which marker(s) it wants (e.g. a "pick a location" field) rather than filtering
  by type/taxonomy.
* Fixed: the map's CSS could fail to load on some pages — it's now enqueued unconditionally on the front end
  (matching SC Events Manager's own frontend.css) rather than only when a map was already known to be rendering,
  which was too late for wp_head() to still be able to print it.

= 0.1.0 =
* Initial release.
