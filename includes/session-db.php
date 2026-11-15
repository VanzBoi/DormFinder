<?php
declare(strict_types=1);
// Database-backed sessions for hosts without a durable local disk (SESSION_DRIVER=database).
final class DatabaseSessionHandler implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface
{
    public function open(string $path, string $name): bool { return true; }
    public function close(): bool { return true; }
    public function read(string $id): string|false
    {
        $data = scalar('SELECT data FROM sessions WHERE id=?', [$id]);
        return is_string($data) ? $data : '';
    }
    public function write(string $id, string $data): bool
    {
        query('INSERT INTO sessions (id,data,updated_at) VALUES (?,?,?) ON DUPLICATE KEY UPDATE data=VALUES(data),updated_at=VALUES(updated_at)', [$id, $data, time()]);
        return true;
    }
    public function destroy(string $id): bool { query('DELETE FROM sessions WHERE id=?', [$id]); return true; }
    public function gc(int $max_lifetime): int|false { return query('DELETE FROM sessions WHERE updated_at<?', [time() - $max_lifetime])->rowCount(); }
    public function validateId(string $id): bool { return (bool)scalar('SELECT COUNT(*) FROM sessions WHERE id=?', [$id]); }
    public function updateTimestamp(string $id, string $data): bool { query('UPDATE sessions SET updated_at=? WHERE id=?', [time(), $id]); return true; }
}
