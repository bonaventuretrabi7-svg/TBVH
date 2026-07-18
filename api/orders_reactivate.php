<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

// Admin : réactive une commande suspendue, restaure son statut précédent —
// remplace DB.business.reactivateTransaction() (js/db.js).
requireAuth(['admin']);

$in = body();
$txnId = (string)($in['transaction_id'] ?? '');
if ($txnId === '') fail('Identifiant de commande requis.');

$pdo = db();
$stmt = $pdo->prepare("UPDATE transactions SET
    statut = COALESCE(NULLIF(statut_avant_suspension, ''), 'en_attente'),
    statut_avant_suspension = NULL, motif_suspension = NULL
  WHERE id = ? AND statut = 'suspendue'");
$stmt->execute([$txnId]);
if ($stmt->rowCount() === 0) fail("Cette commande n'est pas suspendue.");

$txnStmt = $pdo->prepare('SELECT cabine_id FROM transactions WHERE id = ?');
$txnStmt->execute([$txnId]);
$cabineId = $txnStmt->fetchColumn();
if ($cabineId) createNotification($cabineId, 'Une commande a été réactivée par l\'administration.', 'info');

echo json_encode(['ok' => true]);
