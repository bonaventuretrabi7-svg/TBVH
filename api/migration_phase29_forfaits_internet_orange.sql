-- KBINE PLUS | Phase 29 — Catalogue Orange "Internet" (Pass réseaux sociaux,
-- Pass 1-3 jours, Pass 5-7 jours, Pass internet Mois, Pass nuit).
-- À coller UNE SEULE FOIS dans phpMyAdmin (onglet SQL) sur la base déjà en
-- place — le seed local (js/db.js, migrateForfaitsSeed()) ne s'applique
-- jamais à une base de production déjà peuplée, ces forfaits n'existaient
-- donc que côté client jusqu'ici. INSERT IGNORE : sans effet si rejoué
-- (les id sont déjà uniques, mais idempotent par précaution).
--
-- "Pass internet 1000 F" (1,5 Go + 150 Mo Spotify) était listé deux fois à
-- l'identique dans le catalogue fourni — inséré une seule fois ici.
-- "Pass internet 1500 F" indiquait "valable 30 jours" dans le catalogue
-- fourni bien que classé parmi les Pass 5-7 jours — corrigé à "7 jours"
-- pour rester cohérent avec sa section (confirmé).

INSERT IGNORE INTO forfaits (id, operateur, categorie, nom, detail, duree, prix, ussd_template, verified) VALUES
('os1',  'Orange', 'Internet', 'Pass social 300 F',       '450 Mo pour Facebook, WhatsApp, Twitter, Instagram, YouTube, Dailymotion', '3 jours', 300,  '#161*{numero_destinataire}*3*1*1#', 1),
('os2',  'Orange', 'Internet', 'Pass social TikTok 300 F', '1 Go + TikTok et WhatsApp',                                              '1 jour',  300,  '#161*{numero_destinataire}*3*1*2#', 1),
('os3',  'Orange', 'Internet', 'Pass social 500 F',       '1 Go pour Facebook, WhatsApp, Twitter, Instagram, YouTube, Dailymotion',   '3 jours', 500,  '#161*{numero_destinataire}*3*1*3#', 1),

('o131', 'Orange', 'Internet', 'Pass Internet 200 F',   '220 Mo',                      '2 jours', 200,  '#161*{numero_destinataire}*3*2*1#', 1),
('o132', 'Orange', 'Internet', 'Pass internet 300 F',   '400 Mo',                      '2 jours', 300,  '#161*{numero_destinataire}*3*2*2#', 1),
('o133', 'Orange', 'Internet', 'Pass internet 500 F',   '500 Mo',                      '3 jours', 500,  '#161*{numero_destinataire}*3*2*5#', 1),
('o134', 'Orange', 'Internet', 'Pass internet 400 F',   '340 Mo + Life TV',            '3 jours', 400,  '#161*{numero_destinataire}*3*2*3#', 1),
('o135', 'Orange', 'Internet', 'Pass internet 400 F',   '340 Mo + WAW MUSIK illimité', '3 jours', 400,  '#161*{numero_destinataire}*3*2*4#', 1),
('o136', 'Orange', 'Internet', 'Pass spécial 500 F',    '1,5 Go',                      '3 jours', 500,  '#161*{numero_destinataire}*3*2*6#', 1),
('o137', 'Orange', 'Internet', 'Pass Megawin 500 F',    '1 Go',                        '3 jours', 500,  '#161*{numero_destinataire}*3*2*7#', 1),
('o138', 'Orange', 'Internet', 'Pass Max it TV 500 F',  '1 Go + accès à Max it TV',    '1 jour',  500,  '#161*{numero_destinataire}*3*2*8#', 1),
('o139', 'Orange', 'Internet', 'Pass 700 F',            '750 Mo + vidéo cuisine',      '3 jours', 700,  '#161*{numero_destinataire}*3*2*9#', 1),

('o571', 'Orange', 'Internet', 'Pass internet Semaine 700 F', '750 Mo + WAW MUZIK illimité', '7 jours', 700,  '#161*{numero_destinataire}*3*3*1#', 1),
('o572', 'Orange', 'Internet', 'Pass internet 1000 F',        '1,5 Go + 150 Mo Spotify',     '7 jours', 1000, '#161*{numero_destinataire}*3*3*2#', 1),
('o573', 'Orange', 'Internet', 'Pass internet 1200 F',        '1,5 Go + 7 Info',             '7 jours', 1200, '#161*{numero_destinataire}*3*3*3#', 1),
('o574', 'Orange', 'Internet', 'Pass internet 1500 F',        '2,5 Go + 150 Mo sportif',     '7 jours', 1500, '#161*{numero_destinataire}*3*3*4#', 1),

('om1', 'Orange', 'Internet', 'Pass internet 2 500 F',  '3,5 Go + 500 Mo sportif', '30 jours', 2500,  '#161*{numero_destinataire}*3*4*1#', 1),
('om2', 'Orange', 'Internet', 'Pass internet 3 000 F',  '2,7 Go + WAW illimité',   '30 jours', 3000,  '#161*{numero_destinataire}*3*4*2#', 1),
('om3', 'Orange', 'Internet', 'Pass internet 5 000 F',  '7,5 Go + 500 Mo Spotify', '30 jours', 5000,  '#161*{numero_destinataire}*3*4*3#', 1),
('om4', 'Orange', 'Internet', 'Pass internet 10 000 F', '18 Go + 500 Mo Spotify',  '30 jours', 10000, '#161*{numero_destinataire}*3*4*4#', 1),
('om5', 'Orange', 'Internet', 'Pass internet 20 000 F', '40 Go + 500 Mo Spotify',  '30 jours', 20000, '#161*{numero_destinataire}*3*4*5#', 1),

('on1', 'Orange', 'Internet', 'Pass nuit 250 F', '2 Go + illimité numéros préférés', '22h à 07h', 250, '#161*{numero_destinataire}*3*5*1#', 1);
