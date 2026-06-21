<?php
/**
 * PDO helper functions to replace mysqli_* calls throughout the app.
 * This shim layer keeps the rewrite minimal and safe.
 */

/**
 * Run a parameterized query and return the PDOStatement.
 * Usage: $stmt = db_query($pdo, "SELECT * FROM \"user\" WHERE \"UserId\" = ?", [14]);
 */
function db_query(PDO $pdo, string $sql, array $params = []): PDOStatement {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/**
 * Fetch all rows from a query.
 */
function db_fetch_all(PDO $pdo, string $sql, array $params = []): array {
    return db_query($pdo, $sql, $params)->fetchAll();
}

/**
 * Fetch a single row from a query.
 */
function db_fetch_one(PDO $pdo, string $sql, array $params = []): ?array {
    $row = db_query($pdo, $sql, $params)->fetch();
    return $row === false ? null : $row;
}

/**
 * Execute an INSERT/UPDATE/DELETE and return number of affected rows.
 */
function db_execute(PDO $pdo, string $sql, array $params = []): int {
    $stmt = db_query($pdo, $sql, $params);
    return $stmt->rowCount();
}

/**
 * Get the last inserted ID.
 */
function db_last_id(PDO $pdo, string $sequence = ''): string {
    return $pdo->lastInsertId($sequence ?: null);
}

/**
 * PostgreSQL uses RANDOM() instead of MySQL's RAND()
 * PostgreSQL uses double-quoted identifiers, not backticks
 * This helper converts common MySQL-isms in SQL strings for PostgreSQL.
 */
function pg_sql(string $sql): string {
    // MySQL RAND() → PostgreSQL RANDOM()
    $sql = preg_replace('/\bRAND\(\)/i', 'RANDOM()', $sql);
    // MySQL backtick identifiers → PostgreSQL double quotes
    $sql = preg_replace('/`([^`]+)`/', '"$1"', $sql);
    // MySQL NOW() is also valid in PostgreSQL, no change needed
    return $sql;
}
