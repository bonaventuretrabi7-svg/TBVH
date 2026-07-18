-- KBINE PLUS | Phase 6/6 — colonnes manquantes sur `forfaits`/`commissions`.
-- À coller UNE SEULE FOIS dans phpMyAdmin (onglet SQL) sur la base déjà en
-- place. Ces deux tables avaient été créées à vide dès la Phase 1
-- (fondations) mais avec un schéma provisoire — les vrais champs lus/écrits
-- par js/admin.js (forfaits : detail, duree ; commissions : label,
-- montant_min, montant_max, date) n'étaient pas encore connus à ce moment.

ALTER TABLE forfaits
  ADD COLUMN detail TEXT        NULL,
  ADD COLUMN duree  VARCHAR(64) NULL;

ALTER TABLE commissions
  ADD COLUMN label        VARCHAR(190)  NULL,
  ADD COLUMN montant_min  BIGINT        NULL,
  ADD COLUMN montant_max  BIGINT        NULL,
  ADD COLUMN date         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP;

-- Règle de commission par défaut (même contenu que le seed local, voir
-- js/db.js) — n'a d'effet que si la table est encore vide (une base déjà
-- utilisée ne doit jamais être réinitialisée par cette migration).
INSERT INTO commissions (id, label, pourcentage, montant_min, montant_max, actif, date)
SELECT UUID(), 'Commission standard', 5, 0, 99999, 1, '2024-01-01 00:00:00'
WHERE NOT EXISTS (SELECT 1 FROM commissions);
