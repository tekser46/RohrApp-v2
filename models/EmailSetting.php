<?php
/**
 * EmailSetting Model
 */
class EmailSetting extends Model
{
    protected string $table = 'email_settings';

    /**
     * Get settings for a user
     */
    public function getByUserId(int $userId): ?array
    {
        return $this->findBy('user_id', $userId);
    }

    /**
     * Save settings (insert or update)
     */
    public function saveForUser(int $userId, array $data): void
    {
        $existing = $this->getByUserId($userId);
        if ($existing) {
            $this->update($existing['id'], $data);
        } else {
            $data['user_id'] = $userId;
            $this->create($data);
        }
    }
}
