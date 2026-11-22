# Gestion autonome des adhérents d'une association.

## Fonctionnalités

- Saisie simplifiée d'un adhérent : personne physique ou morale (prénom, nom, société, email) et date d'adhésion.
- Calcul automatique de la fin d'adhésion (date d'adhésion + 1 an) et gestion des réadhésions (report de la période en cours).
- Recherche en direct des adhérents au fil de la saisie pour éviter les doublons.
- Fiche adhérent, édition et suppression.
- Aucune gestion de montant ni de paiement : les cotisations sont saisies directement dans Dolibarr.

## Stack

- Laravel 13 (PHP >= 8.3) — backend, base de données, authentification par session.
- Vite + Vue 3 + Tailwind CSS 4 — frontend.
- SQLite par défaut (fichier `database/database.sqlite`), migration vers MySQL possible via `.env`.

## Installation

```sh
composer install
npm install

cp .env.example .env
php artisan key:generate

touch database/database.sqlite
php artisan migrate --seed
```

Le compte de gestionnaire est créé par le seeder à partir des variables
`ADMIN_EMAIL` / `ADMIN_PASSWORD` (voir `.env.example`) — à personnaliser avant
la première exécution, sinon les identifiants par défaut sont
`admin@exemple.org` / `M1niAdherent!` (à changer impérativement).

## Développement

```sh
npm run dev        # build Vite en mode watch
php artisan serve  # http://localhost:8000
```

## Production

```sh
composer install --no-dev --optimize-autoloader
npm run build
php artisan config:cache route:cache view:cache
```

## Roadmap Dolibarr

L'application est volontairement autonome : les données adhérents vivent dans
sa propre base. Une future version pourra exporter ou synchroniser les
adhérents vers l'API REST de Dolibarr (modules Adhérents/Tiers), avec un suivi
d'état par adhérent pour absorber les indisponibilités de l'API.
