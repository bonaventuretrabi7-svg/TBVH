-- Phase 27 -- alerte cabine avant réattribution automatique d'une commande
-- en attente (voir orders_sweep.php) : jusqu'ici, la cabine ne découvrait
-- le retard qu'après coup (commande déjà réattribuée). `alerte_envoyee`
-- marque qu'une notification d'avertissement a déjà été envoyée pour
-- l'attribution EN COURS (remise à 0 à chaque nouvelle attribution/
-- réattribution — voir orders_sweep.php/orders_reassign.php/orders_refuse.php).
-- A coller UNE SEULE FOIS dans phpMyAdmin (onglet SQL) sur la base déjà en place.

ALTER TABLE transactions
  ADD COLUMN alerte_envoyee TINYINT(1) NOT NULL DEFAULT 0 AFTER retard_logged_cabine_id;
