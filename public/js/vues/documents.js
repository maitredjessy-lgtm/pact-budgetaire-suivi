const VueDocuments = {
  type: '',
  statut: '',
  q: '',

  async afficher(vue) {
    const docs = await Api.get('documents.php');
    const compte = (t) => docs.filter((d) => !t || d.type === t).length;
    const onglets = { '': 'Tous', rapport: 'Rapports', courrier: 'Courriers', tdr: 'TDR', compte_rendu: 'Comptes rendus', note: 'Notes', communique: 'Communiqués', autre: 'Autres' };
    vue.innerHTML = `
      <div class="entete-vue">
        <div><h1>Rapports, courriers et TDR</h1><p class="muted">Registre des documents du projet : à produire, en cours et finalisés.</p></div>
        <div class="actions"><button class="btn-sec" id="btn-drive">☁️ Google Drive</button>${UI.peutEcrire() ? '<button class="btn-sec" id="btn-ajout">+ Référencer un document</button><a class="btn btn-ia" href="#/rediger">✍️ Rédiger avec l\'IA</a>' : ''}</div>
      </div>
      <div class="onglets">${Object.entries(onglets).map(([k, l]) => `<button data-t="${k}" class="${k === this.type ? 'actif' : ''}">${l} (${compte(k)})</button>`).join('')}</div>
      <div class="filtres">
        <input type="search" id="f-q" placeholder="Rechercher un document…" value="${UI.esc(this.q)}">
        <select id="f-s">${UI.options({ ...UI.STATUTS_DOC, ouverts: 'Non finalisés' }, this.statut, 'Tous les statuts')}</select>
      </div>
      <div class="carte"><div class="table-wrap" id="liste"></div></div>`;

    const dessiner = () => {
      const q = this.q.toLowerCase();
      const l = docs.filter((d) => (!this.type || d.type === this.type)
        && (!this.statut || (this.statut === 'ouverts' ? !['valide', 'envoye'].includes(d.statut) : d.statut === this.statut))
        && (!q || `${d.titre} ${d.activite_titre || ''} ${d.notes || ''}`.toLowerCase().includes(q)));
      vue.querySelector('#liste').innerHTML = l.length ? `<table>
        <tr><th>Document</th><th class="masquer-mobile">Activité</th><th class="masquer-etroit">Responsable</th><th>Échéance / finalisé</th><th>Statut</th><th></th></tr>
        ${l.map((d) => `<tr class="cliquable" data-ouvrir="${d.id}">
          <td class="col-doc"><span class="badge type">${UI.esc(UI.TYPES_DOC[d.type] || d.type)}</span> <a class="titre-doc" href="#/editeur/${d.id}">${UI.esc(d.titre)}</a>
            ${d.notes ? `<br><span class="muted chemin">${UI.esc(d.notes.slice(0, 140))}</span>` : ''}
            <div style="margin-top:.35rem"><a class="btn ${d.a_contenu || +d.nb_fichiers ? '' : 'btn-sec'} btn-sm" href="#/editeur/${d.id}">${d.a_contenu ? '📖 Lire' : +d.nb_fichiers ? '📎 Voir le fichier' : UI.peutEcrire() && !['valide', 'envoye'].includes(d.statut) ? '✍️ Rédiger' : 'Ouvrir'}</a></div></td>
          <td class="masquer-mobile">${d.activite_titre ? `<a href="javascript:void(0)" data-act="${d.activite_id}">${d.activite_code ? UI.esc(d.activite_code) + ' — ' : ''}${UI.esc(d.activite_titre)}</a>` : '—'}</td>
          <td class="masquer-etroit">${UI.esc(d.responsable_nom || '—')}</td>
          <td style="white-space:nowrap">${['valide', 'envoye'].includes(d.statut) ? UI.date(d.date_finalisation) : `${UI.date(d.date_echeance)}<br>${UI.delai(d.date_echeance)}`}</td>
          <td>${UI.badge(d.statut, UI.STATUTS_DOC, d.en_retard)}</td>
          <td style="white-space:nowrap">
            ${+d.nb_fichiers ? `<span title="${d.nb_fichiers} fichier(s) joint(s)">📎</span>` : ''}
            ${d.lien && /^https?:\/\//i.test(d.lien) ? `<a class="btn-lien btn-sm" href="${UI.esc(d.lien)}" target="_blank" rel="noopener" title="Ouvrir le fichier">🔗</a>` : ''}
            ${UI.peutEcrire() ? `<button class="btn-lien btn-sm" data-modif="${d.id}" title="Propriétés">⚙️</button>` : ''}
          </td></tr>`).join('')}
      </table>` : '<p class="vide-etat">Aucun document.</p>';
      vue.querySelectorAll('[data-act]').forEach((a) => { a.onclick = (e) => { e.stopPropagation(); VueActivites.ouvrir(+a.dataset.act); }; });
      // Un clic n'importe ou sur la ligne ouvre le document (sauf sur les liens et boutons eux-memes)
      vue.querySelectorAll('tr[data-ouvrir]').forEach((tr) => {
        tr.onclick = (e) => { if (!e.target.closest('a, button')) location.hash = `#/editeur/${tr.dataset.ouvrir}`; };
      });
      vue.querySelectorAll('[data-modif]').forEach((b) => {
        b.onclick = async () => this.formulaire(await Api.get('documents.php', { id: b.dataset.modif }), () => this.afficher(vue));
      });
    };
    dessiner();
    vue.querySelectorAll('[data-t]').forEach((b) => { b.onclick = () => { this.type = b.dataset.t; this.afficher(vue); }; });
    vue.querySelector('#f-q').oninput = (e) => { this.q = e.target.value; dessiner(); };
    vue.querySelector('#f-s').onchange = (e) => { this.statut = e.target.value; dessiner(); };
    vue.querySelector('#btn-drive').onclick = () => VueDrive.ouvrir(() => this.afficher(vue));
    vue.querySelector('#btn-ajout')?.addEventListener('click', () => this.formulaire({ type: this.type || 'rapport' }, () => this.afficher(vue)));
  },

  /** Proprietes d'un document (sans le contenu redige) */
  async formulaire(d, apres) {
    const [resp, acts] = await Promise.all([reference('responsables'), reference('activites')]);
    const m = UI.modale(`<h2>${d.id ? 'Propriétés du document' : 'Référencer un document'}</h2>
      <form class="grille-form">
        <label>Type <select name="type">${UI.options(UI.TYPES_DOC, d.type || 'rapport')}</select></label>
        <label>Statut <select name="statut">${UI.options(UI.STATUTS_DOC, d.statut || 'a_faire')}</select></label>
        <label class="plein">Titre * <input name="titre" required value="${UI.esc(d.titre)}"></label>
        <label class="plein">Activité liée <select name="activite_id">${UI.optionsListe(acts, 'id', (a) => `${a.code ? a.code + ' — ' : ''}${a.titre}`, d.activite_id, 'Aucune')}</select></label>
        <label>Personne imputée <select name="responsable_id">${UI.optionsListe(resp, 'id', 'nom', d.responsable_id, 'Non assigné')}</select></label>
        <label>Échéance <input type="date" name="date_echeance" value="${UI.esc(d.date_echeance)}"></label>
        <label>Date de finalisation <input type="date" name="date_finalisation" value="${UI.esc(d.date_finalisation)}"></label>
        <label>Lien vers le fichier (Drive, OneDrive…) <input type="url" name="lien" value="${UI.esc(d.lien)}" placeholder="https://…"></label>
        <label class="plein">Notes <textarea name="notes" rows="2">${UI.esc(d.notes)}</textarea></label>
        <div class="actions plein">${d.id ? '<button type="button" class="btn-danger" id="suppr">Supprimer</button>' : ''}<button type="submit">Enregistrer</button></div>
      </form>`);
    const form = m.querySelector('form');
    form.querySelector('[name=statut]').onchange = (e) => {
      const f = form.querySelector('[name=date_finalisation]');
      if (['valide', 'envoye'].includes(e.target.value) && !f.value) f.value = new Date().toISOString().slice(0, 10);
    };
    form.onsubmit = async (e) => {
      e.preventDefault();
      const data = UI.lireForm(form);
      await UI.occupe(e.submitter, () => (d.id ? Api.put(`documents.php?id=${d.id}`, data) : Api.post('documents.php', data)));
      UI.fermerModale(); UI.toast('Document enregistré', 'ok'); window.majPastille?.(); apres?.();
    };
    m.querySelector('#suppr')?.addEventListener('click', async () => {
      if (!confirm('Supprimer ce document du registre ?')) return;
      await Api.del(`documents.php?id=${d.id}`); UI.fermerModale(); apres?.();
    });
  },
};
