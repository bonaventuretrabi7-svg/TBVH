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

$appStmt = db()->prepare("SELECT id FROM partner_applications
    WHERE LOWER(TRIM(prenom)) = LOWER(TRIM(?)) AND LOWER(TRIM(nom)) = LOWER(TRIM(?))
    AND statut IN ('en_attente', 'validée')");
$appStmt->execute([$prenom, $nom]);
if ($appStmt->fetch()) { echo json_encode(['ok' => true, 'exists' => true]); exit; }

$cabineStmt = db()->prepare("SELECT id FROM profiles
    WHERE role = 'cabine' AND LOWER(TRIM(prenom)) = LOWER(TRIM(?)) AND LOWER(TRIM(nom)) = LOWER(TRIM(?))");
$cabineStmt->execute([$prenom, $nom]);

echo json_encode(['ok' => true, 'exists' => (bool)$cabineStmt->fetch()]);
