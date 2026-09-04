<?php

namespace SCMaps\Frontend;

use SCMaps\Contracts\Hookable;
use SCMaps\Settings\Settings;

/**
 * The plugin's public, versioned data contract for anything outside
 * this site's own PHP — a decoupled front end, another site, a build
 * step. "v1" is a promise the same way SC Events Manager's and SC Room
 * Bookings' own REST endpoints document one: existing fields won't be
 * renamed or removed within it; a breaking change gets its own v2
 * route instead.
 *
 * One route, under scm/v1:
 *   GET /markers — every marker with a location set, across every
 *                  configured type by default
 *
 * Deliberately open (no auth) — same trust model as the other two
 * plugins' own GET routes: it only returns what a marker's own
 * front-end page already shows.
 */
final class MapRestController implements Hookable
{
    private const NAMESPACE = 'scm/v1';

    public function __construct(private Settings $settings)
    {
    }

    public function register(): void
    {
        \add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        \register_rest_route(self::NAMESPACE, '/markers', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'handleListMarkers'],
            'permission_callback' => '__return_true',
            'args' => [
                'type' => [
                    'type' => 'string',
                    'default' => '',
                    'description' => 'Comma-separated marker type post_type keys. Empty means every configured type.',
                ],
                'taxonomy' => [
                    'type' => 'string',
                    'default' => '',
                    'sanitize_callback' => 'sanitize_key',
                    'description' => 'A custom taxonomy slug to filter by, paired with term.',
                ],
                'term' => [
                    'type' => 'string',
                    'default' => '',
                    'sanitize_callback' => 'sanitize_title',
                    'description' => 'The term slug within taxonomy to filter by.',
                ],
                'limit' => [
                    'type' => 'integer',
                    'default' => 0,
                    'sanitize_callback' => 'absint',
                    'description' => 'Maximum markers to return. Default 0 (no limit).',
                ],
            ],
        ]);
    }

    public function handleListMarkers(\WP_REST_Request $request): \WP_REST_Response
    {
        $markers = MarkerListing::query([
            'type' => (string) $request->get_param('type'),
            'taxonomy' => (string) $request->get_param('taxonomy'),
            'term' => (string) $request->get_param('term'),
            'limit' => (int) $request->get_param('limit'),
        ], $this->settings);

        return new \WP_REST_Response(['markers' => $markers, 'total' => \count($markers)]);
    }
}
