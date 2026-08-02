<?php
/**
 * Idempotent migration script — GSB SINIYAT
 * Safe to run on both a fresh (empty) database and an existing one.
 * Custom levels added by administrators are preserved.
 *
 * Usage (CLI):  php scripts/migrate.php
 * Usage (web):  blocked for non-localhost requests
 */

if (PHP_SAPI !== 'cli') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!in_array($ip, ['127.0.0.1', '::1'])) {
        http_response_code(403);
        exit('Forbidden: run this script from the command line.');
    }
}

require_once dirname(__DIR__) . '/config/database.php';

function mlog(string $msg): void {
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
}

try {
    $pdo = getDB();
    mlog('Connected to database.');

    // ── Detect whether the database is fresh ─────────────────────────────────
    $tableExists = (int) $pdo->query(
        "SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema = 'public' AND table_name = 'niveaux'"
    )->fetchColumn();

    if (!$tableExists) {
        // ── FRESH DATABASE: apply the full schema and we're done ──────────────
        mlog('Fresh database detected — applying schema.sql.');
        $schema = file_get_contents(dirname(__DIR__) . '/schema.sql');
        $pdo->exec($schema);
        mlog('schema.sql applied (tables created + seed data inserted).');

    } else {
        // ── EXISTING DATABASE: incremental migrations only ───────────────────
        mlog('Existing database detected — applying incremental migrations.');

        // 1. Add user_agent column if missing
        $pdo->exec(
            "ALTER TABLE historique_connexions
             ADD COLUMN IF NOT EXISTS user_agent TEXT"
        );
        mlog('historique_connexions.user_agent ensured.');

        // 2. Ensure unique constraint on niveaux(nom_fr, section).
        //    Requires deduplication if the constraint was not yet present.
        $constraintExists = (int) $pdo->query(
            "SELECT COUNT(*) FROM pg_constraint
             WHERE conname = 'niveaux_nom_fr_section_unique'"
        )->fetchColumn();

        if (!$constraintExists) {
            mlog('Unique constraint missing — deduplicating niveaux safely.');

            // All steps run inside a single transaction so the DB stays consistent
            // on any failure.
            $pdo->exec("BEGIN");

            try {
                // Step A: build the keeper map (lowest id per nom_fr+section).
                // Create a temp table so each CTE below uses the same snapshot.
                $pdo->exec("
                    CREATE TEMP TABLE _dupe_map AS
                    SELECT n.id AS dupe_id, k.keep_id
                    FROM niveaux n
                    JOIN (
                        SELECT MIN(id) AS keep_id, nom_fr, section
                        FROM niveaux GROUP BY nom_fr, section
                        HAVING COUNT(*) > 1
                    ) k ON n.nom_fr = k.nom_fr AND n.section = k.section
                    WHERE n.id <> k.keep_id;
                ");

                // Step B: nullify paiements.tranche_id for tranches that belong
                // to conflicting (duplicate) grids — BEFORE any cascade delete.
                $pdo->exec("
                    UPDATE paiements SET tranche_id = NULL
                    WHERE tranche_id IN (
                        SELECT t.id
                        FROM tranches t
                        JOIN grille_frais gf ON gf.id = t.grille_id
                        JOIN grille_frais gf_keeper
                            ON gf_keeper.niveau_id = (
                                SELECT keep_id FROM _dupe_map WHERE dupe_id = gf.niveau_id
                            )
                            AND gf_keeper.annee_id = gf.annee_id
                        WHERE gf.niveau_id IN (SELECT dupe_id FROM _dupe_map)
                    )
                ");

                // Step C: delete conflicting duplicate grids (tranches cascade).
                $pdo->exec("
                    DELETE FROM grille_frais gf
                    USING _dupe_map dm
                    WHERE gf.niveau_id = dm.dupe_id
                      AND EXISTS (
                          SELECT 1 FROM grille_frais gf2
                          WHERE gf2.niveau_id = dm.keep_id
                            AND gf2.annee_id  = gf.annee_id
                      )
                ");

                // Step D: redirect remaining grille_frais to keeper.
                $pdo->exec("
                    UPDATE grille_frais SET niveau_id = dm.keep_id
                    FROM _dupe_map dm WHERE grille_frais.niveau_id = dm.dupe_id
                ");

                // Step E: redirect eleves to keeper.
                $pdo->exec("
                    UPDATE eleves SET niveau_id = dm.keep_id
                    FROM _dupe_map dm WHERE eleves.niveau_id = dm.dupe_id
                ");

                // Step F: delete duplicate niveaux rows (all FK refs resolved).
                $pdo->exec("
                    DELETE FROM niveaux
                    WHERE id IN (SELECT dupe_id FROM _dupe_map)
                ");

                // Step G: add the unique constraint now that the table is clean.
                $pdo->exec("
                    ALTER TABLE niveaux
                        ADD CONSTRAINT niveaux_nom_fr_section_unique
                        UNIQUE (nom_fr, section)
                ");

                $pdo->exec("DROP TABLE _dupe_map");
                $pdo->exec("COMMIT");
                mlog('Deduplication complete and unique constraint added.');

            } catch (Exception $inner) {
                $pdo->exec("ROLLBACK");
                throw $inner;
            }

        } else {
            mlog('niveaux_nom_fr_section_unique already present — skipping dedup.');
        }

        // 3. Re-apply schema: CREATE TABLE IF NOT EXISTS + ON CONFLICT seeding.
        //    With the unique constraint in place, seed INSERTs are genuine no-ops
        //    if the rows already exist; custom administrator-added levels are
        //    never touched.
        $schema = file_get_contents(dirname(__DIR__) . '/schema.sql');
        $pdo->exec($schema);
        mlog('schema.sql re-applied (idempotent).');
    }

    // ── Verify: required seed levels are present ─────────────────────────────
    $seedLevels = ['Pré-Maternelle', 'CM2', 'Pre-Nursery', 'Class 6'];
    foreach ($seedLevels as $name) {
        $found = (int) $pdo->prepare(
            "SELECT COUNT(*) FROM niveaux WHERE nom_fr = ?"
        )->execute([$name]) && (int) $pdo->query(
            "SELECT COUNT(*) FROM niveaux WHERE nom_fr = " .
            $pdo->quote($name)
        )->fetchColumn();
        if (!$found) {
            throw new RuntimeException("Required seed level missing: $name");
        }
    }

    $nbNiveaux = (int) $pdo->query("SELECT COUNT(*) FROM niveaux")->fetchColumn();
    $nbUsers   = (int) $pdo->query("SELECT COUNT(*) FROM utilisateurs")->fetchColumn();
    mlog("Row counts — niveaux: $nbNiveaux (≥16 incl. custom), utilisateurs: $nbUsers (≥1).");
    mlog('All migrations completed successfully.');

} catch (Exception $e) {
    echo '[ERROR] ' . $e->getMessage() . "\n";
    exit(1);
}
