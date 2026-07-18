<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

// Historique des retards — lecture seule, voir DB.retards (js/db.js). Sa
// seule écriture se fait désormais côté serveur (api/orders_sweep.php,
// Phase 4) ; sans cette liste, l'affichage (badge cabine, onglet admin)
// resterait figé à ce qu'il était avant cette migration. Portée par rôle,
// même patron que orders_list.php : une cabine voit ses propres retards,
// un administrateur voit tout.
$me = requireAuth(['cabine', 'admin']);

if ($me['role'] === 'cabine') {
  $stmt = db()->prepare('SELECT * FROM retards WHERE cabine_id = ? ORDER BY date DESC');
  $stmt->execute([$me['id']]);
} else {
  $stmt = db()->query('SELECT * FROM retards ORDER BY date DESC');
}

echo json_encode(['retards' => $stmt->fetchAll()]);
