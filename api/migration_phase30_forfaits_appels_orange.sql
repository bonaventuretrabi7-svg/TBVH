-- KBINE PLUS | Phase 30 — Catalogue Orange "Appels" (Pass Mix 1-3/5-7/30
-- jours) et Pass International (Burkina Faso, Mali, Sénégal, Guinée
-- Conakry, Niger, Nigéria, Amérique/Asie/Europe).
-- À coller UNE SEULE FOIS dans phpMyAdmin (onglet SQL) sur la base déjà en
-- place — ce catalogue n'existait jusqu'ici que dans le seed local
-- (js/db.js, migrateForfaitsSeed()), jamais inséré côté production (le
-- seed ne s'applique qu'à un appareil qui n'a encore aucun forfait en
-- cache local, jamais à la base serveur). INSERT IGNORE : sans effet si
-- rejoué.
--
-- verified=0 sur 'omx4' (Pass mix Veedz 600 F) et 'oae3' (Pass Amérique/
-- Asie/Europe 2 000 F) — déjà marqués non vérifiés dans le seed d'origine
-- (USSD à confirmer, notamment 'omx4' qui partage le même code que
-- 'omx3' et 'oae3' qui partage le même code que 'oae2').

INSERT IGNORE INTO forfaits (id, operateur, categorie, nom, detail, duree, prix, ussd_template, verified) VALUES
('omx1',  'Orange', 'Appels', 'Pass mix 200 F',       '17 min tous réseaux',        '1 jour',  200,   '#161*{numero_destinataire}*2*1*1#', 1),
('omx2',  'Orange', 'Appels', 'Pass mix 300 F',       '30 min tous réseaux + 100 Mo', '2 jours', 300,   '#161*{numero_destinataire}*2*1*2#', 1),
('omx3',  'Orange', 'Appels', 'Pass mix 400 F',       '50 min tous réseaux + 100 Mo', '2 jours', 400,   '#161*{numero_destinataire}*2*1*3#', 1),
('omx4',  'Orange', 'Appels', 'Pass mix Veedz 600 F', '50 min + 100 Mo + Veedz',      '3 jours', 600,   '#161*{numero_destinataire}*2*1*3#', 0),
('omx5',  'Orange', 'Appels', 'Pass mix 500 F',   '55 min + 300 SMS + illimité numéro préféré',                              '5 jours',  500,   '#161*{numero_destinataire}*2*2*1#', 1),
('omx6',  'Orange', 'Appels', 'Pass Mix 700 F',   '60 min + 1,5 Go',                                                          '5 jours',  700,   '#161*{numero_destinataire}*2*2*2#', 1),
('omx7',  'Orange', 'Appels', 'Pass Mix 1 000 F', '100 min + 1 Go + illimité numéro préféré + 150 Mo Spotify',               '7 jours',  1000,  '#161*{numero_destinataire}*2*2*3#', 1),
('omx8',  'Orange', 'Appels', 'Pass Mix 1 500 F', '200 min + 1500 Mo + illimité numéro préféré + 150 Mo Spotify',            '7 jours',  1500,  '#161*{numero_destinataire}*2*2*4#', 1),
('omx9',  'Orange', 'Appels', 'Pass Mix 3 000 F',  '250 min + 2,5 Go + 500 SMS + illimité numéro préféré + 500 Mo Spotify',  '30 jours', 3000,  '#161*{numero_destinataire}*2*3*1#', 1),
('omx10', 'Orange', 'Appels', 'Pass Mix 5 000 F',  '400 min + 5 Go + illimité numéro préféré + 500 Mo Spotify',              '30 jours', 5000,  '#161*{numero_destinataire}*2*3*2#', 1),
('omx11', 'Orange', 'Appels', 'Pass Mix 10 000 F', '500 min + 10 Go + illimité numéro préféré + 500 Mo Spotify',             '30 jours', 10000, '#161*{numero_destinataire}*2*3*3#', 1),
('omx12', 'Orange', 'Appels', 'Pass Mix 20 000 F', '1200 min + 20 Go + illimité numéro préféré + 500 Mo Spotify',            '30 jours', 20000, '#161*{numero_destinataire}*2*3*4#', 1),

('obf1', 'Orange', 'Appels', 'Pass Burkina Faso 300 F',   '4 min vers Orange Burkina/Onatel/Telecel + 4 min locales',   '1 jour',  300,  '#161*{numero_destinataire}*5*1*1#', 1),
('obf2', 'Orange', 'Appels', 'Pass Burkina Faso 500 F',   '20 min vers Orange Burkina/Onatel/Telecel + 15 min locales', '3 jours', 500,  '#161*{numero_destinataire}*5*1*2#', 1),
('obf3', 'Orange', 'Appels', 'Pass Burkina Faso 1 000 F', '35 min vers Orange Burkina/Onatel/Telecel + 35 min locales', '7 jours', 1000, '#161*{numero_destinataire}*5*1*3#', 1),
('obf4', 'Orange', 'Appels', 'Pass Burkina Faso 2 500 F', '100 min vers Orange Burkina/Onatel/Telecel + 50 min locales', '7 jours', 2500, '#161*{numero_destinataire}*5*1*4#', 1),

