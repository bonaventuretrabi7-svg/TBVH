<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

// Marque TOUTES les notifications de l'utilisateur authentifié comme lues.
$me = requireAuth();

db()->prepare('UPDATE notifications SET lu = 1 WHERE utilisateur_id = ? AND lu = 0')
    ->execute([$me['id']]);

echo json_encode(['ok' => true]);
