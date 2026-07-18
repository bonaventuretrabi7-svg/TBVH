'use strict';
// Règles de suspension cabine qui restent 100% locales après la Phase 4
// (moteur de commandes) : checkAutoUnsuspend() (appelée à la connexion,
// voir Auth._checkAccountGates dans js/auth.js) et suspendCabineAuto()/
// suspendCabineManually() (helpers réutilisés par des endpoints déjà
// migrés côté serveur — voir api/orders_common.php pour leur portage PHP
// — mais dont la copie JS reste utile pour ce cas de connexion isolé).
// Les scénarios de détection de retard/réattribution/suspension par
// accumulation de retards ont été retirés d'ici : cette logique vit
// désormais dans api/orders_sweep.php + orders_common.php (voir
// tests/orders-business.test.js pour ce qui reste testable côté JS —
// le transport, pas les règles elles-mêmes).
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

test('suspensionLogs : suspension manuelle puis levée enregistrent date_levee/levee_par', () => {
  const { DB } = setup();
  const cab = makeCabine(DB, { nom: 'Manuelle' });

  DB.business.suspendCabineManually(cab.id, 'motif test', 'admin123');
  const open = DB.suspensionLogs.active(cab.id);
  assert.ok(open);
  assert.equal(open.auto, false);
  assert.equal(open.motif, 'motif test');
  assert.equal(open.date_levee, null);

  // Levée manuelle par un admin — même opération que toggleCabine(id, true)
  // dans js/admin.js (fonction DOM-dépendante, non chargeable dans ce
  // harnais de test).
  DB.users.update(cab.id, { statut: 'actif', suspendu_auto: false, suspendu_by: null, suspendu_motif: null, suspendu_jusqu: null });
  DB.suspensionLogs.close(cab.id, 'admin123');

  assert.equal(DB.suspensionLogs.active(cab.id), null);
  const closed = DB.suspensionLogs.byCabine(cab.id)[0];
  assert.ok(closed.date_levee);
  assert.equal(closed.levee_par, 'admin123');
});
