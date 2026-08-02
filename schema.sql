-- ================================================================
-- GSB SINIYAT — Schéma PostgreSQL complet
-- ================================================================

-- Sequences globales (jamais réinitialisées)
CREATE SEQUENCE IF NOT EXISTS seq_matricule START 1;
CREATE SEQUENCE IF NOT EXISTS seq_numero_recu START 1;

-- Utilisateurs
CREATE TABLE IF NOT EXISTS utilisateurs (
    id               SERIAL PRIMARY KEY,
    nom              VARCHAR(100) NOT NULL,
    prenom           VARCHAR(100) NOT NULL,
    login            VARCHAR(50)  UNIQUE NOT NULL,
    mot_de_passe     TEXT         NOT NULL,
    role             VARCHAR(20)  NOT NULL DEFAULT 'secretaire' CHECK (role IN ('admin','secretaire')),
    actif            BOOLEAN      NOT NULL DEFAULT TRUE,
    derniere_connexion TIMESTAMP,
    created_by       INTEGER REFERENCES utilisateurs(id),
    created_at       TIMESTAMP    NOT NULL DEFAULT NOW()
);

-- Historique connexions
CREATE TABLE IF NOT EXISTS historique_connexions (
    id              SERIAL PRIMARY KEY,
    utilisateur_id  INTEGER REFERENCES utilisateurs(id),
    login_tente     VARCHAR(100),
    succes          BOOLEAN  NOT NULL DEFAULT FALSE,
    ip_address      VARCHAR(45),
    user_agent      TEXT,
    date_heure      TIMESTAMP NOT NULL DEFAULT NOW()
);

-- Années scolaires
CREATE TABLE IF NOT EXISTS annees_scolaires (
    id          SERIAL PRIMARY KEY,
    libelle     VARCHAR(20) UNIQUE NOT NULL,
    statut      VARCHAR(20) NOT NULL DEFAULT 'active' CHECK (statut IN ('active','cloturee')),
    created_at  TIMESTAMP NOT NULL DEFAULT NOW()
);

-- Niveaux / Classes  (section: francophone | anglophone)
CREATE TABLE IF NOT EXISTS niveaux (
    id       SERIAL PRIMARY KEY,
    nom_fr   VARCHAR(100) NOT NULL,
    nom_en   VARCHAR(100) NOT NULL,
    section  VARCHAR(20)  NOT NULL DEFAULT 'francophone' CHECK (section IN ('francophone','anglophone')),
    ordre    INTEGER      NOT NULL DEFAULT 0,
    actif    BOOLEAN      NOT NULL DEFAULT TRUE,
    CONSTRAINT niveaux_nom_fr_section_unique UNIQUE (nom_fr, section)
);

-- Élèves
CREATE TABLE IF NOT EXISTS eleves (
    id               SERIAL PRIMARY KEY,
    matricule        VARCHAR(30) UNIQUE,
    nom              VARCHAR(100)  NOT NULL,
    prenoms          VARCHAR(200)  NOT NULL,
    sexe             CHAR(1)       NOT NULL DEFAULT 'M' CHECK (sexe IN ('M','F')),
    date_naissance   DATE,
    lieu_naissance   VARCHAR(200),
    quartier         VARCHAR(200),
    adresse          TEXT,
    nom_pere         VARCHAR(200),
    tel_pere         VARCHAR(30),
    nom_mere         VARCHAR(200),
    tel_mere         VARCHAR(30),
    nom_tuteur       VARCHAR(200),
    tel_tuteur       VARCHAR(30),
    contact_urgence  VARCHAR(200),
    annee_id         INTEGER NOT NULL REFERENCES annees_scolaires(id),
    niveau_id        INTEGER NOT NULL REFERENCES niveaux(id),
    statut_eleve     VARCHAR(20) DEFAULT 'nouveau',
    famille_id       INTEGER,
    actif            BOOLEAN     NOT NULL DEFAULT TRUE,
    date_inscription TIMESTAMP   NOT NULL DEFAULT NOW(),
    inscrit_par      INTEGER REFERENCES utilisateurs(id)
);

-- Documents administratifs
CREATE TABLE IF NOT EXISTS documents_eleve (
    id                    SERIAL PRIMARY KEY,
    eleve_id              INTEGER UNIQUE NOT NULL REFERENCES eleves(id) ON DELETE CASCADE,
    photos_identite       BOOLEAN DEFAULT FALSE,
    acte_naissance        BOOLEAN DEFAULT FALSE,
    carnet_vaccination    BOOLEAN DEFAULT FALSE,
    certificat_transfert  BOOLEAN DEFAULT FALSE,
    livret_scolaire       BOOLEAN DEFAULT FALSE,
    certificat_medical    BOOLEAN DEFAULT FALSE,
    date_maj              TIMESTAMP NOT NULL DEFAULT NOW()
);

-- Grille des frais
CREATE TABLE IF NOT EXISTS grille_frais (
    id                SERIAL PRIMARY KEY,
    annee_id          INTEGER NOT NULL REFERENCES annees_scolaires(id),
    niveau_id         INTEGER NOT NULL REFERENCES niveaux(id),
    frais_inscription DECIMAL(12,2) NOT NULL DEFAULT 0,
    UNIQUE(annee_id, niveau_id)
);

-- Tranches de paiement
CREATE TABLE IF NOT EXISTS tranches (
    id                   SERIAL PRIMARY KEY,
    grille_id            INTEGER NOT NULL REFERENCES grille_frais(id) ON DELETE CASCADE,
    numero               INTEGER NOT NULL,
    libelle_fr           VARCHAR(100) NOT NULL,
    libelle_en           VARCHAR(100) NOT NULL,
    montant              DECIMAL(12,2) NOT NULL,
    echeance_indicative  VARCHAR(50),
    UNIQUE(grille_id, numero)
);

