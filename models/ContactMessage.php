<?php
/**
 * ContactMessage Model — Website contact form messages
 */
class ContactMessage extends Model
{
    protected string $table = 'contact_messages';

    /**
     * Get messages for user with search, filter, sort, pagination
     */
    public function getByUser(int $userId, int $limit = 30, int $offset = 0, string $status = '', string $search = '', string $sort = 'date_desc'): array
    {
        $sql = "SELECT cm.*, w.domain, w.name as website_name FROM {$this->table} cm
                LEFT JOIN websites w ON cm.website_id = w.id
                WHERE cm.user_id = ?";
        $params = [$userId];

        if ($status === 'unread') {
            $sql .= " AND cm.status = 'unread'";
        } elseif ($status === 'archived') {
            $sql .= " AND cm.status = 'archived'";
        } elseif ($status === 'read') {
            $sql .= " AND cm.status = 'read'";
        }

        if ($search !== '') {
            $sql .= " AND (cm.sender_name LIKE ? OR cm.sender_email LIKE ? OR cm.subject LIKE ? OR cm.message LIKE ?)";
            $term = '%' . $search . '%';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        $orderDir = ($sort === 'date_asc') ? 'ASC' : 'DESC';
        $sql .= " ORDER BY cm.created_at {$orderDir} LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Count messages for user
     */
    public function countByUser(int $userId, string $status = '', string $search = ''): int
    {
        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE user_id = ?";
        $params = [$userId];

        if ($status === 'unread') {
            $sql .= " AND status = 'unread'";
        } elseif ($status === 'archived') {
            $sql .= " AND status = 'archived'";
        } elseif ($status === 'read') {
            $sql .= " AND status = 'read'";
        }

        if ($search !== '') {
            $sql .= " AND (sender_name LIKE ? OR sender_email LIKE ? OR subject LIKE ? OR message LIKE ?)";
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
     * Count unread for user (for sidebar badge)
     */
    public function countUnread(int $userId): int
    {
        $stmt = $this->db()->prepare("SELECT COUNT(*) FROM {$this->table} WHERE user_id = ? AND status = 'unread'");
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Get single message (owned by user)
     */
    public function getByIdAndUser(int $id, int $userId): ?array
    {
        $stmt = $this->db()->prepare("SELECT cm.*, w.domain, w.name as website_name FROM {$this->table} cm
            LEFT JOIN websites w ON cm.website_id = w.id
            WHERE cm.id = ? AND cm.user_id = ?");
        $stmt->execute([$id, $userId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Update status
     */
    public function setStatus(int $id, int $userId, string $status): bool
    {
        $stmt = $this->db()->prepare("UPDATE {$this->table} SET status = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$status, $id, $userId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Delete message
     */
    public function deleteByUser(int $id, int $userId): bool
    {
        $stmt = $this->db()->prepare("DELETE FROM {$this->table} WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Create from webhook
     */
    public function createFromWebhook(array $data): int
    {
        $stmt = $this->db()->prepare("INSERT INTO {$this->table}
            (website_id, user_id, sender_name, sender_email, sender_phone, subject, message, ip_address)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['website_id'],
            $data['user_id'],
            $data['sender_name'] ?? '',
            $data['sender_email'] ?? '',
            $data['sender_phone'] ?? '',
            $data['subject'] ?? '',
            $data['message'] ?? '',
            $data['ip_address'] ?? '',
        ]);
        return (int) $this->db()->lastInsertId();
    }
}
