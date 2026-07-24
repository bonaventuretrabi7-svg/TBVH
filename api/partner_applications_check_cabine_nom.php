<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

// Même principe que partner_applications_check_phone.php/_email.php, pour
// le nom de cabine : bloque seulement si une candidature encore active
// (en_attente ou validée) ou une cabine existante utilise déjà ce nom — un
// refus n'empêche jamais une nouvelle tentative. Public (aucune
// authentification : le candidat n'a pas encore de compte à ce stade).
$in = body();
$cabineNom = trim((string)($in['cabine_nom'] ?? ''));
if ($cabineNom === '') fail('Nom de cabine requis.');

// Compare sur cabine_nom_key (colonne générée, indexée — voir
// migration_phase33_perf_indexes.sql) : LOWER()/TRIM() ne porte ici que sur
// le PARAMÈTRE (?), jamais sur la colonne — calculé par MySQL, garanti
// identique à la fonction utilisée pour générer la colonne.
$appStmt = db()->prepare("SELECT id FROM partner_applications
    WHERE cabine_nom_key = LOWER(TRIM(?)) AND statut IN ('en_attente', 'validée')");
$appStmt->execute([$cabineNom]);
if ($appStmt->fetch()) { echo json_encode(['ok' => true, 'exists' => true]); exit; }

$cabineStmt = db()->prepare("SELECT id FROM profiles WHERE cabine_nom_key = LOWER(TRIM(?))");
$cabineStmt->execute([$cabineNom]);

echo json_encode(['ok' => true, 'exists' => (bool)$cabineStmt->fetch()]);
