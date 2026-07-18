<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

// Règles de commission — lecture publique (même patron que settings_get.php
// et forfaits_list.php) : une cabine affiche le taux courant dès l'écran
// de profil (voir js/cabine.js), et api/orders_common.php (calcCommission)
// lit déjà cette même table côté serveur depuis la Phase 4.
$rows = db()->query('SELECT * FROM commissions ORDER BY date DESC')->fetchAll();
echo json_encode(['commissions' => $rows]);
