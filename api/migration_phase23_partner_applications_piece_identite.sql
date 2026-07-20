-- Phase 23 -- ajoute la pièce d'identité (recto/verso) aux candidatures
-- partenaire. Jusqu'ici le formulaire de candidature (js/client.js,
-- prgSubmit()) exigeait ces deux fichiers mais ne les envoyait jamais au
-- serveur (bug corrigé en même temps que cette migration) : l'administration
-- n'avait donc jamais accès à la pièce fournie par le candidat. A coller
-- UNE SEULE FOIS dans phpMyAdmin (onglet SQL) sur la base déjà en place.

ALTER TABLE partner_applications
  ADD COLUMN IF NOT EXISTS piece_recto LONGTEXT NULL AFTER photo,
  ADD COLUMN IF NOT EXISTS piece_verso LONGTEXT NULL AFTER piece_recto;