('oml1', 'Orange', 'Appels', 'Pass Mali 300 F',   '4 min vers Orange Mali/Onatel/Telecel + 4 min locales',   '1 jour',  300,  '#161*{numero_destinataire}*5*2*1#', 1),
('oml2', 'Orange', 'Appels', 'Pass Mali 500 F',   '20 min vers Orange Mali/Onatel/Telecel + 15 min locales', '3 jours', 500,  '#161*{numero_destinataire}*5*2*2#', 1),
('oml3', 'Orange', 'Appels', 'Pass Mali 1 000 F', '35 min vers Orange Mali/Onatel/Telecel + 35 min locales', '7 jours', 1000, '#161*{numero_destinataire}*5*2*3#', 1),
('oml4', 'Orange', 'Appels', 'Pass Mali 2 500 F', '100 min vers Orange Mali/Onatel/Telecel + 50 min locales', '7 jours', 2500, '#161*{numero_destinataire}*5*2*4#', 1),

('osn1', 'Orange', 'Appels', 'Pass Sénégal 300 F',   '4 min vers Orange Sénégal/Onatel/Telecel + 4 min locales',   '1 jour',  300,  '#161*{numero_destinataire}*5*3*1#', 1),
('osn2', 'Orange', 'Appels', 'Pass Sénégal 500 F',   '20 min vers Orange Sénégal/Onatel/Telecel + 15 min locales', '3 jours', 500,  '#161*{numero_destinataire}*5*3*2#', 1),
('osn3', 'Orange', 'Appels', 'Pass Sénégal 1 000 F', '35 min vers Orange Sénégal/Onatel/Telecel + 35 min locales', '7 jours', 1000, '#161*{numero_destinataire}*5*3*3#', 1),
('osn4', 'Orange', 'Appels', 'Pass Sénégal 2 500 F', '100 min vers Orange Sénégal/Onatel/Telecel + 50 min locales', '7 jours', 2500, '#161*{numero_destinataire}*5*3*4#', 1),

('ogn1', 'Orange', 'Appels', 'Pass Guinée Conakry 500 F',   '6 min vers Orange Guinée Conakry + 3 min locales', '3 jours', 500,  '#161*{numero_destinataire}*5*4*1#', 1),
('ogn2', 'Orange', 'Appels', 'Pass Guinée Conakry 1 000 F', '7 min vers Orange Guinée Conakry + 7 min locales', '7 jours', 1000, '#161*{numero_destinataire}*5*4*2#', 1),

('oni1', 'Orange', 'Appels', 'Pass Niger 500 F',   '10 min vers numéros mobiles + 5 min locales',  '3 jours', 500,  '#161*{numero_destinataire}*5*5*1#', 1),
('oni2', 'Orange', 'Appels', 'Pass Niger 1 000 F', '12 min vers numéros mobiles + 12 min locales', '3 jours', 1000, '#161*{numero_destinataire}*5*5*2#', 1),

('ong1', 'Orange', 'Appels', 'Pass Nigéria 500 F',   '3 min vers numéros mobiles + 3 min locales',   '1 jour',   500,  '#161*{numero_destinataire}*5*6*1#', 1),
('ong2', 'Orange', 'Appels', 'Pass Nigéria 1 000 F', '11 min vers numéros mobiles + 11 min locales', '7 jours',  1000, '#161*{numero_destinataire}*5*6*2#', 1),
('ong3', 'Orange', 'Appels', 'Pass Nigéria 3 000 F', '35 min vers numéros mobiles + 35 min locales', '30 jours', 3000, '#161*{numero_destinataire}*5*6*3#', 1),

('oae1', 'Orange', 'Appels', 'Pass Amérique/Asie/Europe 500 F',   '20 min vers USA, Inde, Canada, Orange France, Roumanie, Brésil, Colombie, Mexique, Singapour + 10 min locales', '1 mois', 500,  '#161*{numero_destinataire}*5*7*1#', 1),
('oae2', 'Orange', 'Appels', 'Pass Amérique/Asie/Europe 1 000 F', '50 min vers les mêmes destinations + 20 min locales',  '1 mois', 1000, '#161*{numero_destinataire}*5*7*2#', 1),
('oae3', 'Orange', 'Appels', 'Pass Amérique/Asie/Europe 2 000 F', '110 min vers les mêmes destinations + 30 min locales', '1 mois', 2000, '#161*{numero_destinataire}*5*7*2#', 0);
