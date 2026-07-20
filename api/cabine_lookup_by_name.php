<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

// Recherche une cabine par son nom exact — remplace la recherche locale de
// handleCabTransferLookup() (js/cabine.js), qui ne pouvait fiablement voir
// que les autres cabines déjà présentes dans le cache LOCAL de l'appareil
// (jamais garanti complet côté cabine, contrairement à l'admin) : un
// transfert vers une cabine bien réelle mais absente de ce cache affichait
// "Cabine introuvable" à tort, et bloquait le bouton "Envoyer" (lui-même
// conditionné par ce résultat). Ici, seule la base fait foi.
requireAuth(['cabine']);

$in = body();
$nom = trim((string)($in['nom'] ?? ''));
if ($nom === '') { echo json_encode(['matches' => []]); exit; }

$stmt = db()->prepare(
  "SELECT id, cabine_nom, prenom, nom, zone FROM profiles
   WHERE role = 'cabine' AND statut = 'actif' AND LOWER(TRIM(cabine_nom)) = LOWER(?)"
);
$stmt->execute([$nom]);
echo json_encode(['matches' => $stmt->fetchAll()]);
