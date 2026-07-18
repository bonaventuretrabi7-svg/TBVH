<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

// Supprime un forfait du catalogue — remplace DB.forfaits.remove() (js/db.js).
// Réservé au super administrateur.
$me = requireAuth(['admin']);
if ($me['admin_level'] !== 'super') fail('Seul le super administrateur peut gérer les forfaits.', 403);

$in = body();
$id = (string)($in['id'] ?? '');
if ($id === '') fail('Identifiant de forfait requis.');

db()->prepare('DELETE FROM forfaits WHERE id = ?')->execute([$id]);
echo json_encode(['ok' => true]);