-- Frais annexes (config)
CREATE TABLE IF NOT EXISTS frais_annexes_config (
    id          SERIAL PRIMARY KEY,
    libelle_fr  VARCHAR(100) NOT NULL,
    libelle_en  VARCHAR(100) NOT NULL,
    montant     DECIMAL(12,2) NOT NULL,
    actif       BOOLEAN NOT NULL DEFAULT TRUE
);

-- Frais annexes par élève
CREATE TABLE IF NOT EXISTS frais_annexes_eleve (
    id              SERIAL PRIMARY KEY,
    eleve_id        INTEGER NOT NULL REFERENCES eleves(id) ON DELETE CASCADE,
    frais_annexe_id INTEGER NOT NULL REFERENCES frais_annexes_config(id),
    active          BOOLEAN NOT NULL DEFAULT TRUE,
    UNIQUE(eleve_id, frais_annexe_id)
);

-- Paiements
CREATE TABLE IF NOT EXISTS paiements (
    id                  SERIAL PRIMARY KEY,
    eleve_id            INTEGER NOT NULL REFERENCES eleves(id),
    tranche_id          INTEGER REFERENCES tranches(id),
    type_paiement       VARCHAR(30) NOT NULL DEFAULT 'tranche',
    montant             DECIMAL(12,2) NOT NULL,
    mode_paiement       VARCHAR(20) NOT NULL DEFAULT 'especes' CHECK (mode_paiement IN ('especes','virement')),
    nom_banque          VARCHAR(200),
    reference_bancaire  VARCHAR(100),
    date_depot          DATE,
    date_paiement       TIMESTAMP NOT NULL DEFAULT NOW(),
    encaisse_par        INTEGER REFERENCES utilisateurs(id),
    notes               TEXT,
    annule              BOOLEAN NOT NULL DEFAULT FALSE,
    created_at          TIMESTAMP NOT NULL DEFAULT NOW()
);

-- Reçus (numéro global jamais réinitialisé)
CREATE TABLE IF NOT EXISTS recus (
    id              SERIAL PRIMARY KEY,
    numero_recu     INTEGER UNIQUE NOT NULL,
    paiement_id     INTEGER UNIQUE NOT NULL REFERENCES paiements(id),
    eleve_id        INTEGER NOT NULL REFERENCES eleves(id),
    genere_par      INTEGER REFERENCES utilisateurs(id),
    date_generation TIMESTAMP NOT NULL DEFAULT NOW(),
    duplicata       BOOLEAN NOT NULL DEFAULT FALSE
);

-- Paramètres configurables (réductions, etc.)
CREATE TABLE IF NOT EXISTS parametres (
    cle        VARCHAR(100) PRIMARY KEY,
    valeur     TEXT         NOT NULL,
    updated_at TIMESTAMP    NOT NULL DEFAULT NOW()
);

INSERT INTO parametres (cle, valeur) VALUES
  ('reduction_paiement_complet', '2'),
  ('reduction_fratrie',          '2')
ON CONFLICT (cle) DO NOTHING;

-- Journal d'audit
CREATE TABLE IF NOT EXISTS journal_audit (
    id             SERIAL PRIMARY KEY,
    utilisateur_id INTEGER REFERENCES utilisateurs(id),
    action         VARCHAR(100) NOT NULL,
    entite         VARCHAR(50),
    entite_id      INTEGER,
    details        TEXT,
    ip_address     VARCHAR(45),
    created_at     TIMESTAMP NOT NULL DEFAULT NOW()
);

-- ================================================================
-- Données initiales
-- ================================================================

-- Compte admin (login: admin / mot de passe: password)
INSERT INTO utilisateurs (nom, prenom, login, mot_de_passe, role, actif)
VALUES ('Admin', 'Super', 'admin', '$2y$12$3J2bjJ84EyZaR.B8vD8zIusH2HxZNtmK.S9jkUNhc6ZkxH.rw12sC', 'admin', TRUE)
ON CONFLICT (login) DO NOTHING;

-- Année scolaire active
INSERT INTO annees_scolaires (libelle, statut)
VALUES ('2026-2027', 'active')
ON CONFLICT (libelle) DO NOTHING;

-- ---- Niveaux Francophones ----
INSERT INTO niveaux (nom_fr, nom_en, section, ordre, actif) VALUES
('Pré-Maternelle', 'Pre-School (FR)',  'francophone', 1, TRUE),
('Maternelle',     'Kindergarten (FR)','francophone', 2, TRUE),
('SIL',            'SIL',              'francophone', 3, TRUE),
('CP',             'CP',               'francophone', 4, TRUE),
('CE1',            'CE1',              'francophone', 5, TRUE),
('CE2',            'CE2',              'francophone', 6, TRUE),
('CM1',            'CM1',              'francophone', 7, TRUE),
('CM2',            'CM2',              'francophone', 8, TRUE)
ON CONFLICT (nom_fr, section) DO NOTHING;

-- ---- Niveaux Anglophones ----
INSERT INTO niveaux (nom_fr, nom_en, section, ordre, actif) VALUES
('Pre-Nursery', 'Pre-Nursery', 'anglophone', 11, TRUE),
('Nursery',     'Nursery',     'anglophone', 12, TRUE),
('Class 1',     'Class 1',     'anglophone', 13, TRUE),
('Class 2',     'Class 2',     'anglophone', 14, TRUE),
('Class 3',     'Class 3',     'anglophone', 15, TRUE),
('Class 4',     'Class 4',     'anglophone', 16, TRUE),
('Class 5',     'Class 5',     'anglophone', 17, TRUE),
('Class 6',     'Class 6',     'anglophone', 18, TRUE)
ON CONFLICT (nom_fr, section) DO NOTHING;
