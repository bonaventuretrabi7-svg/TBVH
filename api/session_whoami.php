<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

// Valide le jeton "Authorization: Bearer <token>" envoyé et renvoie le
// profil à jour — utilisé pour la reprise de session "rester connecté"
// (voir Auth.resumeSession()/ServerAPI.whoami(), js/auth.js et
// js/server-api.js) : plus aucune session n'est rouverte uniquement depuis
// des données locales, ce endpoint est la vérification serveur exigée à
// chaque redémarrage de l'app.

$profile = requireAuth();
unset($profile['mot_de_passe_hash']);
echo json_encode(['profile' => $profile]);
