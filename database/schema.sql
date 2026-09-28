-- Schema commun SQLite / PostgreSQL.
-- {PK} est remplace par "INTEGER PRIMARY KEY AUTOINCREMENT" (SQLite) ou "SERIAL PRIMARY KEY" (PostgreSQL).

CREATE TABLE IF NOT EXISTS users (
  id {PK},
  email TEXT UNIQUE NOT NULL,
  password_hash TEXT NOT NULL,
  nom TEXT NOT NULL,
  role TEXT NOT NULL DEFAULT 'editeur',          -- admin | editeur | lecteur
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS responsables (
  id {PK},
  nom TEXT NOT NULL,
  email TEXT,
  telephone TEXT,
  fonction TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS activites (
  id {PK},
  code TEXT,
  titre TEXT NOT NULL,
  description TEXT,
  composante TEXT,
  type_activite TEXT DEFAULT 'atelier',          -- atelier | formation | reunion | plaidoyer | communication | etude | autre
  responsable_id INTEGER REFERENCES responsables(id) ON DELETE SET NULL,
  lieu TEXT,
  date_debut TEXT,
  date_echeance TEXT,
  statut TEXT NOT NULL DEFAULT 'a_faire',       -- a_faire | en_cours | termine | reporte | annule
  priorite TEXT NOT NULL DEFAULT 'normale',     -- basse | normale | haute
  avancement INTEGER NOT NULL DEFAULT 0,        -- 0..100
  budget_prevu REAL,
  budget_depense REAL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS modeles (
  id {PK},
  type TEXT NOT NULL,                            -- rapport | courrier | tdr | compte_rendu | note | communique | autre
  nom TEXT NOT NULL,
  description TEXT,
  consignes TEXT,                                -- consignes de redaction donnees a l'IA
  contenu TEXT NOT NULL,                         -- structure Markdown avec {{variables}}
  source TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS documents (
  id {PK},
  type TEXT NOT NULL,
  titre TEXT NOT NULL,
  activite_id INTEGER REFERENCES activites(id) ON DELETE SET NULL,
  responsable_id INTEGER REFERENCES responsables(id) ON DELETE SET NULL,
  modele_id INTEGER REFERENCES modeles(id) ON DELETE SET NULL,
  statut TEXT NOT NULL DEFAULT 'a_faire',       -- a_faire | brouillon | en_revue | valide | envoye
  date_echeance TEXT,
  date_finalisation TEXT,
  lien TEXT,
  notes TEXT,
  contenu TEXT,                                  -- contenu redige dans la plateforme (Markdown)
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS taches (
  id {PK},
  activite_id INTEGER REFERENCES activites(id) ON DELETE CASCADE,
  titre TEXT NOT NULL,
  responsable_id INTEGER REFERENCES responsables(id) ON DELETE SET NULL,
  echeance TEXT,
  statut TEXT NOT NULL DEFAULT 'a_faire',       -- a_faire | en_cours | termine
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS indicateurs (
  id {PK},
  code TEXT,
  niveau TEXT NOT NULL DEFAULT 'produit',       -- impact | effet | produit
  libelle TEXT NOT NULL,
  unite TEXT,
  valeur_reference REAL,
  cible REAL,
  valeur_actuelle REAL,
  source_verification TEXT,
  commentaire TEXT,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Fichiers originaux joints aux documents (stockes en base : le disque Render gratuit est ephemere)
CREATE TABLE IF NOT EXISTS fichiers (
  id {PK},
  document_id INTEGER NOT NULL REFERENCES documents(id) ON DELETE CASCADE,
  nom TEXT NOT NULL,
  mime TEXT NOT NULL,
  taille INTEGER NOT NULL,
  donnees TEXT NOT NULL,                         -- contenu encode en base64
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ===== Suivi financier =====
-- Lignes du budget UE (Annexe III), montants en EUR
CREATE TABLE IF NOT EXISTS budget_lignes (
  id {PK},
  cle TEXT UNIQUE NOT NULL,                      -- identifiant stable pour les reimports
  rubrique TEXT NOT NULL,                        -- 1 a 6 (couts directs), 8 (couts indirects)
  rubrique_libelle TEXT,
  groupe TEXT,                                   -- sous-section (ex. « 1.1.1.2 Atelier de presentation… »)
  code TEXT,
  libelle TEXT NOT NULL,
  unite TEXT,
  quantite REAL,
  cout_unitaire REAL,
  montant REAL NOT NULL DEFAULT 0,               -- total 3 ans
  montant_a1 REAL NOT NULL DEFAULT 0,
  montant_a2 REAL NOT NULL DEFAULT 0,
  montant_a3 REAL NOT NULL DEFAULT 0,
  partenaire TEXT,                               -- IBP | SJ
  activite_id INTEGER REFERENCES activites(id) ON DELETE SET NULL,
  ordre INTEGER NOT NULL DEFAULT 0,
  obsolete INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS depenses (
  id {PK},
  ligne_id INTEGER REFERENCES budget_lignes(id) ON DELETE SET NULL,
  activite_id INTEGER REFERENCES activites(id) ON DELETE SET NULL,
  date_depense TEXT NOT NULL,
  libelle TEXT NOT NULL,
  montant_eur REAL NOT NULL,
  montant_fcfa REAL,
  fournisseur TEXT,
  piece TEXT,                                    -- n° de piece comptable / facture
  mode_paiement TEXT,
  statut TEXT NOT NULL DEFAULT 'payee',          -- engagee | payee | justifiee
  partenaire TEXT,
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS pieces_justificatives (
  id {PK},
  depense_id INTEGER NOT NULL REFERENCES depenses(id) ON DELETE CASCADE,
  nom TEXT NOT NULL,
  mime TEXT NOT NULL,
  taille INTEGER NOT NULL,
  donnees TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Fonds recus (tranches UE, Hewlett…)
CREATE TABLE IF NOT EXISTS financements (
  id {PK},
  date_reception TEXT NOT NULL,
  source TEXT NOT NULL,
  libelle TEXT,
  montant_eur REAL NOT NULL,
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Reglages persistants (dossier Google Drive, derniere synchronisation…)
CREATE TABLE IF NOT EXISTS parametres (
  cle TEXT PRIMARY KEY,
  valeur TEXT
);

CREATE TABLE IF NOT EXISTS ai_log (
  id {PK},
  user_id INTEGER,
  action TEXT,
  question TEXT,
  reponse TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_activites_statut ON activites(statut);
CREATE INDEX IF NOT EXISTS idx_activites_echeance ON activites(date_echeance);
CREATE INDEX IF NOT EXISTS idx_documents_activite ON documents(activite_id);
CREATE INDEX IF NOT EXISTS idx_documents_echeance ON documents(date_echeance);
CREATE INDEX IF NOT EXISTS idx_taches_activite ON taches(activite_id);
CREATE INDEX IF NOT EXISTS idx_fichiers_document ON fichiers(document_id);
CREATE INDEX IF NOT EXISTS idx_depenses_ligne ON depenses(ligne_id);
CREATE INDEX IF NOT EXISTS idx_depenses_date ON depenses(date_depense);
CREATE INDEX IF NOT EXISTS idx_budget_activite ON budget_lignes(activite_id);
