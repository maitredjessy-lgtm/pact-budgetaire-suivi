/* Liaison Google Drive : configuration, synchronisation manuelle et automatique */
const VueDrive = {
  enCours: false,

  /** Synchronise par lots jusqu'a la fin ; surProgression(stats cumulees) a chaque lot */
  async synchroniser(surProgression) {
    if (this.enCours) return null;
    this.enCours = true;
    const total = { lies: 0, crees: 0, mis_a_jour: 0, preserves: 0, erreurs: [] };
    try {
      for (let i = 0; i < 60; i++) {
        const r = await Api.post('drive.php?action=synchroniser', {});
        ['lies', 'crees', 'mis_a_jour', 'preserves'].forEach((k) => { total[k] += r[k]; });
        total.erreurs.push(...r.erreurs);
        total.total_drive = r.total_drive; total.restants = r.restants; total.etat = r.etat;
        surProgression?.(total);
        if (r.restants <= 0) break;
      }
      return total;
    } finally {
      this.enCours = false;
    }
  },

  /** Au demarrage : synchronisation silencieuse si la derniere date de plus de 12 h */
  async auto() {
    if (!UI.peutEcrire()) return;
    try {
      const e = await Api.get('drive.php');
      if (!e.configure || !e.dossier_id) return;
      if (e.derniere_synchro && Date.now() - new Date(e.derniere_synchro).getTime() < 12 * 3600 * 1000) return;
      const r = await this.synchroniser();
      if (r && (r.crees || r.mis_a_jour)) {
        UI.toast(`Google Drive : ${r.crees} nouveau(x) document(s), ${r.mis_a_jour} mis à jour`, 'ok');
        invalider('activites');
      }
    } catch (err) { /* silencieux : la synchronisation manuelle affichera l'erreur */ }
  },

  async ouvrir(apres) {
    const e = await Api.get('drive.php');
    const admin = Etat.user.role === 'admin';
    const etapes = `
      <ol class="etapes">
        <li>Dans <b>votre navigateur habituel</b>, ouvrez <a href="https://console.cloud.google.com/projectcreate" target="_blank" rel="noopener">Google Cloud Console</a> (connecté avec le compte Google du Drive) et créez un projet, par ex. « PACT Budgetaire ».</li>
        <li>Activez l'API Google Drive : <a href="https://console.cloud.google.com/apis/library/drive.googleapis.com" target="_blank" rel="noopener">page de l'API Drive</a> → <b>Activer</b>.</li>
        <li>Créez un compte de service : <a href="https://console.cloud.google.com/iam-admin/serviceaccounts/create" target="_blank" rel="noopener">IAM → Comptes de service → Créer</a> (nom : « site-pact », aucun rôle nécessaire).</li>
        <li>Dans ce compte de service : onglet <b>Clés</b> → <b>Ajouter une clé</b> → <b>JSON</b>. Un fichier .json se télécharge : ouvrez-le avec le Bloc-notes et copiez <b>tout</b> son contenu.</li>
        <li>Dans <a href="https://dashboard.render.com" target="_blank" rel="noopener">Render</a> → service → <b>Environment</b> → <b>Add Environment Variable</b> : nom <code>GOOGLE_SERVICE_ACCOUNT_JSON</code>, valeur : le contenu copié. Enregistrez (le site redémarre en 1 à 2 min).</li>
        <li>Revenez ici : l'adresse du compte de service s'affichera. Partagez-lui votre dossier Drive en <b>Lecteur</b>, puis collez le lien du dossier ci-dessous.</li>
      </ol>`;
    const m = UI.modale(`<h2>☁️ Google Drive</h2>
      ${!e.configure ? `<div class="alerte">Le site n'est pas encore relié à Google Drive. Un administrateur doit suivre ces étapes une seule fois (10 minutes) :</div>${etapes}`
        : `<div class="info">✅ Connexion Google configurée.<br>Compte du site à inviter sur le dossier Drive (rôle <b>Lecteur</b>) :
            <div style="display:flex;gap:.4rem;margin-top:.4rem;flex-wrap:wrap"><code id="email-compte" style="background:#fff;padding:.3rem .5rem;border-radius:6px;overflow-wrap:anywhere">${UI.esc(e.compte)}</code>
            <button class="btn-sec btn-sm" id="copier-email">📋 Copier</button></div></div>
          <p style="margin:.4rem 0"><b>Dossier synchronisé :</b> ${e.dossier_id ? `<a href="${UI.esc(e.dossier_lien)}" target="_blank" rel="noopener">${UI.esc(e.dossier_nom || e.dossier_id)}</a>` : '<span class="muted">aucun</span>'}<br>
          <b>Dernière synchronisation :</b> ${e.derniere_synchro ? new Date(e.derniere_synchro).toLocaleString('fr-FR') : 'jamais'} · <b>${e.documents_lies}</b> documents reliés au Drive</p>
          ${admin ? `<form id="form-dossier" class="filtres" style="margin:.8rem 0">
            <input name="dossier" placeholder="Lien du dossier Drive : https://drive.google.com/drive/folders/…" value="${UI.esc(e.dossier_lien || '')}" style="flex:1;min-width:240px">
            <button type="submit" class="btn-sec">Enregistrer le dossier</button></form>` : ''}
          <div id="progression"></div>
          <div class="actions"><button id="btn-sync" ${e.dossier_id ? '' : 'disabled'}>🔄 Synchroniser maintenant</button></div>
          <p class="muted" style="font-size:.82rem">Le site lit le Drive sans jamais le modifier. Nouveaux fichiers : ajoutés (texte lisible, original joint, lien « Ouvrir dans Drive »). Fichiers modifiés : mis à jour, sauf si le texte a été retouché sur le site. Synchronisation automatique toutes les 12 h à l'ouverture du site.</p>`}`, { large: true });

    m.querySelector('#copier-email')?.addEventListener('click', () => navigator.clipboard.writeText(e.compte).then(() => UI.toast('Adresse copiée')));
    m.querySelector('#form-dossier')?.addEventListener('submit', async (ev) => {
      ev.preventDefault();
      await UI.occupe(ev.submitter, () => Api.post('drive.php?action=dossier', UI.lireForm(ev.target)), 'Vérification…');
      UI.toast('Dossier enregistré et accessible', 'ok');
      this.ouvrir(apres);
    });
    m.querySelector('#btn-sync')?.addEventListener('click', async (ev) => {
      const zone = m.querySelector('#progression');
      const btn = ev.currentTarget;
      const r = await UI.occupe(btn, () => this.synchroniser((t) => {
        const faits = t.total_drive - t.restants;
        zone.innerHTML = `<div class="ligne-prog"><div class="haut"><span>${faits} / ${t.total_drive} fichiers traités</span><span>${t.crees} nouveaux · ${t.mis_a_jour} mis à jour · ${t.lies} reliés</span></div>${UI.prog(t.total_drive ? (100 * faits) / t.total_drive : 100, 'vert')}</div>`;
      }), 'Synchronisation…');
      if (!r) return;
      zone.innerHTML += r.erreurs.length ? `<div class="erreur">${r.erreurs.length} fichier(s) en erreur :<br>${r.erreurs.slice(0, 5).map(UI.esc).join('<br>')}</div>` : '';
      UI.toast(`Synchronisation terminée : ${r.crees} nouveau(x), ${r.mis_a_jour} mis à jour, ${r.lies} relié(s)${r.preserves ? `, ${r.preserves} conservé(s) (modifiés sur le site)` : ''}`, 'ok');
      invalider('activites');
      apres?.();
    });
  },
};
