<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

// Droit de veto du super admin : change instantanément la formule d'une
// cabine — remplace DB.business.adminSetCabineAbonnement() (js/db.js).
// Aucun débit de solde, aucune vérification de quota (contrairement au
// flux self-service, api/cabine_resubscribe.php).
requireAuth(['admin']);

$in = body();
$cabineId = (string)($in['cabine_id'] ?? '');
$formule = (string)($in['formule'] ?? '');

$prices = ['Premium' => 10000, 'VIP' => 20000, 'VVIP' => 50000];
if (!isset($prices[$formule])) fail('Formule invalide.');

// Existence vérifiée séparément plutôt que via rowCount() de l'UPDATE
// ci-dessous : si la cabine a déjà cette formule ET commissions_total à 0,
// rowCount() vaudrait 0 (aucune LIGNE changée) alors que la cabine existe
// bel et bien — rowCount() reflète les changements, pas les lignes
// matchées par le WHERE (comportement par défaut de PDO MySQL).
$checkStmt = db()->prepare("SELECT id FROM profiles WHERE id = ? AND role = 'cabine'");
$checkStmt->execute([$cabineId]);
if (!$checkStmt->fetch()) fail('Cabine introuvable.');

db()->prepare("UPDATE profiles SET abonnement = ?, commissions_total = 0 WHERE id = ? AND role = 'cabine'")
    ->execute([$formule, $cabineId]);

createNotification($cabineId, 'Votre formule a été changée en ' . $formule . ' par l\'administration.', 'info');

echo json_encode(['ok' => true]);
