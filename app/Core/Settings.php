<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Configurazione = default di config/app.php + override locali in storage/settings.json (senza segreti).
 *
 * La password del database sta SEPARATA in storage/secrets.json, cifrata (AES-256-GCM) con una chiave
 * locale (app.key). La chiave per default è in storage/, ma può stare fuori dal progetto:
 * config/app.php → security.key_file, oppure variabile d'ambiente MATRIOSGA_KEY_FILE.
 * La password non viene mai restituita da connection().
 */
final class Settings
{
    private const FILE = BASE_PATH . '/storage/settings.json';
    private const SECRETS_FILE = BASE_PATH . '/storage/secrets.json';
    private const DEFAULT_KEY_FILE = BASE_PATH . '/storage/app.key';
    private const EDITABLE = ['connection', 'limits'];

    /** @var array<string, mixed> */
    private array $defaults;
    /** @var array<string, mixed> */
    private array $local;
    /** @var array<string, string> */
    private array $secrets;
    /** @var array<string, mixed> */
    private array $data;

    public function __construct()
    {
        $this->defaults = require BASE_PATH . '/config/app.php';
        $this->local = self::readJson(self::FILE);
        $this->secrets = self::readJson(self::SECRETS_FILE);
        $this->migrateLegacyPassword();
        $this->merge();
    }

    /** Lettura con notazione a punti: get('limits.preview_rows'). */
    public function get(string $path, mixed $default = null): mixed
    {
        $node = $this->data;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($node) || !array_key_exists($segment, $node)) {
                return $default;
            }
            $node = $node[$segment];
        }
        return $node;
    }

    /** @return array<string, mixed> parametri di connessione SENZA password */
    public function connection(): array
    {
        $conn = $this->data['connection'];
        $conn['has_password'] = $this->hasPassword();
        unset($conn['password']);
        return $conn;
    }

    public function hasPassword(): bool
    {
        return ($this->secrets['db_password'] ?? '') !== '';
    }

    public function password(): string
    {
        return $this->decrypt($this->secrets['db_password'] ?? '');
    }

    /** Percorso della chiave di cifratura (vedi docblock della classe). */
    public function keyFile(): string
    {
        $configured = getenv('MATRIOSGA_KEY_FILE') ?: (string) ($this->defaults['security']['key_file'] ?? '');
        return $configured !== '' ? $configured : self::DEFAULT_KEY_FILE;
    }

    /** La password salvata si può decifrare? (false se app.key è stato perso o spostato). */
    public function passwordReadable(): bool
    {
        return !$this->hasPassword() || $this->password() !== '';
    }

    public function isConfigured(): bool
    {
        return $this->get('connection.server') !== '' && $this->get('connection.database') !== '';
    }

    /** Identifica server+database (per la cache metadata). */
    public function connectionId(): string
    {
        return strtolower($this->get('connection.server') . '|' . $this->get('connection.port') . '|' . $this->get('connection.database'));
    }

    /** @return array<string, mixed> */
    public function defaults(string $section): array
    {
        return $this->defaults[$section] ?? [];
    }

    /**
     * Aggiorna una sezione modificabile, accettando solo chiavi note e convertendo
     * ogni valore al tipo del default. La password si gestisce con setPassword().
     * @param array<string, mixed> $values
     */
    public function update(string $section, array $values): void
    {
        foreach ($this->normalize($section, $values) as $key => $value) {
            $this->local[$section][$key] = $value;
        }
        $this->persist();
    }

    /**
     * Filtra le chiavi note (esclusa la password) e converte al tipo del default.
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    public function normalize(string $section, array $values): array
    {
        if (!in_array($section, self::EDITABLE, true)) {
            throw new \InvalidArgumentException("Sezione non modificabile: {$section}");
        }
        $out = [];
        foreach ($this->defaults[$section] as $key => $default) {
            if ($key === 'password' || !array_key_exists($key, $values) || is_array($values[$key])) {
                continue;
            }
            $out[$key] = match (true) {
                is_bool($default) => filter_var($values[$key], FILTER_VALIDATE_BOOLEAN),
                is_int($default)  => max(0, (int) $values[$key]),
                default           => trim((string) $values[$key]),
            };
        }
        return $out;
    }

    public function setPassword(string $password): void
    {
        $this->secrets['db_password'] = $password === '' ? '' : $this->encrypt($password);
        self::writeJson(self::SECRETS_FILE, $this->secrets);
    }

    /** Versioni precedenti salvavano la password in settings.json: la sposto in secrets.json. */
    private function migrateLegacyPassword(): void
    {
        $legacy = $this->local['connection']['password'] ?? null;
        if ($legacy === null) {
            return;
        }
        if (!isset($this->secrets['db_password'])) {
            $this->secrets['db_password'] = (string) $legacy;
            self::writeJson(self::SECRETS_FILE, $this->secrets);
        }
        unset($this->local['connection']['password']);
        self::writeJson(self::FILE, $this->local);
    }

    private function persist(): void
    {
        self::writeJson(self::FILE, $this->local);
        $this->merge();
    }

    private function merge(): void
    {
        $this->data = array_replace_recursive($this->defaults, $this->local);
    }

    /** @return array<string, mixed> */
    private static function readJson(string $file): array
    {
        $raw = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        return is_array($raw) ? $raw : [];
    }

    /** @param array<string, mixed> $data */
    private static function writeJson(string $file, array $data): void
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (file_put_contents($file, (string) $json, LOCK_EX) === false) {
            throw new \RuntimeException('Impossibile scrivere ' . basename($file) . ': controllare i permessi della cartella storage.');
        }
    }

    private function key(): string
    {
        $file = $this->keyFile();
        if (!is_file($file)) {
            if (!is_dir(dirname($file)) || file_put_contents($file, base64_encode(random_bytes(32)), LOCK_EX) === false) {
                throw new \RuntimeException("Impossibile creare la chiave di cifratura in {$file}.");
            }
        }
        return (string) base64_decode(trim((string) file_get_contents($file)));
    }

    private function encrypt(string $plain): string
    {
        $iv = random_bytes(12);
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $this->key(), OPENSSL_RAW_DATA, $iv, $tag);
        return 'enc:' . base64_encode($iv . $tag . $cipher);
    }

    private function decrypt(string $stored): string
    {
        if (!str_starts_with($stored, 'enc:')) {
            return $stored; // consentito scrivere la password in chiaro a mano nel JSON
        }
        if (!is_file($this->keyFile())) {
            return ''; // chiave persa o spostata: la password va reinserita (vedi Ambiente server)
        }
        $raw = (string) base64_decode(substr($stored, 4));
        $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $this->key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
        return $plain === false ? '' : $plain;
    }
}
