(function () {
    'use strict';

    function qs(id) {
        return document.getElementById(id);
    }

    /* ---- Leaflet map with geocoder search + click-to-set marker ---- */

    function initMap() {
        var container = qs('scm-map');

        if (!container || typeof L === 'undefined') {
            return;
        }

        var latInput = qs('scm_lat');
        var lngInput = qs('scm_lng');

        var lat = parseFloat(container.getAttribute('data-lat'));
        var lng = parseFloat(container.getAttribute('data-lng'));
        var hasLocation = !isNaN(lat) && !isNaN(lng);

        var map = L.map(container).setView(hasLocation ? [lat, lng] : [54.5, -3], hasLocation ? 15 : 6);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        var marker = hasLocation ? L.marker([lat, lng]).addTo(map) : null;

        function setLocation(latLng) {
            if (marker) {
                marker.setLatLng(latLng);
            } else {
                marker = L.marker(latLng).addTo(map);
            }

            latInput.value = latLng.lat.toFixed(6);
            lngInput.value = latLng.lng.toFixed(6);
        }

        if (L.Control && typeof L.Control.geocoder === 'function') {
            L.Control.geocoder({ defaultMarkGeocode: false })
                .on('markgeocode', function (event) {
                    var center = event.geocode.center;
                    map.setView(center, 16);
                    setLocation(center);
                })
                .addTo(map);
        }

        map.on('click', function (event) {
            setLocation(event.latlng);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMap);
    } else {
        initMap();
    }
})();
