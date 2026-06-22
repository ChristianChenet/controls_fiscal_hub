<?php
declare(strict_types=1);

namespace ControlS\Portal;

final class Auth
{
    public function __construct(private array $config)
    {
    }

    public function check(): bool
    {
        if (!$this->config['auth_enabled']) {
            return true;
        }

        return ($_SESSION['auth_ok'] ?? false) === true;
    }

    public function login(string $user, string $pass): bool
    {
        if (!$this->config['auth_enabled']) {
            return true;
        }

        if ($user === $this->config['auth_user'] && $pass === $this->config['auth_pass']) {
            $_SESSION['auth_ok'] = true;
            return true;
        }

        return false;
    }

    public function logout(): void
    {
        unset($_SESSION['auth_ok']);
    }

    public function require(): void
    {
        if (!$this->check()) {
            redirect_to(base_url('?page=login'));
        }
    }
}
