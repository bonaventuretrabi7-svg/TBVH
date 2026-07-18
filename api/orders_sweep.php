<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/orders_common.php';

// Balayage des commandes en retard — remplace DB.business.sweepStaleOrders()
// (js/db.js). Appelé par le même mécanisme de sondage périodique côté
// client (client.js/cabine.js/admin.js, toutes les 10-30s) : aucun cron
// n'existe sur cet hébergement, le polling reste le déclencheur, mais
// chaque étape sensible est désormais un CAS (compare-and-swap) qui élimine
// la course entre plusieurs onglets/appareils qui balaient au même
// instant — l'ancienne version locale (retard_logged_cabine_id) ne faisait
// que la réduire, jamais l'éliminer complètement.
requireAuth();

$pdo = db();
$pending = $pdo->query("SELECT * FROM transactions WHERE statut = 'en_attente' AND cabine_id IS NOT NULL")->fetchAll();

$staleCount = 0;
$suspendedCabineIds = [];

foreach ($pending as $t) {
  $assignedAt = strtotime($t['date_assignation'] ?: $t['date']);
  if ((time() - $assignedAt) <= ORDER_RETARD_SECONDS) continue;

  $cabineId = $t['cabine_id'];
  checkAutoUnsuspend($pdo, $cabineId);

  // CAS : marque le retard "déjà loggé pour cette période d'attribution" —
  // seule la requête qui gagne cette course continue le traitement.
  $claim = $pdo->prepare("UPDATE transactions SET retard_logged_cabine_id = ?
    WHERE id = ? AND cabine_id = ? AND statut = 'en_attente'
      AND (retard_logged_cabine_id IS NULL OR retard_logged_cabine_id != ?)");
  $claim->execute([$cabineId, $t['id'], $cabineId, $cabineId]);
  if ($claim->rowCount() === 0) continue;

  $staleCount++;
  $retardId = uuid4();
  $pdo->prepare('INSERT INTO retards (id, transaction_id, cabine_id, date, reassigned_to_cabine_id, triggered_suspension) VALUES (?, ?, ?, NOW(), NULL, 0)')
      ->execute([$retardId, $t['id'], $cabineId]);

  $triggeredSuspension = false;
  $todayCountStmt = $pdo->prepare('SELECT COUNT(*) FROM retards WHERE cabine_id = ? AND date >= CURDATE()');
  $todayCountStmt->execute([$cabineId]);
  if ((int)$todayCountStmt->fetchColumn() >= 3) {
    suspendCabineAuto($pdo, $cabineId, '3 retards de traitement au cours de la journée');
    $suspendedCabineIds[] = $cabineId;
    $triggeredSuspension = true;
  }

  $target = findReassignmentTarget($pdo, $cabineId, $t['operateur'], $t['type']);
  $reassignedTo = null;
  if ($target) {
    $reassign = $pdo->prepare("UPDATE transactions SET cabine_id = ?, date_assignation = NOW() WHERE id = ? AND cabine_id = ? AND statut = 'en_attente'");
    $reassign->execute([$target['id'], $t['id'], $cabineId]);
    if ($reassign->rowCount() > 0) {
      $reassignedTo = $target['id'];
      createNotification($target['id'], 'Nouvelle commande assignée automatiquement (réattribution pour retard) : ' . $t['operateur'] . ' ' . number_format((float)$t['montant'], 0, ',', ' ') . ' F.', 'new_request');
      createNotification($cabineId, 'Une commande a été réattribuée automatiquement suite à un retard.', 'reassigned');
    }
  }
  if ($reassignedTo === null) {
    $pdo->prepare("UPDATE transactions SET cabine_id = NULL WHERE id = ? AND cabine_id = ? AND statut = 'en_attente'")->execute([$t['id'], $cabineId]);
  }

  $pdo->prepare('UPDATE retards SET reassigned_to_cabine_id = ?, triggered_suspension = ? WHERE id = ?')
      ->execute([$reassignedTo, $triggeredSuspension ? 1 : 0, $retardId]);
}

echo json_encode(['ok' => true, 'staleCount' => $staleCount, 'suspendedCabineIds' => $suspendedCabineIds]);
