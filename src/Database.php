<?php
/**
 * Connexion PDO unique, deux modes :
 *  - PostgreSQL si DATABASE_URL est definie (recommande en production : donnees persistantes)
 *  - SQLite sinon (fichier local data/pact.sqlite, zero configuration)
 * Le schema est cree automatiquement et la base est pre-remplie avec les donnees
 * reelles du projet (activites, documents, modeles, indicateurs) si elle est vide.
 */
class Database
{
    private static ?PDO $pdo = null;
    private static string $driver = 'sqlite';

    public static function driver(): string
    {
        self::connect();
        return self::$driver;
    }

    public static function connect(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $url = getenv('DATABASE_URL') ?: '';
        if ($url !== '') {
            self::$pdo = self::connectPostgres($url);
            self::$driver = 'pgsql';
        } else {
            $path = getenv('SQLITE_PATH') ?: (__DIR__ . '/../data/pact.sqlite');
            if (!is_dir(dirname($path))) {
                mkdir(dirname($path), 0775, true);
            }
            self::$pdo = new PDO('sqlite:' . $path);
            self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$pdo->exec('PRAGMA foreign_keys = ON');
            self::$pdo->exec('PRAGMA journal_mode = WAL');
            self::$driver = 'sqlite';
        }
        self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        self::migrate();
        self::seedIfEmpty();

        return self::$pdo;
    }

    private static function connectPostgres(string $url): PDO
    {
        $p = parse_url($url);
        $sslmode = 'require';
        if (isset($p['query'])) {
            parse_str($p['query'], $q);
            $sslmode = $q['sslmode'] ?? $sslmode;
        }
        $dsn = sprintf(
            'pgsql:host=%s;port=%d;dbname=%s;sslmode=%s',
            $p['host'] ?? 'localhost',
            $p['port'] ?? 5432,
            ltrim($p['path'] ?? '', '/'),
            $sslmode
        );
        $pdo = new PDO($dsn, urldecode($p['user'] ?? ''), urldecode($p['pass'] ?? ''));
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    }

    private static function migrate(): void
    {
        $sql = file_get_contents(__DIR__ . '/../database/schema.sql');
        $pk = self::$driver === 'pgsql' ? 'SERIAL PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $sql = str_replace('{PK}', $pk, $sql);
        // Retire les commentaires puis execute instruction par instruction (compatible pgsql/sqlite)
        $sql = preg_replace('/--[^\n]*/', '', $sql);
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
            self::$pdo->exec($stmt);
        }
    }

    private static function seedIfEmpty(): void
    {
        $count = (int) self::$pdo->query('SELECT COUNT(*) FROM activites')->fetchColumn();
        $countModeles = (int) self::$pdo->query('SELECT COUNT(*) FROM modeles')->fetchColumn();
        if ($count > 0 && $countModeles > 0) {
            return;
        }
        require_once __DIR__ . '/Seeder.php';
        Seeder::run(self::$pdo, $count === 0, $countModeles === 0);
    }

    public static function lastId(string $table): int
    {
        if (self::$driver === 'pgsql') {
            return (int) self::$pdo->query("SELECT currval(pg_get_serial_sequence('{$table}', 'id'))")->fetchColumn();
        }
        return (int) self::$pdo->lastInsertId();
    }

    /** Operateur de recherche insensible a la casse */
    public static function like(): string
    {
        return self::$driver === 'pgsql' ? 'ILIKE' : 'LIKE';
    }
}
