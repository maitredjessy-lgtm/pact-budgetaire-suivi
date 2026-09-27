/* Routeur, authentification et demarrage */
(function () {
  const routes = {
    tableau: VueTableau,
    activites: VueActivites,
    echeances: VueEcheances,
    equipe: VueEquipe,
    documents: VueDocuments,
    rediger: VueRediger,
    editeur: VueRediger,
    modeles: VueModeles,
    evaluation: VueEvaluation,
    assistant: VueAssistant,
  };

  function routeur() {
    const [nom, ...params] = (location.hash.replace(/^#\/?/, '') || 'tableau').split('/');
    const route = routes[nom] ? nom : 'tableau';
    document.querySelectorAll('#nav a').forEach((a) => a.classList.toggle('actif', a.dataset.route === (route === 'editeur' ? 'documents' : route)));
    document.getElementById('barre').classList.remove('ouverte');
    UI.fermerModale(); UI.fermerPanneau();
    const vue = document.getElementById('vue');
    vue.innerHTML = '<div class="chargement"><span class="spinner fonce"></span> Chargement…</div>';
    window.scrollTo(0, 0);
    Promise.resolve(routes[route].afficher(vue, params, route)).catch((e) => {
      vue.innerHTML = `<div class="erreur">${UI.esc(e.message)}</div>`;
    });
  }

  async function majPastille() {
    try {
      const items = await Api.get('echeances.php');
      const n = items.filter((i) => i.jours <= 7).length;
      const titre = `${items.filter((i) => i.jours < 0).length} en retard, ${items.filter((i) => i.jours >= 0 && i.jours <= 7).length} dans les 7 jours`;
      ['pastille-ech', 'pastille-mobile'].forEach((id) => {
        const p = document.getElementById(id);
        p.textContent = id === 'pastille-mobile' ? `🔔 ${n}` : n; p.hidden = n === 0; p.title = titre;
      });
    } catch (e) { /* silencieux */ }
  }
  window.majPastille = majPastille;

  function afficherApp() {
    document.getElementById('ecran-connexion').hidden = true;
    document.getElementById('coquille').hidden = false;
    const u = Etat.user;
    const roles = { admin: 'Administrateur', editeur: 'Éditeur', lecteur: 'Lecteur' };
    document.getElementById('badge-utilisateur').innerHTML = `<strong style="color:#fff">${UI.esc(u.nom)}</strong><br><span>${roles[u.role] || u.role}</span>`;
    routeur();
    majPastille();
    setTimeout(() => VueDrive.auto(), 3000);
  }

  function afficherConnexion(configure) {
    document.getElementById('coquille').hidden = true;
    document.getElementById('ecran-connexion').hidden = false;
    const setup = !configure;
    document.getElementById('champ-nom').hidden = !setup;
    document.getElementById('connexion-setup-info').hidden = !setup;
    document.getElementById('connexion-btn').textContent = setup ? 'Créer le compte administrateur' : 'Se connecter';
    document.getElementById('connexion-mdp').autocomplete = setup ? 'new-password' : 'current-password';
    document.getElementById('form-connexion').dataset.mode = setup ? 'setup' : 'login';
  }

  document.getElementById('form-connexion').addEventListener('submit', async (e) => {
    e.preventDefault();
    const f = e.target; const err = document.getElementById('connexion-erreur');
    err.hidden = true;
    const corps = { email: f.querySelector('#connexion-email').value, password: f.querySelector('#connexion-mdp').value, nom: f.querySelector('#connexion-nom').value };
    try {
      const r = await Api.post(`auth.php?action=${f.dataset.mode}`, corps);
      Etat.user = r.user;
      afficherApp();
    } catch (ex) { err.textContent = ex.message; err.hidden = false; }
  });

  document.getElementById('btn-deconnexion').onclick = async () => {
    await Api.post('auth.php?action=logout', {});
    Etat.user = null; Etat.cache = {};
    afficherConnexion(true);
  };

  document.getElementById('btn-mdp').onclick = () => {
    const m = UI.modale(`<h2>Changer mon mot de passe</h2><form>
      <label>Mot de passe actuel <input type="password" name="ancien" required autocomplete="current-password"></label>
      <label>Nouveau mot de passe (8 caractères min.) <input type="password" name="nouveau" required minlength="8" autocomplete="new-password"></label>
      <div class="actions"><button type="submit">Enregistrer</button></div></form>`);
    m.querySelector('form').onsubmit = async (e) => {
      e.preventDefault();
      await UI.occupe(e.submitter, () => Api.post('auth.php?action=password', UI.lireForm(e.target)));
      UI.fermerModale(); UI.toast('Mot de passe modifié', 'ok');
    };
  };

  document.getElementById('btn-menu').onclick = () => document.getElementById('barre').classList.toggle('ouverte');
  document.getElementById('voile-menu').onclick = () => document.getElementById('barre').classList.remove('ouverte');

  /* Sur telephone, les tableaux de donnees s'affichent en fiches : chaque cellule
     recoit le libelle de sa colonne (data-label), lu par le CSS. */
  function etiqueterTableaux(racine) {
    racine.querySelectorAll('table:not(.fiches)').forEach((t) => {
      if (t.closest('.md')) return;
      const entetes = [...t.querySelectorAll('tr:first-child th')].map((th) => th.textContent.trim());
      if (!entetes.length) return;
      t.classList.add('fiches');
      t.querySelectorAll('tr').forEach((tr) => {
        [...tr.children].forEach((td, i) => { if (td.tagName === 'TD') td.dataset.label = entetes[i] || ''; });
      });
    });
  }
  new MutationObserver(() => etiqueterTableaux(document.body)).observe(document.body, { childList: true, subtree: true });
  window.addEventListener('hashchange', routeur);
  window.addEventListener('session-expiree', () => afficherConnexion(true));

  (async function init() {
    try {
      const s = await Api.get('auth.php?action=status');
      Etat.config = s;
      if (s.user) { Etat.user = s.user; afficherApp(); } else { afficherConnexion(s.configure); }
    } catch (e) {
      document.body.innerHTML = `<div class="erreur" style="margin:2rem">Impossible de joindre le serveur : ${UI.esc(e.message)}</div>`;
    }
  })();
})();
