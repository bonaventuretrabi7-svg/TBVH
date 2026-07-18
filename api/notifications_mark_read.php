<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

// Marque UNE notification comme lue — CAS de propriété (WHERE
// utilisateur_id = ?) : un appelant ne peut jamais marquer comme lue la
// notification d'un autre compte.
$me = requireAuth();

$in = body();
$id = (string)($in['notification_id'] ?? '');
if ($id === '') fail('Identifiant de notification requis.');

db()->prepare('UPDATE notifications SET lu = 1 WHERE id = ? AND utilisateur_id = ?')
    ->execute([$id, $me['id']]);

echo json_encode(['ok' => true]);
