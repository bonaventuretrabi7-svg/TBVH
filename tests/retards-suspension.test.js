'use strict';
// Règles de suspension cabine qui restent 100% locales après la Phase 4
// (moteur de commandes) : checkAutoUnsuspend() (appelée à la connexion,
// voir Auth._checkAccountGates dans js/auth.js) et suspendCabineAuto()
// (helper interne réutilisé par le portage serveur — voir orders_common.php
// — mais dont la copie JS reste utile pour ce cas de connexion isolé).
// suspendCabineManually() est désormais un appel serveur (voir
// api/cabine_suspend_manual.php, testé pour le transport dans
// tests/orders-business.test.js). Les scénarios de détection de retard/
// réattribution/suspension par accumulation de retards ont été retirés
// d'ici : cette logique vit désormais dans api/orders_sweep.php +
// orders_common.php.
const test = require('node:test');
const assert = require('node:assert/strict');
const { loadDb } = require('./helpers/loadDb');

function setup(startNow) {
  const { DB, clock } = loadDb(startNow);
  DB.init();
  return { DB, clock };
}

function makeCabine(DB, overrides = {}) {
  return DB.users.create({
    prenom: 'Cab', nom: overrides.nom || 'Test', telephone: overrides.telephone || ('07' + Math.random().toString().slice(2, 10)),
    email: overrides.email || `cab${Math.random().toString(36).slice(2)}@gmail.com`,
    mot_de_passe: '1234', role: 'cabine', solde: 0, statut: 'actif', ...overrides,
  });
}

test('déblocage automatique pile à l\'heure (checkAutoUnsuspend et sweepAutoUnsuspensions)', async () => {
  const { DB, clock } = setup();
  const cabA = makeCabine(DB, { nom: 'Suspendue1' });
  const cabB = makeCabine(DB, { nom: 'Suspendue2' }); // sans commande en attente

  DB.business.suspendCabineAuto(cabA.id, 'test');
  const jusqu = new Date(DB.users.byId(cabA.id).suspendu_jusqu).getTime();
  assert.equal(jusqu, clock.now + 24 * 60 * 60 * 1000);

  // 1 seconde avant l'échéance : toujours suspendue.
  clock.now = jusqu - 1000;
  DB.business.checkAutoUnsuspend(cabA.id);
  assert.equal(DB.users.byId(cabA.id).statut, 'suspendu');

  // Pile à l'échéance : débloquée.
  clock.now = jusqu;
  DB.business.checkAutoUnsuspend(cabA.id);
  const cabAfter = DB.users.byId(cabA.id);
  assert.equal(cabAfter.statut, 'actif');
  assert.equal(cabAfter.suspendu_jusqu, null);
  assert.equal(DB.suspensionLogs.active(cabA.id), null);

  // cabB : suspendue, sans commande en attente — sweepAutoUnsuspensions()
  // est désormais un appel serveur (voir api/orders_sweep_unsuspend.php,
  // testé pour le transport dans tests/orders-business.test.js) ; ici on
  // vérifie seulement que suspendCabineAuto()/checkAutoUnsuspend() (encore
  // locaux) posent des données cohérentes entre elles.
  DB.business.suspendCabineAuto(cabB.id, 'test2');
  const jusquB = new Date(DB.users.byId(cabB.id).suspendu_jusqu).getTime();
  clock.now = jusquB + 1000;
  const lifted = DB.business.checkAutoUnsuspend(cabB.id);
  assert.equal(lifted, true);
  assert.equal(DB.users.byId(cabB.id).statut, 'actif');
});

