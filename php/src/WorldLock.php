<?php
declare(strict_types=1);

namespace Dimx;

use PDO;
use PDOException;

/**
 * WorldLock
 *
 * Provides a simple "critical section" for world-state mutations using
 * MySQL's application-level advisory locks (GET_LOCK / RELEASE_LOCK).
 *
 * Design goals:
 *  - Only one PHP request at a time may mutate a given world's state.
 *  - Reads and chat are always outside the lock (fully concurrent).
 *  - At most 10 concurrent users → a 5-second timeout is generous.
 *  - No external daemons, works on shared hosting.
 *
 * Usage:
 *   $lock = new WorldLock($pdo);
 *   $result = $lock->executeAtomic('my_world', function(PDO $pdo) use (&$world) {
 *       // load, mutate, save world here
 *       return 'ok';
 *   });
 *   if ($result === null) {
 *       // lock timed out — ask player to retry
 *   }
 */
class WorldLock
{
    private PDO $pdo;
    private int $timeoutSeconds;
    /** @var array<string, bool> Track acquired locks for cleanup */
    private array $held = [];

    public function __construct(PDO $pdo, int $timeoutSeconds = 5)
    {
        $this->pdo           = $pdo;
        $this->timeoutSeconds = $timeoutSeconds;
    }

    /**
     * Acquire the named advisory lock.
     *
     * @param string $worldId World identifier (used as lock name)
     * @return bool  true if acquired, false if timed out or error
     */
    public function acquire(string $worldId): bool
    {
        $lockName = $this->lockName($worldId);
        try {
            $stmt = $this->pdo->prepare('SELECT GET_LOCK(:name, :timeout)');
            $stmt->execute([':name' => $lockName, ':timeout' => $this->timeoutSeconds]);
            $result = $stmt->fetchColumn();
            if ($result === '1' || $result === 1) {
                $this->held[$worldId] = true;
                return true;
            }
        } catch (PDOException $e) {
            error_log("WorldLock::acquire failed for '{$worldId}': " . $e->getMessage());
        }
        return false;
    }

    /**
     * Release the named advisory lock.
     */
    public function release(string $worldId): void
    {
        if (!isset($this->held[$worldId])) {
            return;
        }
        $lockName = $this->lockName($worldId);
        try {
            $stmt = $this->pdo->prepare('SELECT RELEASE_LOCK(:name)');
            $stmt->execute([':name' => $lockName]);
        } catch (PDOException $e) {
            error_log("WorldLock::release failed for '{$worldId}': " . $e->getMessage());
        }
        unset($this->held[$worldId]);
    }

    /**
     * Execute a callable atomically under the world lock.
     *
     * The callable receives the PDO connection and may freely read/write the DB.
     * Returns the callable's return value, or NULL if the lock could not be acquired.
     *
     * @param string   $worldId
     * @param callable $action  fn(PDO $pdo): mixed
     * @return mixed   Result of $action, or null on lock timeout
     */
    public function executeAtomic(string $worldId, callable $action): mixed
    {
        if (!$this->acquire($worldId)) {
            return null; // Caller should inform the player to retry
        }

        try {
            return $action($this->pdo);
        } finally {
            $this->release($worldId);
        }
    }

    /**
     * Release all held locks (call on shutdown / error paths).
     */
    public function releaseAll(): void
    {
        foreach (array_keys($this->held) as $worldId) {
            $this->release($worldId);
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function lockName(string $worldId): string
    {
        // MySQL lock names are max 64 chars; prefix to avoid collisions.
        return 'dimx_world_' . substr($worldId, 0, 52);
    }
}
