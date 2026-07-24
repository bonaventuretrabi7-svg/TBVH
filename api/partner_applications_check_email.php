<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

// Même principe que partner_applications_check_phone.php, pour l'adresse
// Gmail : bloque seulement si une candidature encore active (en_attente ou
// validée) ou un compte partenaire existant utilise déjà cette adresse — un
// refus n'empêche jamais une nouvelle tentative. Public (aucune
// authentification : le candidat n'a pas encore de compte à ce stade).
$in = body();
$email = trim((string)($in['email'] ?? ''));
if ($email === '') fail('Adresse e-mail requise.');

$appStmt = db()->prepare("SELECT id FROM partner_applications WHERE LOWER(email) = LOWER(?) AND statut IN ('en_attente', 'validée')");
$appStmt->execute([$email]);
if ($appStmt->fetch()) { echo json_encode(['ok' => true, 'exists' => true]); exit; }

$cabineStmt = db()->prepare("SELECT id FROM profiles WHERE role = 'cabine' AND LOWER(email) = LOWER(?)");
$cabineStmt->execute([$email]);

echo json_encode(['ok' => true, 'exists' => (bool)$cabineStmt->fetch()]);
