# Filesystem adapter for databases
This package contains a filesystem adapter to store files within the database.

## Installation
You can install the package via composer:
1. ``composer require ryangurnick/filesystem-database``
2. Setup your .env to have proper database configuration.
3. In the .env set ``FILESYSTEM_DISK=database``
4. ``php artisan migrate``
