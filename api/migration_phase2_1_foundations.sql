-- KBINE PLUS | Phase 2, étape 1/6 — Fondations.
-- À coller UNE SEULE FOIS dans phpMyAdmin (onglet SQL) sur la base déjà en
-- place (schema.sql initial déjà exécuté) : ajoute les colonnes `profiles`
-- que la logique métier lit déjà côté client, et crée à vide toutes les
-- tables des étapes suivantes (2 à 6) — aucun comportement visible ne
-- change tant que les endpoints correspondants n'existent pas encore.
--
-- Pour une INSTALLATION NEUVE : pas besoin de ce fichier séparément, son
-- contenu est déjà intégré dans schema.sql.

-- ── Colonnes manquantes sur `profiles` (cabine) ─────────────────────────
ALTER TABLE profiles
  ADD COLUMN en_pause            TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN reseaux_actifs      JSON       NULL,
  ADD COLUMN services_actifs     JSON       NULL,
  ADD COLUMN commandes_renvoyees INT        NOT NULL DEFAULT 0,
  ADD COLUMN remboursements_recus INT       NOT NULL DEFAULT 0;

-- ── Présence en ligne (remplace le Map localStorage par appareil) ──────
CREATE TABLE IF NOT EXISTS presence (
  profile_id    CHAR(36)  NOT NULL PRIMARY KEY,
  last_seen_at  DATETIME  NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Transactions (commandes) — cœur du moteur d'attribution ────────────
CREATE TABLE IF NOT EXISTS transactions (
  id                            CHAR(36)     NOT NULL PRIMARY KEY,
  client_id                     CHAR(36)     NULL,
  cabine_id                     CHAR(36)     NULL,
  type                          VARCHAR(32)  NULL,
  service                       VARCHAR(64)  NULL,
  operateur                     VARCHAR(32)  NULL,
  numero_beneficiaire           VARCHAR(32)  NULL,
  montant                       BIGINT       NOT NULL DEFAULT 0,
  frais_service                 BIGINT       NOT NULL DEFAULT 0,
  commission                    BIGINT       NOT NULL DEFAULT 0,
  statut                        VARCHAR(32)  NOT NULL DEFAULT 'en_attente',
  moyen_paiement                VARCHAR(64)  NULL,
  numero_paiement                VARCHAR(64)  NULL,
  details                       JSON         NULL,
  notes                         TEXT         NULL,
  date                          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  date_assignation               DATETIME     NULL,
  date_fin                      DATETIME     NULL,
  date_suspension                DATETIME     NULL,
  date_remboursement            DATETIME     NULL,
  preuve_paiement                LONGTEXT     NULL,
  retard_logged_cabine_id        CHAR(36)     NULL,
  dernier_renvoi_motif           VARCHAR(64)  NULL,
  dernier_renvoi_justification   TEXT         NULL,
  dernier_renvoi_date            DATETIME     NULL,
  dernier_renvoi_cabine_id       CHAR(36)     NULL,
  statut_avant_suspension        VARCHAR(32)  NULL,
  motif_suspension                TEXT         NULL,
  KEY idx_txn_client (client_id),
  KEY idx_txn_cabine (cabine_id),
  KEY idx_txn_statut (statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Retraits de commission (cabiniste) ──────────────────────────────────
CREATE TABLE IF NOT EXISTS retraits (
  id               CHAR(36)     NOT NULL PRIMARY KEY,
  cabine_id        CHAR(36)     NOT NULL,
  montant          BIGINT       NOT NULL,
  statut           VARCHAR(32)  NOT NULL DEFAULT 'en_attente',
  methode_retrait  VARCHAR(64)  NULL,
  numero_paiement  VARCHAR(64)  NULL,
  type             VARCHAR(32)  NULL,
  motif            TEXT         NULL,
  date             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_retraits_cabine (cabine_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Retards (historique des commandes réattribuées pour dépassement) ───
CREATE TABLE IF NOT EXISTS retards (
  id                        CHAR(36)   NOT NULL PRIMARY KEY,
  transaction_id            CHAR(36)   NOT NULL,
  cabine_id                 CHAR(36)   NOT NULL,
  date                      DATETIME   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reassigned_to_cabine_id   CHAR(36)   NULL,
  triggered_suspension      TINYINT(1) NOT NULL DEFAULT 0,
  KEY idx_retards_cabine (cabine_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Renvois manuels de commande (historique horodaté) ───────────────────
CREATE TABLE IF NOT EXISTS cabine_refusals (
  id          CHAR(36)  NOT NULL PRIMARY KEY,
  cabine_id   CHAR(36)  NOT NULL,
  date        DATETIME  NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_refusals_cabine (cabine_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Transferts cabine-à-cabine ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS transferts_cabine (
  id             CHAR(36)  NOT NULL PRIMARY KEY,
  from_cabine_id CHAR(36)  NOT NULL,
  to_cabine_id   CHAR(36)  NOT NULL,
  montant        BIGINT    NOT NULL,
  frais          BIGINT    NOT NULL DEFAULT 0,
  date           DATETIME  NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_transferts_from (from_cabine_id),
  KEY idx_transferts_to (to_cabine_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Forfaits (catalogue Orange/MTN/Moov) — champs affinés à l'étape 6 ──
CREATE TABLE IF NOT EXISTS forfaits (
  id             CHAR(36)     NOT NULL PRIMARY KEY,
  operateur      VARCHAR(32)  NOT NULL,
  categorie      VARCHAR(64)  NULL,
  nom            VARCHAR(190) NULL,
  prix           BIGINT       NULL,
  ussd_template  VARCHAR(255) NULL,
  verified       TINYINT(1)   NOT NULL DEFAULT 1,
  details        JSON         NULL,
  KEY idx_forfaits_operateur (operateur)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Notifications ────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS notifications (
  id              CHAR(36)     NOT NULL PRIMARY KEY,
  utilisateur_id  CHAR(36)     NOT NULL,
  message         TEXT         NOT NULL,
  lu              TINYINT(1)   NOT NULL DEFAULT 0,
  date            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  type            VARCHAR(32)  NOT NULL DEFAULT 'info',
  KEY idx_notif_user (utilisateur_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Réclamations ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS reclamations (
  id                      CHAR(36)     NOT NULL PRIMARY KEY,
  transaction_id          CHAR(36)     NOT NULL,
  client_id               CHAR(36)     NOT NULL,
  cabine_id               CHAR(36)     NULL,
  motif                   TEXT         NULL,
  statut                  VARCHAR(32)  NOT NULL DEFAULT 'en_attente',
  screenshot               LONGTEXT     NULL,
  date_created             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  date_resolved            DATETIME     NULL,
  relances_apres_preuve    INT          NOT NULL DEFAULT 0,
  confirmed_by_client      TINYINT(1)   NULL,
  KEY idx_recl_transaction (transaction_id),
  KEY idx_recl_client (client_id),
  KEY idx_recl_cabine (cabine_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Messages de réclamation (normalisé, remplace messages[] embarqué) ──
CREATE TABLE IF NOT EXISTS reclamation_messages (
  id              CHAR(36)     NOT NULL PRIMARY KEY,
  reclamation_id  CHAR(36)     NOT NULL,
  sender          VARCHAR(32)  NOT NULL,
  type            VARCHAR(32)  NOT NULL DEFAULT 'texte',
  texte           TEXT         NULL,
  image           LONGTEXT     NULL,
  date            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_reclmsg_reclamation (reclamation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Demandes de remboursement (soumises par la cabine) ──────────────────
CREATE TABLE IF NOT EXISTS refund_requests (
  id               CHAR(36)     NOT NULL PRIMARY KEY,
  reclamation_id   CHAR(36)     NOT NULL,
  transaction_id   CHAR(36)     NOT NULL,
  cabine_id        CHAR(36)     NOT NULL,
  client_id        CHAR(36)     NOT NULL,
  motif            TEXT         NULL,
  statut           VARCHAR(32)  NOT NULL DEFAULT 'en_attente',
  date_created      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  date_traitement   DATETIME     NULL,
  processed_by     CHAR(36)     NULL,
  KEY idx_rfr_reclamation (reclamation_id),
  KEY idx_rfr_cabine (cabine_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Numéros favoris (client) ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS favoris (
  id             CHAR(36)  NOT NULL PRIMARY KEY,
  client_id      CHAR(36)  NOT NULL,
  nom            VARCHAR(190) NULL,
  numero         VARCHAR(32)  NOT NULL,
  date_creation  DATETIME  NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_favoris_client (client_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Règle de commission active ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS commissions (
  id           CHAR(36)      NOT NULL PRIMARY KEY,
  pourcentage  DECIMAL(6,2)  NOT NULL DEFAULT 5,
  actif        TINYINT(1)    NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Journal des accès admin (impersonation) — lecture seule ─────────────
CREATE TABLE IF NOT EXISTS access_logs (
  id              CHAR(36)     NOT NULL PRIMARY KEY,
  admin_id        CHAR(36)     NOT NULL,
  admin_name      VARCHAR(190) NULL,
  target_user_id  CHAR(36)     NULL,
  target_role     VARCHAR(16)  NULL,
  target_name     VARCHAR(190) NULL,
  date            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Journal des permissions cabine — lecture seule ──────────────────────
CREATE TABLE IF NOT EXISTS permission_logs (
  id           CHAR(36)     NOT NULL PRIMARY KEY,
  admin_id     CHAR(36)     NOT NULL,
  admin_name   VARCHAR(190) NULL,
  cabine_id    CHAR(36)     NOT NULL,
  cabine_name  VARCHAR(190) NULL,
  service      VARCHAR(64)  NULL,
  active       TINYINT(1)   NULL,
  date         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_permlog_cabine (cabine_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Journal de maintenance (service/réseau) — lecture seule ─────────────
CREATE TABLE IF NOT EXISTS maintenance_logs (
  id           CHAR(36)     NOT NULL PRIMARY KEY,
  admin_id     CHAR(36)     NOT NULL,
  admin_name   VARCHAR(190) NULL,
  action       VARCHAR(64)  NULL,
  `key`        VARCHAR(64)  NULL,
  active       TINYINT(1)   NULL,
  service      VARCHAR(64)  NULL,
  message      TEXT         NULL,
  date         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Historique des suspensions cabine — lecture seule ───────────────────
CREATE TABLE IF NOT EXISTS suspension_logs (
  id               CHAR(36)     NOT NULL PRIMARY KEY,
  cabine_id        CHAR(36)     NOT NULL,
  motif            TEXT         NULL,
  auto             TINYINT(1)   NOT NULL DEFAULT 0,
  date_debut        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  date_fin_prevue   DATETIME     NULL,
  date_levee        DATETIME     NULL,
  levee_par        VARCHAR(64)  NULL,
  KEY idx_susplog_cabine (cabine_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Réabonnements cabine — lecture seule ────────────────────────────────
CREATE TABLE IF NOT EXISTS resubscriptions (
  id          CHAR(36)     NOT NULL PRIMARY KEY,
  cabine_id   CHAR(36)     NOT NULL,
  formule     VARCHAR(32)  NOT NULL,
  prix        BIGINT       NOT NULL,
  date        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_resub_cabine (cabine_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
