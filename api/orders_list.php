<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

// Liste des commandes pertinentes pour l'utilisateur authentifié — sans
// ceci, un deuxième appareil ne verrait jamais les commandes créées/
// traitées ailleurs malgré tous les endpoints d'écriture du moteur de
// commandes (orders_create/accept/refuse/...). Portée par rôle : un client
// voit ses propres commandes, une cabine celles qui lui sont assignées, un
// administrateur voit tout (même patron que list_profiles.php).
$me = requireAuth();

if ($me['role'] === 'client') {
  $stmt = db()->prepare('SELECT * FROM transactions WHERE client_id = ? ORDER BY date DESC');
  $stmt->execute([$me['id']]);
} elseif ($me['role'] === 'cabine') {
  $stmt = db()->prepare('SELECT * FROM transactions WHERE cabine_id = ? ORDER BY date DESC');
  $stmt->execute([$me['id']]);
} else {
  $stmt = db()->query('SELECT * FROM transactions ORDER BY date DESC');
}

// `details` (JSON) revient en texte brut de MySQL/PDO — décodé ici pour
// que ce soit un objet exploitable côté client (voir renderHistoryList()/
// dépenses, js/client.js, et le rendu USSD cabine, js/cabine.js).
$rows = array_map(fn($r) => decodeJsonColumns($r, ['details']), $stmt->fetchAll());
echo json_encode(['transactions' => $rows]);
