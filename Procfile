web: php artisan config:clear && php artisan cache:clear && php artisan migrate --force && php -S 0.0.0.0:$PORT -t public
worker: php artisan queue:work