-- KBINE PLUS | Migration Phase 14 — champs de profil administrateur
-- (poste, coordonnées, date de naissance, pièces) — colonnes manquantes sur
-- `profiles`, jamais ajoutées jusqu'ici alors que le formulaire de création/
-- modification d'un compte administrateur (js/admin.js) les collecte déjà
-- depuis le début. Corrige le bug rapporté : un administrateur "Assistant
-- clientèle" créé ou modifié par le super admin voyait son poste et ses
-- permissions disparaître (jamais transmis au serveur, seulement mis à jour
-- dans le cache local de l'appareil du super admin) — voir aussi
-- admin_create_account.php / admin_update_profile.php (nouveau).
-- À coller une seule fois dans phpMyAdmin (onglet SQL) si la base existe déjà.

ALTER TABLE profiles
  ADD COLUMN poste          VARCHAR(64)  NULL AFTER paiement_abo,
  ADD COLUMN pays           VARCHAR(190) NULL AFTER poste,
  ADD COLUMN ville          VARCHAR(190) NULL AFTER pays,
  ADD COLUMN quartier       VARCHAR(190) NULL AFTER ville,
  ADD COLUMN date_naissance DATE         NULL AFTER quartier,
  ADD COLUMN docs           JSON         NULL AFTER date_naissance;
