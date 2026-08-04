<?php
/**
 * Script de migration MySQL — GSB SINIYAT
 * À exécuter UNE SEULE FOIS sur le serveur Aeonfree après upload des fichiers.
 *
 * Usage CLI :  php scripts/migrate_mysql.php
 * Usage web :  bloquer (localhost only)
 *
 * Prérequis : les variables d'environnement MYSQL_HOST, MYSQL_USER,
 *             MYSQL_PASSWORD, MYSQL_DATABASE doivent être définies.
 *             Sur Aeonfree, éditer config/database.php directement si besoin.
 */

if (PHP_SAPI !== 'cli') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!in_array($ip, ['127.0.0.1', '::1'])) {
        http_response_code(403);
        exit('Forbidden: exécuter ce script en ligne de commande uniquement.');
    }
}

require_once dirname(__DIR__) . '/config/database.php';

function mlog(string $msg): void {
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
}

try {
    $pdo = getDB();
    mlog('Connexion à la base de données MySQL réussie.');

    // Détecte si la base est vierge
    $tableExists = (int)$pdo->query(
        "SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = 'niveaux'"
    )->fetchColumn();

    if (!$tableExists) {
        mlog('Base vierge détectée — application du schéma MySQL...');
        $schema = file_get_contents(dirname(__DIR__) . '/schema_mysql.sql');
        // Exécuter instruction par instruction (PDO::exec ne gère pas plusieurs statements)
        $statements = array_filter(
            array_map('trim', explode(';', $schema)),
            fn($s) => $s !== '' && !preg_match('/^--/', $s)
        );
        foreach ($statements as $sql) {
            if (trim($sql)) {
                try { $pdo->exec($sql); }
                catch (PDOException $e) {
                    mlog('AVERTISSEMENT : ' . $e->getMessage() . ' → SQL: ' . substr($sql, 0, 80));
                }
            }
        }
        mlog('Schéma MySQL appliqué (tables créées + données initiales insérées).');
    } else {
        mlog('Base existante détectée — migration incrémentale...');

        // Ajouter user_agent si manquant
        try {
            $pdo->exec("ALTER TABLE historique_connexions ADD COLUMN user_agent TEXT");
            mlog('Colonne user_agent ajoutée.');
        } catch (PDOException $e) { mlog('user_agent déjà présent.'); }

        // Assurer que la table sequences existe
        $pdo->exec("CREATE TABLE IF NOT EXISTS sequences (
            name_seq VARCHAR(50) NOT NULL PRIMARY KEY,
            value    BIGINT      NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("INSERT IGNORE INTO sequences (name_seq, value) VALUES ('matricule',0),('numero_recu',0)");
        mlog('Table sequences assurée.');

        // Assurer la table parametres
        $pdo->exec("CREATE TABLE IF NOT EXISTS parametres (
            cle        VARCHAR(100) NOT NULL PRIMARY KEY,
            valeur     TEXT         NOT NULL,
            updated_at DATETIME     NOT NULL DEFAULT NOW()
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("INSERT IGNORE INTO parametres (cle, valeur) VALUES
            ('reduction_paiement_complet','2'),('reduction_fratrie','2')");
        mlog('Table parametres assurée.');

        mlog('Migration incrémentale terminée.');
    }

    // Seed grilles de frais 2026-2027 si absent
    $hasGrids = (int)$pdo->query("SELECT COUNT(*) FROM grille_frais")->fetchColumn();
    if (!$hasGrids) {
        mlog('Aucune grille trouvée — création de la grille 2026-2027...');
        $anneeId = (int)$pdo->query("SELECT id FROM annees_scolaires WHERE libelle='2026-2027' LIMIT 1")->fetchColumn();
        if ($anneeId) {
            $grilles = [
                ['Pré-Maternelle', 5000, [['1ère Tranche','1st Instalment',50000,'Septembre'],['2ème Tranche','2nd Instalment',15000,'Déc.–Janv.']]],
                ['Pre-Nursery',    5000, [['1st Instalment','1st Instalment',50000,'September'],['2nd Instalment','2nd Instalment',15000,'Dec.–Jan.']]],
                ['Maternelle',     5000, [['1ère Tranche','1st Instalment',35000,'Septembre'],['2ème Tranche','2nd Instalment',20000,'Déc.–Janv.']]],
                ['Nursery',        5000, [['1st Instalment','1st Instalment',35000,'September'],['2nd Instalment','2nd Instalment',20000,'Dec.–Jan.']]],
            ];
            foreach (['SIL','CP','CE1','CE2','CM1','CM2'] as $nom) {
                $grilles[] = [$nom, 5000, [['1ère Tranche','1st Instalment',30000,'Septembre'],['2ème Tranche','2nd Instalment',15000,'Déc.–Janv.']]];
            }
            foreach (['Class 1','Class 2','Class 3','Class 4','Class 5','Class 6'] as $nom) {
                $grilles[] = [$nom, 5000, [['1st Instalment','1st Instalment',30000,'September'],['2nd Instalment','2nd Instalment',15000,'Dec.–Jan.']]];
            }

            $insGrille  = $pdo->prepare("INSERT IGNORE INTO grille_frais (annee_id,niveau_id,frais_inscription)
                SELECT ?, n.id, ? FROM niveaux n WHERE n.nom_fr = ? LIMIT 1");
            $getGrille  = $pdo->prepare("SELECT id FROM grille_frais WHERE annee_id=? AND niveau_id=(SELECT id FROM niveaux WHERE nom_fr=? LIMIT 1)");
            $insTranche = $pdo->prepare("INSERT IGNORE INTO tranches (grille_id,numero,libelle_fr,libelle_en,montant,echeance_indicative) VALUES (?,?,?,?,?,?)");

            foreach ($grilles as [$nomFr, $inscription, $tranches]) {
                $insGrille->execute([$anneeId, $inscription, $nomFr]);
                $getGrille->execute([$anneeId, $nomFr]);
                $grilleId = (int)$getGrille->fetchColumn();
                if (!$grilleId) continue;
                foreach ($tranches as $i => [$lFr, $lEn, $montant, $ech]) {
                    $insTranche->execute([$grilleId, $i+1, $lFr, $lEn, $montant, $ech]);
                }
            }
            mlog('Grille 2026-2027 créée : ' . count($grilles) . ' classes.');
        }
    } else {
        mlog("Grilles déjà présentes ($hasGrids) — ignoré.");
    }

    $nbNiveaux = (int)$pdo->query("SELECT COUNT(*) FROM niveaux")->fetchColumn();
    $nbUsers   = (int)$pdo->query("SELECT COUNT(*) FROM utilisateurs")->fetchColumn();
    mlog("Vérification — niveaux: $nbNiveaux, utilisateurs: $nbUsers");
    mlog('Migration MySQL terminée avec succès.');

} catch (Exception $e) {
    echo '[ERREUR] ' . $e->getMessage() . PHP_EOL;
    exit(1);
}
