<?php
/**
 * UserController — Profile management
 */
class UserController extends Controller
{
    /**
     * GET /api/user/profile
     */
    public function getProfile(array $params = []): void
    {
        $userId = Session::userId();
        $profile = (new UserProfile())->findByUserId($userId);
        $user = (new User())->find($userId);

        Response::success([
            'id'         => $user['id'],
            'email'      => $user['email'],
            'role'       => $user['role'],
            'profile'    => $profile,
        ]);
    }

    /**
     * PUT /api/user/profile
     */
    public function updateProfile(array $params = []): void
    {
        $userId = Session::userId();
        $body = Request::body();
        $profileModel = new UserProfile();

        $allowed = [
            'first_name', 'last_name', 'company_name', 'phone',
            'address_street', 'address_city', 'address_zip', 'address_country',
            'billing_street', 'billing_city', 'billing_zip', 'billing_country',
        ];

        $data = [];
        foreach ($allowed as $field) {
            if (isset($body[$field])) {
                $data[$field] = trim($body[$field]);
            }
        }

        if (empty($data)) {
            Response::error('Keine Änderungen', 400, 'VALIDATION_ERROR');
        }

        $profileModel->updateByUserId($userId, $data);

        // If email changed
        if (isset($body['email']) && filter_var($body['email'], FILTER_VALIDATE_EMAIL)) {
            $userModel = new User();
            $existing = $userModel->findByEmail($body['email']);
            if ($existing && $existing['id'] !== $userId) {
                Response::error('E-Mail-Adresse bereits vergeben', 409, 'DUPLICATE');
            }
            $userModel->update($userId, ['email' => $body['email']]);
        }

        Response::success(null, 'Profil erfolgreich aktualisiert');
    }

    /**
     * POST /api/user/avatar
     */
    public function uploadAvatar(array $params = []): void
    {
        $userId = Session::userId();

        try {
            // Delete old avatar
            $profile = (new UserProfile())->findByUserId($userId);
            if ($profile && $profile['avatar_path']) {
                UploadService::deleteFile($profile['avatar_path']);
            }

            $path = UploadService::upload('file', 'avatars', $userId);
            (new UserProfile())->updateByUserId($userId, ['avatar_path' => $path]);

            Response::success(['path' => $path], 'Profilbild aktualisiert');
        } catch (Exception $e) {
            Response::error($e->getMessage(), 400, 'UPLOAD_ERROR');
        }
    }

    /**
     * POST /api/user/logo
     */
    public function uploadLogo(array $params = []): void
    {
        $userId = Session::userId();

        try {
            $profile = (new UserProfile())->findByUserId($userId);
            if ($profile && $profile['company_logo_path']) {
                UploadService::deleteFile($profile['company_logo_path']);
            }

            $path = UploadService::upload('file', 'logos', $userId);
            (new UserProfile())->updateByUserId($userId, ['company_logo_path' => $path]);

            Response::success(['path' => $path], 'Firmenlogo aktualisiert');
        } catch (Exception $e) {
            Response::error($e->getMessage(), 400, 'UPLOAD_ERROR');
        }
    }

    /**
     * PUT /api/user/password
     */
    public function changePassword(array $params = []): void
    {
        $userId = Session::userId();
        $current = Request::input('current_password', '');
        $newPw   = Request::input('new_password', '');

        try {
            (new AuthService())->changePassword($userId, $current, $newPw);
            Response::success(null, 'Passwort erfolgreich geändert');
        } catch (Exception $e) {
            Response::error($e->getMessage(), 400, 'VALIDATION_ERROR');
        }
    }
}
