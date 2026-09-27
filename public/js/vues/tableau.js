const VueTableau = {
  async afficher(vue) {
    const [d, ech] = await Promise.all([Api.get('dashboard.php'), Api.get('echeances.php')]);
    const k = d.kpi;
    const urgentes = ech.filter((e) => e.jours <= 7).slice(0, 10);
    const alertes = [];
    if (!Etat.config.ia) alertes.push(`L'IA n'est pas encore activée : ajoutez la variable <b>AI_API_KEY</b> (clé Gemini gratuite) dans Render pour activer la rédaction assistée.`);
    if (!Etat.config.persistant) alertes.push(`Base de données locale non persistante : sur Render gratuit, configurez <b>DATABASE_URL</b> (PostgreSQL) pour ne pas perdre les données au redémarrage.`);

    vue.innerHTML = `
      <div class="entete-vue">
        <div><h1>Tableau de bord</h1><p class="muted">Projet PACT Budgétaire — situation au ${UI.date(d.aujourdhui)}</p></div>
        <div class="actions"><a class="btn btn-ia" href="#/evaluation">🎯 Analyse d'évaluation IA</a><a class="btn btn-sec" href="#/rediger">✍️ Rédiger un document</a></div>
      </div>
      ${alertes.map((a) => `<div class="alerte">${a}</div>`).join('')}
      <div class="kpis">
        <a class="kpi" href="#/activites"><div class="val">${k.activites}</div><div class="lib">Activités planifiées</div></a>
        <a class="kpi vert" href="#/activites"><div class="val">${k.taux_realisation}%</div><div class="lib">Taux de réalisation (${k.terminees} terminées)</div></a>
        <div class="kpi"><div class="val">${k.avancement_moyen}%</div><div class="lib">Avancement moyen</div>${UI.prog(k.avancement_moyen)}</div>
        <a class="kpi rouge" href="#/echeances"><div class="val">${k.en_retard}</div><div class="lib">Activités en retard</div></a>
        <a class="kpi orange" href="#/echeances"><div class="val">${k.semaine}</div><div class="lib">Échéances dans 7 jours</div></a>
        <a class="kpi" href="#/documents"><div class="val">${k.docs_a_produire}</div><div class="lib">Documents à produire${k.docs_en_retard ? ` (${k.docs_en_retard} en retard)` : ''}</div></a>
        <a class="kpi" href="#/equipe"><div class="val">${k.taches_ouvertes}</div><div class="lib">Tâches ouvertes${k.taches_en_retard ? ` (${k.taches_en_retard} en retard)` : ''}</div></a>
      </div>
      <div class="deux-col">
        <div>
          <div class="carte">
            <h2>Avancement par composante</h2>
            ${d.par_composante.map((c) => `
              <div class="ligne-prog">
                <div class="haut"><span><b>${UI.esc(c.composante)}</b> <span class="muted">— ${c.termine}/${c.total} terminées, ${c.en_cours} en cours</span></span><b>${c.avancement ?? 0}%</b></div>
                ${UI.prog(c.avancement, c.avancement >= 75 ? 'vert' : '')}
              </div>`).join('')}
          </div>
          <div class="carte">
            <h2>Prochaines échéances d'activités (30 jours et retards)</h2>
            ${d.prochaines.length ? `<div class="table-wrap"><table>
              <tr><th>Activité</th><th>Responsable</th><th>Échéance</th><th>Statut</th></tr>
              ${d.prochaines.map((a) => `<tr class="cliquable" data-id="${a.id}">
                <td>${a.code ? `<span class="code">${UI.esc(a.code)}</span> ` : ''}${UI.esc(a.titre)}</td>
                <td>${UI.esc(a.responsable_nom || '—')}</td>
                <td>${UI.date(a.date_echeance)}<br>${UI.delai(a.date_echeance)}</td>
                <td>${UI.badge(a.statut)}</td></tr>`).join('')}
            </table></div>` : '<p class="vide-etat">Aucune échéance proche.</p>'}
          </div>
        </div>
        <div>
          <div class="carte">
            <h2>🔔 Alertes (retards et 7 prochains jours)</h2>
            ${urgentes.length ? urgentes.map((e) => VueEcheances.ligne(e)).join('') : '<p class="vide-etat">Rien d\'urgent 🎉</p>'}
            <a href="#/echeances" class="btn btn-sec btn-sm">Toutes les échéances →</a>
          </div>
          <div class="carte">
            <h2>Documents du projet</h2>
            ${d.documents_par_type.map((t) => `
              <div class="ligne-prog"><div class="haut"><span>${UI.esc(UI.TYPES_DOC[t.type] || t.type)}</span><span class="muted">${t.finalises}/${t.total} finalisés</span></div>
              ${UI.prog(t.total ? (100 * t.finalises) / t.total : 0, 'or')}</div>`).join('')}
          </div>
        </div>
      </div>`;
    vue.querySelectorAll('tr[data-id]').forEach((tr) => { tr.onclick = () => VueActivites.ouvrir(+tr.dataset.id); });
    VueEcheances.brancher(vue);
  },
};
