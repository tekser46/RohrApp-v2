<?php
/**
 * UserLicense Model
 */
class UserLicense extends Model
{
    protected string $table = 'user_licenses';

    /**
     * Get current active license for a user (with package info)
     */
    public function getCurrentLicense(int $userId): ?array
    {
        $stmt = $this->db()->prepare("
            SELECT ul.*, p.slug AS package_slug, p.name AS package_name, p.description AS package_description,
                   p.price_monthly, p.features, p.max_sipgate_numbers, p.max_websites,
                   p.has_email_inbox, p.has_call_logs, p.has_messages
            FROM {$this->table} ul
            JOIN packages p ON p.id = ul.package_id
            WHERE ul.user_id = ?
            ORDER BY ul.created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: null;
    }
}
