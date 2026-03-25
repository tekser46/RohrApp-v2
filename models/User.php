<?php
/**
 * User Model
 */
class User extends Model
{
    protected string $table = 'users';

    /**
     * Find user by email
     */
    public function findByEmail(string $email): ?array
    {
        return $this->findBy('email', $email);
    }

    /**
     * Find user by remember token
     */
    public function findByRememberToken(string $token): ?array
    {
        return $this->findBy('remember_token', $token);
    }

    /**
     * Create user with profile + license (registration)
     */
    public function register(string $email, string $passwordHash): int
    {
        $db = $this->db();

        $db->beginTransaction();
        try {
            // Create user
            $userId = $this->create([
                'email'         => $email,
                'password_hash' => $passwordHash,
                'role'          => 'user',
                'is_active'     => 1,
            ]);

            // Create empty profile
            $db->prepare("INSERT INTO user_profiles (user_id) VALUES (?)")->execute([$userId]);

            // Assign Demo package license
            $demoPackage = $db->query("SELECT id FROM packages WHERE slug = 'demo' LIMIT 1")->fetch();
            if ($demoPackage) {
                $licenseKey = strtoupper(implode('-', [
                    substr(md5(uniqid(rand(), true)), 0, 4),
                    substr(md5(uniqid(rand(), true)), 0, 4),
                    substr(md5(uniqid(rand(), true)), 0, 4),
                    substr(md5(uniqid(rand(), true)), 0, 4),
                ]));

                $db->prepare("INSERT INTO user_licenses (user_id, package_id, license_key, status, starts_at) VALUES (?, ?, ?, 'trial', NOW())")
                   ->execute([$userId, $demoPackage['id'], $licenseKey]);
            }

            $db->commit();
            return $userId;

        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Update last login info
     */
    public function updateLastLogin(int $userId, string $ip): void
    {
        $this->update($userId, [
            'last_login_at' => date('Y-m-d H:i:s'),
            'last_login_ip' => $ip,
        ]);
    }
}
