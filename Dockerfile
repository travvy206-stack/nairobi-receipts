FROM php:8.2-cli-bookworm

# Prevent interactive prompts
ENV DEBIAN_FRONTEND=noninteractive

# Install system dependencies, Python 3, pip, supervisor, sqlite3, curl
RUN apt-get update && apt-get install -y --no-install-recommends \
    python3 \
    python3-pip \
    python3-venv \
    sqlite3 \
    libsqlite3-dev \
    supervisor \
    curl \
    git \
    && docker-php-ext-install pdo pdo_sqlite pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

# Setup Python virtual environment
RUN python3 -m venv /opt/venv
ENV PATH="/opt/venv/bin:$PATH"

# Install Python requirements
COPY service_pdf/requirements.txt ./service_pdf/requirements.txt
RUN pip install --no-cache-dir -r service_pdf/requirements.txt

# Copy all application code
COPY . .

# Setup directories and permissions
RUN mkdir -p /var/www/html/service_pdf/storage \
    && mkdir -p /var/www/html/db \
    && chmod -R 777 /var/www/html/service_pdf/storage \
    && chmod -R 777 /var/www/html/db

# Copy docker configs
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Environment settings
ENV PORT=8000
EXPOSE 8000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
