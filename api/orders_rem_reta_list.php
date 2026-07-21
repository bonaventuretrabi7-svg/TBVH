<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/orders_common.php';

// Liste des commandes bloquées après refus répétés (voir orders_refuse.php :
// au-delà de ORDER_REM_RETA_REFUS_SEUIL refus, plus de réattribution
// automatique) — onglet admin "REM-RETA". Chaque commande listée inclut le
// numéro de téléphone du CLIENT (pas le bénéficiaire de la commande) et
// l'historique complet des refus (cabine + motif), pour que l'administration
// puisse décider d'un remboursement en toute connaissance de cause.
requireAuth(['admin']);

$pdo = db();

$txnStmt = $pdo->prepare("
  SELECT t.*, (SELECT COUNT(*) FROM commande_refus cr WHERE cr.transaction_id = t.id) AS refus_count
  FROM transactions t
  WHERE t.statut = 'en_attente' AND t.cabine_id IS NULL
  HAVING refus_count >= ?
  ORDER BY t.date ASC
");
$txnStmt->execute([ORDER_REM_RETA_REFUS_SEUIL]);
$txns = $txnStmt->fetchAll();

$refusStmt = $pdo->prepare("
  SELECT cr.motif, cr.justification, cr.date, p.prenom, p.nom, p.cabine_nom
  FROM commande_refus cr
  JOIN profiles p ON p.id = cr.cabine_id
  WHERE cr.transaction_id = ?
  ORDER BY cr.date ASC
");
$clientStmt = $pdo->prepare('SELECT telephone, prenom, nom FROM profiles WHERE id = ?');

$result = [];
foreach ($txns as $txn) {
  $client = null;
  if ($txn['client_id']) {
    $clientStmt->execute([$txn['client_id']]);
    $client = $clientStmt->fetch() ?: null;
  }

  $refusStmt->execute([$txn['id']]);
  $refus = array_map(function ($r) {
    return [
      'cabine' => $r['cabine_nom'] ?: trim($r['prenom'] . ' ' . $r['nom']),
      'motif' => $r['motif'],
      'justification' => $r['justification'],
      'date' => $r['date'],
    ];
  }, $refusStmt->fetchAll());

  $result[] = [
    'id' => $txn['id'],
    'client_telephone' => $client['telephone'] ?? null,
    'client_nom' => $client ? trim($client['prenom'] . ' ' . $client['nom']) : null,
    'operateur' => $txn['operateur'],
    'service' => $txn['service'],
    'montant' => (int)$txn['montant'],
    'date' => $txn['date'],
    'refus_count' => (int)$txn['refus_count'],
    'refus' => $refus,
  ];
}

echo json_encode(['ok' => true, 'commandes' => $result]);
