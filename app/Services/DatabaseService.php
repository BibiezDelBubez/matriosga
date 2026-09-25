<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use App\Core\Settings;
use PDO;
use PDOException;
use RuntimeException;

/**
 * Unica connessione a SQL Server (PDO_SQLSRV), aperta solo quando serve.
 * È READ-ONLY: select() accetta solo SELECT/WITH e rifiuta statement multipli o di scrittura.
 */
final class DatabaseService
{
    private const SLOW_QUERY_MS = 2000;
    private const FORBIDDEN = '/\b(INSERT|UPDATE|DELETE|MERGE|DROP|ALTER|CREATE|TRUNCATE|EXEC|EXECUTE|GRANT|REVOKE|DENY|INTO|BACKUP|RESTORE|SHUTDOWN|DBCC|OPENROWSET|OPENQUERY|OPENDATASOURCE)\b/i';

    private ?PDO $pdo = null;

    public function __construct(
        private readonly Settings $settings,
        private readonly Logger $logger,
    ) {
    }

    public function pdo(): PDO
    {
        if ($this->pdo === null) {
            if (!$this->settings->isConfigured()) {
                throw new RuntimeException('Connessione non configurata: aprire Impostazioni.');
            }
            $this->pdo = $this->connect($this->settings->connection(), $this->settings->password());
        }
        return $this->pdo;
    }

    /**
     * Apre una connessione con i parametri indicati (usato anche da "Testa connessione"
     * con valori non ancora salvati).
     * @param array<string, mixed> $c
     */
    public function connect(array $c, string $password): PDO
    {
        if (!extension_loaded('pdo_sqlsrv')) {
            throw new RuntimeException('Estensione PHP pdo_sqlsrv non caricata: vedere README (sezione Prerequisiti).');
        }
        $server = trim((string) $c['server']) . (trim((string) $c['port']) !== '' ? ',' . trim((string) $c['port']) : '');
        $dsn = sprintf(
            'sqlsrv:Server=%s;Database=%s;LoginTimeout=%d;Encrypt=%s;TrustServerCertificate=%s;APP=Matriosga;ApplicationIntent=ReadOnly',
            $server,
            $c['database'],
            (int) $c['login_timeout'],
            $c['encrypt'] ? 'yes' : 'no',
            $c['trust_server_certificate'] ? 'yes' : 'no',
        );
        $windows = ($c['auth'] ?? 'sql') === 'windows';

        try {
            $pdo = new PDO($dsn, $windows ? null : (string) $c['username'], $windows ? null : $password, [
                PDO::ATTR_ERRMODE                     => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE          => PDO::FETCH_ASSOC,
                PDO::SQLSRV_ATTR_QUERY_TIMEOUT        => (int) $c['query_timeout'],
                PDO::SQLSRV_ATTR_FETCHES_NUMERIC_TYPE => true,
            ]);
            if (!empty($c['read_uncommitted'])) {
                $pdo->exec('SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED');
            }
        } catch (PDOException $e) {
            $this->logger->error('Connessione fallita', ['server' => $server, 'database' => $c['database'], 'error' => $e->getMessage()]);
            throw new RuntimeException(self::cleanMessage($e->getMessage()), 0, $e);
        }
        return $pdo;
    }

    /**
     * @param array<string, mixed> $params parametri nominali (:nome)
     * @return list<array<string, mixed>>
     */
    public function select(string $sql, array $params = [], ?int $timeout = null, ?PDO $pdo = null): array
    {
        $this->guard($sql);
        $pdo ??= $this->pdo();
        $start = microtime(true);
        try {
            $options = $timeout !== null ? [PDO::SQLSRV_ATTR_QUERY_TIMEOUT => $timeout] : [];
            $stmt = $pdo->prepare($sql, $options);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();
        } catch (PDOException $e) {
            $this->logger->error('Query fallita', ['error' => $e->getMessage(), 'sql' => self::shorten($sql)]);
            throw new RuntimeException(self::cleanMessage($e->getMessage()), 0, $e);
        }
        $ms = (int) round((microtime(true) - $start) * 1000);
        if ($ms >= self::SLOW_QUERY_MS) {
            $this->logger->info('Query lenta', ['ms' => $ms, 'rows' => count($rows), 'sql' => self::shorten($sql)]);
        }
        return $rows;
    }

    /** @param array<string, mixed> $params */
    public function selectOne(string $sql, array $params = [], ?int $timeout = null): ?array
    {
        return $this->select($sql, $params, $timeout)[0] ?? null;
    }

    /** @return array<string, mixed> informazioni sul server per test connessione e dashboard */
    public function serverInfo(?PDO $pdo = null): array
    {
        return $this->select(
            "SELECT @@SERVERNAME AS server_name, DB_NAME() AS database_name, SUSER_SNAME() AS login_name,
                    CAST(SERVERPROPERTY('ProductVersion') AS nvarchar(128)) AS product_version,
                    CAST(SERVERPROPERTY('Edition') AS nvarchar(128)) AS edition,
                    CAST(DATABASEPROPERTYEX(DB_NAME(), 'Collation') AS nvarchar(128)) AS collation,
                    LEFT(@@VERSION, CHARINDEX(CHAR(10), @@VERSION + CHAR(10)) - 1) AS version",
            [],
            null,
            $pdo,
        )[0];
    }

    /** Rifiuta tutto ciò che non è una singola lettura. */
    private function guard(string $sql): void
    {
        // Elimina stringhe, identificatori [..] e commenti prima dei controlli.
        $bare = preg_replace(["/'(?:[^']|'')*'/", '/\[(?:[^\]]|\]\])*\]/', '/--[^\n]*/', '#/\*.*?\*/#s'], ' ', $sql) ?? '';
        $bare = trim($bare);
        if (!preg_match('/^(SELECT|WITH)\b/i', $bare) || str_contains($bare, ';') || preg_match(self::FORBIDDEN, $bare)) {
            throw new RuntimeException('Query bloccata: Matriosga esegue solo letture (SELECT).');
        }
    }

    private static function cleanMessage(string $message): string
    {
        // "SQLSTATE[42S02]: [Microsoft][ODBC Driver 18 for SQL Server][SQL Server]Invalid object..." -> testo utile
        return preg_replace('/^SQLSTATE\[\w+\]:\s*(\[[^\]]*\])*\s*/', '', $message) ?: $message;
    }

    private static function shorten(string $sql): string
    {
        $sql = (string) preg_replace('/\s+/', ' ', $sql);
        return strlen($sql) > 600 ? substr($sql, 0, 600) . '…' : $sql;
    }
}
