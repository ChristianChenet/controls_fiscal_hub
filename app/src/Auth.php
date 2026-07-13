<?php
declare(strict_types=1);

namespace ControlS\Portal;

final class Auth
{
    public function __construct(private array $config, private ?Repository $repo = null)
    {
    }

    public function check(): bool
    {
        if (!$this->config['auth_enabled']) {
            return true;
        }

        return ($_SESSION['auth_ok'] ?? false) === true && !empty($_SESSION['auth_user']);
    }

    public function login(string $user, string $pass): bool
    {
        if (!$this->config['auth_enabled']) {
            return true;
        }

        if ($this->repo) {
            $account = $this->repo->findUserByEmail($user);
            if ($account && !empty($account['is_active']) && password_verify($pass, (string)$account['password_hash'])) {
                $_SESSION['auth_ok'] = true;
                $_SESSION['auth_user'] = [
                    'id' => (int)$account['id'],
                    'name' => (string)$account['name'],
                    'email' => (string)$account['email'],
                    'role' => (string)$account['role'],
                    'can_view_cost' => !empty($account['can_view_cost']) || (string)$account['role'] === 'admin',
                ];
                return true;
            }
        }

        if ($user === $this->config['auth_user'] && $pass === $this->config['auth_pass']) {
            $_SESSION['auth_ok'] = true;
            $_SESSION['auth_user'] = ['id' => 0, 'name' => 'Administrador', 'email' => $user, 'role' => 'admin'];
            return true;
        }

        return false;
    }

    public function user(): ?array
    {
        return $this->check() ? ($_SESSION['auth_user'] ?? null) : null;
    }

    public function isAdmin(): bool
    {
        $user = $this->user();
        return !$this->config['auth_enabled'] || (($user['role'] ?? '') === 'admin');
    }

    public function canViewCost(): bool
    {
        $user = $this->user();
        return !$this->config['auth_enabled'] || $this->isAdmin() || !empty($user['can_view_cost']);
    }

    public function canAccess(string $page): bool
    {
        if (!$this->config['auth_enabled'] || $this->isAdmin()) {
            return true;
        }
        return in_array($page, ['revenue', 'revenue_export', 'revenue_xml', 'documents', 'view_xml', 'document_items', 'documents_export', 'documents_xml_zip', 'documents_danfe', 'documents_danfe_zip', 'logout', 'login'], true);
    }

    public function logout(): void
    {
        unset($_SESSION['auth_ok']);
        unset($_SESSION['auth_user']);
    }

    public function require(): void
    {
        if (!$this->check()) {
            redirect_to(base_url('?page=login'));
        }
    }
}
