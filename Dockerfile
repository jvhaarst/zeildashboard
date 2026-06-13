# Standalone Rhine sailing-conditions dashboard.
#
# Stock multi-arch PHP image (arm64 for the Raspberry Pi k3s nodes). The curl
# and openssl extensions are enabled by default; the dashboards also have a
# stream-wrapper fallback, so no extra extensions are needed.
FROM php:8.5-apache

# Bake the standalone dashboard into the docroot. The directory layout must
# match what the code expects: examples/lib/data.php resolves
# ../../includes/class-assessment.php, i.e. /var/www/includes/class-assessment.php.
COPY rhine-sailing-conditions/examples/ /var/www/html/
COPY rhine-sailing-conditions/includes/class-assessment.php /var/www/includes/class-assessment.php

# Apache serves /var/www/html; index.php issues a 302 to the combined dashboard.
EXPOSE 80
