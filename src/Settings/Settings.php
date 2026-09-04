<?php

namespace SCMaps\Settings;

/**
 * Reads/writes the plugin's single options-table row: a collection of
 * marker types keyed by their post_type slug, plus the custom
 * taxonomies attached to them.
 *
 * For each type, only the post_type key and URL slug are fixed for
 * the lifetime of the site — both derived once from the labels given
 * when the type is created (see addMarkerType()). Renaming a type
 * afterwards (relabelMarkerType()) only ever touches its display
 * labels/colour/icon; changing the key or slug later would orphan
 * existing posts or break their permalinks. Deleting one
 * (deleteMarkerType()) is allowed, but only removes this settings
 * entry — Admin\MarkerTypesPage is responsible for trashing the
 * type's posts and deleting its taxonomies *before* calling it, so
 * nothing is left orphaned.
 *
 * Same shape as SC Events Manager's own Settings class, deliberately
 * — but this plugin shares no code with it. Copy the pattern, not
 * the dependency.
 */
final class Settings
{
    private const OPTION_KEY = 'scm_settings';

    /**
     * A small, distinct palette so two marker types picked at random
     * still look different on the same map — the colour input can
     * always be changed to anything else afterwards.
     */
    public const DEFAULT_COLOURS = ['#00854A', '#A2455F', '#1D4ED8', '#D97706', '#7C3AED', '#0891B2'];

    private const DEFAULTS = [
        'marker_types' => [],
        'taxonomies' => [],
    ];

    private array $values;

    public function __construct()
    {
        $stored = \get_option(self::OPTION_KEY, []);
        $this->values = \is_array($stored) ? \array_merge(self::DEFAULTS, $stored) : self::DEFAULTS;
    }

    /**
     * @return array<string, array{post_type: string, slug: string, label_singular: string, label_plural: string, colour: string, icon: string}>
     */
    public function allMarkerTypes(): array
    {
        return $this->values['marker_types'];
    }

    public function hasMarkerTypes(): bool
    {
        return $this->values['marker_types'] !== [];
    }

    public function getMarkerType(string $postType): ?array
    {
        return $this->values['marker_types'][$postType] ?? null;
    }

    /**
     * Filterable so a site can raise the cap later — e.g.
     * add_filter('scm_max_marker_types', fn () => 8) — without
     * touching the plugin itself.
     */
    public function maxMarkerTypes(): int
    {
        return (int) \apply_filters('scm_max_marker_types', 6);
    }

    public function canAddMarkerType(): bool
    {
        return \count($this->values['marker_types']) < $this->maxMarkerTypes();
    }

    /**
     * A WordPress post type name is capped at 20 characters and must
     * be lowercase alphanumeric/underscore/dash — sanitize_key() plus
     * a hard truncate keeps register_post_type() from silently
     * failing on a long or oddly-punctuated singular label.
     */
    public static function derivePostTypeKey(string $label): string
    {
        return \substr(\sanitize_key($label), 0, 20);
    }

    public static function deriveSlug(string $label): string
    {
        return \sanitize_title($label);
    }

    /**
     * A default colour for the "Add a marker type" form, cycling
     * through DEFAULT_COLOURS so it's never the same as the most
     * recently added type.
     */
    public function nextDefaultColour(): string
    {
        $index = \count($this->values['marker_types']) % \count(self::DEFAULT_COLOURS);

        return self::DEFAULT_COLOURS[$index];
    }

    /**
     * Checked before addMarkerType() so the admin screen can show one
     * error message covering every failure mode.
     */
    public function validateNewMarkerType(string $labelSingular, string $labelPlural): ?string
    {
        if ($labelSingular === '' || $labelPlural === '') {
            return \__('Please enter both a singular and plural name.', 'sc-maps');
        }

        if (! $this->canAddMarkerType()) {
            return \sprintf(
                \__('You already have the maximum of %d marker types.', 'sc-maps'),
                $this->maxMarkerTypes()
            );
        }

        $postType = self::derivePostTypeKey($labelSingular);

        if ($postType === '') {
            return \__('That singular name can\'t be turned into a valid post type — please use letters or numbers.', 'sc-maps');
        }

        if (\post_type_exists($postType) || isset($this->values['marker_types'][$postType])) {
            return \__('That singular name is already in use — please try a different one.', 'sc-maps');
        }

        return null;
    }

    /**
     * Existing entries always win outright rather than being
     * overwritten — see SC Room Bookings' own addRoomType() for why
     * this matters: a stray call from outside the admin form (a
     * script, a future integration) should never be able to silently
     * clobber a real marker type.
     *
     * @return string The marker type's post_type key — newly created, or the existing one if this collided with it.
     */
    public function addMarkerType(string $labelSingular, string $labelPlural, string $colour, string $icon = ''): string
    {
        $postType = self::derivePostTypeKey($labelSingular);

        if (isset($this->values['marker_types'][$postType])) {
            return $postType;
        }

        $this->values['marker_types'][$postType] = [
            'post_type' => $postType,
            'slug' => self::deriveSlug($labelPlural),
            'label_singular' => $labelSingular,
            'label_plural' => $labelPlural,
            'colour' => $this->sanitizeColour($colour),
            'icon' => \esc_url_raw($icon),
        ];

        \update_option(self::OPTION_KEY, $this->values);

        return $postType;
    }

