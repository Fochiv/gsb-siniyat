-- ================================================================
-- GSB SINIYAT — Schéma MySQL (Aeonfree / Hostinger)
-- Importer via PHPMyAdmin sur la base mseet_42573994_gsbsiniyat
-- ================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Séquences globales (remplace les SEQUENCE PostgreSQL)
CREATE TABLE IF NOT EXISTS sequences (
    name_seq  VARCHAR(50) NOT NULL PRIMARY KEY,
    value     BIGINT      NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO sequences (name_seq, value) VALUES ('matricule', 0), ('numero_recu', 0);

-- Utilisateurs
CREATE TABLE IF NOT EXISTS utilisateurs (
    id                  INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nom                 VARCHAR(100) NOT NULL,
    prenom              VARCHAR(100) NOT NULL,
    login               VARCHAR(50)  NOT NULL UNIQUE,
    mot_de_passe        TEXT         NOT NULL,
    role                VARCHAR(20)  NOT NULL DEFAULT 'secretaire',
    actif               TINYINT(1)   NOT NULL DEFAULT 1,
    derniere_connexion  DATETIME,
    created_by          INT,
    created_at          DATETIME     NOT NULL DEFAULT NOW()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Historique connexions
CREATE TABLE IF NOT EXISTS historique_connexions (
    id              INT         NOT NULL AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id  INT,
    login_tente     VARCHAR(100),
    succes          TINYINT(1)  NOT NULL DEFAULT 0,
    ip_address      VARCHAR(45),
    user_agent      TEXT,
    date_heure      DATETIME    NOT NULL DEFAULT NOW()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Années scolaires
CREATE TABLE IF NOT EXISTS annees_scolaires (
    id          INT         NOT NULL AUTO_INCREMENT PRIMARY KEY,
    libelle     VARCHAR(20) NOT NULL UNIQUE,
    statut      VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at  DATETIME    NOT NULL DEFAULT NOW()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Niveaux / Classes
CREATE TABLE IF NOT EXISTS niveaux (
    id       INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nom_fr   VARCHAR(100) NOT NULL,
    nom_en   VARCHAR(100) NOT NULL,
    section  VARCHAR(20)  NOT NULL DEFAULT 'francophone',
    ordre    INT          NOT NULL DEFAULT 0,
    actif    TINYINT(1)   NOT NULL DEFAULT 1,
    UNIQUE KEY niveaux_nom_fr_section_unique (nom_fr, section)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Élèves
CREATE TABLE IF NOT EXISTS eleves (
    id               INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    matricule        VARCHAR(30)  UNIQUE,
    nom              VARCHAR(100) NOT NULL,
    prenoms          VARCHAR(200) NOT NULL,
    sexe             CHAR(1)      NOT NULL DEFAULT 'M',
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
    annee_id         INT          NOT NULL,
    niveau_id        INT          NOT NULL,
    statut_eleve     VARCHAR(20)  DEFAULT 'nouveau',
    famille_id       INT,
    actif            TINYINT(1)   NOT NULL DEFAULT 1,
    date_inscription DATETIME     NOT NULL DEFAULT NOW(),
    inscrit_par      INT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Documents administratifs
CREATE TABLE IF NOT EXISTS documents_eleve (
    id                    INT        NOT NULL AUTO_INCREMENT PRIMARY KEY,
    eleve_id              INT        NOT NULL UNIQUE,
    photos_identite       TINYINT(1) DEFAULT 0,
    acte_naissance        TINYINT(1) DEFAULT 0,
    carnet_vaccination    TINYINT(1) DEFAULT 0,
    certificat_transfert  TINYINT(1) DEFAULT 0,
    livret_scolaire       TINYINT(1) DEFAULT 0,
    certificat_medical    TINYINT(1) DEFAULT 0,
    date_maj              DATETIME   NOT NULL DEFAULT NOW()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Grille des frais
CREATE TABLE IF NOT EXISTS grille_frais (
    id                INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    annee_id          INT          NOT NULL,
    niveau_id         INT          NOT NULL,
    frais_inscription DECIMAL(12,2) NOT NULL DEFAULT 0,
    UNIQUE KEY gf_annee_niveau (annee_id, niveau_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tranches de paiement
CREATE TABLE IF NOT EXISTS tranches (
    id                   INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    grille_id            INT          NOT NULL,
    numero               INT          NOT NULL,
    libelle_fr           VARCHAR(100) NOT NULL,
    libelle_en           VARCHAR(100) NOT NULL,
    montant              DECIMAL(12,2) NOT NULL,
    echeance_indicative  VARCHAR(50),
    UNIQUE KEY tranches_grille_numero (grille_id, numero)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Frais annexes (config)
CREATE TABLE IF NOT EXISTS frais_annexes_config (
    id          INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    libelle_fr  VARCHAR(100) NOT NULL,
    libelle_en  VARCHAR(100) NOT NULL,
    montant     DECIMAL(12,2) NOT NULL,
    actif       TINYINT(1)   NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Frais annexes par élève
CREATE TABLE IF NOT EXISTS frais_annexes_eleve (
    id              INT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
    eleve_id        INT       NOT NULL,
    frais_annexe_id INT       NOT NULL,
    active          TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY fae_eleve_annexe (eleve_id, frais_annexe_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Paiements
CREATE TABLE IF NOT EXISTS paiements (
    id                  INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    eleve_id            INT          NOT NULL,
    tranche_id          INT,
    type_paiement       VARCHAR(30)  NOT NULL DEFAULT 'tranche',
    montant             DECIMAL(12,2) NOT NULL,
    mode_paiement       VARCHAR(20)  NOT NULL DEFAULT 'especes',
    nom_banque          VARCHAR(200),
    reference_bancaire  VARCHAR(100),
    date_depot          DATE,
    date_paiement       DATETIME     NOT NULL DEFAULT NOW(),
    encaisse_par        INT,
    notes               TEXT,
    annule              TINYINT(1)   NOT NULL DEFAULT 0,
    created_at          DATETIME     NOT NULL DEFAULT NOW()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Reçus (numéro global jamais réinitialisé)
CREATE TABLE IF NOT EXISTS recus (
    id              INT      NOT NULL AUTO_INCREMENT PRIMARY KEY,
    numero_recu     INT      NOT NULL UNIQUE,
    paiement_id     INT      NOT NULL UNIQUE,
    eleve_id        INT      NOT NULL,
    genere_par      INT,
    date_generation DATETIME NOT NULL DEFAULT NOW(),
    duplicata       TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Paramètres configurables
CREATE TABLE IF NOT EXISTS parametres (
    cle        VARCHAR(100) NOT NULL PRIMARY KEY,
    valeur     TEXT         NOT NULL,
    updated_at DATETIME     NOT NULL DEFAULT NOW()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO parametres (cle, valeur) VALUES
  ('reduction_paiement_complet', '2'),
  ('reduction_fratrie',          '2')
ON DUPLICATE KEY UPDATE cle=cle;

-- Journal d'audit
CREATE TABLE IF NOT EXISTS journal_audit (
    id             INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    utilisateur_id INT,
    action         VARCHAR(100) NOT NULL,
    entite         VARCHAR(50),
    entite_id      INT,
    details        TEXT,
    ip_address     VARCHAR(45),
    created_at     DATETIME     NOT NULL DEFAULT NOW()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- ================================================================
-- Données initiales
-- ================================================================

-- Compte admin  (login: GSB-Siniyat  /  mot de passe: Siniyat@2026)
INSERT IGNORE INTO utilisateurs (nom, prenom, login, mot_de_passe, role, actif)
VALUES ('Admin', 'GSB', 'GSB-Siniyat',
        '$2y$10$LplmfCFUnChAL3ZBCed6jOBqvoeTHTE38sseX.7uZ3UXyCR7Lo7hW', 'admin', 1);

-- Année scolaire active
INSERT IGNORE INTO annees_scolaires (libelle, statut) VALUES ('2026-2027', 'active');

-- Niveaux Francophones
INSERT IGNORE INTO niveaux (nom_fr, nom_en, section, ordre, actif) VALUES
('Pré-Maternelle', 'Pre-School (FR)',   'francophone', 1, 1),
('Maternelle',     'Kindergarten (FR)', 'francophone', 2, 1),
('SIL',            'SIL',               'francophone', 3, 1),
('CP',             'CP',                'francophone', 4, 1),
('CE1',            'CE1',               'francophone', 5, 1),
('CE2',            'CE2',               'francophone', 6, 1),
('CM1',            'CM1',               'francophone', 7, 1),
('CM2',            'CM2',               'francophone', 8, 1);

-- Niveaux Anglophones
INSERT IGNORE INTO niveaux (nom_fr, nom_en, section, ordre, actif) VALUES
('Pre-Nursery', 'Pre-Nursery', 'anglophone', 11, 1),
('Nursery',     'Nursery',     'anglophone', 12, 1),
('Class 1',     'Class 1',     'anglophone', 13, 1),
('Class 2',     'Class 2',     'anglophone', 14, 1),
('Class 3',     'Class 3',     'anglophone', 15, 1),
('Class 4',     'Class 4',     'anglophone', 16, 1),
('Class 5',     'Class 5',     'anglophone', 17, 1),
('Class 6',     'Class 6',     'anglophone', 18, 1);
