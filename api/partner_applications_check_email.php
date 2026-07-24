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

// Compare sur email_key (colonne générée, indexée — voir
// migration_phase33_perf_indexes.sql) : LOWER() ne porte ici que sur le
// PARAMÈTRE (?), jamais sur la colonne — calculé par MySQL, garanti
// identique à la fonction utilisée pour générer la colonne.
$appStmt = db()->prepare("SELECT id FROM partner_applications WHERE email_key = LOWER(?) AND statut IN ('en_attente', 'validée')");
$appStmt->execute([$email]);
if ($appStmt->fetch()) { echo json_encode(['ok' => true, 'exists' => true]); exit; }

// Table profiles nettement plus petite (un compte par utilisateur réel,
// jamais une candidature par étape) : pas de colonne générée dédiée ici,
// LOWER(email) reste un scan mais sur un volume négligeable comparé à
// partner_applications ci-dessus.
$cabineStmt = db()->prepare("SELECT id FROM profiles WHERE role = 'cabine' AND LOWER(email) = LOWER(?)");
$cabineStmt->execute([$email]);

echo json_encode(['ok' => true, 'exists' => (bool)$cabineStmt->fetch()]);
