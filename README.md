# Gestion autonome des adhérents d'une association.

## Fonctionnalités

- Saisie simplifiée d'un adhérent : personne physique ou morale (prénom, nom, pseudo, société, email) et date d'adhésion.
  Contraintes : ((prénom + nom) ou un pseudo) pour un physique, raison sociale pour un moral (prénom et nom restent
  affichés et optionnels pour un moral) ; l'email est optionnel.
  La date de création du membre en base est alignée sur la date d'adhésion saisie lors de la création.
- Deux modes de fin d'adhésion, paramétrables par le gestionnaire (page Paramètres) :
  - **date flottante** (défaut) : date d'adhésion + 1 an, réadhésion reportée depuis la période en cours ;
  - **période fixe annuelle** : deux dates paramétrables (format JJ/MM, défauts 31/07 et 01/06) —
    adhésion **avant** la date de bascule → fin de période de l'année en cours ;
    adhésion **à partir** de la date de bascule → fin de période de l'année suivante.
- Import / export CSV des membres (upsert par email, séparateur auto-détecté, UTF-8, dates AAAA-MM-JJ).
  Colonnes de dates : `created_at` (date de création) et `subscription_end` (fin d'adhésion) ; la date d'adhésion n'est pas saisie, elle est déduite de la fin d'adhésion moins un an (à défaut, la date de création sert de date d'adhésion).
- Recherche en direct des adhérents au fil de la saisie pour éviter les doublons.
- Blocage des doublons à la création comme à l'édition : pseudo identique refusé quand l'identité repose sur le pseudo seul (prénom et nom vides), email identique refusé (comparaison insensible à la casse). Un tip ambre « Membre déjà existant » s'affiche sous les champs concernés au fil de la saisie.
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
- export : `GET /members/export` (colonnes `morphy;firstname;lastname;pseudo;company;email;created_at;subscription_end`) ;
- import : `POST /members/import` (mêmes colonnes ; connexion par email → mise à jour si existant, sinon création ;
  la date d'adhésion est extrapolée par déduction d'un an depuis la fin d'adhésion ; si la fin est absente, la date
  de création sert de date d'adhésion et la fin est recalculée selon le mode courant ; les lignes en erreur sont
  signalées sans bloquer le reste).

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
