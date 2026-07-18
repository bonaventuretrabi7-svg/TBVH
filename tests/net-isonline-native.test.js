'use strict';
// DB.Net.isOnline() (js/db.js) — corrige un bug rapporté : dans l'app
// Android empaquetée, un solde rechargé par l'administration (ou toute
// autre mise à jour serveur) n'apparaissait jamais sans se déconnecter/
// reconnecter, même en actualisant. Cause : navigator.onLine ment parfois
// dans cette WebView (signale "hors ligne" alors que la connexion est bien
// là) — un cas déjà documenté dans le code — et TOUT rafraîchissement
// périodique (refreshSelf, transactions.refresh...) est gated derrière
// isOnline(), contrairement à Auth.login() qui, lui, ne dépend jamais de
// ce signal (d'où le "ça remarche après reconnexion"). isOnline() ignore
// donc désormais navigator.onLine dans l'app (window.Capacitor présent) —
// voir tests/helpers/loadDb.js (option `capacitor: true`).
const test = require('node:test');
const assert = require('node:assert/strict');
const { loadDb } = require('./helpers/loadDb');

test('isOnline() : reflète navigator.onLine sur le site web (comportement inchangé)', () => {
  const { DB, net } = loadDb({ online: true, capacitor: false });
  assert.equal(DB.Net.isOnline(), true);
  net.setOnline(false);
  assert.equal(DB.Net.isOnline(), false);
});

test('isOnline() : toujours true dans l\'app Android (Capacitor), même si navigator.onLine ment', () => {
  const { DB, net } = loadDb({ online: false, capacitor: true });
  assert.equal(DB.Net.isOnline(), true, 'ignore un navigator.onLine=false trompeur dans l\'app');
  net.setOnline(true);
  assert.equal(DB.Net.isOnline(), true);
});

test('refreshSelf() dans l\'app Android : tente le réseau malgré navigator.onLine=false', async () => {
  let called = false;
  const { DB } = loadDb({
    online: false, capacitor: true,
    serverWhoami: async () => { called = true; return { ok: true, profile: { id: 'u1', role: 'client', telephone: '0700000000', solde: 5000, statut: 'actif' } }; },
  });
  DB.users.cacheFromServer({ id: 'u1', role: 'client', telephone: '0700000000', solde: 1000, statut: 'actif' });

  await DB.users.refreshSelf();
  assert.equal(called, true, 'la vérification réseau doit être tentée malgré navigator.onLine=false, dans l\'app');
  assert.equal(DB.users.byId('u1').solde, 5000);
});
