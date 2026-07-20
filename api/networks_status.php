<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

// Réseaux (Orange/MTN/Moov) sans AUCUNE cabine actuellement joignable pour
// les traiter — remplace le calcul de checkMissingNetworks() (js/cabine.js),
// qui ne pouvait fiablement voir que les cabines déjà présentes dans le
// cache LOCAL de l'appareil (rempli au fil de l'eau, jamais garanti
// complet côté cabine — contrairement à l'admin, une cabine ne charge
// jamais la liste de toutes les autres cabines). Calculé ici directement
// depuis `profiles`/`presence`, seule source fiable pour "qui est
// réellement en ligne, tous appareils confondus, avec quels réseaux
// actifs". Même seuil de fraîcheur que DB.presence.STALE_MS (js/db.js).
requireAuth();

const NETWORKS_STATUS_STALE_SECONDS = 25;

$rows = db()->query(
  "SELECT p.reseaux_actifs
   FROM profiles p
   INNER JOIN presence pr ON pr.profile_id = p.id
   WHERE p.role = 'cabine' AND p.statut = 'actif' AND p.en_pause = 0
     AND pr.last_seen_at >= (NOW() - INTERVAL " . NETWORKS_STATUS_STALE_SECONDS . " SECOND)"
)->fetchAll();

$present = ['orange' => false, 'mtn' => false, 'moov' => false];
foreach ($rows as $row) {
  $reseaux = $row['reseaux_actifs'] ? json_decode($row['reseaux_actifs'], true) : null;
  if (!is_array($reseaux)) continue;
  foreach (array_keys($present) as $key) {
    if (!empty($reseaux[$key])) $present[$key] = true;
  }
}

$missing = [];
foreach (['orange' => 'Orange', 'mtn' => 'MTN', 'moov' => 'Moov'] as $key => $label) {
  if (!$present[$key]) $missing[] = $label;
}

echo json_encode(['missing' => $missing]);
