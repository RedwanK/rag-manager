# Get started

## Project requirements

- Install php >=8.2
- Install composer & symfony-cli (not mandatory but recommanded)

Then check symfony requirements (especially php extensions and stuff like this) : `symfony check:requirements`

## Setup the project

- You'll need a doctrine compatible database system up and running. I'm using MySQL but Postgres is also fine.
- Then create your .env.local file and fill your informations : `$ cp .env .env.local` (mainly DATABASE_URL)
- Then create your database : `$ php bin/console doctrine:database:create`
- And run the migrations : `$ php bin/console doctrine:migration:migrate`
- And finally setup your own HTTP server or use Symfony's : `(inside project app dir)$ symfony serve` (For dev only)
