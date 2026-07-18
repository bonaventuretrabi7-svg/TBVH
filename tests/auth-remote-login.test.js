'use strict';
// Vérifie la suppression de la connexion hors ligne : Auth.login() (écran
// PIN) et Auth.resumeSession() ("rester connecté", voir js/cabine.js
// _tryRememberMeRestore()) exigent désormais TOUS LES DEUX une vérification
// serveur réussie avant d'ouvrir la moindre session — un compte déjà
// "onboardé" localement (voir js/db.js DB.users.cacheFromServer) ne suffit
// plus à lui seul, et une panne réseau réelle (networkError, voir
// js/server-api.js _call()) est distinguée d'un refus applicatif (mauvais
// PIN, compte bloqué...) renvoyé par le serveur. Voir tests/helpers/loadApp.js
// pour les mocks ServerAPI injectables.
const test = require('node:test');
const assert = require('node:assert/strict');
const { loadApp } = require('./helpers/loadApp');

function serverProfile(overrides = {}) {
  return {
    id: 'uuid-server-1', nom: 'Traoré', prenom: 'Awa', telephone: '0711223344',
    email: null, role: 'client', solde: 5000, statut: 'actif',
    admin_level: null, permissions: null, zone: null, cabine_nom: null,
    commissions_total: 0, transferts_total: 0, limite_commandes: null,
    tentatives_echouees: 0, suspendu_auto: false, suspendu_by: null,
    suspendu_motif: null, suspendu_jusqu: null, abonnement: null,
    date_creation: '2026-01-01T00:00:00.000Z',
    ...overrides,
  };
}

/* ── Auth.login() (écran PIN) ─────────────────────────────────────── */

test('connexion réussie : ouvre la session et met le profil à jour en cache local', async () => {
  const calls = [];
  const serverLogin = async (identifiant, pin, role) => {
    calls.push({ identifiant, pin, role });
    return { ok: true, profile: serverProfile() };
  };
  const { DB, Auth } = loadApp({ serverLogin });
  DB.init();

  const res = await Auth.login('0711223344', '1234', false, 'client');
  assert.equal(res.ok, true);
  assert.equal(res.user.id, 'uuid-server-1');
  assert.equal(res.user.solde, 5000);
  assert.equal(calls.length, 1);
  assert.deepEqual(calls[0], { identifiant: '0711223344', pin: '1234', role: 'client' });

  const cached = DB.users.byId('uuid-server-1');
  assert.ok(cached);
});

test('compte introuvable côté serveur : erreur, aucune session', async () => {
  const serverLogin = async () => ({ ok: false, error: 'Compte introuvable.' });
  const { DB, Auth } = loadApp({ serverLogin });
  DB.init();

  const res = await Auth.login('0799999999', '1234', false, 'client');
  assert.equal(res.ok, false);
  assert.equal(res.error, 'Compte introuvable.');
  assert.equal(Auth.current(), null);
});

test('identifiants incorrects côté serveur : erreur renvoyée telle quelle, aucun compteur local', async () => {
  const serverLogin = async () => ({ ok: false, error: 'Identifiant ou PIN incorrect.' });
  const { DB, Auth } = loadApp({ serverLogin });
  DB.init();
  DB.users.create({
    prenom: 'Awa', nom: 'Traoré', telephone: '0711223344',
    mot_de_passe: '1234', role: 'client', statut: 'actif',
  });

  const res = await Auth.login('0711223344', '0000', false, 'client');
  assert.equal(res.ok, false);
  assert.equal(res.error, 'Identifiant ou PIN incorrect.');
  // Le blocage après 3 essais est désormais entièrement géré par
  // api/login.php (voir tentatives_echouees côté serveur) — plus de
  // compteur local dupliqué qui pourrait bloquer un compte que le serveur
  // considère encore valide.
  const stored = DB.users.byPhoneAndRole('0711223344', 'client');
  assert.equal(stored.statut, 'actif');
});

test('compte marqué "bloqué" en cache local mais actif côté serveur : la connexion réussit quand même (le serveur fait foi, pas le cache)', async () => {
  const serverLogin = async () => ({ ok: true, profile: serverProfile({ statut: 'actif' }) });
  const { DB, Auth } = loadApp({ serverLogin });
  DB.init();
  DB.users.create({
    prenom: 'Awa', nom: 'Traoré', telephone: '0711223344',
    mot_de_passe: '1234', role: 'client', statut: 'bloqué',
  });

  const res = await Auth.login('0711223344', '1234', false, 'client');
  assert.equal(res.ok, true);
});

