# Standalone Rhine sailing-conditions dashboard.
#
# Stock multi-arch PHP image (arm64 for the Raspberry Pi k3s nodes). The curl
# and openssl extensions are enabled by default; the dashboards also have a
# stream-wrapper fallback, so no extra extensions are needed.
FROM php:8.5-apache@sha256:4cc63b3d4e05c40da73e7af5d39364ca2678ac07208919497bda3bbab975be95

# CVE-2026-111497, the out-of-bounds write in the Brotli C decoder: the
# digest-pinned base carries libbrotli1 1.1.0-2+b7, while the fix is
# 1.1.0-2+deb13u1 in trixie-security, which this image already has enabled.
# Apache never brotli-decodes a request body, so nothing inbound reaches the
# decoder -- this closes the outbound libcurl path only. Drop it once the base
# rebuilds past the fix.
RUN apt-get update \
    && apt-get install -y --no-install-recommends libbrotli1 \
    && rm -rf /var/lib/apt/lists/*

# Bake the standalone dashboard into the docroot. The directory layout must
# match what the code expects: examples/lib/data.php resolves
# ../../includes/class-assessment.php, i.e. /var/www/includes/class-assessment.php.
COPY rhine-sailing-conditions/examples/ /var/www/html/
COPY rhine-sailing-conditions/includes/class-assessment.php /var/www/includes/class-assessment.php

# Apache serves /var/www/html; index.php issues a 302 to the combined dashboard.
EXPOSE 80
