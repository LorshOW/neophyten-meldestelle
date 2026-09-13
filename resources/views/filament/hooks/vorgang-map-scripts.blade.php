{{--
    Leaflet and the Alpine component must live on the panel layout.

    Livewire does not execute <script> tags it injects into the DOM, so defining
    vorgangMap() next to the map container (modals, slide-overs, form re-renders)
    leaves Alpine with "vorgangMap is not defined".
--}}
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    (function () {
        function register() {
            if (!window.Alpine || window.Alpine.data === undefined) {
                return;
            }

            if (window.__vorgangMapRegistered) {
                return;
            }

            window.__vorgangMapRegistered = true;

            window.Alpine.data('vorgangMap', function (geometry, lat, lon) {
                return {
                    init() {
                        this.$nextTick(() => this.whenReady());
                    },

                    whenReady() {
                        const container = this.$refs.map;

                        if (!container) {
                            return;
                        }

                        if (typeof L === 'undefined') {
                            setTimeout(() => this.whenReady(), 50);

                            return;
                        }

                        const start = () => {
                            if (container._leaflet_id) {
                                return;
                            }

                            this.build(container);
                        };

                        const observer = new IntersectionObserver((entries) => {
                            for (const entry of entries) {
                                if (!entry.isIntersecting) {
                                    continue;
                                }

                                observer.disconnect();
                                start();
                            }
                        });

                        observer.observe(container);

                        // Modals and slide-overs can report 0×0 for a frame;
                        // if IntersectionObserver never fires, still try once.
                        setTimeout(() => {
                            if (!container._leaflet_id && container.offsetWidth > 0) {
                                observer.disconnect();
                                start();
                            }
                        }, 400);
                    },

                    build(container) {
                        const map = L.map(container);

                        map.setView([lat ?? 50.7, lon ?? 12.95], 15);

                        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            maxZoom: 19,
                            attribution: '&copy; OpenStreetMap contributors',
                        }).addTo(map);

                        let bounds = null;

                        if (geometry) {
                            bounds = L.geoJSON(geometry, {
                                style: { color: '#2f6d3e', weight: 2, fillOpacity: 0.25 },
                            }).addTo(map).getBounds();
                        }

                        if (lat !== null && lon !== null) {
                            L.circleMarker([lat, lon], {
                                radius: 9,
                                weight: 3,
                                color: '#2f6d3e',
                                fillOpacity: 0.85,
                            }).addTo(map);
                        }

                        const fit = () => {
                            map.invalidateSize();

                            if (bounds && bounds.isValid()) {
                                map.fitBounds(bounds, { padding: [30, 30], maxZoom: 17 });
                            } else if (lat !== null && lon !== null) {
                                map.setView([lat, lon], 16);
                            } else {
                                map.setView([50.7, 12.95], 11);
                            }
                        };

                        fit();
                        requestAnimationFrame(fit);

                        new ResizeObserver(() => map.invalidateSize()).observe(container);
                    },
                };
            });
        }

        if (window.Alpine) {
            register();
        } else {
            document.addEventListener('alpine:init', register);
        }
    })();
</script>