test('panne réseau réelle : "Connexion Internet requise", même si le compte est déjà connu localement', async () => {
  let called = false;
  const serverLogin = async () => { called = true; return { ok: false, networkError: true, error: 'Connexion Internet requise pour vous connecter.' }; };
  const { DB, Auth } = loadApp({ serverLogin });
  DB.init();
  DB.users.create({
    prenom: 'Awa', nom: 'Traoré', telephone: '0711223344',
    mot_de_passe: '1234', role: 'client', statut: 'actif',
  });

  const res = await Auth.login('0711223344', '1234', false, 'client');
  assert.equal(res.ok, false);
  assert.match(res.error, /Connexion Internet requise/);
  assert.equal(called, true); // l'appel a bien été tenté, c'est sa réponse qui signale la panne
});

test('rôle de connexion manquant : erreur explicite, aucun appel serveur', async () => {
  let called = false;
  const serverLogin = async () => { called = true; return { ok: true, profile: serverProfile() }; };
  const { DB, Auth } = loadApp({ serverLogin });
  DB.init();

  const res = await Auth.login('0711223344', '1234', false, undefined);
  assert.equal(res.ok, false);
  assert.equal(called, false);
});

test('deux connexions successives sur le même appareil : le serveur est revérifié à chaque fois, jamais de repli local', async () => {
  let calls = 0;
  const serverLogin = async () => { calls++; return { ok: true, profile: serverProfile() }; };
  const { DB, Auth } = loadApp({ serverLogin });
  DB.init();

  const first = await Auth.login('0711223344', '1234', false, 'client');
  assert.equal(first.ok, true);
  assert.equal(calls, 1);

  const second = await Auth.login('0711223344', '1234', false, 'client');
  assert.equal(second.ok, true);
  assert.equal(calls, 2); // pas de repli local : chaque connexion revérifie le serveur
});

test('compte déjà connu localement sous un ancien id : la fusion serveur met à jour les champs mais conserve l\'id local (ne casse pas les données déjà liées)', async () => {
  const serverLogin = async () => ({ ok: true, profile: serverProfile({ solde: 12000 }) });
  const { DB, Auth } = loadApp({ serverLogin });
  DB.init();
  const localUser = DB.users.create({
    prenom: 'Awa', nom: 'Traoré', telephone: '0711223344',
    mot_de_passe: '0000', role: 'client', statut: 'actif',
  });

  const res = await Auth.login('0711223344', '1234', false, 'client');
  assert.equal(res.ok, true);
  assert.equal(res.user.id, localUser.id);
  assert.equal(res.user.solde, 12000);
});

/* ── Auth.resumeSession() ("rester connecté", voir js/cabine.js) ────── */

test('resumeSession : jeton valide, rouvre la session avec le profil à jour', async () => {
  const serverWhoami = async () => ({ ok: true, profile: serverProfile({ solde: 7000 }) });
  const { DB, Auth } = loadApp({ serverWhoami });
  DB.init();

  const res = await Auth.resumeSession('un-jeton-serveur-valide');
  assert.equal(res.ok, true);
  assert.equal(res.user.solde, 7000);
  assert.equal(Auth.current().id, 'uuid-server-1');
});

test('resumeSession : jeton invalide/expiré, ok:false sans networkError (à oublier côté appelant)', async () => {
  const serverWhoami = async () => ({ ok: false, error: 'Session expirée, reconnectez-vous.' });
  const { DB, Auth } = loadApp({ serverWhoami });
  DB.init();

  const res = await Auth.resumeSession('un-vieux-jeton');
  assert.equal(res.ok, false);
  assert.ok(!res.networkError);
  assert.equal(Auth.current(), null);
});

test('resumeSession : panne réseau, ok:false avec networkError:true (jeton à conserver, réessayer plus tard)', async () => {
  const serverWhoami = async () => ({ ok: false, networkError: true, error: 'Connexion Internet requise.' });
  const { DB, Auth } = loadApp({ serverWhoami });
  DB.init();

  const res = await Auth.resumeSession('un-jeton-quelconque');
  assert.equal(res.ok, false);
  assert.equal(res.networkError, true);
  assert.equal(Auth.current(), null);
});

test('resumeSession : jeton valide mais compte bloqué entre-temps, refusé malgré la vérification serveur réussie', async () => {
  const serverWhoami = async () => ({ ok: true, profile: serverProfile({ statut: 'bloqué' }) });
  const { DB, Auth } = loadApp({ serverWhoami });
  DB.init();

  const res = await Auth.resumeSession('un-jeton-valide');
  assert.equal(res.ok, false);
  assert.match(res.error, /bloqué/);
  assert.equal(Auth.current(), null);
});
