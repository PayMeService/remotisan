# Local Repository Setup for Testing

This document explains how to configure a local repository for testing instead of pulling from Git, and how to reflect changes in the main application.

## Initial Setup: Using Local Repository Instead of Git

### 1. Configure Composer to Use Local Repository

Add the local repository path to `composer.json`:

```json
"repositories": [
    {
        "type": "path",
        "url": "/path/to/your/local/package"
    },
    // ... other repositories
]
```

Set the package version to use the local version:
```json
"require": {
    "your-vendor/your-package": "dev-main"
}
```

### 2. Configure Docker Volume Mounts

Add the local repository path as a volume mount in your `docker-compose.yml`:

```yaml
php:
  volumes:
    - '.:/var/www'
    - '/var/www/.docker'
    - '/var/www/.git'
    - '/var/www/.idea'
    - './docker/data/php-logs:/var/log/app:rw'
    - '/path/to/your/local/package:/path/to/your/local/package'  # Add this line
```

### 3. Restart Docker Container

```bash
docker compose -f docker-compose.yml stop php
docker compose -f docker-compose.yml up -d php
```

### 4. Update Composer Dependencies

```bash
docker exec your-php-container composer update your-vendor/your-package --no-scripts
docker exec your-php-container composer dump-autoload
```

### 5. Build Frontend Assets (if needed)

If the package has frontend components:
```bash
npm run build --prefix /path/to/your/local/package
```

### 6. Clear Laravel Caches

```bash
docker exec your-php-container php /var/www/artisan config:clear
docker exec your-php-container php /var/www/artisan view:clear
docker exec your-php-container php /var/www/artisan cache:clear
```

## Reflecting Local Repository Changes in Main App

When you make changes to your local repository, here's what you need to do:

### For PHP Code Changes

**No action needed!** PHP changes are reflected immediately because:
- The volume mount creates a live symlink to your local files
- PHP doesn't compile/cache source code
- Changes are picked up on the next request

### For Frontend Assets (CSS/JS/React components)

**Rebuild the assets:**
```bash
npm run build --prefix /path/to/your/local/package
```

### For Configuration Changes

**Clear Laravel caches:**
```bash
docker exec your-php-container php /var/www/artisan config:clear
docker exec your-php-container php /var/www/artisan cache:clear
```

### For Database Migrations/Schema Changes

**Run migrations:**
```bash
docker exec your-php-container php /var/www/artisan migrate
```

### For Service Provider Changes

**Clear compiled services:**
```bash
docker exec your-php-container php /var/www/artisan clear-compiled
docker exec your-php-container composer dump-autoload
```

### For Route Changes

**Clear route cache:**
```bash
docker exec your-php-container php /var/www/artisan route:clear
```

### For View Changes

**Clear view cache:**
```bash
docker exec your-php-container php /var/www/artisan view:clear
```

## Quick Development Workflow

For most development work, you only need:

1. **Make changes** in `/path/to/your/local/package`
2. **If frontend changes**: `npm run build --prefix /path/to/your/local/package`
3. **If needed**: Clear relevant Laravel caches

## Key Points

- The local repository path must be accessible from both host and container
- Volume mounts are essential for Docker environments
- Frontend assets may need to be built separately
- Cache clearing helps ensure changes are recognized
- The symlink in `vendor/your-vendor/your-package` will point to your local development directory
- Most PHP changes are reflected immediately without any additional steps!

## Example Use Cases

This setup is particularly useful for:
- Testing changes to internal packages before publishing
- Developing multiple interconnected packages simultaneously
- Debugging issues in dependencies
- Contributing to open-source packages
- Local development of private packages

## Troubleshooting

### Common Issues

1. **File not found errors**: Ensure volume mounts are correctly configured and container is restarted
2. **CSS/JS not loading**: Run `npm run build` in the local repository
3. **Service provider not found**: Clear compiled services and dump autoload
4. **Routes not working**: Clear route cache
5. **Views not updating**: Clear view cache

### Verification Commands

Check if local repository is properly mounted:
```bash
docker exec your-php-container ls -la /path/to/your/local/package/src/
```

Verify symlink in vendor directory:
```bash
docker exec your-php-container ls -la /var/www/vendor/your-vendor/your-package
```

Test if service provider loads (example):
```bash
docker exec your-php-container php -r "require '/var/www/vendor/autoload.php'; echo class_exists('YourVendor\\YourPackage\\ServiceProvider') ? 'Found' : 'Not Found';"
```

## Alternative Approaches

### Using Composer's --prefer-source Flag

For simpler cases, you can also use:
```bash
composer install --prefer-source
```

This clones the Git repository instead of downloading archives, making it easier to make changes.

### Using Composer Link (Third-party tool)

Another option is using the `composer-link` plugin:
```bash
composer global require kylekatarnls/composer-link
composer link /path/to/your/local/package
```

However, the volume mount approach described above is more reliable for Docker environments.