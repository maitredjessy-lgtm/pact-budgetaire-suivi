# Plateforme de suivi, d'exécution et d'évaluation — PACT Budgétaire

Application web de l'ONG **Social Justice** pour piloter le projet **PACT BUDGÉTAIRE**
(Participation Active de la Société Civile pour la Transparence Budgétaire en Côte d'Ivoire),
financé par l'Union européenne et mis en œuvre avec IBP Sénégal.

## Fonctionnalités

| Module | Ce qu'il fait |
|---|---|
| 📊 **Tableau de bord** | Taux de réalisation, avancement par composante, retards, échéances à 7 jours, documents à produire |
| 🗂️ **Activités** | Plan de travail (39 activités réelles pré-chargées) : responsable, dates, lieu, statut, avancement, budget. Fiche détaillée avec **documents requis** et **tâches** |
| 📋 **Checklist des documents requis** | Pour chaque type d'activité (atelier, formation, réunion, plaidoyer…) : TDR, courriers d'invitation, liste de présence, rapport… avec échéances calculées. L'**IA peut proposer des éléments supplémentaires** |
| ⏰ **Échéances** | Retards / 7 jours / 30 jours pour activités, documents et tâches ; filtre par personne ; **relance par e-mail** en un clic ; **export vers Outlook / Google Agenda** (.ics avec rappel J-3) ; pastille d'alerte dans le menu |
| 👥 **Équipe & tâches** | Personnes imputées, charge de travail et retards de chacun, gestion des tâches, gestion des comptes d'accès (admin / éditeur / lecteur) |
| 📁 **Documents** | Registre des rapports, courriers, TDR, comptes rendus, notes, communiqués (133 documents réels du Drive pré-référencés) |
| ✍️ **Rédiger avec l'IA** | Choix d'un modèle + activité + vos notes → l'IA rédige le document complet. Éditeur avec aperçu, actions IA sur une sélection (améliorer, développer, résumer, corriger, ton officiel, traduire), **export Word (.docx)** et **PDF** |
| 🧩 **Modèles** | 12 modèles reconstruits depuis vos canevas (rapport d'activité PACT, Annexe VI UE intermédiaire/final, TDR, courriers, compte rendu, communiqué, note de plaidoyer, rapport de S&E…). **Import d'un .docx** existant comme nouveau modèle |
| 🎯 **Indicateurs & évaluation** | Cadre logique (impact, effets, produits), taux de réalisation, **analyse d'évaluation IA** (critères CAD-OCDE, risques, recommandations) enregistrable comme rapport |
| 🤖 **Assistant IA** | Conversation qui connaît l'état réel du projet (retards, responsables, documents à produire) |

## Architecture

- **Frontend** : HTML, CSS, JavaScript (sans framework) — `public/`
- **Backend** : PHP 8.3 (API JSON) — `public/api/` et `src/`
- **Base de données** : PostgreSQL (production) ou SQLite (local), créée et pré-remplie automatiquement au premier démarrage
- **IA** : appelée uniquement côté serveur (la clé n'est jamais visible dans le navigateur)
  - par défaut **Google Gemini** (offre gratuite)
  - ou tout **modèle open source** (Llama, Mistral, Qwen…) via Groq, OpenRouter ou un serveur Ollama
- **Hébergement** : Render (Docker)

```
Dockerfile, render.yaml     déploiement Render
database/schema.sql         schéma (SQLite / PostgreSQL)
database/seed_data/         données réelles : activités, documents, responsables, modèles, indicateurs
src/                        Database, Auth, AI, Contexte (données envoyées à l'IA), Docx (import/export Word), Checklist
public/api/                 activites, documents, taches, responsables, modeles, indicateurs, echeances, dashboard, ia, auth, utilisateurs
public/js/                  core.js (API, UI, Markdown), app.js (routeur), vues/*.js (un fichier par écran)
```

## Démarrage en local

PHP 8.1+ requis (extensions `pdo_sqlite`, `curl` ; `zip` pour l'export Word).

```bash
cd site-suivi-pact
php -d extension=zip -S localhost:8010 -t public
```

Ouvrez http://localhost:8010 : au premier lancement, l'écran demande de **créer le compte administrateur**.
Pour activer l'IA en local : `set AI_API_KEY=votre_cle` (Windows) avant la commande `php`.

## Déploiement sur Render

1. **Base de données persistante** (indispensable : sur Render gratuit, le disque est effacé à chaque redémarrage)
   - Créez une base PostgreSQL gratuite sur [neon.tech](https://neon.tech) (ou Supabase)
   - Copiez l'URL de connexion `postgresql://…?sslmode=require`
2. **Clé IA gratuite** : https://aistudio.google.com/apikey → « Create API key »
3. Sur [dashboard.render.com](https://dashboard.render.com) : **New → Blueprint**, choisissez ce dépôt GitHub.
   Render lit `render.yaml` et demande les valeurs de `AI_API_KEY` et `DATABASE_URL`.
4. Une fois déployé, ouvrez l'URL `https://….onrender.com` et **créez immédiatement le compte administrateur**
   (le premier compte créé devient administrateur), puis créez les comptes de l'équipe dans *Équipe & tâches → Comptes d'accès*.

> Le plan gratuit de Render met le service en veille après 15 min d'inactivité : le premier chargement suivant prend ~1 minute.

### Utiliser un modèle open source au lieu de Gemini

Il suffit de coller une clé **Groq** (gratuite, modèle open source Llama 3.3 70B : https://console.groq.com/keys)
dans `AI_API_KEY` : le site reconnaît le préfixe `gsk_` et bascule tout seul. Même chose pour une clé
**OpenRouter** (`sk-or-…`). Le bouton *🩺 Tester la connexion IA* (Assistant IA, administrateur) vérifie la configuration.

Pour un serveur Ollama auto-hébergé : `AI_PROVIDER=openai`, `AI_BASE_URL=http://serveur:11434/v1`, `AI_MODEL=llama3.1`.

## Sécurité

- Mots de passe hachés (`password_hash`), sessions en cookie `HttpOnly` / `SameSite=Lax` / `Secure` en HTTPS
- Rôles : **admin** (tout + comptes), **éditeur** (modifie et rédige), **lecteur** (consultation)
- Les écritures exigent un en-tête applicatif (protection CSRF) ; `src/`, `database/` et `data/` ne sont pas exposés
- Les appels IA sont journalisés (table `ai_log`)
