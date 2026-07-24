<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

// Même principe que partner_applications_check_phone.php/_email.php, pour
// le nom complet (prénom + nom ENSEMBLE, jamais un seul des deux — deux
// candidats différents peuvent tout à fait partager un simple prénom ou nom
// de famille courant) : bloque seulement si une candidature encore active
// (en_attente ou validée) ou un compte partenaire existant a exactement le
// même prénom+nom — un refus n'empêche jamais une nouvelle tentative.
// Public (aucune authentification : le candidat n'a pas encore de compte à
// ce stade).
$in = body();
$prenom = trim((string)($in['prenom'] ?? ''));
$nom    = trim((string)($in['nom'] ?? ''));
if ($prenom === '' || $nom === '') fail('Prénom et nom requis.');

// Compare sur fullname_key/cabine_fullname_key (colonnes générées,
// indexées — voir migration_phase33_perf_indexes.sql) : la fonction
// LOWER()/TRIM()/CONCAT() ne porte ici que sur les PARAMÈTRES (?), jamais
// sur la colonne elle-même — MySQL calcule le côté droit avec sa propre
// LOWER(), garanti identique à celle utilisée pour générer la colonne
// (pas de risque d'écart avec un équivalent recalculé côté PHP sur des
// caractères accentués).
$appStmt = db()->prepare("SELECT id FROM partner_applications
    WHERE fullname_key = CONCAT(LOWER(TRIM(?)), '|', LOWER(TRIM(?))) AND statut IN ('en_attente', 'validée')");
$appStmt->execute([$prenom, $nom]);
if ($appStmt->fetch()) { echo json_encode(['ok' => true, 'exists' => true]); exit; }

$cabineStmt = db()->prepare("SELECT id FROM profiles WHERE cabine_fullname_key = CONCAT(LOWER(TRIM(?)), '|', LOWER(TRIM(?)))");
$cabineStmt->execute([$prenom, $nom]);

echo json_encode(['ok' => true, 'exists' => (bool)$cabineStmt->fetch()]);
