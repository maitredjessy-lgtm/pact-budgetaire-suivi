/* Generation de documents a partir des modeles (#/rediger) et editeur de document (#/editeur/ID) */
const VueRediger = {
  async afficher(vue, params, route) {
    if (route === 'editeur') return this.editeur(vue, +params[0]);
    return this.generateur(vue, params[0] === 'activite' ? +params[1] : null, params[0] === 'modele' ? +params[1] : null);
  },

  // ------------------------------------------------------------------ generateur
  async generateur(vue, activiteId, modeleId) {
    const [modeles, acts] = await Promise.all([reference('modeles', true), reference('activites')]);
    const ia = Etat.config.ia;
    vue.innerHTML = `
      <div class="entete-vue"><div><h1>✍️ Rédiger un document avec l'IA</h1>
        <p class="muted">Choisissez un modèle, l'activité concernée et donnez vos notes : l'IA rédige un premier jet complet, que vous relisez et finalisez dans l'éditeur.</p></div></div>
      ${ia ? '' : `<div class="alerte">L'IA n'est pas configurée : vous pouvez tout de même créer le document à partir du modèle vierge (variables pré-remplies), puis le rédiger manuellement.</div>`}
      ${UI.peutEcrire() ? '' : '<div class="erreur">Votre compte est en lecture seule.</div>'}
      <form class="carte" id="form-gen">
        <div class="grille-form">
          <label class="plein">1. Modèle de document *
            <select name="modele_id" required>${UI.optionsListe(modeles, 'id', (m) => `${UI.TYPES_DOC[m.type] || m.type} — ${m.nom}`, modeleId, 'Choisir un modèle…')}</select>
            <span class="muted" id="desc-modele" style="font-weight:400"></span>
          </label>
          <label class="plein">2. Activité concernée
            <select name="activite_id">${UI.optionsListe(acts, 'id', (a) => `${a.code ? a.code + ' — ' : ''}${a.titre}`, activiteId, 'Aucune (document général / rapport périodique)')}</select>
          </label>
          <div class="plein" id="variables"></div>
          <label class="plein">3. Vos notes et informations clés
            <textarea name="infos" rows="8" placeholder="Ex. : Atelier tenu le 12/09/2026 à l'hôtel Azalaï, 42 participants (18 femmes, 10 jeunes). Ouverture par le DGBF. Présentations : … Recommandations : … Difficultés : …&#10;&#10;Collez aussi ici des extraits de comptes rendus, de listes de présence, de TDR…"></textarea>
            <span class="muted" style="font-weight:400">Plus vos notes sont précises, plus le document sera fidèle. L'IA n'invente pas de chiffres : les informations manquantes apparaîtront en <span class="md"><span class="vide">[À compléter]</span></span>.</span>
          </label>
          <label class="plein">Instructions supplémentaires (facultatif)
            <input name="instructions" placeholder="Ex. : ton plus formel, 3 pages maximum, insister sur la participation des femmes…">
          </label>
          <div class="plein options-ia">
            <label class="case"><input type="checkbox" name="style" checked> <span><b>S'inspirer de nos documents</b><br><span class="muted">L'IA reprend le style de vos TDR, rapports ou courriers déjà rédigés (sans en copier les faits).</span></span></label>
            <label class="case"><input type="checkbox" name="relecture" checked> <span><b>Relecture automatique</b><br><span class="muted">Une seconde passe vérifie le modèle, corrige le français et retire toute information inventée.</span></span></label>
          </div>
        </div>
        <div class="actions">
          <button type="button" class="btn-sec" id="btn-vierge">Utiliser le modèle vierge</button>
          <button type="submit" class="btn-ia" ${ia && UI.peutEcrire() ? '' : 'disabled'}>🤖 Générer le document</button>
        </div>
      </form>`;

    const form = vue.querySelector('#form-gen');
    const selModele = form.querySelector('[name=modele_id]');
    const majModele = () => {
      const m = modeles.find((x) => String(x.id) === selModele.value);
      form.querySelector('#desc-modele').textContent = m ? m.description || '' : '';
      const vars = m ? [...new Set([...m.contenu.matchAll(/\{\{\s*(\w+)\s*\}\}/g)].map((x) => x[1]))] : [];
      const libelles = { destinataire: 'Destinataire (ex. Monsieur le Directeur Général du Budget et des Finances)', formule_appel: "Formule d'appel (ex. Monsieur le Directeur Général)", objet: 'Objet', reference: 'Référence du courrier', periode: 'Période couverte (ex. janvier – juin 2026)' };
      const aSaisir = vars.filter((v) => libelles[v]);
      form.querySelector('#variables').innerHTML = aSaisir.length ? `<div class="grille-form">${aSaisir.map((v) => `<label>${libelles[v]} <input data-var="${v}"></label>`).join('')}</div>` : '';
    };
    selModele.onchange = majModele; majModele();

    const lire = () => {
      const d = UI.lireForm(form);
      d.variables = {};
      form.querySelectorAll('[data-var]').forEach((i) => { d.variables[i.dataset.var] = i.value; });
      return d;
    };
    const creerDocument = async (contenu, titre, d, notes = null) => {
      const m = modeles.find((x) => String(x.id) === String(d.modele_id));
      const a = acts.find((x) => String(x.id) === String(d.activite_id));
      const doc = await Api.post('documents.php', {
        type: m.type, titre, contenu, notes, statut: 'brouillon', modele_id: m.id, activite_id: d.activite_id || null, responsable_id: a ? a.responsable_id : null,
      });
      window.majPastille?.();
      location.hash = `#/editeur/${doc.id}`;
    };

    form.onsubmit = async (e) => {
      e.preventDefault();
      const d = lire();
      if (!d.modele_id) return UI.toast('Choisissez un modèle', 'erreur');
      const m = modeles.find((x) => String(x.id) === d.modele_id);
      const long = (m.contenu.match(/^##\s/gm) || []).length > 5;
      const r = await UI.occupe(e.submitter, () => Api.ia('generer', d),
        long ? 'Rédaction section par section (2 à 4 min)…' : `Rédaction${d.relecture ? ' et relecture' : ''} en cours (jusqu'à 2 min)…`);
      const notes = [`Rédigé par l'IA le ${new Date().toLocaleDateString('fr-FR')} à partir du modèle « ${m.nom} »`,
        r.references?.length ? `style inspiré de : ${r.references.map((x) => x.titre).join(' ; ')}` : '',
        r.relu ? 'relu automatiquement' : ''].filter(Boolean).join(' — ');
      const n = r.a_completer?.length || 0;
      UI.toast(`Document rédigé${r.relu ? ' et relu' : ''}. ${n ? `${n} information(s) à compléter (en rouge).` : 'Relisez-le avant validation.'}`, 'ok');
      await creerDocument(r.contenu, r.titre_suggere, d, notes);
    };
    form.querySelector('#btn-vierge').onclick = async () => {
      const d = lire();
      if (!d.modele_id) return UI.toast('Choisissez un modèle', 'erreur');
      const m = modeles.find((x) => String(x.id) === d.modele_id);
      const a = acts.find((x) => String(x.id) === String(d.activite_id));
      const vals = {
        date_du_jour: new Date().toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' }), redacteur: Etat.user.nom,
        ...(a ? { titre_activite: a.titre, code_activite: a.code || '', composante: a.composante || '', lieu: a.lieu || 'Abidjan', date_activite: UI.date(a.date_debut || a.date_echeance), responsable: a.responsable_nom || '', objet: a.titre } : {}),
        ...Object.fromEntries(Object.entries(d.variables).filter(([, v]) => v)),
      };
      const contenu = m.contenu.replace(/\{\{\s*(\w+)\s*\}\}/g, (_, k) => vals[k] ?? `[${k.replace(/_/g, ' ')}]`);
      await creerDocument(contenu, m.nom + (a ? ' — ' + a.titre : ''), d);
    };
  },

  // ------------------------------------------------------------------ editeur
  async editeur(vue, id) {
    const doc = await Api.get('documents.php', { id });
    const ecrire = UI.peutEcrire();
    const ia = Etat.config.ia && ecrire;
    vue.innerHTML = `
      <div class="entete-vue no-print">
        <div style="flex:1;min-width:260px">
          <a href="#/documents" class="muted">← Documents</a>
          <input id="titre-doc" value="${UI.esc(doc.titre)}" ${ecrire ? '' : 'readonly'} style="font-size:1.2rem;font-weight:700;color:var(--bleu);border-color:transparent;padding-left:0">
          <span class="badge type">${UI.esc(UI.TYPES_DOC[doc.type] || doc.type)}</span>
          ${doc.activite_titre ? `<a href="javascript:void(0)" id="lien-act" class="muted">🗂️ ${UI.esc(doc.activite_titre)}</a>` : ''}
        </div>
        <div class="actions">
          <select id="statut-doc" style="width:auto;margin:0" ${ecrire ? '' : 'disabled'}>${UI.options(UI.STATUTS_DOC, doc.statut)}</select>
          ${ecrire ? '<button id="btn-save">💾 Enregistrer</button>' : ''}
          <button class="btn-sec" id="btn-word">⬇️ Word</button>
          <button class="btn-sec" id="btn-pdf">🖨️ PDF</button>
        </div>
      </div>
      <div class="bascule no-print">
        <button data-vue="lecture">📖 Lire</button>${ecrire ? '<button data-vue="edition">✏️ Modifier</button>' : ''}
      </div>
      <div class="alerte no-print a-completer" id="a-completer" hidden></div>
      <div class="carte no-print" id="zone-fichiers"></div>
      ${ecrire ? `<div class="barre-outils no-print">
        <b style="font-size:.85rem">🤖 IA sur la sélection (ou tout le texte) :</b>
        <button class="btn-ia btn-sm" data-ia="ameliorer">Améliorer</button>
        <button class="btn-ia btn-sm" data-ia="developper">Développer</button>
        <button class="btn-ia btn-sm" data-ia="resumer">Résumer</button>
        <button class="btn-ia btn-sm" data-ia="corriger">Corriger</button>
        <button class="btn-ia btn-sm" data-ia="formel">Ton officiel</button>
        <button class="btn-ia btn-sm" data-ia="puces">En puces</button>
        <button class="btn-ia btn-sm" data-ia="anglais">→ Anglais</button>
        <button class="btn-ia btn-sm" data-ia="libre">Consigne libre…</button>
        <span class="sepv"></span>
        <button class="btn-sec btn-sm" data-md="## ">Titre</button>
        <button class="btn-sec btn-sm" data-md="- ">Puce</button>
        <button class="btn-sec btn-sm" data-md="**">Gras</button>
        <button class="btn-sec btn-sm" data-md="table">Tableau</button>
        <span class="muted" id="etat-save" style="margin-left:auto;font-size:.8rem"></span>
      </div>` : ''}
      <div class="onglets onglets-edit no-print"><button data-mode="texte" class="actif">✏️ Texte</button><button data-mode="apercu">👁️ Aperçu</button></div>
      <div class="editeur mode-texte">
        <div class="zone-texte"><textarea id="texte" spellcheck="true" ${ecrire ? '' : 'readonly'} placeholder="Rédigez ici en Markdown (# Titre, ## Sous-titre, - puce, **gras**, | tableau |)…">${UI.esc(doc.contenu || '')}</textarea></div>
        <div class="apercu md" id="apercu"></div>
      </div>`;

    const ta = vue.querySelector('#texte');
    const apercu = vue.querySelector('#apercu');
    const etat = vue.querySelector('#etat-save');
    let modifie = false;
    const rendu = () => { apercu.innerHTML = MD.html(ta.value) || '<p class="muted">Aperçu du document…</p>'; };
    rendu();
    ta.addEventListener('input', () => { rendu(); modifie = true; if (etat) etat.textContent = 'Modifications non enregistrées'; });

    const enregistrer = async (silencieux) => {
      await Api.put(`documents.php?id=${id}`, { titre: vue.querySelector('#titre-doc').value, contenu: ta.value, statut: vue.querySelector('#statut-doc').value,
        ...(['valide', 'envoye'].includes(vue.querySelector('#statut-doc').value) && !doc.date_finalisation ? { date_finalisation: new Date().toISOString().slice(0, 10) } : {}) });
      modifie = false;
      if (etat) etat.textContent = `Enregistré à ${new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}`;
      if (!silencieux) UI.toast('Document enregistré', 'ok');
    };
    // Sauvegarde automatique toutes les 60 s si modifie
    const auto = setInterval(() => { if (!document.body.contains(ta)) return clearInterval(auto); if (modifie && ecrire) enregistrer(true).catch(() => {}); }, 60000);
    vue.querySelector('#btn-save')?.addEventListener('click', (e) => UI.occupe(e.currentTarget, () => enregistrer(false), 'Enregistrement…'));
    vue.querySelector('#statut-doc').onchange = () => { if (ecrire) enregistrer(false); };
    vue.querySelector('#titre-doc').onchange = () => { modifie = true; };
    ta.addEventListener('keydown', (e) => { if ((e.ctrlKey || e.metaKey) && e.key === 's') { e.preventDefault(); enregistrer(false); } });
    vue.querySelector('#lien-act')?.addEventListener('click', () => VueActivites.ouvrir(doc.activite_id));

    vue.querySelector('#btn-word').onclick = async () => { if (modifie && ecrire) await enregistrer(true); telecharger(`api/documents.php?action=export&id=${id}`); };
    vue.querySelector('#btn-pdf').onclick = () => {
      const w = window.open('', '_blank');
      w.document.write(`<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><title>${UI.esc(vue.querySelector('#titre-doc').value)}</title>
        <link rel="stylesheet" href="${new URL('css/style.css', location.href).href}"><style>body{background:#fff;padding:2cm}@page{margin:1.5cm}</style></head>
        <body><div class="md">${MD.html(ta.value)}</div><script>window.onload=()=>{window.print()}<\/script></body></html>`);
      w.document.close();
    };

    // Bascule lecture / modification : lecture par defaut des qu'il y a un contenu
    const basculer = (mode) => {
      vue.classList.toggle('en-lecture', mode === 'lecture');
      vue.querySelectorAll('.bascule [data-vue]').forEach((b) => b.classList.toggle('actif', b.dataset.vue === mode));
      rendu();
      // En lecture, pas de page blanche quand seul le fichier original existe
      apercu.hidden = mode === 'lecture' && !ta.value.trim() && doc.fichiers.length > 0;
    };
    vue.querySelectorAll('.bascule [data-vue]').forEach((b) => { b.onclick = () => basculer(b.dataset.vue); });
    basculer(!ecrire || (doc.contenu || '').trim() || doc.fichiers.length ? 'lecture' : 'edition');
    window.addEventListener('hashchange', () => vue.classList.remove('en-lecture'), { once: true });

    // Informations laissees « [À compléter] » par l'IA : compteur et navigation de l'une a l'autre
    const RE_VIDE = /\[(?:À|A|à) (?:compléter|préciser)[^\]]*\]/g;
    const bandeau = vue.querySelector('#a-completer');
    const majACompleter = () => {
      const n = (ta.value.match(RE_VIDE) || []).length;
      bandeau.hidden = n === 0;
      bandeau.innerHTML = `🔎 <b>${n} information${n > 1 ? 's' : ''} à compléter</b> avant de valider ce document.
        ${ecrire ? '<button class="btn-sec btn-sm" id="btn-suivant">Aller à la suivante →</button>' : ''}`;
      bandeau.querySelector('#btn-suivant')?.addEventListener('click', () => {
        basculer('edition');
        const texte = ta.value;
        RE_VIDE.lastIndex = 0;
        const depuis = ta.selectionEnd;
        let m = [...texte.matchAll(RE_VIDE)].find((x) => x.index >= depuis) || [...texte.matchAll(RE_VIDE)][0];
        if (!m) return;
        vue.querySelectorAll('.onglets-edit [data-mode="texte"]').forEach((b) => b.click());
        ta.focus();
        ta.setSelectionRange(m.index, m.index + m[0].length);
        const ligne = texte.slice(0, m.index).split('\n').length;
        ta.scrollTop = Math.max(0, ligne * parseFloat(getComputedStyle(ta).lineHeight || '20') - ta.clientHeight / 3);
      });
    };
    majACompleter();
    let minuteur;
    ta.addEventListener('input', () => { clearTimeout(minuteur); minuteur = setTimeout(majACompleter, 400); });

    this.fichiers(vue.querySelector('#zone-fichiers'), doc, ecrire, (texteExtrait) => {
      if (texteExtrait) this.editeur(vue, id);
    });

    vue.querySelectorAll('.onglets-edit [data-mode]').forEach((b) => {
      b.onclick = () => {
        vue.querySelectorAll('.onglets-edit button').forEach((x) => x.classList.toggle('actif', x === b));
        vue.querySelector('.editeur').className = `editeur mode-${b.dataset.mode}`;
      };
    });

    // Mise en forme rapide
    vue.querySelectorAll('[data-md]').forEach((b) => {
      b.onclick = () => {
        const [s, f] = [ta.selectionStart, ta.selectionEnd];
        const sel = ta.value.slice(s, f);
        let ins;
        if (b.dataset.md === '**') ins = `**${sel || 'texte en gras'}**`;
        else if (b.dataset.md === 'table') ins = '\n| Colonne 1 | Colonne 2 | Colonne 3 |\n|---|---|---|\n|  |  |  |\n';
        else ins = (sel || 'texte').split('\n').map((l) => b.dataset.md + l).join('\n');
        ta.setRangeText(ins, s, f, 'select'); ta.focus(); ta.dispatchEvent(new Event('input'));
      };
    });

    // Actions IA sur la selection (ou tout le document)
    vue.querySelectorAll('[data-ia]').forEach((b) => {
      b.onclick = async () => {
        if (!ia) return UI.toast("L'IA n'est pas configurée sur ce serveur.", 'erreur');
        let [s, f] = [ta.selectionStart, ta.selectionEnd];
        if (s === f) { s = 0; f = ta.value.length; }
        const texte = ta.value.slice(s, f);
        if (!texte.trim()) return UI.toast('Rien à traiter : sélectionnez un passage.', 'erreur');
        if (texte.length > 30000) return UI.toast('Sélection trop longue : traitez le document section par section.', 'erreur');
        const corps = { texte, mode: b.dataset.ia, contexte: vue.querySelector('#titre-doc').value };
        if (b.dataset.ia === 'libre') {
          corps.consigne = prompt('Que doit faire l\'IA sur ce passage ?\nEx. : « ajoute une section sur la participation des femmes », « rends plus diplomatique »');
          if (!corps.consigne) return;
          corps.mode = '';
        }
        const r = await UI.occupe(b, () => Api.ia('reecrire', corps), '…');
        this.comparer(texte, r.texte, () => {
          ta.focus(); ta.setSelectionRange(s, f); ta.setRangeText(r.texte, s, f, 'select');
          ta.dispatchEvent(new Event('input'));
        });
      };
    });

    window.onbeforeunload = () => (modifie ? true : undefined);
    window.addEventListener('hashchange', () => { window.onbeforeunload = null; }, { once: true });
  },

  /** Section « Fichier original » : consultation, telechargement, ajout de fichiers joints */
  fichiers(zone, doc, ecrire, apresAjout) {
    const url = (f) => `api/documents.php?action=fichier&fid=${f.id}`;
    const taille = (o) => (o > 1048576 ? `${(o / 1048576).toFixed(1)} Mo` : `${Math.max(1, Math.round(o / 1024))} Ko`);
    const icone = (m) => (m.includes('pdf') ? '📕' : m.includes('word') ? '📘' : m.includes('sheet') || m.includes('excel') ? '📗' : m.includes('presentation') ? '📙' : m.startsWith('image') ? '🖼️' : '📄');
    const lienOk = doc.lien && /^https?:\/\//i.test(doc.lien);
    const pdf = doc.fichiers.find((f) => f.mime === 'application/pdf');
    const vide = !(doc.contenu || '').trim();
    zone.innerHTML = `
      <div class="entete-vue" style="margin-bottom:.4rem"><h3 style="margin:0">📎 Fichier original</h3>
        ${ecrire ? `<label class="btn btn-sec btn-sm" style="margin:0">⬆️ Joindre un fichier<input type="file" id="input-fichier" hidden accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.txt,.csv"></label>` : ''}</div>
      ${doc.fichiers.map((f) => `<div class="item-doc">
          <span>${icone(f.mime)}</span><span class="t">${UI.esc(f.nom)} <span class="muted">— ${taille(f.taille)}</span></span>
          <a class="btn btn-sec btn-sm" href="${url(f)}" target="_blank" rel="noopener">${f.mime === 'application/pdf' || f.mime.startsWith('image') ? '👁️ Ouvrir' : '⬇️ Télécharger'}</a>
          ${ecrire ? `<button class="btn-lien btn-sm" data-suppr-fichier="${f.id}" title="Retirer ce fichier">🗑️</button>` : ''}
        </div>`).join('')}
      ${lienOk ? (doc.lien.includes('drive.google.com')
        ? `<div class="item-doc"><span>☁️</span><span class="t">Fichier dans Google Drive</span><a class="btn btn-sec btn-sm" href="${UI.esc(doc.lien)}" target="_blank" rel="noopener">Ouvrir dans Google Drive</a></div>`
        : `<div class="item-doc"><span>🔗</span><span class="t">Lien externe</span><a class="btn btn-sec btn-sm" href="${UI.esc(doc.lien)}" target="_blank" rel="noopener">Ouvrir le lien</a></div>`) : ''}
      ${!doc.fichiers.length && !lienOk ? `<p class="muted" style="margin:.3rem 0 0">${vide
        ? "Ce document est seulement référencé : son contenu n'est pas encore sur la plateforme. " + (ecrire ? "Joignez le fichier (Word, PDF…) : le texte d'un fichier Word sera extrait automatiquement pour être lu ici." : '')
        : 'Aucun fichier joint. Le texte ci-dessous est lisible directement.'}${doc.notes ? `<br><span style="font-size:.8rem">${UI.esc(doc.notes)}</span>` : ''}</p>` : ''}
      ${pdf && vide ? `<iframe src="${url(pdf)}" title="Aperçu PDF" style="width:100%;height:75vh;border:1px solid var(--bord);border-radius:8px;margin-top:.6rem"></iframe>` : ''}`;

    zone.querySelector('#input-fichier')?.addEventListener('change', async (e) => {
      const fichier = e.target.files[0];
      if (!fichier) return;
      if (fichier.size > 10 * 1024 * 1024) return UI.toast('Fichier trop volumineux (10 Mo maximum) : renseignez plutôt un lien Drive dans les propriétés du document.', 'erreur');
      const fd = new FormData(); fd.append('fichier', fichier);
      UI.toast('Envoi du fichier…');
      try {
        const r = await Api.post(`documents.php?action=joindre&id=${doc.id}`, fd);
        doc.fichiers = r.fichiers;
        UI.toast(r.texte_extrait ? 'Fichier joint — son texte est maintenant lisible sur la plateforme' : 'Fichier joint', 'ok');
        if (r.texte_extrait) apresAjout(true); else this.fichiers(zone, doc, ecrire, apresAjout);
      } catch (err) { UI.toast(err.message, 'erreur'); }
    });
    zone.querySelectorAll('[data-suppr-fichier]').forEach((b) => {
      b.onclick = async () => {
        if (!confirm('Retirer ce fichier du document ?')) return;
        await Api.del(`documents.php?action=supprimer_fichier&fid=${b.dataset.supprFichier}`);
        doc.fichiers = doc.fichiers.filter((f) => String(f.id) !== b.dataset.supprFichier);
        this.fichiers(zone, doc, ecrire, apresAjout);
      };
    });
  },

  /** Affiche la proposition de l'IA avant de l'appliquer */
  comparer(avant, apres, appliquer) {
    const m = UI.modale(`<h2>Proposition de l'IA</h2>
      <div class="grille-form">
        <div><h3>Texte actuel</h3><div class="apercu md" style="min-height:0;max-height:55vh">${MD.html(avant)}</div></div>
        <div><h3>Proposition</h3><div class="apercu md" style="min-height:0;max-height:55vh">${MD.html(apres)}</div></div>
      </div>
      <div class="actions"><button class="btn-sec" data-fermer2>Ignorer</button><button id="appliquer">✔ Remplacer par la proposition</button></div>`, { large: true });
    m.querySelector('[data-fermer2]').onclick = UI.fermerModale;
    m.querySelector('#appliquer').onclick = () => { appliquer(); UI.fermerModale(); UI.toast('Texte remplacé (pensez à enregistrer)'); };
  },
};
