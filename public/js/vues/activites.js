const VueActivites = {
  filtres: { q: '', statut: '', composante: '', responsable_id: '' },

  async afficher(vue, params = []) {
    const [acts, resp] = await Promise.all([Api.get('activites.php'), reference('responsables')]);
    // Lien direct vers la fiche d'une activite : #/activites/23
    if (params[0]) setTimeout(() => this.ouvrir(+params[0]), 0);
    Etat.cache.activites = acts;
    const composantes = [...new Set(acts.map((a) => a.composante).filter(Boolean))].sort();
    const f = this.filtres;
    vue.innerHTML = `
      <div class="entete-vue">
        <div><h1>Activités du plan de travail</h1><p class="muted">Cliquez sur une activité pour voir les documents requis, les tâches et les personnes imputées.</p></div>
        <div class="actions">${UI.peutEcrire() ? '<button id="btn-nouvelle">+ Nouvelle activité</button>' : ''}</div>
      </div>
      <div class="filtres">
        <input type="search" id="f-q" placeholder="Rechercher (titre, code…)" value="${UI.esc(f.q)}">
        <select id="f-statut">${UI.options({ ...UI.STATUTS, retard: '⚠ En retard' }, f.statut, 'Tous les statuts')}</select>
        <select id="f-composante">${UI.options(Object.fromEntries(composantes.map((c) => [c, c])), f.composante, 'Toutes les composantes')}</select>
        <select id="f-resp">${UI.optionsListe(resp, 'id', 'nom', f.responsable_id, 'Tous les responsables')}</select>
      </div>
      <div class="carte"><div class="table-wrap" id="liste"></div></div>`;

    const dessiner = () => {
      const q = f.q.toLowerCase();
      const liste = acts.filter((a) =>
        (!q || `${a.code} ${a.titre} ${a.description}`.toLowerCase().includes(q)) &&
        (!f.statut || (f.statut === 'retard' ? a.en_retard : a.statut === f.statut)) &&
        (!f.composante || a.composante === f.composante) &&
        (!f.responsable_id || String(a.responsable_id) === f.responsable_id));
      vue.querySelector('#liste').innerHTML = liste.length ? `<table>
        <tr><th>Activité</th><th class="masquer-mobile">Composante</th><th>Responsable</th><th>Échéance</th><th>Avancement</th><th class="masquer-mobile">Documents</th><th>Statut</th></tr>
        ${liste.map((a) => `<tr class="cliquable" data-id="${a.id}">
          <td>${a.code ? `<span class="code">${UI.esc(a.code)}</span> ` : ''}<b>${UI.esc(a.titre)}</b>${a.priorite === 'haute' ? ' <span class="badge haute">Priorité haute</span>' : ''}<br><span class="muted">${UI.esc(UI.TYPES_ACT[a.type_activite] || '')}</span></td>
          <td class="masquer-mobile">${UI.esc(a.composante || '—')}</td>
          <td>${UI.esc(a.responsable_nom || '—')}</td>
          <td style="white-space:nowrap">${UI.date(a.date_echeance)}<br>${['termine', 'annule'].includes(a.statut) ? '' : UI.delai(a.date_echeance)}</td>
          <td style="min-width:90px">${a.avancement}%${UI.prog(a.avancement, a.avancement >= 100 ? 'vert' : '')}</td>
          <td class="masquer-mobile">${a.nb_documents_ok}/${a.nb_documents}</td>
          <td>${UI.badge(a.statut, UI.STATUTS, a.en_retard)}</td></tr>`).join('')}
      </table>` : '<p class="vide-etat">Aucune activité ne correspond aux filtres.</p>';
      vue.querySelectorAll('tr[data-id]').forEach((tr) => { tr.onclick = () => this.ouvrir(+tr.dataset.id); });
    };
    dessiner();
    vue.querySelector('#f-q').oninput = (e) => { f.q = e.target.value; dessiner(); };
    vue.querySelector('#f-statut').onchange = (e) => { f.statut = e.target.value; dessiner(); };
    vue.querySelector('#f-composante').onchange = (e) => { f.composante = e.target.value; dessiner(); };
    vue.querySelector('#f-resp').onchange = (e) => { f.responsable_id = e.target.value; dessiner(); };
    vue.querySelector('#btn-nouvelle')?.addEventListener('click', () => this.formulaire());
  },

  rafraichir() {
    invalider('activites', 'responsables', 'acteurs');
    window.majPastille?.();
    const route = location.hash.split('/')[1] || 'tableau';
    if (['activites', 'tableau', 'echeances', 'equipe'].includes(route)) window.dispatchEvent(new HashChangeEvent('hashchange'));
  },

  async formulaire(a = {}) {
    const [resp, acteurs] = await Promise.all([reference('responsables'), reference('acteurs')]);
    const choisis = new Set((a.acteurs || '').split(',').filter(Boolean));
    const m = UI.modale(`<h2>${a.id ? "Modifier l'activité" : 'Nouvelle activité'}</h2>
      <form class="grille-form">
        <label>Code <input name="code" value="${UI.esc(a.code)}" placeholder="ex. 2.3.1"></label>
        <label>Type d'activité <select name="type_activite">${UI.options(UI.TYPES_ACT, a.type_activite || 'atelier')}</select></label>
        <label class="plein">Titre * <input name="titre" required value="${UI.esc(a.titre)}"></label>
        <label>Composante / résultat <input name="composante" value="${UI.esc(a.composante)}" list="liste-comp" placeholder="ex. Résultat 2"></label>
        <label>Responsable <select name="responsable_id">${UI.optionsListe(resp, 'id', 'nom', a.responsable_id, 'Non assigné')}</select></label>
        <label>Date de début / de l'activité <input type="date" name="date_debut" value="${UI.esc(a.date_debut)}"></label>
        <label>Date d'échéance <input type="date" name="date_echeance" value="${UI.esc(a.date_echeance)}"></label>
        <label>Lieu <input name="lieu" value="${UI.esc(a.lieu)}" placeholder="Abidjan"></label>
        <label>Priorité <select name="priorite">${UI.options(UI.PRIORITES, a.priorite || 'normale')}</select></label>
        <label>Statut <select name="statut">${UI.options(UI.STATUTS, a.statut || 'a_faire')}</select></label>
        <label>Avancement : <output>${a.avancement || 0}</output>% <input type="range" name="avancement" min="0" max="100" step="5" value="${a.avancement || 0}" oninput="this.previousElementSibling.value=this.value"></label>
        <label class="plein">🎯 Objectif de l'activité <textarea name="objectif" rows="2">${UI.esc(a.objectif)}</textarea></label>
        <label class="plein">✅ Résultat(s) attendu(s) <textarea name="resultats" rows="3">${UI.esc(a.resultats)}</textarea></label>
        <fieldset class="plein acteurs-choix"><legend>🏛️ Acteurs institutionnels concernés ou susceptibles de l'être</legend>
          ${acteurs.map((x) => `<label class="case"><input type="checkbox" data-acteur="${UI.esc(x.cle)}" ${choisis.has(x.cle) ? 'checked' : ''}><span>${UI.esc(x.sigle)} <span class="muted">— ${UI.esc(x.nom)}</span></span></label>`).join('')}
        </fieldset>
        <label class="plein">Description / notes de suivi <textarea name="description" rows="4">${UI.esc(a.description)}</textarea></label>
        <datalist id="liste-comp">${[...new Set((Etat.cache.activites || []).map((x) => x.composante).filter(Boolean))].map((c) => `<option value="${UI.esc(c)}">`).join('')}</datalist>
        <div class="actions plein">
          ${a.id ? '<button type="button" class="btn-danger" id="btn-suppr">Supprimer</button>' : ''}
          <button type="submit">Enregistrer</button>
        </div>
      </form>`);
    const form = m.querySelector('form');
    form.querySelector('[name=statut]').onchange = (e) => {
      if (e.target.value === 'termine') { form.querySelector('[name=avancement]').value = 100; form.querySelector('output').value = 100; }
    };
    form.onsubmit = async (e) => {
      e.preventDefault();
      const data = UI.lireForm(form);
      data.avancement = +data.avancement;
      data.acteurs = [...form.querySelectorAll('[data-acteur]:checked')].map((c) => c.dataset.acteur).join(',');
      const r = await UI.occupe(e.submitter, () => (a.id ? Api.put(`activites.php?id=${a.id}`, data) : Api.post('activites.php', data)));
      UI.fermerModale(); UI.toast('Activité enregistrée', 'ok');
      this.rafraichir();
      this.ouvrir(r.id);
    };
    m.querySelector('#btn-suppr')?.addEventListener('click', async () => {
      if (!confirm('Supprimer définitivement cette activité et ses tâches ?')) return;
      await Api.del(`activites.php?id=${a.id}`);
      UI.fermerModale(); UI.fermerPanneau(); UI.toast('Activité supprimée'); this.rafraichir();
    });
  },

  async ouvrir(id) {
    const [a, docs, taches, acteurs] = await Promise.all([
      Api.get('activites.php', { id }), Api.get('documents.php', { activite_id: id }), Api.get('taches.php', { activite_id: id }), reference('acteurs'),
    ]);
    const cles = (a.acteurs || '').split(',').filter(Boolean);
    const lies = cles.map((c) => acteurs.find((x) => x.cle === c)).filter(Boolean);
    const ecrire = UI.peutEcrire();
    const p = UI.panneau(`
      <div style="margin-right:2rem">
        ${a.code ? `<span class="code">${UI.esc(a.code)}</span> ` : ''}${UI.badge(a.statut, UI.STATUTS, a.en_retard)} <span class="badge type">${UI.esc(UI.TYPES_ACT[a.type_activite] || '')}</span>
        <h2 style="margin-top:.5rem">${UI.esc(a.titre)}</h2>
      </div>
      <div class="grille-form" style="font-size:.9rem;margin:.8rem 0">
        <div>👤 <b>Responsable :</b> ${UI.esc(a.responsable_nom || 'Non assigné')}</div>
        <div>🧭 <b>Composante :</b> ${UI.esc(a.composante || '—')}</div>
        <div>📅 <b>Dates :</b> ${UI.date(a.date_debut)} → ${UI.date(a.date_echeance)} ${['termine', 'annule'].includes(a.statut) ? '' : UI.delai(a.date_echeance)}</div>
        <div>📍 <b>Lieu :</b> ${UI.esc(a.lieu || '—')}</div>
        ${a.budget_prevu ? `<div>💰 <b>Budget :</b> <a href="#/finances">${Devise.montant(a.budget_depense || 0)} dépensés / ${Devise.montant(a.budget_prevu)} prévus</a></div>` : ''}
      </div>
      <div class="carte fiche-objectif">
        <div class="bloc"><h3>🎯 Objectif</h3><p>${a.objectif ? UI.esc(a.objectif) : '<span class="muted">Non renseigné — utilisez « Modifier ».</span>'}</p></div>
        <div class="bloc"><h3>✅ Résultat(s) attendu(s)</h3><p style="white-space:pre-wrap">${a.resultats ? UI.esc(a.resultats) : '<span class="muted">Non renseigné.</span>'}</p></div>
        <div class="bloc"><h3>🏛️ Acteurs institutionnels concernés ou susceptibles de l'être</h3>
          ${lies.length ? `<div class="puces-acteurs">${lies.map((x) => `<a class="puce-acteur" href="#/institutions/${UI.esc(x.cle)}" title="${UI.esc(x.nom)} — ${UI.esc(x.role)}">${UI.esc(x.sigle)}</a>`).join('')}</div>
            <ul class="liste-acteurs">${lies.map((x) => `<li><b>${UI.esc(x.sigle)}</b> — ${UI.esc(x.nom)}${x.site ? ` · <a href="${UI.esc(x.site)}" target="_blank" rel="noopener">site officiel ↗</a>` : ''} · <a href="#/institutions/${UI.esc(x.cle)}">actualités</a></li>`).join('')}</ul>`
            : '<span class="muted">Aucun acteur renseigné.</span>'}
        </div>
      </div>
      <div class="ligne-prog"><div class="haut"><b>Avancement</b><b>${a.avancement}%</b></div>${UI.prog(a.avancement, a.avancement >= 100 ? 'vert' : '')}</div>
      ${a.description ? `<p style="white-space:pre-wrap;background:var(--gris-clair);padding:.7rem;border-radius:8px;font-size:.9rem">${UI.esc(a.description)}</p>` : ''}
      <div class="actions" style="justify-content:flex-start;margin:.8rem 0 1.2rem">
        ${ecrire ? '<button class="btn-sec btn-sm" id="btn-modif">✏️ Modifier</button>' : ''}
        <a class="btn btn-ia btn-sm" href="#/rediger/activite/${a.id}">✍️ Rédiger un document pour cette activité</a>
      </div>

      <div class="carte">
        <div class="entete-vue" style="margin-bottom:.4rem"><h3>📄 Documents requis (${docs.filter((d) => ['valide', 'envoye'].includes(d.statut)).length}/${docs.length} finalisés)</h3>
          ${ecrire ? `<div class="actions" style="margin:0"><button class="btn-sec btn-sm" id="btn-checklist">📋 Checklist standard</button><button class="btn-ia btn-sm" id="btn-sugg">🤖 Suggestions IA</button><button class="btn-sec btn-sm" id="btn-doc">+ Document</button></div>` : ''}</div>
        ${docs.length ? docs.map((d) => `<div class="item-doc">
            <span class="badge type">${UI.esc(UI.TYPES_DOC[d.type] || d.type)}</span>
            <span class="t">${UI.esc(d.titre)}${d.date_echeance && !['valide', 'envoye'].includes(d.statut) ? `<br><span class="muted">Échéance ${UI.date(d.date_echeance)}</span> ${UI.delai(d.date_echeance)}` : ''}</span>
            ${UI.badge(d.statut, UI.STATUTS_DOC, d.en_retard)}
            <a class="btn btn-sec btn-sm" href="#/editeur/${d.id}">${d.a_contenu ? '📖 Lire' : +d.nb_fichiers ? '📎 Voir' : ecrire && !['valide', 'envoye'].includes(d.statut) ? '✍️ Rédiger' : 'Ouvrir'}</a>
          </div>`).join('') : '<p class="muted">Aucun document rattaché. Utilisez la checklist standard ou les suggestions IA pour lister les documents nécessaires (TDR, courriers, rapport…).</p>'}
      </div>

      <div class="carte">
        <h3>✅ Actions / tâches (${taches.filter((t) => t.statut === 'termine').length}/${taches.length})</h3>
        <div id="taches">${taches.map((t) => VueEquipe.ligneTache(t, false)).join('') || '<p class="muted">Aucune tâche.</p>'}</div>
        ${ecrire ? `<form id="form-tache" class="filtres" style="margin:.8rem 0 0">
          <input name="titre" placeholder="Nouvelle tâche…" required style="flex:1;min-width:180px">
          <select name="responsable_id" id="sel-resp-tache"></select>
          <input type="date" name="echeance">
          <button type="submit" class="btn-sm">Ajouter</button></form>` : ''}
      </div>`);

    if (ecrire) {
      reference('responsables').then((r) => { const s = p.querySelector('#sel-resp-tache'); if (s) s.innerHTML = UI.optionsListe(r, 'id', 'nom', a.responsable_id, 'Responsable…'); });
      p.querySelector('#btn-modif').onclick = () => this.formulaire(a);
      p.querySelector('#btn-checklist').onclick = () => this.checklist(a);
      p.querySelector('#btn-sugg').onclick = (e) => this.suggestions(a, e.currentTarget);
      p.querySelector('#btn-doc').onclick = () => VueDocuments.formulaire({ activite_id: a.id, responsable_id: a.responsable_id }, () => this.ouvrir(a.id));
      p.querySelector('#form-tache').onsubmit = async (e) => {
        e.preventDefault();
        await UI.occupe(e.submitter, () => Api.post('taches.php', { ...UI.lireForm(e.target), activite_id: a.id }));
        this.ouvrir(a.id); window.majPastille?.();
      };
    }
    VueEquipe.brancherTaches(p, () => this.ouvrir(a.id));
  },

  async checklist(a) {
    const c = await Api.get('activites.php', { action: 'checklist', id: a.id });
    this.choisirElements(a, c, `Checklist standard — ${UI.TYPES_ACT[c.type]}`,
      "Documents et actions habituellement requis pour ce type d'activité. Les échéances sont calculées à partir de la date de l'activité ; décochez ce qui ne s'applique pas.");
  },

  async suggestions(a, bouton) {
    const r = await UI.occupe(bouton, () => Api.ia('suggestions', { activite_id: a.id }), 'Analyse…');
    this.choisirElements(a, r, '🤖 Suggestions de l\'IA', r.conseils || '');
  },

  choisirElements(a, c, titre, intro) {
    const docs = c.documents || []; const taches = c.taches || [];
    const m = UI.modale(`<h2>${UI.esc(titre)}</h2><p class="muted">${UI.esc(intro)}</p>
      <form><div class="checklist">
        <h3>Documents à produire</h3>
        ${docs.map((d, i) => `<label><input type="checkbox" data-d="${i}" checked><span><span class="badge type">${UI.esc(UI.TYPES_DOC[d.type] || d.type)}</span> ${UI.esc(d.titre)} <span class="muted">— ${UI.date(d.date_echeance)}</span>${d.pourquoi ? `<br><span class="muted">${UI.esc(d.pourquoi)}</span>` : ''}</span></label>`).join('') || '<p class="muted">Aucun</p>'}
        <h3 style="margin-top:1rem">Actions / tâches</h3>
        ${taches.map((t, i) => `<label><input type="checkbox" data-t="${i}" checked><span>${UI.esc(t.titre)} <span class="muted">— ${UI.date(t.echeance)}</span></span></label>`).join('') || '<p class="muted">Aucune</p>'}
      </div>
      <p class="muted">Les éléments seront imputés à : <b>${UI.esc(a.responsable_nom || 'personne (à assigner)')}</b></p>
      <div class="actions"><button type="submit">Ajouter la sélection</button></div></form>`, { large: true });
    m.querySelector('form').onsubmit = async (e) => {
      e.preventDefault();
      const sel = (attr, liste) => [...m.querySelectorAll(`[data-${attr}]`)].filter((x) => x.checked).map((x) => liste[+x.dataset[attr]]);
      const valide = (d) => (/^\d{4}-\d{2}-\d{2}$/.test(d || '') ? d : null);
      const body = {
        documents: sel('d', docs).map((d) => ({ ...d, date_echeance: valide(d.date_echeance) })),
        taches: sel('t', taches).map((t) => ({ ...t, echeance: valide(t.echeance) })),
      };
      const r = await UI.occupe(e.submitter, () => Api.post(`activites.php?action=checklist&id=${a.id}`, body));
      UI.fermerModale(); UI.toast(`${r.documents} document(s) et ${r.taches} tâche(s) ajoutés`, 'ok');
      this.ouvrir(a.id); this.rafraichir();
    };
  },
};
