# Gestion autonome des adhérents d'une association.

## Fonctionnalités

- Saisie simplifiée d'un adhérent : personne physique ou morale (prénom, nom, société, email) et date d'adhésion.
- Deux modes de fin d'adhésion, paramétrables par le gestionnaire (page Paramètres) :
  - **date flottante** (défaut) : date d'adhésion + 1 an, réadhésion reportée depuis la période en cours ;
  - **période fixe annuelle** : fin au 31 juillet suivant la date d'adhésion.
- Import / export CSV des membres (upsert par email, séparateur auto-détecté, UTF-8, dates AAAA-MM-JJ).
- Recherche en direct des adhérents au fil de la saisie pour éviter les doublons.
- Fiche adhérent, édition et suppression.
- Aucune gestion de montant ni de paiement : les cotisations sont saisies directement dans Dolibarr.

## Stack

- Laravel 13 (PHP >= 8.3) — backend, base de données, authentification par session.
- Vite + Vue 3 + Tailwind CSS 4 — frontend.
- SQLite par défaut (fichier `database/database.sqlite`), migration vers MySQL possible via `.env`.

## Démarrage rapide

```sh
# 1. Dépendances PHP et JS
composer install
npm install

# 2. Configuration
cp .env.example .env
php artisan key:generate

# 3. Base de données
touch database/database.sqlite
php artisan migrate --seed

# 4. Build des assets front (obligatoire avant le premier lancement)
npm run build

# 5. Lancement
php artisan serve   # http://localhost:8000
```

Le compte de gestionnaire est créé par le seeder à partir des variables
`ADMIN_EMAIL` / `ADMIN_PASSWORD` (voir `.env.example`) — à personnaliser avant
la première exécution, sinon les identifiants par défaut sont
`admin@exemple.org` / `M1niAdherent!` (à changer impérativement).

Après toute modification de ces variables, réexécutez le seeder — il garantit
qu'un seul compte de gestionnaire existe, celui du `.env` :

```sh
php artisan db:seed --force
```

## Développement

```sh
npm run dev        # build Vite en mode watch (bundleling à chaud, remplace npm run build)
php artisan serve  # http://localhost:8000
```

`npm run dev` doit rester actif pendant le développement : sans lui et sans build
préalable, les pages renvoient l'exception `ViteManifestNotFoundException`.

## Paramètres et CSV

La page **Paramètres** (lien en haut à droite, réservée à la session connectée) permet de basculer entre
les deux modes de calcul et d'importer/exporter les membres :
- export : `GET /members/export` (colonnes `morphy;firstname;lastname;company;email;subscription_date;subscription_end`) ;
- import : `POST /members/import` (mêmes colonnes ; connexion par email → mise à jour si existant, sinon création ; les lignes en erreur sont signalées sans bloquer le reste).

Le changement de mode s'applique aux adhésions saisies après la bascule ; les dates déjà enregistrées ne sont pas recalculées.

## Dépannage

**`ViteManifestNotFoundException` (manifest.json introuvable)** : les assets front
ne sont pas compilés. Lancez `npm run build` (production) ou gardez
`npm run dev` actif (développement), puis rechargez la page.

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
