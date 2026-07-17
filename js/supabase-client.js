/* ================================================================
   KBINE PLUS | Client Supabase
   ================================================================
   Doit être chargé après le SDK https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2
   et js/supabase-config.js, avant js/db.js et js/auth.js. */
const SupabaseAPI = (() => {
  const client = supabase.createClient(SUPABASE_CONFIG.url, SUPABASE_CONFIG.anonKey);

  // Tant que js/supabase-config.js n'a pas été renseigné avec un vrai
  // projet (valeurs par défaut "VOTRE-PROJET"/"VOTRE-ANON-KEY"), tout
  // appel réseau échouerait de toute façon (domaine inexistant,
  // ERR_NAME_NOT_RESOLVED) — DB.settings (js/db.js) vérifie ce drapeau
  // avant de tenter quoi que ce soit, pour ne jamais multiplier des
  // requêtes vouées à l'échec (retombe directement sur le cache local,
  // déjà le comportement hors ligne existant).
  const isConfigured = !/VOTRE-PROJET|VOTRE-ANON-KEY/.test(SUPABASE_CONFIG.url + SUPABASE_CONFIG.anonKey);

  /* Appelle l'Edge Function `login` (vérifie identifiant+PIN côté serveur
     via verify_login()), puis adopte la session Supabase retournée pour que
     les appels suivants (RLS basé sur auth.uid()) soient authentifiés. */
  async function login(identifiant, pin, role) {
    const { data, error } = await client.functions.invoke('login', {
      body: { identifiant, pin, role },
    });
    if (error || !data || data.error) {
      return { ok: false, error: (data && data.error) || 'Identifiant ou PIN incorrect.' };
    }
    await client.auth.setSession({
      access_token: data.session.access_token,
      refresh_token: data.session.refresh_token,
    });
    return { ok: true, profile: data.profile };
  }

  async function logout() {
    await client.auth.signOut();
  }

  return { client, login, logout, isConfigured };
})();
