-- Phase 15 -- liens de connexion sans mot de passe pour un administrateur
-- simple, generes par le super admin (voir api/admin_create_login_link.php
-- et api/admin_magic_login.php). A coller UNE SEULE FOIS dans phpMyAdmin
-- (onglet SQL) sur la base deja en place.

CREATE TABLE IF NOT EXISTS admin_login_links (
  id          CHAR(36)  NOT NULL PRIMARY KEY,
  admin_id    CHAR(36)  NOT NULL,
  token_hash  CHAR(64)  NOT NULL,
  expires_at  DATETIME  NOT NULL,
  used_at     DATETIME  NULL,
  created_by  CHAR(36)  NOT NULL,
  created_at  DATETIME  NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_admin (admin_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
