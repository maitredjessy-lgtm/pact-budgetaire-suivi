/* Noyau de l'application : appels API, utilitaires d'interface, rendu Markdown. */

const Api = (() => {
  async function requete(chemin, options = {}) {
    const headers = { 'X-Requested-With': 'pact-app' };
    if (options.body && !(options.body instanceof FormData)) headers['Content-Type'] = 'application/json';
    const res = await fetch(`api/${chemin}`, { credentials: 'same-origin', ...options, headers });
    let data = null;
    try { data = await res.json(); } catch (e) { /* corps vide */ }
    if (res.status === 401 && !chemin.startsWith('auth.php')) {
      window.dispatchEvent(new Event('session-expiree'));
    }
    if (!res.ok) {
      const err = new Error((data && data.error) || `Erreur ${res.status}`);
      err.status = res.status;
      throw err;
    }
    return data;
  }
  const qs = (o = {}) => {
    const p = Object.entries(o).filter(([, v]) => v !== '' && v !== null && v !== undefined);
    return p.length ? '?' + new URLSearchParams(p).toString() : '';
  };
  return {
    get: (c, params) => requete(c + qs(params)),
    post: (c, body) => requete(c, { method: 'POST', body: body instanceof FormData ? body : JSON.stringify(body) }),
    put: (c, body) => requete(c, { method: 'PUT', body: JSON.stringify(body) }),
    del: (c) => requete(c, { method: 'DELETE' }),
    ia: (action, body) => requete(`ia.php?action=${action}`, { method: 'POST', body: JSON.stringify(body) }),
  };
})();

const Etat = { user: null, config: {}, cache: {} };

/** Donnees de reference partagees (listes deroulantes), rechargees apres modification */
async function reference(nom, forcer = false) {
  if (!forcer && Etat.cache[nom]) return Etat.cache[nom];
  const chemins = { responsables: 'responsables.php', activites: 'activites.php', modeles: 'modeles.php', acteurs: 'acteurs.php' };
  Etat.cache[nom] = await Api.get(chemins[nom]);
  return Etat.cache[nom];
}
function invalider(...noms) { noms.forEach((n) => delete Etat.cache[n]); }

const UI = (() => {
  const esc = (s) => (s === null || s === undefined ? '' : String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])));

  const date = (iso) => {
    if (!iso) return '—';
    const d = new Date(iso.length === 10 ? iso + 'T00:00:00' : iso.replace(' ', 'T'));
    return isNaN(d) ? iso : d.toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' });
  };
  const jours = (iso) => {
    if (!iso) return null;
    const t = new Date(iso + 'T00:00:00'); const a = new Date(); a.setHours(0, 0, 0, 0);
    return Math.round((t - a) / 86400000);
  };
  const delai = (iso) => {
    const j = jours(iso);
    if (j === null) return '';
    if (j < 0) return `<span class="badge retard">${-j} j de retard</span>`;
    if (j === 0) return `<span class="badge retard">aujourd'hui</span>`;
    if (j <= 7) return `<span class="badge reporte">dans ${j} j</span>`;
    return `<span class="muted">dans ${j} j</span>`;
  };

  const STATUTS = { a_faire: 'À faire', en_cours: 'En cours', termine: 'Terminé', reporte: 'Reporté', annule: 'Annulé' };
  const STATUTS_DOC = { a_faire: 'À produire', brouillon: 'Brouillon', en_revue: 'En revue', valide: 'Validé', envoye: 'Envoyé' };
  const TYPES_DOC = { rapport: 'Rapport', courrier: 'Courrier', tdr: 'TDR', compte_rendu: 'Compte rendu', note: 'Note', communique: 'Communiqué', autre: 'Autre' };
  const TYPES_ACT = { atelier: 'Atelier', formation: 'Formation', reunion: 'Réunion / audience', plaidoyer: 'Plaidoyer', communication: 'Communication', etude: 'Étude / diagnostic', autre: 'Autre' };
  const PRIORITES = { basse: 'Basse', normale: 'Normale', haute: 'Haute' };

  const badge = (statut, libelles = STATUTS, retard = false) =>
    retard ? `<span class="badge retard">En retard</span>` : `<span class="badge ${esc(statut)}">${esc(libelles[statut] || statut)}</span>`;

  const options = (obj, sel, vide) =>
    (vide !== undefined ? `<option value="">${esc(vide)}</option>` : '') +
    Object.entries(obj).map(([v, l]) => `<option value="${esc(v)}" ${String(v) === String(sel ?? '') ? 'selected' : ''}>${esc(l)}</option>`).join('');
  const optionsListe = (liste, cle, lib, sel, vide = '—') =>
    `<option value="">${esc(vide)}</option>` + liste.map((x) => `<option value="${esc(x[cle])}" ${String(x[cle]) === String(sel ?? '') ? 'selected' : ''}>${esc(typeof lib === 'function' ? lib(x) : x[lib])}</option>`).join('');

  function toast(msg, type = '') {
    const el = document.getElementById('toast');
    el.textContent = msg; el.className = `toast ${type}`; el.hidden = false;
    clearTimeout(toast.t); toast.t = setTimeout(() => { el.hidden = true; }, type === 'erreur' ? 6000 : 3500);
  }

  function modale(html, { large = false } = {}) {
    fermerModale();
    const v = document.createElement('div');
    v.className = 'modale-voile'; v.id = 'modale';
    v.innerHTML = `<div class="modale ${large ? 'large' : ''}"><button class="fermer" data-fermer aria-label="Fermer">×</button>${html}</div>`;
    v.addEventListener('mousedown', (e) => { if (e.target === v) fermerModale(); });
    v.querySelector('[data-fermer]').onclick = fermerModale;
    document.body.appendChild(v);
    return v;
  }
  function fermerModale() { document.getElementById('modale')?.remove(); }

  function panneau(html) {
    fermerPanneau();
    const v = document.createElement('div');
    v.className = 'voile'; v.id = 'panneau';
    v.innerHTML = `<div class="panneau"><button class="fermer" data-fermer aria-label="Fermer">×</button>${html}</div>`;
    v.addEventListener('mousedown', (e) => { if (e.target === v) fermerPanneau(); });
    v.querySelector('[data-fermer]').onclick = fermerPanneau;
    document.body.appendChild(v);
    return v;
  }
  function fermerPanneau() { document.getElementById('panneau')?.remove(); }

  /** Lit les champs [name] d'un formulaire en objet */
  function lireForm(form) {
    const o = {};
    form.querySelectorAll('[name]').forEach((el) => {
      o[el.name] = el.type === 'checkbox' ? el.checked : el.value;
    });
    return o;
  }

  /** Execute une action asynchrone en desactivant le bouton et en affichant les erreurs */
  async function occupe(bouton, fn, libelleAttente) {
    const orig = bouton ? bouton.innerHTML : '';
    if (bouton) { bouton.disabled = true; bouton.innerHTML = `<span class="spinner"></span> ${esc(libelleAttente || 'Patientez…')}`; }
    try { return await fn(); } catch (e) { toast(e.message, 'erreur'); throw e; } finally {
      if (bouton) { bouton.disabled = false; bouton.innerHTML = orig; }
    }
  }

  const peutEcrire = () => Etat.user && Etat.user.role !== 'lecteur';
  const prog = (v, cls = '') => `<div class="barre-prog ${cls}"><span style="width:${Math.max(0, Math.min(100, Number(v) || 0))}%"></span></div>`;
  const initiales = (nom) => (nom || '?').split(/\s+/).filter(Boolean).slice(0, 2).map((m) => m[0]).join('').toUpperCase();

  return { esc, date, jours, delai, STATUTS, STATUTS_DOC, TYPES_DOC, TYPES_ACT, PRIORITES, badge, options, optionsListe,
    toast, modale, fermerModale, panneau, fermerPanneau, lireForm, occupe, peutEcrire, prog, initiales };
})();

