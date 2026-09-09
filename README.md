# Rhine Sailing Conditions

Live sailing conditions for the Rhine between Driel-Boven and Arnhem Nederrijn
(WSV Peter Hensen). Two deliverables live in this repo:

- **WordPress plugin** — `rhine-sailing-conditions/`. A `[rhine-sailing-conditions]`
  shortcode showing current wind and water, a 6-hour forecast, and a sailing
  recommendation. See [its README](rhine-sailing-conditions/README.md).
- **Standalone dashboards** — `rhine-sailing-conditions/examples/`. The same data
  as plain PHP pages, deployed as a public website.

**Live site:** https://zeilweer.vanhaarst.net

## Data sources

- Wind + precipitation forecast: **Open-Meteo** (free, no auth)
- Water level / current speed / temperature: **Rijkswaterstaat DDAPI** (`driel.boven`)

The qualitative sailing assessment lives in one place —
`rhine-sailing-conditions/includes/class-assessment.php` — so the plugin and the
standalone dashboards share identical thresholds and never drift.

## Container image & deployment

`.github/workflows/docker-publish.yaml` bakes the standalone dashboard into the
stock multi-arch `php:*-apache` image (amd64 + arm64) and publishes it to
**`ghcr.io/jvhaarst/zeildashboard`**, auto-versioned `1.4.<run_number>` + `latest`.

The Helm chart that runs it on k3s lives in a separate repo —
**[zeildashboard_k8s](https://github.com/jvhaarst/zeildashboard_k8s)** — published
as a GitHub Pages Helm repository and added as a chart repository in Rancher.
Renovate keeps both the base image and the chart current, so a code change or a
`php:*-apache` release flows automatically to the live site:

```
code / base-image change → new ghcr image → Renovate bumps the chart → Rancher deploys
```

## Local development

Run the standalone dashboards without WordPress (PHP 7.4+ with outbound internet):

```bash
cd rhine-sailing-conditions/examples && php -S localhost:8765
```

Dutch by default; `RSC_LANG=fy php -S localhost:8765` to switch language.

## License

GPL2
