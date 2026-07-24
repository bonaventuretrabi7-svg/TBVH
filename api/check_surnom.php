<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

// Vérifie qu'un surnom n'est pas déjà pris par un autre client — aperçu en
// direct à la saisie (voir _wireTakenCheck(), js/client.js) ET revérifié
// avant de passer à l'étape suivante de l'inscription (handleAuthGateRegister()).
// Public (aucune authentification : l'utilisateur n'est pas encore inscrit à
// ce stade), ne renvoie qu'un booléen, jamais l'identité du compte trouvé —
// voir migration_phase32_client_surnom_unique.sql pour la contrainte
// définitive côté base (colonne générée client_prenom_key, unique).
$in = body();
$prenom = trim((string)($in['prenom'] ?? ''));
if ($prenom === '') fail('Surnom requis.');

$stmt = db()->prepare("SELECT id FROM profiles WHERE role = 'client' AND LOWER(TRIM(prenom)) = LOWER(TRIM(?))");
$stmt->execute([$prenom]);

echo json_encode(['ok' => true, 'exists' => (bool)$stmt->fetch()]);
