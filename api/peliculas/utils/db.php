<?php
function getDbConnection() {
    $dbPath = '/tmp/peliculas_cache.db';
    $isNewDb = !file_exists($dbPath);
    
    $db = new SQLite3($dbPath);
    
    if ($isNewDb) {
        // Create tables if they don't exist
        $db->exec('
            CREATE TABLE IF NOT EXISTS cache (
                key TEXT PRIMARY KEY,
                data TEXT,
                expiry INTEGER,
                endpoint TEXT
            )
        ');
    }
    
    return $db;
}

function getCachedData($key, $endpoint) {
    $db = getDbConnection();
    $stmt = $db->prepare('SELECT data, expiry FROM cache WHERE key = :key AND endpoint = :endpoint');
    $stmt->bindValue(':key', $key, SQLITE3_TEXT);
    $stmt->bindValue(':endpoint', $endpoint, SQLITE3_TEXT);
    $result = $stmt->execute();
    
    if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        // Check if cache is still valid
        if ($row['expiry'] > time()) {
            $db->close();
            return $row['data'];
        }
        
        // Cache expired, delete it
        $deleteStmt = $db->prepare('DELETE FROM cache WHERE key = :key AND endpoint = :endpoint');
        $deleteStmt->bindValue(':key', $key, SQLITE3_TEXT);
        $deleteStmt->bindValue(':endpoint', $endpoint, SQLITE3_TEXT);
        $deleteStmt->execute();
    }
    
    $db->close();
    return null;
}

function setCachedData($key, $data, $endpoint, $days) {
    $db = getDbConnection();
    $expiry = time() + ($days * 24 * 60 * 60);
    
    $stmt = $db->prepare('
        INSERT OR REPLACE INTO cache (key, data, expiry, endpoint) 
        VALUES (:key, :data, :expiry, :endpoint)
    ');
    
    $stmt->bindValue(':key', $key, SQLITE3_TEXT);
    $stmt->bindValue(':data', $data, SQLITE3_TEXT);
    $stmt->bindValue(':expiry', $expiry, SQLITE3_INTEGER);
    $stmt->bindValue(':endpoint', $endpoint, SQLITE3_TEXT);
    
    $stmt->execute();
    $db->close();
}

function normalizeSearchQuery($query) {
    // Trim spaces
    $query = trim($query);
    
    // Convert to lowercase
    $query = strtolower($query);
    
    // Replace multiple spaces with a single space
    $query = preg_replace('/\s+/', ' ', $query);
    
    return $query;
}
?>