/** Rendu Markdown minimal et sur (le HTML brut est echappe) */
const MD = (() => {
  const inline = (s) => UI.esc(s)
    .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
    .replace(/(^|[^*])\*([^*\s][^*]*)\*/g, '$1<em>$2</em>')
    .replace(/\[(À compléter|À préciser|à compléter|à préciser)[^\]]*\]/g, (m) => `<span class="vide">${m}</span>`)
    .replace(/(https?:\/\/[^\s<)]+)/g, '<a href="$1" target="_blank" rel="noopener">$1</a>');

  function html(md) {
    const lignes = (md || '').replace(/\r/g, '').split('\n');
    let out = ''; let para = []; let liste = false;
    const finPara = () => { if (para.length) { out += `<p>${para.map(inline).join('<br>')}</p>`; para = []; } };
    const finListe = () => { if (liste) { out += '</ul>'; liste = false; } };
    for (let i = 0; i < lignes.length; i++) {
      const l = lignes[i]; const t = l.trim();
      let m;
      if (!t) { finPara(); finListe(); continue; }
      if ((m = t.match(/^(#{1,6})\s+(.*)$/))) { finPara(); finListe(); const n = Math.min(4, m[1].length); out += `<h${n}>${inline(m[2])}</h${n}>`; continue; }
      if ((m = t.match(/^[-*+]\s+(.*)$/))) { finPara(); if (!liste) { out += '<ul>'; liste = true; } out += `<li>${inline(m[1])}</li>`; continue; }
      if ((m = t.match(/^>\s?(.*)$/))) { finPara(); finListe(); out += `<blockquote>${inline(m[1])}</blockquote>`; continue; }
      if (/^(-{3,}|\*{3,})$/.test(t)) { finPara(); finListe(); out += '<hr>'; continue; }
      if (t.startsWith('|')) {
        finPara(); finListe();
        const rows = [];
        while (i < lignes.length && lignes[i].trim().startsWith('|')) {
          const r = lignes[i].trim().replace(/^\||\|$/g, '');
          if (!/^[\s|:-]+$/.test(r)) rows.push(r.split('|').map((c) => c.trim()));
          i++;
        }
        i--;
        out += '<div class="table-wrap"><table>' + rows.map((r, k) => '<tr>' + r.map((c) => (k === 0 ? `<th>${inline(c)}</th>` : `<td>${inline(c)}</td>`)).join('') + '</tr>').join('') + '</table></div>';
        continue;
      }
      finListe(); para.push(t);
    }
    finPara(); finListe();
    return out;
  }
  return { html };
})();

/** Telechargement d'un fichier servi par l'API (export Word, calendrier) */
function telecharger(url) {
  const a = document.createElement('a');
  a.href = url; a.rel = 'noopener';
  document.body.appendChild(a); a.click(); a.remove();
}
