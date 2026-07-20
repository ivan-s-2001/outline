<?php

declare(strict_types=1);

namespace App\Domain\Attachment;

use Psr\Http\Message\UploadedFileInterface;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Throwable;
use Yiisoft\Db\Connection\ConnectionInterface;

final readonly class AttachmentService
{
    private string $root;
    private int $maximumSize;

    public function __construct(private ConnectionInterface $database)
    {
        $configuredRoot = getenv('FILE_STORAGE_LOCAL_ROOT_DIR');
        if ($configuredRoot === false || trim($configuredRoot) === '') {
            $configuredRoot = dirname(__DIR__, 3) . '/runtime/uploads';
        }
        $this->root = rtrim(str_replace('\\', '/', (string) $configuredRoot), '/');

        $configuredSize = getenv('FILE_STORAGE_UPLOAD_MAX_SIZE');
        $this->maximumSize = $configuredSize !== false && ctype_digit((string) $configuredSize)
            ? (int) $configuredSize
            : 262_144_000;
    }

    /** @return list<array<string, mixed>> */
    public function list(string $teamId, string $requesterId, bool $admin, ?string $documentId, ?string $userId, int $offset, int $limit): array
    {
        $where = ['team_id = :team_id', 'deleted_at IS NULL'];
        $params = [':team_id' => $teamId];
        $where[] = 'user_id = :user_id';
        $params[':user_id'] = $admin && $userId !== null ? $userId : $requesterId;
        if ($documentId !== null) {
            $where[] = 'document_id = :document_id';
            $params[':document_id'] = $documentId;
        }
        $offset = max(0, $offset);
        $limit = max(1, min(100, $limit));

        return $this->database->createCommand(
            'SELECT * FROM attachments WHERE ' . implode(' AND ', $where)
            . ' ORDER BY created_at DESC LIMIT ' . $limit . ' OFFSET ' . $offset,
            $params,
        )->queryAll();
    }

    /** @return array<string, mixed>|null */
    public function find(string $id): ?array
    {
        $row = $this->database->createCommand(
            'SELECT * FROM attachments WHERE id = :id AND deleted_at IS NULL LIMIT 1',
            [':id' => $id],
        )->queryOne();

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed> */
    public function create(
        string $teamId,
        string $userId,
        string $name,
        string $contentType,
        int $size,
        ?string $documentId,
        string $preset,
        ?string $requestedId = null,
    ): array {
        if ($size < 0 || $size > $this->maximumSize) {
            throw new RuntimeException('File size exceeds the configured upload limit.');
        }
        if ($documentId !== null) {
            $exists = (int) $this->database->createCommand(
                'SELECT COUNT(*) FROM documents WHERE id = :id AND team_id = :team_id AND deleted_at IS NULL',
                [':id' => $documentId, ':team_id' => $teamId],
            )->queryScalar();
            if ($exists !== 1) {
                throw new RuntimeException('Document not found.');
            }
        }

        $id = $requestedId !== null && Uuid::isValid($requestedId)
            ? $requestedId
            : Uuid::uuid4()->toString();
        $safeName = $this->safeName($name);
        $key = implode('/', [$teamId, $userId, $id, $safeName]);
        $acl = in_array($preset, ['avatar', 'emoji'], true) ? 'public-read' : 'private';

        $this->database->createCommand(
            <<<'SQL'
            INSERT INTO attachments (
                id, team_id, user_id, document_id, key_path, name, content_type,
                size, acl, created_at, updated_at
            ) VALUES (
                :id, :team_id, :user_id, :document_id, :key_path, :name, :content_type,
                :size, :acl, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6)
            )
            SQL,
            [
                ':id' => $id,
                ':team_id' => $teamId,
                ':user_id' => $userId,
                ':document_id' => $documentId,
                ':key_path' => $key,
                ':name' => $safeName,
                ':content_type' => $contentType !== '' ? $contentType : 'application/octet-stream',
                ':size' => $size,
                ':acl' => $acl,
            ],
        )->execute();

        return $this->find($id) ?? throw new RuntimeException('Created attachment was not found.');
    }

    /** @return array<string, mixed> */
    public function store(string $id, UploadedFileInterface $uploadedFile): array
    {
        $row = $this->find($id);
        if ($row === null) {
            throw new RuntimeException('Attachment not found.');
        }
        if ($uploadedFile->getError() !== UPLOAD_ERR_OK) {
            throw new RuntimeException('File upload failed with code ' . $uploadedFile->getError() . '.');
        }

        $declaredSize = (int) $row['size'];
        $actualSize = $uploadedFile->getSize();
        if ($actualSize === null || $actualSize < 0 || $actualSize > $this->maximumSize) {
            throw new RuntimeException('Uploaded file has an invalid size.');
        }
        if ($declaredSize > 0 && $actualSize > $declaredSize) {
            throw new RuntimeException('Uploaded file is larger than declared.');
        }

        $path = $this->path((string) $row['key_path']);
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create attachment directory.');
        }

        try {
            $uploadedFile->moveTo($path);
        } catch (Throwable $exception) {
            throw new RuntimeException('Unable to store uploaded file.', 0, $exception);
        }

        $detectedType = $this->detectMime($path) ?? (string) $row['content_type'];
        $this->database->createCommand(
            <<<'SQL'
            UPDATE attachments
            SET size = :size, content_type = :content_type, updated_at = UTC_TIMESTAMP(6)
            WHERE id = :id AND deleted_at IS NULL
            SQL,
            [':size' => $actualSize, ':content_type' => $detectedType, ':id' => $id],
        )->execute();

        return $this->find($id) ?? throw new RuntimeException('Stored attachment was not found.');
    }

    public function delete(string $id): bool
    {
        $row = $this->find($id);
        if ($row === null) {
            return false;
        }

        $affected = $this->database->createCommand(
            'UPDATE attachments SET deleted_at = UTC_TIMESTAMP(6), updated_at = UTC_TIMESTAMP(6) WHERE id = :id AND deleted_at IS NULL',
            [':id' => $id],
        )->execute();
        if ($affected > 0) {
            $path = $this->path((string) $row['key_path']);
            if (is_file($path)) {
                @unlink($path);
            }
        }

        return $affected > 0;
    }

    public function filePath(array $row): string
    {
        $path = $this->path((string) $row['key_path']);
        if (!is_file($path)) {
            throw new RuntimeException('Attachment file is missing.');
        }

        return $path;
    }

    public function maximumSize(): int
    {
        return $this->maximumSize;
    }

    private function path(string $key): string
    {
        $key = ltrim(str_replace('\\', '/', $key), '/');
        if ($key === '' || str_contains($key, '../') || str_starts_with($key, '..')) {
            throw new RuntimeException('Unsafe attachment path.');
        }

        return $this->root . '/' . $key;
    }

    private function safeName(string $name): string
    {
        $name = trim(str_replace(["\0", '/', '\\'], '', $name));
        $name = preg_replace('/[\x00-\x1F\x7F]+/u', '', $name) ?? '';
        $name = trim($name, ". \t\n\r\0\x0B");
        if ($name === '') {
            return 'file';
        }

        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $base = pathinfo($name, PATHINFO_FILENAME);
        $base = mb_substr($base !== '' ? $base : 'file', 0, 180);
        $extension = preg_replace('/[^A-Za-z0-9]+/', '', $extension) ?? '';

        return $extension !== '' ? $base . '.' . mb_substr($extension, 0, 20) : $base;
    }

    private function detectMime(string $path): ?string
    {
        if (!class_exists(\finfo::class)) {
            return null;
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $type = $finfo->file($path);

        return is_string($type) && $type !== '' ? $type : null;
    }
}
