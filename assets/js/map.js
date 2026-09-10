(function () {
    'use strict';

    function buildIcon(marker) {
        var html = marker.icon
            ? '<span class="scm-marker-pin__icon" style="--scm-icon: url(' + JSON.stringify(marker.icon) + ');"></span>'
            : '<span class="scm-marker-pin__dot"></span>';

        return L.divIcon({
            className: 'scm-marker-pin',
            html: html,
            iconSize: [34, 34],
            iconAnchor: [17, 34],
            popupAnchor: [0, -34]
        });
    }

    function escapeHtml(value) {
        var div = document.createElement('div');
        div.textContent = value == null ? '' : String(value);

        return div.innerHTML;
    }

    function popupHtml(marker) {
        var html = '<div class="scm-popup">';

        if (marker.featured_image_url) {
            html += '<img src="' + escapeHtml(marker.featured_image_url) + '" alt="">';
        }

        html += '<h3>' + escapeHtml(marker.name) + '</h3>';

        if (marker.excerpt) {
            html += '<p>' + escapeHtml(marker.excerpt) + '</p>';
        }

        if (marker.view_url) {
            html += '<a href="' + escapeHtml(marker.view_url) + '">View ' + escapeHtml(marker.type_label).toLowerCase() + ' &rarr;</a>';
        }

        html += '</div>';

        return html;
    }

    // Toggles a marker's hovered look (pin turns white, name tooltip
    // shows) — pulled out of the mouseover/mouseout handlers below so
    // the exact same function can also be called from outside this
    // file (see leafletMarker.scmSetHover, attached further down),
    // for a page that wants some other element — a list item, say —
    // to drive a specific marker's hover state.
    function setHoverState(leafletMarker, pinEl, marker, isHovered) {
        if (pinEl) {
            pinEl.style.background = isHovered ? '#fff' : marker.colour;
        }

        if (isHovered) {
            leafletMarker.openTooltip();
        } else {
            leafletMarker.closeTooltip();
        }
    }

    function initMap(container) {
        var config;

        try {
            config = JSON.parse(container.getAttribute('data-config'));
        } catch (error) {
            return;
        }

        var markers = config.markers || [];

        var map = L.map(container, {
            scrollWheelZoom: false
        });

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        var bounds = [];

        // Keyed by marker post ID, and left on the container itself
        // (rather than some closure only this file can reach) so a
        // theme's own script can look up a specific marker — e.g. to
        // call .scmSetHover(true) on it when the visitor hovers a
        // matching item in a list next to the map. See the 'scm:ready'
        // event dispatched below for how to know when this is
        // populated and safe to read.
        var leafletMarkersById = {};

        markers.forEach(function (marker) {
            var icon = buildIcon(marker);
            var leafletMarker = L.marker([marker.lat, marker.lng], { icon: icon }).addTo(map);

            // divIcon has no first-class "background colour" option —
            // its className only reaches the wrapping <div>, not the
            // element built from html — so the colour is set directly
            // on that element once it exists in the DOM.
            var pinEl = leafletMarker.getElement();
            if (pinEl) {
                pinEl.style.background = marker.colour;
            }

            if (config.onClick !== 'none') {
                leafletMarker.bindPopup(popupHtml(marker));
            }

            if (config.onHover === 'tooltip') {
                leafletMarker.bindTooltip(escapeHtml(marker.name), {
                    direction: 'top',
                    offset: [0, -30],
                    className: 'scm-marker-tooltip'
                });

                leafletMarker.on('mouseover', function () {
                    setHoverState(leafletMarker, pinEl, marker, true);
                });

                leafletMarker.on('mouseout', function () {
                    setHoverState(leafletMarker, pinEl, marker, false);
                });

                // The external hook itself — see the comment on
                // leafletMarkersById above.
                leafletMarker.scmSetHover = function (isHovered) {
                    setHoverState(leafletMarker, pinEl, marker, isHovered);
                };
            }

            leafletMarkersById[marker.id] = leafletMarker;
            bounds.push([marker.lat, marker.lng]);
        });

        container.scmMap = map;
        container.scmMarkers = leafletMarkersById;
        container.dispatchEvent(new CustomEvent('scm:ready', {
            bubbles: true,
            detail: { map: map, markers: leafletMarkersById }
        }));

        if (config.center) {
            map.setView([config.center.lat, config.center.lng], config.zoom || 15);
        } else if (bounds.length === 1) {
            map.setView(bounds[0], config.zoom || 15);
        } else if (bounds.length > 1) {
            map.fitBounds(bounds, { padding: [30, 30] });
        } else {
            map.setView([54.5, -3], config.zoom || 6);
        }
    }

    function init() {
        document.querySelectorAll('.scm-map-display').forEach(initMap);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
