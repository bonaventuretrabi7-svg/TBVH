-- Phase 36 -- index manquants sur profiles/transactions pour les listes de
-- l'espace administrateur (list_profiles.php filtre deja par role, orders_list.php
-- trie par date -- sans index, MySQL parcourt toute la table a chaque appel,
-- meme filtre). Idempotent (IF NOT EXISTS), a coller UNE SEULE FOIS dans
-- phpMyAdmin (onglet SQL) sur la base deja en place.

ALTER TABLE profiles
  ADD INDEX IF NOT EXISTS idx_profiles_role (role),
  ADD INDEX IF NOT EXISTS idx_profiles_statut (statut);

ALTER TABLE transactions
  ADD INDEX IF NOT EXISTS idx_txn_type (type),
  ADD INDEX IF NOT EXISTS idx_txn_date (date);
