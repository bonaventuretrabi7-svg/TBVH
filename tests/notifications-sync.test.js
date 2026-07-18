'use strict';
// DB.notifications.{refresh,markRead,markAllRead} (js/db.js) — Phase C
// (mise en conformité temps réel) : la table `notifications` est déjà
// peuplée depuis la Phase 4 par createNotification() (api/bootstrap.php),
// appelée par la quasi-totalité des endpoints métier ; ces tests
// vérifient le transport JS<->serveur nouvellement ajouté (lecture +
// marquage lu), pas les règles métier elles-mêmes.
const test = require('node:test');
const assert = require('node:assert/strict');
const { loadDb } = require('./helpers/loadDb');

test('notifications.refresh() : remplace le cache local pour CET utilisateur, garde les autres intacts', async () => {
  const { DB } = loadDb({
    online: true,
    serverNotificationsList: async () => ({ ok: true, notifications: [
      { id: 'n1', utilisateur_id: 'u1', message: 'Serveur', lu: 0, date: new Date().toISOString(), type: 'info' },
    ] }),
  });
  DB.init();
  // Entrée locale pré-existante d'un AUTRE utilisateur (ex. impersonation) —
  // ne doit jamais être touchée par le refresh() de u1.
  DB.notifications.create('u2', 'Message existant u2', 'info');

  await DB.notifications.refresh('u1');

  assert.equal(DB.notifications.forUser('u1').length, 1);
  assert.equal(DB.notifications.forUser('u1')[0].message, 'Serveur');
  assert.equal(DB.notifications.forUser('u2').length, 1, 'les notifications des autres utilisateurs restent intactes');
});

test('notifications.markRead() : met à jour le cache local ET appelle le serveur', async () => {
  let calledWith = null;
  const { DB } = loadDb({
    online: true,
    serverNotificationsMarkRead: async (id) => { calledWith = id; return { ok: true }; },
  });
  DB.init();
  const n = DB.notifications.create('u1', 'Test', 'info');

  await DB.notifications.markRead(n.id);

  assert.equal(DB.notifications.forUser('u1')[0].lu, true);
  assert.equal(calledWith, n.id);
});

test('notifications.markRead() hors ligne : met à jour le cache local, ne tente jamais le réseau', async () => {
  let called = false;
  const { DB } = loadDb({
    online: false,
    serverNotificationsMarkRead: async () => { called = true; return { ok: true }; },
  });
  DB.init();
  const n = DB.notifications.create('u1', 'Test', 'info');

  await DB.notifications.markRead(n.id);

  assert.equal(DB.notifications.forUser('u1')[0].lu, true);
  assert.equal(called, false);
});

test('notifications.markAllRead() : marque uniquement les notifications de cet utilisateur', async () => {
  const { DB } = loadDb({ online: true, serverNotificationsMarkAllRead: async () => ({ ok: true }) });
  DB.init();
  DB.notifications.create('u1', 'A', 'info');
  DB.notifications.create('u1', 'B', 'info');
  DB.notifications.create('u2', 'C', 'info');

  await DB.notifications.markAllRead('u1');

  assert.ok(DB.notifications.forUser('u1').every(n => n.lu === true));
  assert.equal(DB.notifications.forUser('u2')[0].lu, false);
});
