const VueModeles = {
  async afficher(vue) {
    const modeles = await reference('modeles', true);
    const ecrire = UI.peutEcrire();
    vue.innerHTML = `
      <div class="entete-vue">
        <div><h1>🧩 Modèles de documents</h1><p class="muted">Canevas utilisés par l'IA pour générer rapports, courriers, TDR… Les <code>{{variables}}</code> sont remplies automatiquement depuis l'activité.</p></div>
        <div class="actions">${ecrire ? '<button class="btn-sec" id="btn-import">📥 Importer un .docx</button><button id="btn-nouveau">+ Nouveau modèle</button>' : ''}</div>
      </div>
      ${Object.entries(UI.TYPES_DOC).map(([type, lib]) => {
        const l = modeles.filter((m) => m.type === type);
        return l.length ? `<h2 style="margin-top:1rem">${lib}s</h2><div class="cartes-modeles">${l.map((m) => `
          <div class="carte-modele">
            <b>${UI.esc(m.nom)}</b>
            <p>${UI.esc(m.description || '')}</p>
            ${m.source ? `<span class="muted" style="font-size:.75rem">Source : ${UI.esc(m.source)}</span>` : ''}
            <div class="actions">
              <a class="btn btn-ia btn-sm" href="#/rediger/modele/${m.id}">✍️ Utiliser</a>
              <button class="btn-sec btn-sm" data-voir="${m.id}">${ecrire ? 'Modifier' : 'Voir'}</button>
            </div>
          </div>`).join('')}</div>` : '';
      }).join('')}`;
    vue.querySelectorAll('[data-voir]').forEach((b) => { b.onclick = () => this.formulaire(modeles.find((m) => String(m.id) === b.dataset.voir), vue); });
    vue.querySelector('#btn-nouveau')?.addEventListener('click', () => this.formulaire({ type: 'rapport', contenu: '# {{titre_activite}}\n\n## 1. Introduction\n\n## 2. …' }, vue));
    vue.querySelector('#btn-import')?.addEventListener('click', () => this.importer(vue));
  },

  formulaire(m, vue) {
    const ecrire = UI.peutEcrire();
    const dis = ecrire ? '' : 'disabled';
    const md = UI.modale(`<h2>${m.id ? UI.esc(m.nom) : 'Nouveau modèle'}</h2>
      <form class="grille-form">
        <label>Type <select name="type" ${dis}>${UI.options(UI.TYPES_DOC, m.type)}</select></label>
        <label>Nom * <input name="nom" required value="${UI.esc(m.nom)}" ${dis}></label>
        <label class="plein">Description <input name="description" value="${UI.esc(m.description)}" ${dis}></label>
        <label class="plein">Consignes de rédaction pour l'IA (ton, longueur, points obligatoires…)
          <textarea name="consignes" rows="3" ${dis}>${UI.esc(m.consignes)}</textarea></label>
        <label class="plein">Structure du modèle (Markdown) *
          <textarea name="contenu" rows="16" required style="font-family:Consolas,monospace;font-size:.85rem" ${dis}>${UI.esc(m.contenu)}</textarea>
          <span class="muted" style="font-weight:400">Variables disponibles : {{titre_activite}} {{code_activite}} {{composante}} {{lieu}} {{date_activite}} {{responsable}} {{date_du_jour}} {{redacteur}} {{periode}} {{reference}} {{destinataire}} {{formule_appel}} {{objet}}</span>
        </label>
        <input type="hidden" name="source" value="${UI.esc(m.source)}">
        ${ecrire ? `<div class="actions plein">${m.id ? '<button type="button" class="btn-danger" id="suppr">Supprimer</button>' : ''}<button type="submit">Enregistrer</button></div>` : ''}
      </form>`, { large: true });
    md.querySelector('form').onsubmit = async (e) => {
      e.preventDefault();
      const d = UI.lireForm(e.target);
      await UI.occupe(e.submitter, () => (m.id ? Api.put(`modeles.php?id=${m.id}`, d) : Api.post('modeles.php', d)));
      invalider('modeles'); UI.fermerModale(); UI.toast('Modèle enregistré', 'ok'); this.afficher(vue);
    };
    md.querySelector('#suppr')?.addEventListener('click', async () => {
      if (!confirm('Supprimer ce modèle ?')) return;
      await Api.del(`modeles.php?id=${m.id}`); invalider('modeles'); UI.fermerModale(); this.afficher(vue);
    });
  },

  importer(vue) {
    const m = UI.modale(`<h2>📥 Importer un document Word comme modèle</h2>
      <p class="muted">Choisissez un rapport, courrier ou TDR réussi : sa structure et son texte serviront de référence à l'IA pour les prochains documents du même type.</p>
      <form>
        <label>Fichier .docx * <input type="file" name="fichier" accept=".docx" required></label>
        <div class="grille-form">
          <label>Type <select name="type">${UI.options(UI.TYPES_DOC, 'rapport')}</select></label>
          <label>Nom du modèle <input name="nom" placeholder="(nom du fichier par défaut)"></label>
        </div>
        <div class="actions"><button type="submit">Importer</button></div>
      </form>`);
    m.querySelector('form').onsubmit = async (e) => {
      e.preventDefault();
      const fd = new FormData(e.target);
      const r = await UI.occupe(e.submitter, () => Api.post('modeles.php?action=import', fd), 'Import…');
      invalider('modeles'); UI.fermerModale(); UI.toast('Modèle importé — vérifiez-le et ajoutez des {{variables}} si besoin', 'ok');
      await this.afficher(vue);
      this.formulaire(r, vue);
    };
  },
};
