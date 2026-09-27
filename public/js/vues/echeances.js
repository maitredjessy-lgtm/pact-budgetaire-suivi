const VueEcheances = {
  resp: '',
  nature: '',

  ligne(e) {
    const icone = { activite: '🗂️', document: '📄', tache: '✅' }[e.nature];
    const nat = { activite: 'Activité', document: UI.TYPES_DOC[e.type] || 'Document', tache: 'Tâche' }[e.nature];
    const jour = e.jours < 0 ? `<b>${-e.jours} j</b>retard` : e.jours === 0 ? "<b>Auj.</b>" : `<b>J-${e.jours}</b>${UI.date(e.echeance).replace(/ \d{4}$/, '')}`;
    const sujet = encodeURIComponent(`[PACT Budgétaire] Rappel d'échéance : ${e.titre}`);
    const corps = encodeURIComponent(`Bonjour ${e.responsable_nom || ''},\n\nPetit rappel : l'échéance « ${e.titre} »${e.contexte ? ` (${e.contexte})` : ''} est fixée au ${UI.date(e.echeance)}${e.jours < 0 ? `, soit ${-e.jours} jour(s) de retard` : ''}.\n\nMerci de mettre à jour son statut sur la plateforme de suivi.\n\nCordialement`);
    return `<div class="ech ${e.urgence}">
      <div class="jour">${jour}</div>
      <div class="corps">
        <div class="titre">${icone} <a href="${e.nature === 'document' ? `#/editeur/${e.id}` : 'javascript:void(0)'}" data-ouvrir="${e.nature}:${e.nature === 'activite' ? e.id : (e.activite_id || '')}">${UI.esc(e.titre)}</a></div>
        <div class="meta">${nat}${e.contexte && e.nature !== 'activite' ? ` · ${UI.esc(e.contexte)}` : ''} · 👤 ${UI.esc(e.responsable_nom || 'Non assigné')}
          ${e.responsable_email ? ` · <a href="mailto:${UI.esc(e.responsable_email)}?subject=${sujet}&body=${corps}">✉️ Relancer</a>` : ''}</div>
      </div>
    </div>`;
  },

  brancher(racine) {
    racine.querySelectorAll('[data-ouvrir]').forEach((a) => {
      const [nature, id] = a.dataset.ouvrir.split(':');
      if (nature === 'document') return;
      a.onclick = (ev) => { ev.preventDefault(); if (id) VueActivites.ouvrir(+id); };
    });
  },

  async afficher(vue) {
    const [items, resp] = await Promise.all([Api.get('echeances.php', { responsable_id: this.resp }), reference('responsables')]);
    const liste = items.filter((e) => !this.nature || e.nature === this.nature);
    const groupes = [
      ['retard', '🔴 En retard'], ['semaine', '🟠 Dans les 7 jours'], ['mois', '🟡 Dans les 30 jours'], ['plus_tard', '⚪ Plus tard'],
    ];
    vue.innerHTML = `
      <div class="entete-vue">
        <div><h1>Échéances et alertes</h1><p class="muted">Activités, documents et tâches non terminés, classés par urgence.</p></div>
        <div class="actions"><button class="btn-sec" id="btn-ics" title="Importer dans Outlook ou Google Agenda (rappel 3 jours avant)">📅 Exporter vers mon agenda</button></div>
      </div>
      <div class="filtres">
        <select id="f-resp">${UI.optionsListe(resp, 'id', 'nom', this.resp, 'Toutes les personnes')}</select>
        <select id="f-nature">${UI.options({ activite: 'Activités', document: 'Documents', tache: 'Tâches' }, this.nature, 'Tout')}</select>
        <span class="muted">${liste.length} échéance(s)</span>
      </div>
      ${groupes.map(([cle, titre]) => {
        const g = liste.filter((e) => e.urgence === cle);
        return g.length ? `<div class="carte groupe-ech"><h2>${titre} <span class="badge">${g.length}</span></h2>${g.map((e) => this.ligne(e)).join('')}</div>` : '';
      }).join('') || '<div class="carte vide-etat">Aucune échéance en cours 🎉</div>'}
      <p class="muted">💡 Renseignez l'e-mail des membres de l'équipe (page Équipe) pour pouvoir les relancer en un clic.</p>`;
    vue.querySelector('#f-resp').onchange = (e) => { this.resp = e.target.value; this.afficher(vue); };
    vue.querySelector('#f-nature').onchange = (e) => { this.nature = e.target.value; this.afficher(vue); };
    vue.querySelector('#btn-ics').onclick = () => telecharger('api/echeances.php?format=ics' + (this.resp ? `&responsable_id=${this.resp}` : ''));
    this.brancher(vue);
  },
};
