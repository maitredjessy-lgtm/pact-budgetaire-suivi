/* Acteurs institutionnels : coordonnees, activites liees et veille sur leurs sites officiels */
const VueInstitutions = {
  filtre: '',

  async afficher(vue, params = []) {
    const acteurs = await reference('acteurs', true);
    const categories = [...new Set(acteurs.map((a) => a.categorie))];
    vue.innerHTML = `
      <div class="entete-vue">
        <div><h1>🏛️ Institutions & veille</h1>
          <p class="muted">Acteurs institutionnels liés au projet, avec leurs sites officiels et leurs dernières actualités (mises à jour automatiquement, toutes les 3 h au plus).</p></div>
      </div>
      <div class="filtres">
        <input type="search" id="i-q" placeholder="Rechercher une institution…">
        <select id="i-cat">${UI.options(Object.fromEntries(categories.map((c) => [c, c])), this.filtre, 'Toutes les catégories')}</select>
      </div>
      <div class="grille-acteurs" id="grille"></div>`;

    const dessiner = () => {
      const q = vue.querySelector('#i-q').value.toLowerCase();
      const liste = acteurs.filter((a) => (!this.filtre || a.categorie === this.filtre) && (!q || `${a.sigle} ${a.nom} ${a.role}`.toLowerCase().includes(q)));
      vue.querySelector('#grille').innerHTML = liste.map((a) => this.carte(a)).join('') || '<p class="vide-etat">Aucune institution.</p>';
      vue.querySelectorAll('[data-actu]').forEach((b) => { b.onclick = () => this.actualites(vue.querySelector(`#actu-${b.dataset.actu}`), b.dataset.actu, b); });
      vue.querySelectorAll('[data-courrier]').forEach((b) => { b.onclick = () => { location.hash = `#/rediger/acteur/${b.dataset.courrier}`; }; });
    };
    vue.querySelector('#i-q').oninput = dessiner;
    vue.querySelector('#i-cat').onchange = (e) => { this.filtre = e.target.value; dessiner(); };
    dessiner();

    // Lien direct : #/institutions/dgbf ouvre l'acteur et charge ses actualites
    const cle = params[0];
    if (cle) {
      const carte = vue.querySelector(`#acteur-${cle}`);
      if (carte) {
        carte.scrollIntoView({ block: 'center' });
        carte.classList.add('surligne');
        vue.querySelector(`[data-actu="${cle}"]`)?.click();
      }
    }
  },

  carte(a) {
    const nb = a.activites.length;
    return `<article class="carte carte-acteur" id="acteur-${UI.esc(a.cle)}">
      <div class="haut-acteur"><span class="sigle">${UI.esc(a.sigle)}</span><span class="badge type">${UI.esc(a.categorie)}</span></div>
      <h3>${UI.esc(a.nom)}</h3>
      <p class="muted">${UI.esc(a.role)}</p>
      <div class="actions-acteur">
        ${a.site ? `<a class="btn btn-sec btn-sm" href="${UI.esc(a.site)}" target="_blank" rel="noopener">🌐 Site officiel ↗</a>` : '<span class="muted">Pas de site public identifié</span>'}
        ${a.actualites ? `<button class="btn-sec btn-sm" data-actu="${UI.esc(a.cle)}">📰 Dernières actualités</button>` : ''}
        ${UI.peutEcrire() && a.destinataire ? `<button class="btn-ia btn-sm" data-courrier="${UI.esc(a.cle)}">✉️ Rédiger un courrier</button>` : ''}
      </div>
      <div class="actu" id="actu-${UI.esc(a.cle)}" hidden></div>
      <details class="activites-liees"><summary>🗂️ ${nb} activité${nb > 1 ? 's' : ''} liée${nb > 1 ? 's' : ''}</summary>
        <ul>${a.activites.map((x) => `<li><a href="#/activites/${x.id}">${x.code ? `<span class="code">${UI.esc(x.code)}</span> ` : ''}${UI.esc(x.titre)}</a></li>`).join('') || '<li class="muted">Aucune</li>'}</ul>
      </details>
    </article>`;
  },

  async actualites(zone, cle, bouton, forcer = false) {
    zone.hidden = false;
    zone.innerHTML = '<p class="muted"><span class="spinner fonce"></span> Consultation du site officiel…</p>';
    try {
      const r = await Api.get('acteurs.php', { action: 'actualites', cle, forcer: forcer ? 1 : '' });
      const a = (Etat.cache.acteurs || []).find((x) => x.cle === cle) || {};
      const maj = r.maj ? new Date(r.maj * 1000).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' }) : '';
      zone.innerHTML = `
        ${r.erreur ? `<div class="alerte">${UI.esc(r.erreur)}${a.actualites ? ` <a href="${UI.esc(a.actualites)}" target="_blank" rel="noopener">Consulter directement le site ↗</a>` : ''}</div>` : ''}
        ${r.items.length ? `<ul class="liste-actu">${r.items.map((i) => `<li>
            <a href="${UI.esc(i.lien)}" target="_blank" rel="noopener">${UI.esc(i.titre)}</a>
            ${i.date ? `<span class="muted"> — ${UI.date(i.date)}</span>` : ''}
            ${i.resume ? `<br><span class="muted">${UI.esc(i.resume)}</span>` : ''}</li>`).join('')}</ul>` : ''}
        <p class="muted" style="font-size:.8rem">${maj ? `Mis à jour le ${maj} · ` : ''}${r.source === 'page' ? 'liens relevés sur la page d\'actualités · ' : ''}<button class="btn-lien" data-rafraichir>Actualiser</button></p>`;
      zone.querySelector('[data-rafraichir]')?.addEventListener('click', () => this.actualites(zone, cle, bouton, true));
    } catch (e) {
      zone.innerHTML = `<div class="erreur">${UI.esc(e.message)}</div>`;
    }
  },
};
