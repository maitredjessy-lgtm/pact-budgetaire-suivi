const VueEquipe = {
  onglet: 'membres',

  ligneTache(t, avecActivite = true) {
    const ecrire = UI.peutEcrire();
    return `<div class="item-doc" data-tache="${t.id}">
      <input type="checkbox" style="width:auto;margin:0" data-cocher="${t.id}" ${t.statut === 'termine' ? 'checked' : ''} ${ecrire ? '' : 'disabled'} title="Marquer comme terminée">
      <span class="t" style="${t.statut === 'termine' ? 'text-decoration:line-through;color:var(--gris)' : ''}">${UI.esc(t.titre)}
        ${avecActivite && t.activite_titre ? `<br><span class="muted">🗂️ ${t.activite_code ? UI.esc(t.activite_code) + ' — ' : ''}${UI.esc(t.activite_titre)}</span>` : ''}</span>
      <span class="muted">👤 ${UI.esc(t.responsable_nom || 'Non assigné')}</span>
      <span style="white-space:nowrap">${UI.date(t.echeance)} ${t.statut !== 'termine' ? UI.delai(t.echeance) : ''}</span>
      ${ecrire ? `<button class="btn-lien btn-sm" data-suppr-tache="${t.id}" title="Supprimer">🗑️</button>` : ''}
    </div>`;
  },

  brancherTaches(racine, apres) {
    racine.querySelectorAll('[data-cocher]').forEach((c) => {
      c.onchange = async () => {
        await Api.put(`taches.php?id=${c.dataset.cocher}`, { statut: c.checked ? 'termine' : 'a_faire' });
        window.majPastille?.(); apres();
      };
    });
    racine.querySelectorAll('[data-suppr-tache]').forEach((b) => {
      b.onclick = async () => {
        if (!confirm('Supprimer cette tâche ?')) return;
        await Api.del(`taches.php?id=${b.dataset.supprTache}`); apres();
      };
    });
  },

  async afficher(vue) {
    const admin = Etat.user.role === 'admin';
    vue.innerHTML = `
      <div class="entete-vue"><div><h1>Équipe & tâches</h1><p class="muted">Personnes imputées aux activités, charge de travail et répartition des tâches.</p></div>
        <div class="actions">${UI.peutEcrire() ? '<button id="btn-membre">+ Membre</button><button class="btn-sec" id="btn-tache">+ Tâche</button>' : ''}</div></div>
      <div class="onglets">
        <button data-o="membres">👥 Membres</button><button data-o="taches">✅ Toutes les tâches</button>${admin ? '<button data-o="comptes">🔐 Comptes d\'accès</button>' : ''}
      </div>
      <div id="contenu"></div>`;
    vue.querySelectorAll('[data-o]').forEach((b) => {
      b.classList.toggle('actif', b.dataset.o === this.onglet);
      b.onclick = () => { this.onglet = b.dataset.o; this.afficher(vue); };
    });
    vue.querySelector('#btn-membre')?.addEventListener('click', () => this.formMembre({}, () => this.afficher(vue)));
    vue.querySelector('#btn-tache')?.addEventListener('click', () => this.formTache({}, () => this.afficher(vue)));
    const c = vue.querySelector('#contenu');
    if (this.onglet === 'taches') return this.taches(c, vue);
    if (this.onglet === 'comptes' && admin) return this.comptes(c);
    return this.membres(c, vue);
  },

  async membres(c, vue) {
    const resp = await reference('responsables', true);
    c.innerHTML = `<div class="equipe-grille">${resp.map((r) => `
      <div class="carte" style="margin:0">
        <div style="display:flex;gap:.7rem;align-items:center">
          <div class="avatar">${UI.esc(UI.initiales(r.nom))}</div>
          <div style="min-width:0"><b>${UI.esc(r.nom)}</b><br><span class="muted">${UI.esc(r.fonction || '')}</span></div>
        </div>
        <div class="muted" style="margin-top:.5rem;font-size:.82rem">${r.email ? `✉️ <a href="mailto:${UI.esc(r.email)}">${UI.esc(r.email)}</a><br>` : ''}${r.telephone ? `📞 ${UI.esc(r.telephone)}` : ''}</div>
        <div class="chiffres">
          <span><b>${r.nb_activites}</b>activités</span><span><b>${r.nb_taches}</b>tâches</span><span><b>${r.nb_documents}</b>docs</span>
          <span style="color:${r.nb_retards ? 'var(--rouge)' : 'inherit'}"><b style="color:inherit">${r.nb_retards}</b>retards</span>
        </div>
        <div class="actions" style="justify-content:flex-start">
          <a class="btn btn-sec btn-sm" href="#/echeances" data-voir="${r.id}">Ses échéances</a>
          ${UI.peutEcrire() ? `<button class="btn-lien btn-sm" data-modif="${r.id}">Modifier</button>` : ''}
        </div>
      </div>`).join('')}</div>`;
    c.querySelectorAll('[data-voir]').forEach((a) => { a.onclick = () => { VueEcheances.resp = a.dataset.voir; }; });
    c.querySelectorAll('[data-modif]').forEach((b) => {
      b.onclick = () => this.formMembre(resp.find((r) => String(r.id) === b.dataset.modif), () => this.afficher(vue));
    });
  },

  formMembre(r, apres) {
    const m = UI.modale(`<h2>${r.id ? 'Modifier le membre' : "Nouveau membre de l'équipe"}</h2>
      <form class="grille-form">
        <label class="plein">Nom * <input name="nom" required value="${UI.esc(r.nom)}"></label>
        <label>Fonction <input name="fonction" value="${UI.esc(r.fonction)}"></label>
        <label>Téléphone <input name="telephone" value="${UI.esc(r.telephone)}"></label>
        <label class="plein">E-mail (pour les relances) <input type="email" name="email" value="${UI.esc(r.email)}"></label>
        <div class="actions plein">${r.id ? '<button type="button" class="btn-danger" id="suppr">Supprimer</button>' : ''}<button type="submit">Enregistrer</button></div>
      </form>`);
    m.querySelector('form').onsubmit = async (e) => {
      e.preventDefault();
      const d = UI.lireForm(e.target);
      await UI.occupe(e.submitter, () => (r.id ? Api.put(`responsables.php?id=${r.id}`, d) : Api.post('responsables.php', d)));
      invalider('responsables'); UI.fermerModale(); UI.toast('Enregistré', 'ok'); apres();
    };
    m.querySelector('#suppr')?.addEventListener('click', async () => {
      if (!confirm(`Supprimer ${r.nom} ? Ses activités et tâches deviendront « non assignées ».`)) return;
      await Api.del(`responsables.php?id=${r.id}`);
      invalider('responsables'); UI.fermerModale(); apres();
    });
  },

  async formTache(t, apres) {
    const [resp, acts] = await Promise.all([reference('responsables'), reference('activites')]);
    const m = UI.modale(`<h2>Nouvelle tâche</h2><form class="grille-form">
      <label class="plein">Tâche * <input name="titre" required></label>
      <label class="plein">Activité liée <select name="activite_id">${UI.optionsListe(acts, 'id', (a) => `${a.code ? a.code + ' — ' : ''}${a.titre}`, t.activite_id, 'Aucune')}</select></label>
      <label>Personne imputée <select name="responsable_id">${UI.optionsListe(resp, 'id', 'nom', t.responsable_id, 'Non assigné')}</select></label>
      <label>Échéance <input type="date" name="echeance"></label>
      <div class="actions plein"><button type="submit">Ajouter</button></div></form>`);
    m.querySelector('form').onsubmit = async (e) => {
      e.preventDefault();
      await UI.occupe(e.submitter, () => Api.post('taches.php', UI.lireForm(e.target)));
      UI.fermerModale(); UI.toast('Tâche ajoutée', 'ok'); window.majPastille?.(); apres();
    };
  },

  async taches(c, vue) {
    const [taches, resp] = await Promise.all([Api.get('taches.php'), reference('responsables')]);
    this.filtreResp ??= '';
    this.voirTerminees ??= false;
    const dessiner = () => {
      const l = taches.filter((t) => (!this.filtreResp || String(t.responsable_id) === this.filtreResp) && (this.voirTerminees || t.statut !== 'termine'));
      c.querySelector('#liste-taches').innerHTML = l.map((t) => this.ligneTache(t)).join('') || '<p class="vide-etat">Aucune tâche. Ajoutez-en depuis une activité (checklist) ou avec « + Tâche ».</p>';
      this.brancherTaches(c, () => this.afficher(vue));
    };
    c.innerHTML = `<div class="filtres"><select id="f-r">${UI.optionsListe(resp, 'id', 'nom', this.filtreResp, 'Toutes les personnes')}</select>
      <label style="margin:0;display:flex;gap:.4rem;align-items:center;font-weight:400"><input type="checkbox" id="f-t" style="width:auto;margin:0" ${this.voirTerminees ? 'checked' : ''}> Afficher les tâches terminées</label></div>
      <div class="carte" id="liste-taches"></div>`;
    c.querySelector('#f-r').onchange = (e) => { this.filtreResp = e.target.value; dessiner(); };
    c.querySelector('#f-t').onchange = (e) => { this.voirTerminees = e.target.checked; dessiner(); };
    dessiner();
  },

  async comptes(c) {
    const users = await Api.get('utilisateurs.php');
    const roles = { admin: 'Administrateur', editeur: 'Éditeur (modifie et rédige)', lecteur: 'Lecteur (consultation seule)' };
    c.innerHTML = `<div class="carte">
      <div class="entete-vue"><h2>Comptes d'accès à la plateforme</h2><button id="btn-compte">+ Créer un compte</button></div>
      <div class="table-wrap"><table><tr><th>Nom</th><th>E-mail</th><th>Rôle</th><th></th></tr>
      ${users.map((u) => `<tr><td>${UI.esc(u.nom)}</td><td>${UI.esc(u.email)}</td>
        <td><select data-role="${u.id}" ${u.id === Etat.user.id ? 'disabled' : ''}>${UI.options(roles, u.role)}</select></td>
        <td style="white-space:nowrap"><button class="btn-lien btn-sm" data-mdp="${u.id}">Réinitialiser le mot de passe</button>
        ${u.id !== Etat.user.id ? `<button class="btn-lien btn-sm" data-suppr="${u.id}">🗑️</button>` : ''}</td></tr>`).join('')}
      </table></div></div>`;
    c.querySelector('#btn-compte').onclick = () => {
      const m = UI.modale(`<h2>Créer un compte</h2><form>
        <label>Nom * <input name="nom" required></label><label>E-mail * <input type="email" name="email" required></label>
        <label>Mot de passe provisoire (8 caractères min.) * <input type="text" name="password" required minlength="8" autocomplete="new-password"></label>
        <label>Rôle <select name="role">${UI.options(roles, 'editeur')}</select></label>
        <p class="muted">Communiquez le mot de passe à la personne : elle pourra le changer après connexion.</p>
        <div class="actions"><button type="submit">Créer</button></div></form>`);
      m.querySelector('form').onsubmit = async (e) => {
        e.preventDefault();
        await UI.occupe(e.submitter, () => Api.post('utilisateurs.php', UI.lireForm(e.target)));
        UI.fermerModale(); UI.toast('Compte créé', 'ok'); this.comptes(c);
      };
    };
    c.querySelectorAll('[data-role]').forEach((s) => { s.onchange = async () => { await Api.put(`utilisateurs.php?id=${s.dataset.role}`, { role: s.value }).catch((e) => UI.toast(e.message, 'erreur')); UI.toast('Rôle modifié', 'ok'); }; });
    c.querySelectorAll('[data-mdp]').forEach((b) => {
      b.onclick = async () => {
        const mdp = prompt('Nouveau mot de passe provisoire (8 caractères minimum) :');
        if (mdp) { await Api.put(`utilisateurs.php?id=${b.dataset.mdp}`, { password: mdp }).then(() => UI.toast('Mot de passe réinitialisé', 'ok')).catch((e) => UI.toast(e.message, 'erreur')); }
      };
    });
    c.querySelectorAll('[data-suppr]').forEach((b) => {
      b.onclick = async () => { if (confirm('Supprimer ce compte ?')) { await Api.del(`utilisateurs.php?id=${b.dataset.suppr}`); this.comptes(c); } };
    });
  },
};
