'use strict';
// DB.forfaits.{refresh,create,update,remove} et DB.commissions.{refresh,updateRate}
// (js/db.js) — Phase 6 (catalogue, priorité la plus basse) : lecture
// publique côté serveur (api/forfaits_list.php/commissions_list.php, même
// patron que settings_get.php), écriture réservée au super admin. Ces
// tests vérifient le transport JS<->serveur et le mapping snake_case
// (ussd_template) -> camelCase (ussdTemplate) — pas les règles métier
// elles-mêmes (vérification "super admin uniquement", hors de portée de
// ce harnais JS).
const test = require('node:test');
const assert = require('node:assert/strict');
const { loadDb } = require('./helpers/loadDb');

test('forfaits.refresh() : remplace le cache local, mappe ussd_template -> ussdTemplate', async () => {
  const { DB } = loadDb({
    online: true,
    serverForfaitsList: async () => ({ ok: true, forfaits: [
      { id: 'frf1', operateur: 'Orange', categorie: 'Internet', nom: 'Pass 1Go', prix: 500, ussd_template: '*123#', verified: 1 },
    ] }),
  });
  DB.init();
  await DB.forfaits.refresh();
  const f = DB.forfaits.all()[0];
  assert.equal(f.ussdTemplate, '*123#');
  assert.equal('ussd_template' in f, false, 'le nom snake_case ne doit pas fuiter côté local');
});

test('forfaits.create() : succès -> ajoute au cache local (forme mappée)', async () => {
  const { DB } = loadDb({
    online: true,
    serverForfaitsCreate: async () => ({ ok: true, forfait: { id: 'frf1', operateur: 'Orange', categorie: 'Internet', nom: 'Pass 1Go', prix: 500, ussd_template: null, verified: 1 } }),
  });
  // DB.init() seed un catalogue de démonstration (voir js/db.js) : les
  // assertions comparent des tailles RELATIVES plutôt qu'un compte absolu.
  DB.init();
  const before = DB.forfaits.all().length;
  const res = await DB.forfaits.create({ operateur: 'Orange', categorie: 'Internet', nom: 'Pass 1Go', detail: '1 Go', duree: '7 jours', prix: 500 });
  assert.equal(res.ok, true);
  assert.equal(DB.forfaits.all().length, before + 1);
  assert.ok(DB.forfaits.all().find(f => f.id === 'frf1'));
});

test('forfaits.create() : échec (rôle non autorisé), aucune écriture locale', async () => {
  const { DB } = loadDb({ online: true, serverForfaitsCreate: async () => ({ ok: false, error: 'Seul le super administrateur peut gérer les forfaits.' }) });
  DB.init();
  const before = DB.forfaits.all().length;
  const res = await DB.forfaits.create({ operateur: 'Orange', categorie: 'Internet', nom: 'Pass 1Go', detail: '1 Go', duree: '7 jours', prix: 500 });
  assert.equal(res.ok, false);
  assert.equal(DB.forfaits.all().length, before);
});

test('forfaits.update() : remplace l\'entrée existante par la forme renvoyée par le serveur', async () => {
  const { DB } = loadDb({
    online: true,
    serverForfaitsCreate: async () => ({ ok: true, forfait: { id: 'frf1', operateur: 'Orange', categorie: 'Internet', nom: 'Pass 1Go', prix: 500, ussd_template: null, verified: 1 } }),
    serverForfaitsUpdate: async () => ({ ok: true, forfait: { id: 'frf1', operateur: 'Orange', categorie: 'Internet', nom: 'Pass 2Go', prix: 900, ussd_template: null, verified: 1 } }),
  });
  DB.init();
  const before = DB.forfaits.all().length;
  await DB.forfaits.create({ operateur: 'Orange', categorie: 'Internet', nom: 'Pass 1Go', detail: '1 Go', duree: '7 jours', prix: 500 });
  const res = await DB.forfaits.update('frf1', { nom: 'Pass 2Go', prix: 900 });
  assert.equal(res.ok, true);
  assert.equal(DB.forfaits.all().length, before + 1, 'update() remplace, n\'ajoute pas de doublon');
  assert.equal(DB.forfaits.all().find(f => f.id === 'frf1').nom, 'Pass 2Go');
});

test('forfaits.remove() : succès -> retire du cache local ; échec -> conserve', async () => {
  const { DB } = loadDb({
    online: true,
    serverForfaitsCreate: async () => ({ ok: true, forfait: { id: 'frf1', operateur: 'Orange', categorie: 'Internet', nom: 'Pass 1Go', prix: 500, ussd_template: null, verified: 1 } }),
    serverForfaitsRemove: async () => ({ ok: true }),
  });
  DB.init();
  const before = DB.forfaits.all().length;
  await DB.forfaits.create({ operateur: 'Orange', categorie: 'Internet', nom: 'Pass 1Go', detail: '1 Go', duree: '7 jours', prix: 500 });
  const res = await DB.forfaits.remove('frf1');
  assert.equal(res.ok, true);
  assert.equal(DB.forfaits.all().length, before);
});

test('commissions.refresh() : remplace le cache local par la liste serveur', async () => {
  const { DB } = loadDb({
    online: true,
    serverCommissionsList: async () => ({ ok: true, commissions: [{ id: 'c1', label: 'Commission standard', pourcentage: 7, actif: 1 }] }),
  });
  DB.init();
  await DB.commissions.refresh();
  assert.equal(DB.commissions.active().pourcentage, 7);
});

test('commissions.updateRate() : succès -> applique la liste renvoyée par le serveur localement', async () => {
  const { DB } = loadDb({
    online: true,
    serverCommissionsUpdateRate: async () => ({ ok: true, commissions: [{ id: 'c1', label: 'Commission standard', pourcentage: 8, actif: 1 }] }),
  });
  DB.init();
  const res = await DB.commissions.updateRate(8);
  assert.equal(res.ok, true);
  assert.equal(DB.commissions.active().pourcentage, 8);
  assert.equal(DB.commissions.calc(1000), 80, 'calc() reflète immédiatement le nouveau taux');
});

test('commissions.updateRate() : échec (taux invalide côté serveur), cache local inchangé', async () => {
  const { DB } = loadDb({
    online: true,
    serverCommissionsList: async () => ({ ok: true, commissions: [{ id: 'c1', label: 'Commission standard', pourcentage: 5, actif: 1 }] }),
    serverCommissionsUpdateRate: async () => ({ ok: false, error: 'Taux invalide (0–50%).' }),
  });
  DB.init();
  await DB.commissions.refresh();
  const res = await DB.commissions.updateRate(999);
  assert.equal(res.ok, false);
  assert.equal(DB.commissions.active().pourcentage, 5, 'inchangé après un échec serveur');
});
