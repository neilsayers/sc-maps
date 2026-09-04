<?php

namespace SCMaps\Frontend;

use SCMaps\Settings\Settings;
use SCMaps\Support\MarkerMeta;

/**
 * Every marker, shaped for theme/shortcode/REST consumption — the
 * shared query behind scm_get_markers(), MapShortcode, and
 * MapRestController's own GET /markers, so all three agree on what a
 * "marker" looks like. Same "extract the query, not the HTTP layer"
 * split as SC Room Bookings' own RoomListing::query().
 */
final class MarkerListing
{
    /**
     * @param array{type?: string, taxonomy?: string, term?: string, limit?: int, ids?: int[]} $args
     * @return array<int, array<string, mixed>>
     */
    public static function query(array $args = [], ?Settings $settings = null): array
    {
        $settings ??= new Settings();

        $ids = \array_filter(\array_map('absint', (array) ($args['ids'] ?? [])));

        $queryArgs = [
            // Explicit post IDs already say exactly which posts are
            // wanted — 'any' rather than resolveTypes()'s configured
            // list, since a caller picking specific posts (e.g. the
            // theme's own marker picker field) shouldn't also have to
            // pass a matching type.
            'post_type' => $ids !== [] ? 'any' : self::resolveTypes((string) ($args['type'] ?? ''), $settings),
            'post_status' => 'publish',
            // Oldest first, not WP_Query's default newest-first — a
            // set of map markers reads as a fixed catalogue an admin
            // built up in some order, not a feed of recent activity.
            'orderby' => 'date',
            'order' => 'ASC',
            'posts_per_page' => (int) ($args['limit'] ?? 0) > 0 ? (int) $args['limit'] : -1,
            'no_found_rows' => true,
        ];

        if ($ids !== []) {
            $queryArgs['post__in'] = $ids;
            $queryArgs['orderby'] = 'post__in';
        } elseif ($queryArgs['post_type'] === []) {
            return [];
        }

        $taxonomy = (string) ($args['taxonomy'] ?? '');
        $term = (string) ($args['term'] ?? '');

        if ($taxonomy !== '' && $term !== '') {
            $queryArgs['tax_query'] = [[
                'taxonomy' => $taxonomy,
                'field' => 'slug',
                'terms' => $term,
            ]];
        }

        $query = new \WP_Query($queryArgs);

        $markers = [];

        foreach ($query->posts as $post) {
            $marker = self::shape($post, $settings);

            if ($marker !== null) {
                $markers[] = $marker;
            }
        }

        return $markers;
    }

    /**
     * @return string[]
     */
    private static function resolveTypes(string $type, Settings $settings): array
    {
        if ($type === '') {
            return \array_keys($settings->allMarkerTypes());
        }

        $requested = \array_map('sanitize_key', \array_map('trim', \explode(',', $type)));

        return \array_values(\array_filter(
            $requested,
            static fn (string $postType): bool => $settings->getMarkerType($postType) !== null
        ));
    }

    /**
     * @return array<string, mixed>|null Null if this marker has no location set — nothing to plot.
     */
    private static function shape(\WP_Post $post, Settings $settings): ?array
    {
        $meta = MarkerMeta::read($post->ID);

        if ($meta['lat'] === '' || $meta['lng'] === '') {
            return null;
        }

        $markerType = $settings->getMarkerType($post->post_type);

        return [
            'id' => $post->ID,
            'type' => $post->post_type,
            'type_label' => $markerType['label_singular'] ?? $post->post_type,
            'name' => \get_the_title($post),
            'excerpt' => \get_the_excerpt($post),
            'content' => \apply_filters('the_content', $post->post_content),
            'featured_image_url' => \get_the_post_thumbnail_url($post, 'medium') ?: '',
            'view_url' => (string) \get_permalink($post),
            'lat' => (float) $meta['lat'],
            'lng' => (float) $meta['lng'],
            'colour' => $markerType['colour'] ?? Settings::DEFAULT_COLOURS[0],
            'icon' => $markerType['icon'] ?? '',
            'terms' => self::terms($post->ID, $post->post_type, $settings),
        ];
    }

    /**
     * @return array<string, string[]> Taxonomy slug => term names.
     */
    private static function terms(int $postId, string $postType, Settings $settings): array
    {
        $terms = [];

        foreach ($settings->taxonomiesForMarkerType($postType) as $taxonomy) {
            $found = \get_the_terms($postId, $taxonomy['taxonomy']);

            if (\is_array($found)) {
                $terms[$taxonomy['taxonomy']] = \wp_list_pluck($found, 'name');
            }
        }

        return $terms;
    }
}
