# Development image only: the source is bind-mounted and nothing is baked in.
# The production image (multi-stage, no dev dependencies) is part of P4.
FROM php:8.4-cli

# install-php-extensions resolves each extension's system libraries for us,
# which keeps this file short and the image free of build toolchains.
COPY --from=ghcr.io/mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_pgsql redis bcmath intl gd zip pcntl

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Run as a non-root user whose UID/GID match the host user,
# so files created in the bind mount (vendor/, storage/) stay owned by you.
ARG UID=1000
ARG GID=1000
# The GID may already exist in the base image (macOS's "staff" is 20, which is
# "dialout" in Debian), so only create the group when it's missing.
RUN (getent group "${GID}" || groupadd --gid "${GID}" app) \
    && useradd --uid "${UID}" --gid "${GID}" --create-home app

USER app
WORKDIR /app
