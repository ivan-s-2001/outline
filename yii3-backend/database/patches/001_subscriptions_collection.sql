SET @column_exists := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'subscriptions'
      AND column_name = 'collection_id'
);

SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE subscriptions ADD COLUMN collection_id CHAR(36) NULL AFTER document_id',
    'SELECT 1'
);
PREPARE statement FROM @sql;
EXECUTE statement;
DEALLOCATE PREPARE statement;

SET @index_exists := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'subscriptions'
      AND index_name = 'uq_subscription_collection'
);
SET @sql := IF(
    @index_exists = 0,
    'ALTER TABLE subscriptions ADD UNIQUE KEY uq_subscription_collection (user_id, collection_id, event_type)',
    'SELECT 1'
);
PREPARE statement FROM @sql;
EXECUTE statement;
DEALLOCATE PREPARE statement;

SET @fk_exists := (
    SELECT COUNT(*)
    FROM information_schema.referential_constraints
    WHERE constraint_schema = DATABASE()
      AND table_name = 'subscriptions'
      AND constraint_name = 'fk_subscriptions_collection'
);
SET @sql := IF(
    @fk_exists = 0,
    'ALTER TABLE subscriptions ADD CONSTRAINT fk_subscriptions_collection FOREIGN KEY (collection_id) REFERENCES collections (id) ON DELETE CASCADE',
    'SELECT 1'
);
PREPARE statement FROM @sql;
EXECUTE statement;
DEALLOCATE PREPARE statement;
