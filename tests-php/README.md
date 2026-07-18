# Suite de tests PHP (logique métier réelle)

Contrairement aux tests JS (`tests/`, `npm test`), qui vérifient seulement le
transport `js/db.js` <-> `ServerAPI` (mocks), cette suite tape les VRAIS
fichiers `api/*.php` en HTTP, via le serveur intégré de PHP, contre une VRAIE
base MariaDB locale — c'est la seule façon de couvrir les règles métier qui
vivent uniquement côté serveur (CAS de concurrence, calcul de commission,
effets financiers d'un remboursement...).

## Prérequis locaux (une fois)

- PHP CLI (≥ 8.3, extensions `pdo_mysql`/`mysqli`/`curl`/`zip` activées)
- Un serveur MariaDB/MySQL local, base vide nommée `kbineplus_test`
- Composer (`vendor/phpunit/phpunit` installé via `composer install`)
- `api/config.php` (jamais commité, voir `.gitignore`) pointant vers cette
  base de test :

  ```php
  <?php
  define('DB_HOST', '127.0.0.1');
  define('DB_NAME', 'kbineplus_test');
  define('DB_USER', 'root');
  define('DB_PASS', '');
  ```

- Le schéma chargé une fois dans `kbineplus_test` : coller `api/schema.sql`
  (déjà à jour, inclut toutes les phases) via `mysql -u root kbineplus_test <
  api/schema.sql`.

## Lancer la suite

```
php vendor/bin/phpunit
```

`tests-php/bootstrap.php` démarre automatiquement `php -S 127.0.0.1:8091 -t
api` (le serveur intégré PHP, pointant sur le dossier `api/` réel) au début
du run et l'arrête à la fin — aucune étape manuelle. Chaque test repart d'une
base vidée (`Fixtures::reset()`, voir `ApiTestCase::setUp()`), donc l'ordre
d'exécution n'a pas d'importance.

## Ce que ça couvre (et pas les tests JS)

- CAS de propriété (`orders_accept.php`/`orders_refuse.php`) : une cabine ne
  peut jamais agir sur la commande d'une autre.
- Non-double-comptage de commission (bug historique corrigé).
- Ordre d'évaluation MySQL d'un `SET` multi-colonnes (`orders_suspend.php`).
- Faux-négatif `rowCount()` sur une mise à jour "no-op" (`admin_set_abonnement.php`).
- Effet financier réel d'un remboursement (pénalité, planchers `GREATEST(0, ...)`).
- Restrictions de rôle (super admin uniquement) réellement appliquées
  côté serveur, pas seulement côté client.
- Blocage de compte après 3 PIN incorrects, jamais pour le super admin.

Ce que ça NE couvre PAS : la vraie interleaving concurrente (le serveur
intégré PHP est mono-thread sous Windows) — les tests "concurrence" ici
prouvent la garde CAS en rejouant les appels en séquence (le second appel
sur un état déjà modifié doit échouer proprement), ce qui suffit à valider
la clause `WHERE` puisque l'atomicité vient de l'instruction SQL elle-même,
pas de l'ordonnancement applicatif.
