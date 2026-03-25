<?php
/**
 * Package Model
 */
class Package extends Model
{
    protected string $table = 'packages';

    /**
     * Get all active packages ordered by sort_order
     */
    public function getAllActive(): array
    {
        return $this->db()->query(
            "SELECT * FROM {$this->table} WHERE is_active = 1 ORDER BY sort_order ASC"
        )->fetchAll();
    }

    /**
     * Find by slug
     */
    public function findBySlug(string $slug): ?array
    {
        return $this->findBy('slug', $slug);
    }
}
