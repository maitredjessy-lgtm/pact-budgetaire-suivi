const VueAssistant = {
  messages: [],

  async afficher(vue) {
    const suggestions = [
      'Quelles activités sont en retard et qui en est responsable ?',
      'Quelles sont les priorités des 30 prochains jours ?',
      'Prépare l\'ordre du jour de la prochaine réunion du COPIL',
      'Quels documents dois-je préparer pour les sessions RTEB avec la DGBF ?',
      'Fais un point d\'avancement du Résultat 3 (Cour des Comptes, Assemblée nationale)',
      'Propose un plan de rattrapage pour les activités non réalisées',
    ];
    vue.innerHTML = `
      <div class="entete-vue"><div><h1>🤖 Assistant IA du projet</h1>
        <p class="muted">Posez vos questions sur l'avancement, les échéances, les responsables ou demandez de l'aide pour préparer une activité. L'assistant connaît les données actuelles de la plateforme.
        ${Etat.config.ia_modele ? `<br>Modèle : ${UI.esc(Etat.config.ia_modele)}` : ''}</p></div>
        <div class="actions"><button class="btn-sec btn-sm" id="btn-vider">Nouvelle conversation</button></div></div>
      ${Etat.config.ia ? '' : '<div class="alerte">L\'IA n\'est pas configurée : un administrateur doit définir la variable AI_API_KEY sur le serveur (voir README).</div>'}
      <div class="carte chat">
        <div class="fil" id="fil"></div>
        <div class="suggestions" id="sugg">${suggestions.map((s) => `<button type="button">${UI.esc(s)}</button>`).join('')}</div>
        <form class="saisie-chat" id="form-chat">
          <textarea id="question" placeholder="Votre question… (Entrée pour envoyer, Maj+Entrée pour aller à la ligne)" required></textarea>
          <button type="submit" class="btn-ia">Envoyer</button>
        </form>
      </div>`;
    const fil = vue.querySelector('#fil');
    const dessiner = () => {
      fil.innerHTML = this.messages.length ? this.messages.map((m, i) => (m.role === 'user'
        ? `<div class="bulle user">${UI.esc(m.content)}</div>`
        : `<div class="bulle ia"><div class="md">${MD.html(m.content)}</div>
            ${UI.peutEcrire() ? `<div class="actions" style="justify-content:flex-start;margin-top:.3rem"><button class="btn-lien btn-sm" data-garder="${i}">💾 Enregistrer comme document</button><button class="btn-lien btn-sm" data-copier="${i}">📋 Copier</button></div>` : ''}</div>`)).join('')
        : '<div class="vide-etat">Bonjour 👋 Comment puis-je vous aider sur le projet PACT Budgétaire ?</div>';
      fil.scrollTop = fil.scrollHeight;
      vue.querySelector('#sugg').hidden = this.messages.length > 0;
      fil.querySelectorAll('[data-copier]').forEach((b) => { b.onclick = () => navigator.clipboard.writeText(this.messages[+b.dataset.copier].content).then(() => UI.toast('Copié')); });
      fil.querySelectorAll('[data-garder]').forEach((b) => {
        b.onclick = async () => {
          const i = +b.dataset.garder;
          const titre = prompt('Titre du document :', (this.messages[i - 1]?.content || 'Note').slice(0, 90));
          if (!titre) return;
          const doc = await Api.post('documents.php', { type: 'note', titre, contenu: this.messages[i].content, statut: 'brouillon' });
          location.hash = `#/editeur/${doc.id}`;
        };
      });
    };
    dessiner();

    const envoyer = async (texte) => {
      if (!texte.trim()) return;
      this.messages.push({ role: 'user', content: texte.trim() });
      dessiner();
      const attente = document.createElement('div');
      attente.className = 'bulle ia'; attente.innerHTML = '<span class="spinner fonce"></span> Réflexion…';
      fil.appendChild(attente); fil.scrollTop = fil.scrollHeight;
      try {
        const r = await Api.ia('chat', { messages: this.messages });
        this.messages.push({ role: 'assistant', content: r.reponse });
      } catch (e) {
        this.messages.pop();
        UI.toast(e.message, 'erreur');
        vue.querySelector('#question').value = texte;
      }
      dessiner();
    };
    const form = vue.querySelector('#form-chat');
    const q = vue.querySelector('#question');
    form.onsubmit = (e) => { e.preventDefault(); const t = q.value; q.value = ''; envoyer(t); };
    q.onkeydown = (e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); form.requestSubmit(); } };
    vue.querySelectorAll('#sugg button').forEach((b) => { b.onclick = () => envoyer(b.textContent); });
    vue.querySelector('#btn-vider').onclick = () => { this.messages = []; dessiner(); };
  },
};
