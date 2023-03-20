CREATE TABLE IF NOT EXISTS urls (
    id TEXT PRIMARY KEY,
    username TEXT,
    url TEXT UNIQUE,
    details_json TEXT,
    last_export UNSIGNED BIG INT,
    target TEXT
);
