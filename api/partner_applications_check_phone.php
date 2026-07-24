<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

// Vérifie qu'un numéro n'est pas déjà "numéro principal" d'une candidature
// en cours (en_attente/validée) ou d'un compte partenaire déjà actif —
// aperçu en direct à la saisie (_wireTakenCheck(), js/client.js) ET
// revérifié à la vraie soumission (partner_applications_create.php). Une
// candidature REFUSÉE ne bloque jamais une nouvelle tentative avec le même
// numéro (statut='refusée' exclu volontairement) — seul un dossier encore
// actif d'une façon ou d'une autre doit empêcher un doublon. Public (aucune
// authentification : le candidat n'a pas encore de compte à ce stade).
$in = body();
$telephone = trim((string)($in['telephone'] ?? ''));
if ($telephone === '') fail('Numéro requis.');

$appStmt = db()->prepare("SELECT id FROM partner_applications WHERE telephone = ? AND statut IN ('en_attente', 'validée')");
$appStmt->execute([$telephone]);
if ($appStmt->fetch()) { echo json_encode(['ok' => true, 'exists' => true]); exit; }

$cabineStmt = db()->prepare("SELECT id FROM profiles WHERE role = 'cabine' AND telephone = ?");
$cabineStmt->execute([$telephone]);

echo json_encode(['ok' => true, 'exists' => (bool)$cabineStmt->fetch()]);
