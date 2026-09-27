const VueEvaluation = {
  async afficher(vue) {
    const [ind, dash] = await Promise.all([Api.get('indicateurs.php'), Api.get('dashboard.php')]);
    const ecrire = UI.peutEcrire();
    const num = (v) => (v === null || v === undefined || v === '' ? '—' : Number(v).toLocaleString('fr-FR'));
    const niveaux = { impact: 'Impact (objectif général)', effet: 'Effets (objectifs spécifiques)', produit: 'Produits' };
    const k = dash.kpi;
    vue.innerHTML = `
      <div class="entete-vue">
        <div><h1>🎯 Indicateurs & évaluation</h1><p class="muted">Suivi du cadre logique et analyse de la performance du projet.</p></div>
        <div class="actions">${ecrire ? '<button class="btn-sec" id="btn-ind">+ Indicateur</button>' : ''}<button class="btn-ia" id="btn-analyse">🤖 Générer une analyse d'évaluation</button></div>
      </div>
      <div class="kpis">
        <div class="kpi vert"><div class="val">${k.taux_realisation}%</div><div class="lib">Taux de réalisation des activités</div></div>
        <div class="kpi"><div class="val">${k.avancement_moyen}%</div><div class="lib">Avancement physique moyen</div></div>
        <div class="kpi rouge"><div class="val">${k.en_retard}</div><div class="lib">Activités en retard</div></div>
        <div class="kpi"><div class="val">${ind.filter((i) => i.taux !== null).length}/${ind.length}</div><div class="lib">Indicateurs renseignés</div></div>
      </div>
      ${Object.entries(niveaux).map(([niv, lib]) => {
        const l = ind.filter((i) => i.niveau === niv);
        return l.length ? `<div class="carte"><h2>${lib}</h2><div class="table-wrap"><table>
          <tr><th>Code</th><th>Indicateur</th><th>Référence</th><th>Cible</th><th>Actuelle</th><th style="min-width:130px">Réalisation</th><th></th></tr>
          ${l.map((i) => `<tr>
            <td><span class="code">${UI.esc(i.code || '')}</span></td>
            <td>${UI.esc(i.libelle)}${i.source_verification ? `<br><span class="muted" style="font-size:.78rem">Source : ${UI.esc(i.source_verification)}</span>` : ''}${i.commentaire ? `<br><span class="muted" style="font-size:.78rem">💬 ${UI.esc(i.commentaire)}</span>` : ''}</td>
            <td>${num(i.valeur_reference)}</td><td><b>${num(i.cible)}</b> <span class="muted">${UI.esc(i.unite || '')}</span></td><td>${num(i.valeur_actuelle)}</td>
            <td>${i.taux === null ? '<span class="muted">à renseigner</span>' : `${i.taux}%${UI.prog(i.taux, i.taux >= 100 ? 'vert' : i.taux >= 50 ? '' : 'or')}`}</td>
            <td>${ecrire ? `<button class="btn-lien btn-sm" data-maj="${i.id}">Mettre à jour</button>` : ''}</td></tr>`).join('')}
        </table></div></div>` : '';
      }).join('')}
      <div class="carte" id="zone-analyse" hidden></div>`;

    vue.querySelectorAll('[data-maj]').forEach((b) => { b.onclick = () => this.formulaire(ind.find((i) => String(i.id) === b.dataset.maj), vue); });
    vue.querySelector('#btn-ind')?.addEventListener('click', () => this.formulaire({ niveau: 'produit' }, vue));
    vue.querySelector('#btn-analyse').onclick = () => this.analyse(vue);
  },

  formulaire(i, vue) {
    const m = UI.modale(`<h2>${i.id ? "Mettre à jour l'indicateur" : 'Nouvel indicateur'}</h2>
      <form class="grille-form">
        <label>Code <input name="code" value="${UI.esc(i.code)}"></label>
        <label>Niveau <select name="niveau">${UI.options({ impact: 'Impact', effet: 'Effet', produit: 'Produit' }, i.niveau)}</select></label>
        <label class="plein">Libellé * <textarea name="libelle" rows="2" required>${UI.esc(i.libelle)}</textarea></label>
        <label>Unité <input name="unite" value="${UI.esc(i.unite)}" placeholder="%, personnes, score…"></label>
        <label>Valeur de référence <input type="number" step="any" name="valeur_reference" value="${UI.esc(i.valeur_reference)}"></label>
        <label>Cible <input type="number" step="any" name="cible" value="${UI.esc(i.cible)}"></label>
        <label>Valeur actuelle <input type="number" step="any" name="valeur_actuelle" value="${UI.esc(i.valeur_actuelle)}"></label>
        <label class="plein">Source de vérification <input name="source_verification" value="${UI.esc(i.source_verification)}"></label>
        <label class="plein">Commentaire (date de mesure, désagrégation femmes/jeunes…) <textarea name="commentaire" rows="2">${UI.esc(i.commentaire)}</textarea></label>
        <div class="actions plein">${i.id ? '<button type="button" class="btn-danger" id="suppr">Supprimer</button>' : ''}<button type="submit">Enregistrer</button></div>
      </form>`);
    m.querySelector('form').onsubmit = async (e) => {
      e.preventDefault();
      await UI.occupe(e.submitter, () => (i.id ? Api.put(`indicateurs.php?id=${i.id}`, UI.lireForm(e.target)) : Api.post('indicateurs.php', UI.lireForm(e.target))));
      UI.fermerModale(); UI.toast('Indicateur enregistré', 'ok'); this.afficher(vue);
    };
    m.querySelector('#suppr')?.addEventListener('click', async () => {
      if (confirm('Supprimer cet indicateur ?')) { await Api.del(`indicateurs.php?id=${i.id}`); UI.fermerModale(); this.afficher(vue); }
    });
  },

  analyse(vue) {
    if (!Etat.config.ia) return UI.toast("L'IA n'est pas configurée sur ce serveur.", 'erreur');
    const m = UI.modale(`<h2>🤖 Analyse d'évaluation</h2>
      <p class="muted">L'IA analyse les activités, retards et indicateurs de la plateforme (critères CAD-OCDE) et propose des recommandations.</p>
      <form><label>Période <input name="periode" placeholder="ex. Année 2 — semestre 1 (juillet – décembre 2026)"></label>
      <label>Point d'attention (facultatif) <input name="focus" placeholder="ex. retards sur le Résultat 3, préparation du COPIL…"></label>
      <div class="actions"><button type="submit" class="btn-ia">Lancer l'analyse</button></div></form>`);
    m.querySelector('form').onsubmit = async (e) => {
      e.preventDefault();
      const d = UI.lireForm(e.target);
      const r = await UI.occupe(e.submitter, () => Api.ia('evaluation', d), 'Analyse en cours…');
      UI.fermerModale();
      const z = vue.querySelector('#zone-analyse');
      z.hidden = false;
      z.innerHTML = `<div class="entete-vue"><h2>Analyse d'évaluation${d.periode ? ' — ' + UI.esc(d.periode) : ''}</h2>
        ${UI.peutEcrire() ? '<button id="btn-garder">💾 Enregistrer comme rapport</button>' : ''}</div><div class="md">${MD.html(r.contenu)}</div>`;
      z.scrollIntoView({ behavior: 'smooth' });
      z.querySelector('#btn-garder')?.addEventListener('click', async (ev) => {
        const doc = await UI.occupe(ev.currentTarget, () => Api.post('documents.php', {
          type: 'rapport', titre: `Analyse de suivi-évaluation${d.periode ? ' — ' + d.periode : ''} (${new Date().toLocaleDateString('fr-FR')})`, contenu: r.contenu, statut: 'brouillon',
        }));
        location.hash = `#/editeur/${doc.id}`;
      });
    };
  },
};
