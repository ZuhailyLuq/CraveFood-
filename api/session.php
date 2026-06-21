<?php
// session.php
require_once __DIR__ . '/db.php';

class DatabaseSessionHandler implements SessionHandlerInterface {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function open($savePath, $sessionName): bool {
        return true;
    }

    public function close(): bool {
        return true;
    }

    #[\ReturnTypeWillChange]
    public function read($id) {
        $stmt = $this->pdo->prepare("SELECT data FROM app_sessions WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return $row['data'];
        }
        return '';
    }

    public function write($id, $data): bool {
        $stmt = $this->pdo->prepare("
            INSERT INTO app_sessions (id, data, last_accessed) 
            VALUES (?, ?, CURRENT_TIMESTAMP)
            ON CONFLICT (id) DO UPDATE SET data = EXCLUDED.data, last_accessed = CURRENT_TIMESTAMP
        ");
        $stmt->execute([$id, $data]);
        return true;
    }

    public function destroy($id): bool {
        $stmt = $this->pdo->prepare("DELETE FROM app_sessions WHERE id = ?");
        $stmt->execute([$id]);
        return true;
    }

    #[\ReturnTypeWillChange]
    public function gc($maxlifetime): int|false {
        $stmt = $this->pdo->prepare("DELETE FROM app_sessions WHERE last_accessed < NOW() - INTERVAL '1 second' * ?");
        $stmt->execute([$maxlifetime]);
        return true;
    }
}

$handler = new DatabaseSessionHandler($pdo);
session_set_save_handler($handler, true);

// Configure session cookies to be more secure/persistent
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax');
// Use a 30 day cookie lifetime
ini_set('session.gc_maxlifetime', 2592000);
ini_set('session.cookie_lifetime', 2592000);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
