<?php

class URLManager {
    private $db;

    public function __construct($filename = 'data/urls.db') {
        $this->db = new SQLite3($filename);
        $this->db->exec("CREATE TABLE IF NOT EXISTS urls (
            id TEXT PRIMARY KEY,
            username TEXT,
            url TEXT UNIQUE,
            details_json TEXT,
            last_export UNSIGNED BIG INT,
            target TEXT
        )");
    }

    public function __destruct() {
        $this->db->close();
    }

    public function isIdPresent($id) {
        $stmt = $this->db->prepare("SELECT id FROM urls WHERE id = :id");
        $stmt->bindValue(':id', $id, SQLITE3_TEXT);
        $result = $stmt->execute();

        return $result->fetchArray(SQLITE3_ASSOC) !== false;
    }

    public function addURL($id, $url, $details_json) {
        if ($this->isIdPresent($id)) {
            return false;
        }

        $details_array = json_decode($details_json, true);
        $target = $details_array['target'] ?? null;

        $stmt = $this->db->prepare("INSERT INTO urls (id, username, url, details_json, target) VALUES (:id, :username, :url, :details_json, :target)");
        $stmt->bindValue(':id', $id, SQLITE3_TEXT);
        $stmt->bindValue(':username', $_SESSION["username"] ?? null, SQLITE3_TEXT);
        $stmt->bindValue(':url', $url, SQLITE3_TEXT);
        $stmt->bindValue(':details_json', $details_json, SQLITE3_TEXT);
        $stmt->bindValue(':target', $target, SQLITE3_TEXT);

        if (!$stmt->execute()) {
            throw new Exception("Error: Could not insert the new row with ID '$id', URL '$url' for target '$target'. SQLite error: " . $this->db->lastErrorMsg());
        }

        return true;
    }

    public function updateLastExport($id) {
        $current_ctime = time();
        $query = $this->db->prepare("UPDATE urls SET last_export = :last_export WHERE id = :id");
        $query->bindValue(':last_export', $current_ctime, SQLITE3_INTEGER);
        $query->bindValue(':id', $id, SQLITE3_TEXT);

        return $query->execute() !== false;
    }

    public function removeMissingIds($ids) {
        // Convert the array of IDs into a comma-separated string enclosed in parentheses
        $ids_string = '(' . implode(',', array_map(function($id) { return "'" . SQLite3::escapeString($id) . "'"; }, $ids)) . ')';

        // Prepare the SQL statement to delete rows with IDs not in the provided array
        $stmt = $this->db->prepare("DELETE FROM urls WHERE id NOT IN {$ids_string}");

        // Execute the statement
        if (!$stmt->execute()) {
            throw new Exception("Error: Could not remove rows with IDs missing from the provided array.");
        }
    }

    public function getAllIds() {
        $ids = [];
        $result = $this->db->query("SELECT id FROM urls");

        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $ids[] = $row['id'];
        }

        return $ids;
    }

    public function dumpAllToJson() {
        $data = [];
        $result = $this->db->query("SELECT * FROM urls");

        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $row['details_json'] = json_decode($row['details_json'], true);
            $data[] = $row;
        }

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function getById($id, $target = null) {
        $query = "SELECT * FROM urls WHERE id = :id";

        if ($target) {
            $query .= " AND target = :target";
        }

        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':id', $id, SQLITE3_TEXT);

        if ($target) {
            $stmt->bindValue(':target', $target, SQLITE3_TEXT);
        }

        $result = $stmt->execute();

        $row = $result->fetchArray(SQLITE3_ASSOC);
        if ($row) {
            $row['details_json'] = json_decode($row['details_json'], true);
            
            return $row;
        } else {
            return null;
        }
    }
}
