<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use PDO;
use RuntimeException;
use Throwable;

/** The TFC systems: their user lists (read only) and the signed one-minute links that sign a person in there. */
class Systems
{
    /** @return array<string, array<string, mixed>> */
    public function all(): array
    {
        return config('tfc.systems');
    }

    public function has(string $system): bool
    {
        return isset($this->all()[$system]);
    }

    public function name(string $system): string
    {
        return $this->all()[$system]['name'] ?? $system;
    }

    /** Whether every active TFC person gets this system automatically, instead of being assigned to it by hand. */
    public function defaultAccess(string $system): bool
    {
        return (bool) ($this->all()[$system]['default_access'] ?? false);
    }

    /**
     * The people who have an account in that system, keyed by their id there.
     *
     * @return Collection<string, array{id: string, name: string, login: string, role: string, active: bool}>
     */
    public function users(string $system): Collection
    {
        $from = $this->all()[$system]['users'] ?? null;
        if (! $from || ! is_readable($from['path'])) {
            throw new RuntimeException("The {$this->name($system)} user list could not be read.");
        }

        $rows = $from['from'] === 'json'
            ? (json_decode((string) file_get_contents($from['path']), true)['users'] ?? [])
            : (new PDO('sqlite:'.$from['path'], null, null, [PDO::SQLITE_ATTR_OPEN_FLAGS => PDO::SQLITE_OPEN_READONLY, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]))
                ->query($from['sql'])->fetchAll(PDO::FETCH_ASSOC);

        return collect($rows)->map(fn (array $u) => [
            'id' => (string) $u['id'],
            'name' => (string) ($u['name'] ?? ''),
            'login' => (string) ($u['login'] ?? $u['username'] ?? $u['email'] ?? ''),
            'role' => (string) ($u['role'] ?? ''),
            'active' => ($u['active'] ?? true) !== false && (string) ($u['active'] ?? '1') !== '0',
        ])->keyBy('id');
    }

    /**
     * Same, or an empty list and the reason when it cannot be read.
     *
     * @return array{0: Collection, 1: ?string}
     */
    public function usersOrError(string $system): array
    {
        try {
            return [$this->users($system), null];
        } catch (Throwable $e) {
            report($e);

            return [collect(), "The {$this->name($system)} user list could not be read right now."];
        }
    }

    /** The address that signs this person in to that system: good for one minute, once. */
    public function signInLink(string $system, string $remoteId): string
    {
        $s = $this->all()[$system];
        if (blank($s['key'])) {
            throw new RuntimeException("{$s['name']} is not connected to the TFC portal yet (no key).");
        }
        $body = self::b64(json_encode(['s' => $system, 'u' => $remoteId, 'e' => time() + (int) config('tfc.link_seconds'), 'n' => bin2hex(random_bytes(16))]));
        $sig = self::b64(hash_hmac('sha256', $body, (string) $s['key'], true));

        return rtrim((string) $s['url'], '/').'/tfc/sso?t='.$body.'.'.$sig;
    }

    /**
     * Creates (or finds, by email) this person's account in a default-access system, via that
     * system's own key-authenticated /tfc/provision endpoint, and returns its id there.
     */
    public function provision(string $system, string $name, string $email): string
    {
        $s = $this->all()[$system];
        if (blank($s['key'])) {
            throw new RuntimeException("{$s['name']} is not connected to the TFC portal yet (no key).");
        }

        $response = Http::timeout(10)
            ->withHeaders(['X-Tfc-Key' => (string) $s['key']])
            ->post(rtrim((string) $s['url'], '/').'/tfc/provision', ['name' => $name, 'email' => $email]);

        $id = $response->successful() ? (string) $response->json('id') : '';
        if ($id === '') {
            throw new RuntimeException("{$s['name']} could not create an account for {$name} right now.");
        }

        return $id;
    }

    private static function b64(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }
}
