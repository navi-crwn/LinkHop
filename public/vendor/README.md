# Vendored browser libraries

Self-hosted copies, so no page loads a script, stylesheet or image from a third-party CDN. Versions are pinned and match what the app used before (`layouts/app.blade.php`); licences sit next to each file.

| Folder | Package | Version | Licence |
|---|---|---|---|
| `chartjs/chart.umd.min.js` | chart.js | 4.4.0 | MIT |
| `chartjs/chartjs-plugin-zoom.min.js` | chartjs-plugin-zoom | 2.0.1 | MIT |
| `leaflet/` | leaflet | 1.9.4 | BSD-2-Clause |
| `qr-code-styling/qr-code-styling.js` | qr-code-styling | 1.6.0-rc.1 | MIT |

To update one: `npm install <package>@<version>` in a scratch folder and copy its `dist` file here (for Leaflet also `images/`), then change the version in the table.
| `alpine/alpine.min.js` | alpinejs | 3.15.2 | MIT |
| `geo/countries.geo.json` | world.geo.json (johan/world.geo.json) | master, 2026-11 | country outlines derived from Natural Earth (public domain); the repository states no licence of its own |

The analytics map still loads its background tiles from OpenStreetMap (`*.tile.openstreetmap.org`): that is the only third-party host a signed-in page contacts, and only when the map is shown.
