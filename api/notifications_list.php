<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

// Liste des notifications de l'utilisateur authentifié — lecture seule.
// createNotification() (bootstrap.php) est déjà appelée par la quasi-
// totalité des endpoits métier (commandes, réclamations, remboursements,
// forfaits/commissions...) depuis la Phase 4 ; seule cette lecture
// manquait, laissant la cloche de notifications figée sur ce qui avait pu
// être créé localement sur CET appareil, jamais sur ce qui se passait
// ailleurs (autre appareil, action d'un tiers).
$me = requireAuth();

$stmt = db()->prepare('SELECT * FROM notifications WHERE utilisateur_id = ? ORDER BY date DESC');
$stmt->execute([$me['id']]);

echo json_encode(['notifications' => $stmt->fetchAll()]);
