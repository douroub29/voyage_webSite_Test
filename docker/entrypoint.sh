#!/bin/sh
# Prépare l'application au démarrage du conteneur : attente de la base, création, migrations.
set -e

if [ "$1" = 'apache2-foreground' ]; then
    # Le code peut être monté depuis l'hôte (dev) : on s'assure que les dépendances sont là
    if [ "$APP_ENV" != 'prod' ] && [ ! -f vendor/autoload_runtime.php ]; then
        composer install --no-interaction --no-progress --prefer-dist
    fi

    echo 'Attente de la base de données...'
    tries=60
    until created=$(php bin/console doctrine:database:create --if-not-exists --no-interaction 2>&1); do
        tries=$((tries - 1))
        if [ "$tries" -le 0 ]; then
            echo "La base de données est injoignable : $created" >&2
            exit 1
        fi
        sleep 2
    done

    php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

    # Jeu de données de démonstration au premier démarrage (dev uniquement)
    if [ "$LOAD_FIXTURES" = '1' ] && [ "$APP_ENV" != 'prod' ] && echo "$created" | grep -q 'Created database'; then
        echo 'Base neuve : chargement des fixtures...'
        php bin/console doctrine:fixtures:load --no-interaction
    fi

    if [ "$APP_ENV" = 'prod' ]; then
        php bin/console cache:warmup
    fi

    mkdir -p var public/uploads
    chown -R www-data:www-data var public/uploads
fi

exec docker-php-entrypoint "$@"
