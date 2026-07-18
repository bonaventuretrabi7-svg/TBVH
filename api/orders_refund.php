<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

// Admin : rembourse le client pour une commande en attente ou terminée —
// remplace DB.business.refundTransaction() (js/db.js). Si elle était déjà
// terminée, retire la commission déjà versée au cabiniste + applique la
// double sanction (montant + pénalité fixe, voir PENALITE_REMBOURSEMENT_TERMINE).
requireAuth(['admin']);

$in = body();
$txnId = (string)($in['transaction_id'] ?? '');
if ($txnId === '') fail('Identifiant de commande requis.');

$PENALITE_REMBOURSEMENT_TERMINE = 60;

$pdo = db();
$pdo->beginTransaction();
try {
  $txnStmt = $pdo->prepare('SELECT * FROM transactions WHERE id = ? FOR UPDATE');
  // FOR UPDATE : verrou de ligne classique ici (pas un CAS) — ce endpoint
  // n'est déclenché que par une action manuelle admin explicite, jamais
  // par le polling automatique, la fenêtre de contention réelle est nulle
  // en pratique ; FOR UPDATE reste la protection la plus simple à lire.
  $txnStmt->execute([$txnId]);
  $txn = $txnStmt->fetch();
  if (!$txn || ($txn['statut'] !== 'en_attente' && $txn['statut'] !== 'terminé')) {
    $pdo->rollBack();
    fail('Cette commande ne peut pas être remboursée.');
  }

  if ($txn['statut'] === 'terminé' && $txn['cabine_id']) {
    $cabStmt = $pdo->prepare('SELECT * FROM profiles WHERE id = ?');
    $cabStmt->execute([$txn['cabine_id']]);
    $cab = $cabStmt->fetch();
    if ($cab) {
      $commission = (int)$txn['commission'];
      $sanction = (int)$txn['montant'] + $PENALITE_REMBOURSEMENT_TERMINE;
      $pdo->prepare('UPDATE profiles SET
          solde = solde - ?,
          commissions_total = GREATEST(0, commissions_total - ?),
          transferts_total = GREATEST(0, transferts_total - 1),
          remboursements_recus = remboursements_recus + 1
        WHERE id = ?')
        ->execute([$commission + $sanction, $commission, $txn['cabine_id']]);

      $pdo->prepare('INSERT INTO retraits (id, cabine_id, montant, statut, methode_retrait, type, motif, date) VALUES (?, ?, ?, \'terminé\', \'Sanction\', \'sanction\', ?, NOW())')
          ->execute([uuid4(), $txn['cabine_id'], $sanction, 'Remboursement commande — montant (' . number_format((float)$txn['montant'], 0, ',', ' ') . ' F) + pénalité (' . $PENALITE_REMBOURSEMENT_TERMINE . ' F)']);

      createNotification($txn['cabine_id'], 'Une commande que vous aviez marquée "Terminée" a été remboursée par l\'administration : ' . number_format((float)$sanction, 0, ',', ' ') . ' F (montant + pénalité de ' . $PENALITE_REMBOURSEMENT_TERMINE . ' F) ont été prélevés sur votre solde.', 'warning');
    }
  }

  $pdo->prepare('UPDATE profiles SET solde = solde + ? WHERE id = ?')->execute([(int)$txn['montant'], $txn['client_id']]);
  $pdo->prepare("UPDATE transactions SET statut='remboursé', date_remboursement=NOW() WHERE id=?")->execute([$txnId]);

  $pdo->commit();
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  throw $e;
}

createNotification($txn['client_id'], 'Votre commande de ' . number_format((float)$txn['montant'], 0, ',', ' ') . ' F a été remboursée par l\'administration.', 'success');

echo json_encode(['ok' => true]);
