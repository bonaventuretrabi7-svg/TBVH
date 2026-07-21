-- Phase 28 -- historique des refus manuels ("Ramener") d'une commande par
-- une cabine, avec motif — jusqu'ici, seul le DERNIER refus était gardé
-- (transactions.dernier_renvoi_*), impossible de savoir combien de fois ni
-- par qui une commande avait déjà été refusée. Sert désormais à la fois à
-- compter les refus (voir orders_refuse.php : au 3e refus, la commande
-- n'est plus réattribuée automatiquement) et à afficher l'historique complet
-- dans le nouvel onglet admin "REM-RETA" (api/orders_rem_reta_list.php).
-- A coller UNE SEULE FOIS dans phpMyAdmin (onglet SQL) sur la base déjà en place.

CREATE TABLE IF NOT EXISTS commande_refus (
  id             CHAR(36)     NOT NULL PRIMARY KEY,
  transaction_id CHAR(36)     NOT NULL,
  cabine_id      CHAR(36)     NOT NULL,
  motif          VARCHAR(64)  NULL,
  justification  TEXT         NULL,
  date           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_transaction (transaction_id),
  KEY idx_cabine (cabine_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
