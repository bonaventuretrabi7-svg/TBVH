<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

// Historique des transferts cabine-à-cabine — lecture seule, voir
// DB.transferts_cabine (js/db.js). La table est déjà correctement peuplée
// par api/cabine_transfer.php depuis le début (le transfert d'argent
// fonctionne réellement) ; seule cette lecture manquait, laissant
// l'historique affiché en permanence vide. Une cabine voit les transferts
// où elle est EXPÉDITRICE OU DESTINATAIRE, un administrateur voit tout.
$me = requireAuth(['cabine', 'admin']);

if ($me['role'] === 'cabine') {
  $stmt = db()->prepare('SELECT * FROM transferts_cabine WHERE from_cabine_id = ? OR to_cabine_id = ? ORDER BY date DESC');
  $stmt->execute([$me['id'], $me['id']]);
} else {
  $stmt = db()->query('SELECT * FROM transferts_cabine ORDER BY date DESC');
}

echo json_encode(['transferts' => $stmt->fetchAll()]);
