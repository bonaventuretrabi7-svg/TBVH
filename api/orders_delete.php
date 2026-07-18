<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

// Suppression définitive d'une commande — réservée au super administrateur.
// Bloquée pour une commande 'terminé' (commission déjà créditée à la
// cabine) : il faut d'abord la rembourser via orders_refund.php, qui annule
// proprement l'effet financier, avant de pouvoir la supprimer — jamais de
// suppression silencieuse d'une trace comptable encore valide. Cascade sur
// les données liées (réclamation + ses messages, demande de remboursement,
// retards) pour ne laisser aucune référence orpheline.
$me = requireAuth(['admin']);
if ($me['admin_level'] !== 'super') fail('Seul le super administrateur peut supprimer une commande.', 403);

$in = body();
$txnId = (string)($in['transaction_id'] ?? '');
if ($txnId === '') fail('Identifiant de commande requis.');

$pdo = db();
$pdo->beginTransaction();
try {
  $txnStmt = $pdo->prepare('SELECT * FROM transactions WHERE id = ? FOR UPDATE');
  $txnStmt->execute([$txnId]);
  $txn = $txnStmt->fetch();
  if (!$txn) {
    $pdo->rollBack();
    fail('Commande introuvable.', 404);
  }
  if (!in_array($txn['statut'], ['en_attente', 'suspendue', 'remboursé'], true)) {
    $pdo->rollBack();
    fail('Seule une commande en attente, suspendue ou déjà remboursée peut être supprimée — remboursez-la d\'abord si elle est terminée.');
  }

  $reclaStmt = $pdo->prepare('SELECT id FROM reclamations WHERE transaction_id = ?');
  $reclaStmt->execute([$txnId]);
  $reclaIds = array_column($reclaStmt->fetchAll(), 'id');
  if ($reclaIds) {
    $placeholders = implode(',', array_fill(0, count($reclaIds), '?'));
    $pdo->prepare("DELETE FROM reclamation_messages WHERE reclamation_id IN ($placeholders)")->execute($reclaIds);
  }
  $pdo->prepare('DELETE FROM refund_requests WHERE transaction_id = ?')->execute([$txnId]);
  $pdo->prepare('DELETE FROM reclamations WHERE transaction_id = ?')->execute([$txnId]);
  $pdo->prepare('DELETE FROM retards WHERE transaction_id = ?')->execute([$txnId]);
  $pdo->prepare('DELETE FROM transactions WHERE id = ?')->execute([$txnId]);

  $pdo->commit();
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  throw $e;
}

echo json_encode(['ok' => true]);
