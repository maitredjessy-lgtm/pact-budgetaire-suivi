/* Suivi financier : synthese, budget, depenses, financements recus, rapports financiers */
const VueFinances = {
  onglet: 'synthese',
  filtreBudget: { q: '', rubrique: '', partenaire: '' },
  periode: { debut: '', fin: '' },
  get TAUX() { return Devise.etat.taux; },

  eur: (v) => `${Number(v || 0).toLocaleString('fr-FR', { minimumFractionDigits: 0, maximumFractionDigits: 0 })} €`,
  eur2: (v) => `${Number(v || 0).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} €`,
  fcfa: (v) => `${Math.round(Number(v || 0)).toLocaleString('fr-FR')} FCFA`,
  pct: (v) => (v === null || v === undefined ? '—' : `${Number(v).toLocaleString('fr-FR', { maximumFractionDigits: 1 })} %`),
  STATUTS: { engagee: 'Engagée', payee: 'Payée', justifiee: 'Justifiée' },
  PARTENAIRES: { SJ: 'Social Justice', IBP: 'IBP Sénégal' },

  async afficher(vue, params = []) {
    // Lien direct vers un onglet : #/finances/budget, #/finances/depenses…
    if (params[0] && typeof this[params[0]] === 'function' && ['synthese', 'budget', 'depenses', 'financements', 'rapports'].includes(params[0])) {
      this.onglet = params[0];
    }
    const admin = Etat.user.role === 'admin';
    vue.innerHTML = `
      <div class="entete-vue">
        <div><h1>💰 Suivi financier</h1><p class="muted">Budget UE du projet, dépenses, fonds reçus et rapports financiers — montants affichés en euros et en FCFA (<span class="point-live"></span> 1 € = ${this.TAUX.toLocaleString('fr-FR', { maximumFractionDigits: 3 })} FCFA, actualisé en continu).</p></div>
        <div class="actions">
          ${UI.peutEcrire() ? '<button id="btn-depense">+ Saisir une dépense</button>' : ''}
          ${admin ? '<button class="btn-sec" id="btn-import">📥 Importer / mettre à jour le budget</button>' : ''}
        </div>
      </div>
      <div class="onglets">
        ${Object.entries({ synthese: '📊 Synthèse', budget: '📒 Budget', depenses: '🧾 Dépenses', financements: '🏦 Fonds reçus', rapports: '📑 Rapports financiers' })
          .map(([k, l]) => `<button data-o="${k}" class="${k === this.onglet ? 'actif' : ''}">${l}</button>`).join('')}
      </div>
      <div id="contenu-fin"><div class="chargement"><span class="spinner fonce"></span></div></div>`;
    vue.querySelectorAll('[data-o]').forEach((b) => { b.onclick = () => { this.onglet = b.dataset.o; history.replaceState(null, '', `#/finances/${b.dataset.o}`); this.afficher(vue); }; });
    vue.querySelector('#btn-depense')?.addEventListener('click', () => this.formDepense({}, () => this.afficher(vue)));
    vue.querySelector('#btn-import')?.addEventListener('click', () => this.importer(vue));
    const zone = vue.querySelector('#contenu-fin');
    const s = await Api.get('finances.php', { vue: 'synthese' });
    this.donnees = s;
    if (!s.lignes.length) {
      zone.innerHTML = `<div class="carte vide-etat">Aucun budget n'est encore chargé.${admin ? ' Cliquez sur <b>📥 Importer / mettre à jour le budget</b> et choisissez le classeur UE (.xlsx).' : ''}</div>`;
      return;
    }
    return this[this.onglet](zone, vue);
  },

  barre(budget, depense, engage = 0) {
    const p = budget > 0 ? (100 * depense) / budget : 0;
    const pe = budget > 0 ? (100 * engage) / budget : 0;
    const cls = p > 100 ? 'rouge' : p >= 90 ? 'orange' : 'vert';
    return `<div class="barre-fin"><span class="${cls}" style="width:${Math.min(100, p)}%"></span><span class="engage" style="width:${Math.min(100 - Math.min(100, p), pe)}%"></span></div>`;
  },

  // ------------------------------------------------------------------ synthese
  synthese(zone) {
    const s = this.donnees;
    const retard = s.taux + 10 < s.taux_temps;
    zone.innerHTML = `
      <div class="kpis">
        <div class="kpi"><div class="val">${Devise.montant(s.total, { bloc: true })}</div><div class="lib">Budget total du projet</div></div>
        <div class="kpi vert"><div class="val">${Devise.montant(s.depense, { bloc: true })}</div><div class="lib">Dépensé (${this.pct(s.taux)})</div>${UI.prog(s.taux, 'vert')}</div>
        <div class="kpi ${retard ? 'orange' : ''}"><div class="val">${this.pct(s.taux_temps)}</div><div class="lib">Durée écoulée (${s.mois_ecoules}/36 mois)${retard ? ' — exécution en retard sur le calendrier' : ''}</div>${UI.prog(s.taux_temps, 'or')}</div>
        <div class="kpi"><div class="val">${Devise.montant(s.engage, { bloc: true })}</div><div class="lib">Engagé, non encore payé</div></div>
        <div class="kpi"><div class="val">${Devise.montant(s.recu, { bloc: true })}</div><div class="lib">Fonds reçus</div></div>
        <div class="kpi ${s.tresorerie < 0 ? 'rouge' : 'vert'}"><div class="val">${Devise.montant(s.tresorerie, { bloc: true })}</div><div class="lib">Trésorerie disponible</div></div>
      </div>
      ${s.alertes.length ? `<div class="carte"><h2>🔔 Alertes financières</h2>${s.alertes.map((a) => `<div class="${a.niveau === 'rouge' ? 'erreur' : 'alerte'}" style="margin-bottom:.4rem">${UI.esc(a.texte)}</div>`).join('')}</div>` : ''}
      <div class="deux-col">
        <div class="carte">
          <h2>Exécution par rubrique</h2>
          ${s.rubriques.map((r) => `<div class="ligne-prog">
              <div class="haut"><span><b>${r.rubrique}. ${UI.esc(r.libelle)}</b></span><span>${this.eur(r.depense)} / ${this.eur(r.budget)} <b>${this.pct(r.taux)}</b></span></div>
              ${this.barre(r.budget, r.depense, r.engage)}</div>`).join('')}
          <p class="muted" style="font-size:.8rem">Vert : dépensé · hachuré : engagé · orange ≥ 90 % · rouge : dépassement. L'UE tolère des transferts jusqu'à 25 % entre rubriques (à justifier).</p>
        </div>
        <div>
          <div class="carte"><h2>Par année budgétaire</h2>
            <div class="table-wrap"><table><tr><th>Année</th><th>Budget</th><th>Dépensé</th><th>Taux</th></tr>
            ${s.annees.map((a) => `<tr><td>Année ${a.annee}<br><span class="muted" style="font-size:.78rem">${UI.date(a.debut)} – ${UI.date(a.fin)}</span></td><td>${this.eur(a.budget)}</td><td>${this.eur(a.depense)}</td><td>${this.pct(a.taux)}</td></tr>`).join('')}
            </table></div></div>
          <div class="carte"><h2>Par partenaire</h2>
            ${s.partenaires.map((p) => `<div class="ligne-prog"><div class="haut"><span><b>${this.PARTENAIRES[p.partenaire] || p.partenaire}</b></span><span>${this.eur(p.depense)} / ${this.eur(p.budget)}</span></div>${this.barre(p.budget, p.depense)}</div>`).join('')}
          </div>
          <div class="carte"><h2>Sources de financement</h2>
            ${s.sources.map((x) => `<div class="item-doc"><span class="t">${UI.esc(x.source)}</span><b>${this.eur(x.montant)}</b></div>`).join('')}
            <p class="muted" style="font-size:.8rem">${UI.esc(s.source_budget || '')}</p>
          </div>
        </div>
      </div>`;
  },

  // ------------------------------------------------------------------ budget
  budget(zone, vue) {
    const s = this.donnees;
    const f = this.filtreBudget;
    zone.innerHTML = `
      <div class="filtres">
        <input type="search" id="fb-q" placeholder="Rechercher une ligne, un atelier…" value="${UI.esc(f.q)}">
        <select id="fb-r">${UI.options(Object.fromEntries(s.rubriques.map((r) => [r.rubrique, `${r.rubrique}. ${r.libelle}`])), f.rubrique, 'Toutes les rubriques')}</select>
        <select id="fb-p">${UI.options(this.PARTENAIRES, f.partenaire, 'Tous les partenaires')}</select>
      </div>
      <div id="liste-budget"></div>`;
    const dessiner = () => {
      const q = f.q.toLowerCase();
      const lignes = s.lignes.filter((l) => (!f.rubrique || l.rubrique === f.rubrique) && (!f.partenaire || l.partenaire === f.partenaire)
        && (!q || `${l.libelle} ${l.groupe || ''} ${l.activite_titre || ''}`.toLowerCase().includes(q)));
      const parRub = {};
      lignes.forEach((l) => { ((parRub[l.rubrique] ??= {})[l.groupe || '—'] ??= []).push(l); });
      zone.querySelector('#liste-budget').innerHTML = Object.entries(parRub).map(([r, groupes]) => {
        const rub = s.rubriques.find((x) => x.rubrique === r);
        return `<div class="carte">
          <div class="entete-vue" style="margin-bottom:.5rem"><h2 style="margin:0">${r}. ${UI.esc(rub?.libelle || '')}</h2><span><b>${this.eur(rub?.depense)}</b> / ${this.eur(rub?.budget)} (${this.pct(rub?.taux)})</span></div>
          ${Object.entries(groupes).map(([g, ls]) => {
            const bg = ls.reduce((t, l) => t + Number(l.montant), 0);
            const dg = ls.reduce((t, l) => t + Number(l.depense), 0);
            return `<details class="groupe-budget" ${f.q ? 'open' : ''}>
              <summary><span class="t">${UI.esc(g)}${ls[0].activite_titre ? ` <span class="badge type">🗂️ ${UI.esc(ls[0].activite_code || '')} ${UI.esc(ls[0].activite_titre.slice(0, 50))}</span>` : ''}</span>
                <span class="montants">${this.eur(dg)} / ${this.eur(bg)}</span>${this.barre(bg, dg)}</summary>
              <div class="table-wrap"><table>
                <tr><th>Ligne</th><th>Calcul</th><th>Budget</th><th class="masquer-mobile">A1 / A2 / A3</th><th>Dépensé</th><th>Reste</th><th></th></tr>
                ${ls.map((l) => `<tr class="cliquable" data-ligne="${l.id}">
                  <td>${UI.esc(l.libelle)}${l.partenaire === 'IBP' ? ' <span class="badge">IBP</span>' : ''}${+l.obsolete ? ' <span class="badge annule">retirée du budget</span>' : ''}</td>
                  <td class="muted" style="font-size:.8rem">${l.quantite ? `${Number(l.quantite).toLocaleString('fr-FR')} ${UI.esc(l.unite || '')} × ${this.eur2(l.cout_unitaire)}` : '—'}</td>
                  <td style="white-space:nowrap">${this.eur2(l.montant)}</td>
                  <td class="masquer-mobile muted" style="font-size:.8rem;white-space:nowrap">${this.eur(l.montant_a1)} / ${this.eur(l.montant_a2)} / ${this.eur(l.montant_a3)}</td>
                  <td style="white-space:nowrap">${this.eur2(l.depense)}${l.engage > 0 ? `<br><span class="muted" style="font-size:.78rem">+ ${this.eur(l.engage)} engagés</span>` : ''}</td>
                  <td style="white-space:nowrap;color:${l.montant - l.depense < 0 ? 'var(--rouge)' : 'inherit'}">${this.eur2(l.montant - l.depense)}</td>
                  <td style="min-width:70px">${this.pct(l.taux)}${this.barre(l.montant, l.depense, l.engage)}</td></tr>`).join('')}
              </table></div></details>`;
          }).join('')}
        </div>`;
      }).join('') || '<div class="carte vide-etat">Aucune ligne ne correspond.</div>';
      zone.querySelectorAll('[data-ligne]').forEach((tr) => { tr.onclick = () => this.ouvrirLigne(s.lignes.find((l) => String(l.id) === tr.dataset.ligne), vue); });
    };
    dessiner();
    zone.querySelector('#fb-q').oninput = (e) => { f.q = e.target.value; dessiner(); };
    zone.querySelector('#fb-r').onchange = (e) => { f.rubrique = e.target.value; dessiner(); };
    zone.querySelector('#fb-p').onchange = (e) => { f.partenaire = e.target.value; dessiner(); };
  },

  async ouvrirLigne(l, vue) {
    const deps = await Api.get('finances.php', { vue: 'depenses', ligne_id: l.id });
    const m = UI.modale(`<h2>${UI.esc(l.libelle)}</h2>
      <p class="muted">${l.rubrique}. ${UI.esc(l.rubrique_libelle || '')} — ${UI.esc(l.groupe || '')}</p>
      <div class="kpis">
        <div class="kpi"><div class="val">${Devise.montant(l.montant, { bloc: true, dec: 2 })}</div><div class="lib">Budget (${UI.esc(l.quantite ? `${l.quantite} ${l.unite || ''}` : '')})</div></div>
        <div class="kpi vert"><div class="val">${Devise.montant(l.depense, { bloc: true, dec: 2 })}</div><div class="lib">Dépensé (${this.pct(l.taux)})</div></div>
        <div class="kpi ${l.montant - l.depense < 0 ? 'rouge' : ''}"><div class="val">${Devise.montant(l.montant - l.depense, { bloc: true, dec: 2 })}</div><div class="lib">Reste</div></div>
      </div>
      <h3>Dépenses imputées (${deps.length})</h3>
      ${this.tableDepenses(deps, false)}
      <div class="actions">${UI.peutEcrire() ? '<button id="ajout-l">+ Dépense sur cette ligne</button>' : ''}</div>`, { large: true });
    m.querySelector('#ajout-l')?.addEventListener('click', () => this.formDepense({ ligne_id: l.id, partenaire: l.partenaire, activite_id: l.activite_id }, () => this.afficher(vue)));
    this.brancherDepenses(m, deps, vue);
  },

  // ------------------------------------------------------------------ depenses
  tableDepenses(deps, avecLigne = true) {
    if (!deps.length) return '<p class="muted">Aucune dépense.</p>';
    return `<div class="table-wrap"><table>
      <tr><th>Date</th><th>Libellé</th>${avecLigne ? '<th>Ligne budgétaire</th>' : ''}<th>Montant</th><th>Statut</th><th>Pièces</th></tr>
      ${deps.map((d) => `<tr class="cliquable" data-dep="${d.id}">
        <td style="white-space:nowrap">${UI.date(d.date_depense)}</td>
        <td><b>${UI.esc(d.libelle)}</b>${d.fournisseur ? `<br><span class="muted" style="font-size:.8rem">${UI.esc(d.fournisseur)}${d.piece ? ` · pièce ${UI.esc(d.piece)}` : ''}</span>` : d.piece ? `<br><span class="muted" style="font-size:.8rem">pièce ${UI.esc(d.piece)}</span>` : ''}</td>
        ${avecLigne ? `<td style="font-size:.85rem">${d.ligne_libelle ? `${d.rubrique}. ${UI.esc(d.ligne_libelle)}<br><span class="muted" style="font-size:.78rem">${UI.esc((d.ligne_groupe || '').slice(0, 60))}</span>` : '<span class="badge retard">non imputée</span>'}</td>` : ''}
        <td style="white-space:nowrap"><b>${this.eur2(d.montant_eur)}</b><br><span class="muted" style="font-size:.78rem">${this.fcfa(d.montant_fcfa || d.montant_eur * this.TAUX)}</span></td>
        <td><span class="badge ${d.statut === 'justifiee' ? 'valide' : d.statut === 'engagee' ? 'en_revue' : 'en_cours'}">${this.STATUTS[d.statut] || d.statut}</span></td>
        <td>${+d.nb_pieces ? `📎 ${d.nb_pieces}` : '<span class="muted">—</span>'}</td></tr>`).join('')}
    </table></div>`;
  },

  brancherDepenses(racine, deps, vue) {
    racine.querySelectorAll('[data-dep]').forEach((tr) => {
      tr.onclick = () => this.formDepense(deps.find((d) => String(d.id) === tr.dataset.dep), () => this.afficher(vue));
    });
  },

  async depenses(zone, vue) {
    const p = this.periode;
    zone.innerHTML = `
      <div class="filtres">
        <label style="margin:0">Du <input type="date" id="fd-d" value="${p.debut}"></label>
        <label style="margin:0">au <input type="date" id="fd-f" value="${p.fin}"></label>
        <input type="search" id="fd-q" placeholder="Rechercher (libellé, fournisseur, pièce…)">
        <button class="btn-sec" id="btn-csv">⬇️ Exporter pour Excel</button>
      </div>
      <div class="carte" id="liste-dep"><div class="chargement"><span class="spinner fonce"></span></div></div>`;
    const charger = async () => {
      const deps = await Api.get('finances.php', { vue: 'depenses', debut: p.debut, fin: p.fin });
      const q = (zone.querySelector('#fd-q').value || '').toLowerCase();
      const l = deps.filter((d) => !q || `${d.libelle} ${d.fournisseur || ''} ${d.piece || ''} ${d.ligne_libelle || ''}`.toLowerCase().includes(q));
      const total = l.reduce((t, d) => t + Number(d.montant_eur), 0);
      zone.querySelector('#liste-dep').innerHTML = `<p style="margin-top:0"><b>${l.length}</b> dépense(s) — total <b>${this.eur2(total)}</b> (${this.fcfa(total * this.TAUX)})</p>${this.tableDepenses(l)}`;
      this.brancherDepenses(zone, l, vue);
    };
    zone.querySelector('#fd-d').onchange = (e) => { p.debut = e.target.value; charger(); };
    zone.querySelector('#fd-f').onchange = (e) => { p.fin = e.target.value; charger(); };
    zone.querySelector('#fd-q').oninput = () => charger();
    zone.querySelector('#btn-csv').onclick = () => telecharger(`api/finances.php?action=export_csv&debut=${p.debut}&fin=${p.fin}`);
    await charger();
  },

  async formDepense(d, apres) {
    const s = this.donnees || await Api.get('finances.php', { vue: 'synthese' });
    const acts = await reference('activites');
    const ecrire = UI.peutEcrire();
    const dis = ecrire ? '' : 'disabled';
    const groupes = {};
    s.lignes.filter((l) => !+l.obsolete || String(l.id) === String(d.ligne_id)).forEach((l) => { (groupes[`${l.rubrique}. ${l.groupe || l.rubrique_libelle}`] ??= []).push(l); });
    const optLignes = '<option value="">— Choisir la ligne budgétaire —</option>' + Object.entries(groupes).map(([g, ls]) =>
      `<optgroup label="${UI.esc(g.slice(0, 90))}">${ls.map((l) => `<option value="${l.id}" ${String(l.id) === String(d.ligne_id) ? 'selected' : ''}>${UI.esc(l.libelle.slice(0, 80))} — reste ${this.eur(l.montant - l.depense)}</option>`).join('')}</optgroup>`).join('');
    const m = UI.modale(`<h2>${d.id ? 'Dépense' : 'Nouvelle dépense'}</h2>
      <form class="grille-form">
        <label>Date * <input type="date" name="date_depense" required value="${UI.esc(d.date_depense || new Date().toISOString().slice(0, 10))}" ${dis}></label>
        <label>Statut <select name="statut" ${dis}>${UI.options(this.STATUTS, d.statut || 'payee')}</select></label>
        <label class="plein">Libellé * <input name="libelle" required value="${UI.esc(d.libelle)}" placeholder="ex. Location de salle — atelier BSG du 9 juillet" ${dis}></label>
        <label class="plein">Ligne budgétaire <select name="ligne_id" ${dis}>${optLignes}</select></label>
        <label>Montant en FCFA <input type="number" step="1" min="0" name="montant_fcfa" value="${UI.esc(d.montant_fcfa ? Math.round(d.montant_fcfa) : '')}" ${dis}></label>
        <label>Montant en € * <input type="number" step="0.01" min="0" name="montant_eur" value="${UI.esc(d.montant_eur || '')}" ${dis}></label>
        <label>Fournisseur / bénéficiaire <input name="fournisseur" value="${UI.esc(d.fournisseur)}" ${dis}></label>
        <label>N° de pièce / facture <input name="piece" value="${UI.esc(d.piece)}" ${dis}></label>
        <label>Mode de paiement <select name="mode_paiement" ${dis}>${UI.options({ virement: 'Virement', cheque: 'Chèque', especes: 'Espèces', mobile: 'Mobile money' }, d.mode_paiement, '—')}</select></label>
        <label>Partenaire <select name="partenaire" ${dis}>${UI.options(this.PARTENAIRES, d.partenaire || 'SJ')}</select></label>
        <label class="plein">Activité liée <select name="activite_id" ${dis}>${UI.optionsListe(acts, 'id', (a) => `${a.code ? a.code + ' — ' : ''}${a.titre}`, d.activite_id, 'Aucune / automatique selon la ligne')}</select></label>
        <label class="plein">Notes <textarea name="notes" rows="2" ${dis}>${UI.esc(d.notes)}</textarea></label>
        ${d.id ? '<div class="plein" id="pieces"></div>' : '<p class="plein muted">Vous pourrez joindre la facture ou le reçu après l\'enregistrement.</p>'}
        ${ecrire ? `<div class="actions plein">${d.id ? '<button type="button" class="btn-danger" id="suppr">Supprimer</button>' : ''}<button type="submit">Enregistrer</button></div>` : ''}
      </form>`, { large: true });
    const form = m.querySelector('form');
    const eur = form.querySelector('[name=montant_eur]');
    const fcfa = form.querySelector('[name=montant_fcfa]');
    fcfa.oninput = () => { eur.value = fcfa.value ? (fcfa.value / this.TAUX).toFixed(2) : ''; };
    eur.oninput = () => { fcfa.value = eur.value ? Math.round(eur.value * this.TAUX) : ''; };
    form.querySelector('[name=ligne_id]').onchange = (e) => {
      const l = s.lignes.find((x) => String(x.id) === e.target.value);
      if (l) form.querySelector('[name=partenaire]').value = l.partenaire || 'SJ';
    };
    form.onsubmit = async (e) => {
      e.preventDefault();
      const data = UI.lireForm(form);
      const r = await UI.occupe(e.submitter, () => (d.id ? Api.put(`finances.php?action=depense&id=${d.id}`, data) : Api.post('finances.php?action=depense', data)));
      UI.toast('Dépense enregistrée', 'ok');
      invalider('activites');
      if (!d.id) { UI.fermerModale(); this.formDepense({ ...data, id: r.id }, apres); } else { UI.fermerModale(); }
      apres?.();
    };
    m.querySelector('#suppr')?.addEventListener('click', async () => {
      if (!confirm('Supprimer cette dépense et ses justificatifs ?')) return;
      await Api.del(`finances.php?action=depense&id=${d.id}`);
      UI.fermerModale(); UI.toast('Dépense supprimée'); invalider('activites'); apres?.();
    });
    if (d.id) this.pieces(m.querySelector('#pieces'), d.id, ecrire);
  },

  async pieces(zone, depenseId, ecrire) {
    const ps = await Api.get('finances.php', { vue: 'pieces', depense_id: depenseId });
    zone.innerHTML = `<h3>📎 Pièces justificatives</h3>
      ${ps.map((p) => `<div class="item-doc"><span class="t">${UI.esc(p.nom)}</span>
        <a class="btn btn-sec btn-sm" href="api/finances.php?action=piece&pid=${p.id}" target="_blank" rel="noopener">Ouvrir</a>
        ${ecrire ? `<button type="button" class="btn-lien btn-sm" data-suppr-piece="${p.id}">🗑️</button>` : ''}</div>`).join('') || '<p class="muted">Aucune pièce jointe (facture, reçu, bon de commande, liste de présence…).</p>'}
      ${ecrire ? '<label class="btn btn-sec btn-sm" style="margin-top:.4rem">⬆️ Joindre une pièce<input type="file" hidden id="input-piece" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx"></label>' : ''}`;
    zone.querySelector('#input-piece')?.addEventListener('change', async (e) => {
      const fd = new FormData(); fd.append('fichier', e.target.files[0]);
      try { await Api.post(`finances.php?action=piece&depense_id=${depenseId}`, fd); UI.toast('Pièce jointe — dépense marquée « justifiée »', 'ok'); } catch (err) { UI.toast(err.message, 'erreur'); }
      this.pieces(zone, depenseId, ecrire);
    });
    zone.querySelectorAll('[data-suppr-piece]').forEach((b) => {
      b.onclick = async () => { if (confirm('Retirer cette pièce ?')) { await Api.del(`finances.php?action=piece&pid=${b.dataset.supprPiece}`); this.pieces(zone, depenseId, ecrire); } };
    });
  },

  // ------------------------------------------------------------------ financements
  async financements(zone, vue) {
    const fs = await Api.get('finances.php', { vue: 'financements' });
    const s = this.donnees;
    const recuPar = {};
    fs.forEach((f) => { recuPar[f.source] = (recuPar[f.source] || 0) + Number(f.montant_eur); });
    zone.innerHTML = `
      <div class="deux-col">
        <div class="carte">
          <div class="entete-vue"><h2>Fonds reçus</h2>${UI.peutEcrire() ? '<button id="ajout-f">+ Enregistrer une réception de fonds</button>' : ''}</div>
          ${fs.length ? `<div class="table-wrap"><table><tr><th>Date</th><th>Source</th><th>Libellé</th><th>Montant</th><th></th></tr>
            ${fs.map((f) => `<tr><td>${UI.date(f.date_reception)}</td><td>${UI.esc(f.source)}</td><td>${UI.esc(f.libelle || '')}</td><td><b>${this.eur2(f.montant_eur)}</b></td>
              <td>${UI.peutEcrire() ? `<button class="btn-lien btn-sm" data-suppr-f="${f.id}">🗑️</button>` : ''}</td></tr>`).join('')}</table></div>`
            : '<p class="muted">Aucun versement enregistré (ex. préfinancement UE, 2e tranche, contribution Hewlett).</p>'}
        </div>
        <div class="carte">
          <h2>Prévu / reçu</h2>
          ${s.sources.map((x) => {
            const recu = Object.entries(recuPar).filter(([k]) => k.toLowerCase().includes(x.source.toLowerCase().split(' ')[0].toLowerCase()) || (x.source.startsWith('Union') && /ue|union/i.test(k))).reduce((t, [, v]) => t + v, 0);
            return `<div class="ligne-prog"><div class="haut"><span><b>${UI.esc(x.source)}</b></span><span>${this.eur(recu)} / ${this.eur(x.montant)}</span></div>${this.barre(x.montant, recu)}</div>`;
          }).join('')}
          <div class="item-doc"><span class="t"><b>Trésorerie disponible</b> (reçu − dépenses payées)</span><b style="color:${s.tresorerie < 0 ? 'var(--rouge)' : 'var(--vert)'}">${this.eur2(s.tresorerie)}</b></div>
        </div>
      </div>`;
    zone.querySelector('#ajout-f')?.addEventListener('click', () => {
      const m = UI.modale(`<h2>Réception de fonds</h2><form class="grille-form">
        <label>Date * <input type="date" name="date_reception" required value="${new Date().toISOString().slice(0, 10)}"></label>
        <label>Source * <select name="source">${UI.options(Object.fromEntries(s.sources.map((x) => [x.source, x.source])), s.sources[0]?.source)}</select></label>
        <label class="plein">Libellé <input name="libelle" placeholder="ex. Préfinancement 1re tranche"></label>
        <label>Montant en € * <input type="number" step="0.01" min="0" name="montant_eur" required></label>
        <label>Notes <input name="notes"></label>
        <div class="actions plein"><button type="submit">Enregistrer</button></div></form>`);
      m.querySelector('form').onsubmit = async (e) => {
        e.preventDefault();
        await UI.occupe(e.submitter, () => Api.post('finances.php?action=financement', UI.lireForm(e.target)));
        UI.fermerModale(); UI.toast('Fonds enregistrés', 'ok'); this.afficher(vue);
      };
    });
    zone.querySelectorAll('[data-suppr-f]').forEach((b) => {
      b.onclick = async () => { if (confirm('Supprimer ce versement ?')) { await Api.del(`finances.php?action=financement&id=${b.dataset.supprF}`); this.afficher(vue); } };
    });
  },

  // ------------------------------------------------------------------ rapports
  async rapports(zone) {
    const s = this.donnees;
    const docs = (await Api.get('documents.php', { q: 'Rapport financier' })).filter((d) => d.titre.startsWith('Rapport financier'));
    const presets = {};
    s.annees_def.forEach(([d, f], i) => {
      const milieu = new Date(d); milieu.setMonth(milieu.getMonth() + 6); milieu.setDate(milieu.getDate() - 1);
      const m = milieu.toISOString().slice(0, 10);
      const debutS2 = new Date(milieu); debutS2.setDate(debutS2.getDate() + 1);
      presets[`${d}|${m}`] = `Année ${i + 1} — semestre 1`;
      presets[`${debutS2.toISOString().slice(0, 10)}|${f}`] = `Année ${i + 1} — semestre 2`;
      presets[`${d}|${f}`] = `Année ${i + 1} complète`;
    });
    presets[`${s.annees_def[0][0]}|${new Date().toISOString().slice(0, 10)}`] = 'Depuis le début du projet';
    zone.innerHTML = `
      <div class="deux-col">
        <form class="carte" id="form-rapport">
          <h2>Générer un rapport financier</h2>
          <label>Période <select id="preset">${UI.options(presets, '', 'Choisir…')}</select></label>
          <div class="grille-form"><label>Du * <input type="date" name="debut" required></label><label>Au * <input type="date" name="fin" required></label></div>
          <label>Intitulé <input name="intitule" placeholder="ex. Année 1 — semestre 2 (janvier – juin 2026)"></label>
          <label class="case" style="display:flex;gap:.5rem;font-weight:400"><input type="checkbox" name="analyse_ia" style="width:auto" ${Etat.config.ia ? 'checked' : 'disabled'}>
            <span><b>Commentaire d'exécution rédigé par l'IA</b><br><span class="muted">Analyse des écarts, du rythme de consommation et de la trésorerie.</span></span></label>
          <p class="muted" style="font-size:.85rem">Le rapport reprend : sources de financement, état par rubrique (budget / période / cumul / solde), exécution par année et par partenaire, lignes mouvementées, écarts, journal des dépenses et attestation. Il s'ouvre dans l'éditeur : export Word et PDF.</p>
          <div class="actions"><button type="submit" ${UI.peutEcrire() ? '' : 'disabled'}>📑 Générer le rapport</button></div>
        </form>
        <div class="carte"><h2>Rapports financiers existants</h2>
          ${docs.map((d) => `<div class="item-doc"><span class="t">${UI.esc(d.titre)}<br><span class="muted" style="font-size:.8rem">${UI.date(d.updated_at || d.created_at)}</span></span>${UI.badge(d.statut, UI.STATUTS_DOC)}<a class="btn btn-sec btn-sm" href="#/editeur/${d.id}">📖 Ouvrir</a></div>`).join('') || '<p class="muted">Aucun rapport généré pour l\'instant.</p>'}
          <p class="muted" style="font-size:.85rem">Le journal complet des dépenses est aussi exportable pour Excel (onglet Dépenses).</p>
        </div>
      </div>`;
    const form = zone.querySelector('#form-rapport');
    zone.querySelector('#preset').onchange = (e) => {
      if (!e.target.value) return;
      const [d, f] = e.target.value.split('|');
      form.querySelector('[name=debut]').value = d; form.querySelector('[name=fin]').value = f;
      form.querySelector('[name=intitule]').value = e.target.selectedOptions[0].textContent + ` (${UI.date(d)} – ${UI.date(f)})`;
    };
    form.onsubmit = async (e) => {
      e.preventDefault();
      const r = await UI.occupe(e.submitter, () => Api.post('finances.php?action=rapport', UI.lireForm(form)), 'Génération…');
      UI.toast('Rapport financier créé — complétez les zones en rouge puis exportez-le en Word', 'ok');
      location.hash = `#/editeur/${r.document_id}`;
    };
  },

  // ------------------------------------------------------------------ import
  importer(vue) {
    const m = UI.modale(`<h2>📥 Importer / mettre à jour le budget</h2>
      <p class="muted">Choisissez le classeur budgétaire UE (.xlsx, format Annexe III avec la feuille « 1a. Budget fondé sur les coûts »). En cas de nouvelle version du budget (avenant), les lignes sont mises à jour et <b>les dépenses déjà saisies sont conservées</b>.</p>
      <form><label>Fichier .xlsx * <input type="file" name="fichier" accept=".xlsx" required></label>
      <div class="actions"><button type="submit">Importer</button></div></form>`);
    m.querySelector('form').onsubmit = async (e) => {
      e.preventDefault();
      const r = await UI.occupe(e.submitter, () => Api.post('finances.php?action=import', new FormData(e.target)), 'Lecture du budget…');
      UI.fermerModale();
      UI.toast(`Budget importé : ${r.lignes} lignes, total ${this.eur(r.total)} (${r.crees} nouvelles, ${r.mis_a_jour} mises à jour)`, 'ok');
      invalider('activites');
      this.afficher(vue);
    };
  },
};
