<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

// Admin : suspend une commande (en attente ou terminée) avec motif
// obligatoire — remplace DB.business.suspendTransaction() (js/db.js). Ne
// touche pas aux soldes (gel, pas une annulation financière — voir
// orders_refund.php pour ça). Réversible via orders_reactivate.php.
requireAuth(['admin']);

$in = body();
$txnId = (string)($in['transaction_id'] ?? '');
$motif = trim((string)($in['motif'] ?? ''));
if ($txnId === '') fail('Identifiant de commande requis.');
if ($motif === '') fail('Le motif de suspension est obligatoire.');

$pdo = db();
// statut_avant_suspension DOIT être affecté AVANT statut dans cette liste :
// MySQL évalue un SET multi-colonnes de gauche à droite, une colonne déjà
// réassignée plus tôt dans la même instruction reflète sa NOUVELLE valeur
// si elle est référencée à nouveau — l'ordre inverse aurait capturé
// "suspendue" au lieu du statut d'origine.
$stmt = $pdo->prepare("UPDATE transactions SET
    statut_avant_suspension = statut, statut = 'suspendue', motif_suspension = ?, date_suspension = NOW()
  WHERE id = ? AND statut IN ('en_attente', 'terminé')");
$stmt->execute([$motif, $txnId]);
if ($stmt->rowCount() === 0) fail('Cette commande ne peut pas être suspendue.');

$txnStmt = $pdo->prepare('SELECT cabine_id FROM transactions WHERE id = ?');
$txnStmt->execute([$txnId]);
$cabineId = $txnStmt->fetchColumn();
if ($cabineId) createNotification($cabineId, 'Une commande a été suspendue par l\'administration : ' . $motif, 'warning');

echo json_encode(['ok' => true]);
