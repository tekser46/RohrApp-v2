<?php
/**
 * EmailCache Model — Cached IMAP emails
 */
class EmailCache extends Model
{
    protected string $table = 'email_cache';

    /**
     * Get cached emails for user (paginated, newest first)
     * @param string $folder IMAP folder filter (empty = all)
     */
    public function getByUser(int $userId, int $limit = 30, int $offset = 0, string $filter = '', string $folder = '', string $search = '', string $sort = 'date_desc'): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE user_id = ?";
        $params = [$userId];

        if ($filter === 'unread') {
            $sql .= " AND is_read = 0";
        } elseif ($filter === 'starred') {
            $sql .= " AND is_starred = 1";
        }

        if ($folder !== '') {
            $sql .= " AND folder = ?";
            $params[] = $folder;
        }

        if ($search !== '') {
            $sql .= " AND (subject LIKE ? OR from_name LIKE ? OR from_address LIKE ? OR body_preview LIKE ?)";
            $term = '%' . $search . '%';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        $orderDir = ($sort === 'date_asc') ? 'ASC' : 'DESC';
        $sql .= " ORDER BY mail_date {$orderDir} LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Count emails for user
     */
    public function countByUser(int $userId, string $filter = '', string $folder = '', string $search = ''): int
    {
        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE user_id = ?";
        $params = [$userId];

        if ($filter === 'unread') {
            $sql .= " AND is_read = 0";
        } elseif ($filter === 'starred') {
            $sql .= " AND is_starred = 1";
        }

        if ($folder !== '') {
            $sql .= " AND folder = ?";
            $params[] = $folder;
        }

        if ($search !== '') {
            $sql .= " AND (subject LIKE ? OR from_name LIKE ? OR from_address LIKE ? OR body_preview LIKE ?)";
            $term = '%' . $search . '%';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Check if email already cached (by message_id — RFC Message-ID, never changes)
     */
    public function exists(int $userId, string $messageUid, string $messageId = ''): bool
    {
        // Prefer message_id (RFC) — unique across all folders
        if ($messageId) {
            $stmt = $this->db()->prepare("SELECT 1 FROM {$this->table} WHERE user_id = ? AND message_id = ? LIMIT 1");
            $stmt->execute([$userId, $messageId]);
            if ($stmt->fetch()) return true;
        }
        // Fallback to message_uid
        $stmt = $this->db()->prepare("SELECT 1 FROM {$this->table} WHERE user_id = ? AND message_uid = ? LIMIT 1");
        $stmt->execute([$userId, $messageUid]);
        return (bool) $stmt->fetch();
    }

    /**
     * Upsert cached email
     */
    public function upsert(int $userId, string $messageUid, array $data): void
    {
        if ($this->exists($userId, $messageUid)) return; // Skip if already cached

        $data['user_id'] = $userId;
        $data['message_uid'] = $messageUid;
        $this->create($data);
    }

    /**
     * Get single email detail
     */
    public function getDetail(int $id, int $userId): ?array
    {
        $stmt = $this->db()->prepare("SELECT * FROM {$this->table} WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Mark as read
     */
    public function markRead(int $id, int $userId): void
    {
        $this->db()->prepare("UPDATE {$this->table} SET is_read = 1 WHERE id = ? AND user_id = ?")->execute([$id, $userId]);
    }

    /**
     * Toggle star on an email
     * @return int New is_starred value (0 or 1)
     */
    public function toggleStar(int $id, int $userId): int
    {
        $this->db()->prepare(
            "UPDATE {$this->table} SET is_starred = IF(is_starred = 1, 0, 1) WHERE id = ? AND user_id = ?"
        )->execute([$id, $userId]);

        $stmt = $this->db()->prepare("SELECT is_starred FROM {$this->table} WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Set color marking on an email
     * @param string|null $color One of: red, yellow, green, blue, or null to clear
     */
    public function setColor(int $id, int $userId, ?string $color): void
    {
        $allowed = ['red', 'yellow', 'green', 'blue', null];
        if (!in_array($color, $allowed, true)) {
            $color = null;
        }
        $this->db()->prepare(
            "UPDATE {$this->table} SET color = ? WHERE id = ? AND user_id = ?"
        )->execute([$color, $id, $userId]);
    }
}
