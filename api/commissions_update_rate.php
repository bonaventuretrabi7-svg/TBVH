<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

// Modifie le taux de commission — remplace saveCommissionRate() (js/admin.js),
// qui applique le même taux à TOUTES les règles existantes (en pratique une
// seule, "Commission standard", jamais plusieurs créées par l'interface).
// Réservé au super administrateur.
$me = requireAuth(['admin']);
if ($me['admin_level'] !== 'super') fail('Seul le super administrateur peut modifier le taux de commission.', 403);

$in = body();
$rate = isset($in['pourcentage']) ? (float)$in['pourcentage'] : null;
if ($rate === null || $rate < 0 || $rate > 50) fail('Taux invalide (0–50%).');

db()->prepare('UPDATE commissions SET pourcentage = ?')->execute([$rate]);

$rows = db()->query('SELECT * FROM commissions ORDER BY date DESC')->fetchAll();
echo json_encode(['ok' => true, 'commissions' => $rows]);
