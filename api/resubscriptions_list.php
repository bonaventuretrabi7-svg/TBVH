<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

// Historique des réabonnements cabine — lecture seule, voir
// DB.resubscriptions (js/db.js). La table est déjà correctement peuplée
// par api/cabine_resubscribe.php depuis le début ; seule cette lecture
// manquait, laissant l'historique invisible d'un autre appareil (cabine
// comme admin). Une cabine voit ses propres réabonnements, un
// administrateur voit tout.
$me = requireAuth(['cabine', 'admin']);

if ($me['role'] === 'cabine') {
  $stmt = db()->prepare('SELECT * FROM resubscriptions WHERE cabine_id = ? ORDER BY date DESC');
  $stmt->execute([$me['id']]);
} else {
  $stmt = db()->query('SELECT * FROM resubscriptions ORDER BY date DESC');
}

echo json_encode(['resubscriptions' => $stmt->fetchAll()]);
