<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

// Suppression définitive d'une commande — réservée au super administrateur.
// Une commande 'terminé' peut aussi être supprimée directement (choix
// explicite de l'administration) : la commission déjà créditée à la cabine
// et le débit éventuel du client ne sont PAS annulés — seule la trace de la
// commande disparaît. Si un remboursement complet (client recrédité,
// commission reprise à la cabine) est souhaité à la place, utiliser
// orders_refund.php avant de supprimer. Cascade sur les données liées
// (réclamation + ses messages, demande de remboursement, retards) pour ne
// laisser aucune référence orpheline.
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
  if (!in_array($txn['statut'], ['en_attente', 'suspendue', 'remboursé', 'terminé'], true)) {
    $pdo->rollBack();
    fail('Cette commande ne peut pas être supprimée.');
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
