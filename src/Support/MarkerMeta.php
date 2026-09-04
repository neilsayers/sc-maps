<?php

namespace SCMaps\Support;

/**
 * A marker's own location — every other fact about it (name, photo,
 * description) is just the post's own title/thumbnail/content, no
 * separate meta needed.
 */
final class MarkerMeta
{
    /**
     * @return array{lat: string, lng: string}
     */
    public static function read(int $postId): array
    {
        return [
            'lat' => (string) \get_post_meta($postId, '_scm_lat', true),
            'lng' => (string) \get_post_meta($postId, '_scm_lng', true),
        ];
    }

    /**
     * @param array<string, mixed> $data The "scm" POST sub-array.
     */
    public static function save(int $postId, array $data): void
    {
        MetaField::saveValue($postId, '_scm_lat', self::sanitizeCoordinate((string) ($data['lat'] ?? '')));
        MetaField::saveValue($postId, '_scm_lng', self::sanitizeCoordinate((string) ($data['lng'] ?? '')));
    }

    public static function sanitizeCoordinate(string $value): string
    {
        if ($value === '' || ! \is_numeric($value)) {
            return '';
        }

        return (string) \round((float) $value, 6);
    }

    public static function enqueueMapAssets(): void
    {
        \wp_enqueue_style('scm-leaflet', SCM_URL.'assets/vendor/leaflet/leaflet.css', [], '1.9.4');
        \wp_enqueue_style('scm-leaflet-geocoder', SCM_URL.'assets/vendor/leaflet-control-geocoder/Control.Geocoder.css', [], '2.4.0');
        \wp_enqueue_style('scm-admin', SCM_URL.'assets/css/admin.css', [], SCM_VERSION);

        \wp_enqueue_script('scm-leaflet', SCM_URL.'assets/vendor/leaflet/leaflet.js', [], '1.9.4', true);
        \wp_enqueue_script('scm-leaflet-geocoder', SCM_URL.'assets/vendor/leaflet-control-geocoder/Control.Geocoder.js', ['scm-leaflet'], '2.4.0', true);
        \wp_enqueue_script('scm-marker-meta-box', SCM_URL.'assets/js/marker-meta-box.js', ['scm-leaflet-geocoder'], SCM_VERSION, true);
    }
}
