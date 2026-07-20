SET @column_exists := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'api_keys'
      AND column_name = 'last4'
);

SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE api_keys ADD COLUMN last4 CHAR(4) NOT NULL DEFAULT '''' AFTER secret_hash',
    'SELECT 1'
);
PREPARE statement FROM @sql;
EXECUTE statement;
DEALLOCATE PREPARE statement;
