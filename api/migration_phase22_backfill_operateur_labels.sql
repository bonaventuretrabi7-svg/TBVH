-- Phase 22 -- remplace, pour les transactions DÉJÀ existantes, les valeurs
-- vides de "réseau"/"numéro bénéficiaire" par un libellé explicite pour
-- les recharges de portefeuille et les transferts client-à-client (voir
-- api/orders_recharge.php/api/client_transfer.php, corrigés pour que les
-- PROCHAINES transactions de ce type l'aient déjà dès leur création). A
-- coller UNE SEULE FOIS dans phpMyAdmin (onglet SQL) sur la base deja en
-- place -- sans effet sur les transactions qui ont déjà un réseau renseigné.

UPDATE transactions
SET operateur = 'Auto recharge', numero_beneficiaire = 'Auto recharge'
WHERE type = 'recharge' AND (operateur IS NULL OR operateur = '');

UPDATE transactions
SET operateur = 'send-client'
WHERE type IN ('transfert_client_envoi', 'transfert_client_reception')
  AND (operateur IS NULL OR operateur = '');
