-- Phase 32 -- unicite du surnom client. Jusqu'ici, deux clients differents
-- pouvaient s'inscrire avec exactement le meme surnom (aucune contrainte,
-- ni cote application ni cote base). Colonne generee STORED : ne vaut
-- LOWER(TRIM(prenom)) que pour role='client' (NULL sinon), pour que la
-- contrainte UNIQUE ne s'applique qu'aux clients -- deux comptes cabine
-- (role='cabine', prenom = vrai prenom, pas un surnom choisi) peuvent
-- toujours partager le meme prenom sans collision, une cle UNIQUE
-- multiples NULL etant ignoree par MySQL/MariaDB. Voir create_account.php
-- (verification lisible avant insertion) et check_surnom.php (apercu en
-- direct a la saisie, js/client.js). A coller UNE SEULE FOIS dans
-- phpMyAdmin (onglet SQL) sur la base deja en place -- echouera si des
-- clients existants partagent deja le meme surnom (a nettoyer avant, le
-- cas echeant).

ALTER TABLE profiles
  ADD COLUMN client_prenom_key VARCHAR(190)
    GENERATED ALWAYS AS (CASE WHEN role = 'client' THEN LOWER(TRIM(prenom)) ELSE NULL END) STORED;

ALTER TABLE profiles ADD UNIQUE KEY uniq_client_prenom (client_prenom_key);