    /**
     * Safe to call any time — only the display labels/colour/icon
     * change; see class docblock for why post_type/slug are never
     * touched again.
     */
    public function relabelMarkerType(string $postType, string $labelSingular, string $labelPlural, string $colour, string $icon = ''): void
    {
        if (! isset($this->values['marker_types'][$postType])) {
            return;
        }

        $this->values['marker_types'][$postType]['label_singular'] = $labelSingular;
        $this->values['marker_types'][$postType]['label_plural'] = $labelPlural;
        $this->values['marker_types'][$postType]['colour'] = $this->sanitizeColour($colour);
        $this->values['marker_types'][$postType]['icon'] = \esc_url_raw($icon);

        \update_option(self::OPTION_KEY, $this->values);
    }

    /**
     * Removes this marker type from settings only — it does not
     * touch the type's posts or taxonomies. Callers
     * (Admin\MarkerTypesPage::handleDelete()) are expected to trash
     * the posts and delete the taxonomies first, since both need the
     * type to still be a real, registered post type to do their own
     * work correctly.
     */
    public function deleteMarkerType(string $postType): void
    {
        unset($this->values['marker_types'][$postType]);

        \update_option(self::OPTION_KEY, $this->values);
    }

    private function sanitizeColour(string $colour): string
    {
        return \preg_match('/^#[0-9a-fA-F]{6}$/', $colour) ? $colour : '#00854A';
    }

    /**
     * @return array<string, array{taxonomy: string, post_type: string, label_singular: string, label_plural: string, hierarchical: bool}>
     */
    public function allTaxonomies(): array
    {
        return $this->values['taxonomies'];
    }

    /**
     * @return array<string, array{taxonomy: string, post_type: string, label_singular: string, label_plural: string, hierarchical: bool}>
     */
    public function taxonomiesForMarkerType(string $postType): array
    {
        return \array_filter(
            $this->values['taxonomies'],
            static fn (array $taxonomy): bool => $taxonomy['post_type'] === $postType
        );
    }

    public function getTaxonomy(string $taxonomy): ?array
    {
        return $this->values['taxonomies'][$taxonomy] ?? null;
    }

    /**
     * Filterable so a site can raise the cap later — e.g.
     * add_filter('scm_max_taxonomies_per_marker_type', fn () => 4).
     */
    public function maxTaxonomiesPerMarkerType(): int
    {
        return (int) \apply_filters('scm_max_taxonomies_per_marker_type', 2);
    }

    public function canAddTaxonomyForMarkerType(string $postType): bool
    {
        return \count($this->taxonomiesForMarkerType($postType)) < $this->maxTaxonomiesPerMarkerType();
    }

    /**
     * A WordPress taxonomy name is capped at 32 characters and must be
     * lowercase alphanumeric/underscore/dash.
     */
    public static function deriveTaxonomyKey(string $label): string
    {
        return \substr(\sanitize_key($label), 0, 32);
    }

    /**
     * Checked before addTaxonomy() so the admin screen can show one
     * error message covering every failure mode.
     */
    public function validateNewTaxonomy(string $postType, string $labelSingular, string $labelPlural): ?string
    {
        if (! isset($this->values['marker_types'][$postType])) {
            return \__('Please choose which marker type this taxonomy belongs to.', 'sc-maps');
        }

        if ($labelSingular === '' || $labelPlural === '') {
            return \__('Please enter both a singular and plural name.', 'sc-maps');
        }

        if (! $this->canAddTaxonomyForMarkerType($postType)) {
            return \sprintf(
                \__('This marker type already has the maximum of %d custom taxonomies.', 'sc-maps'),
                $this->maxTaxonomiesPerMarkerType()
            );
        }

        $taxonomy = self::deriveTaxonomyKey($labelSingular);

        if ($taxonomy === '') {
            return \__('That singular name can\'t be turned into a valid taxonomy — please use letters or numbers.', 'sc-maps');
        }

        if (\taxonomy_exists($taxonomy) || isset($this->values['taxonomies'][$taxonomy])) {
            return \__('That singular name is already in use — please try a different one.', 'sc-maps');
        }

        return null;
    }

    /**
     * @return string The new taxonomy's key.
     */
    public function addTaxonomy(string $postType, string $labelSingular, string $labelPlural, bool $hierarchical): string
    {
        $taxonomy = self::deriveTaxonomyKey($labelSingular);

        $this->values['taxonomies'][$taxonomy] = [
            'taxonomy' => $taxonomy,
            'post_type' => $postType,
            'label_singular' => $labelSingular,
            'label_plural' => $labelPlural,
            'hierarchical' => $hierarchical,
        ];

        \update_option(self::OPTION_KEY, $this->values);

        return $taxonomy;
    }

    /**
     * Unlike marker types, a taxonomy can be freely deleted — its
     * term relationships just stop being read once it's no longer
     * registered, which doesn't touch the posts themselves.
     */
    public function deleteTaxonomy(string $taxonomy): void
    {
        unset($this->values['taxonomies'][$taxonomy]);

        \update_option(self::OPTION_KEY, $this->values);
    }
}
