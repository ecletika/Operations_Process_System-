<?php

declare(strict_types=1);

namespace App\Modules\Auth\Services;

use App\Core\Session;
use App\Modules\Auth\Repositories\UserRepository;
use App\Traits\AuditTrait;

/**
 * "Meu Perfil" (👤) - o próprio utilizador gere os seus dados e a sua
 * password, sem precisar de um Administrador. Toda a regra de negócio
 * (validação, verificação da password atual, hashing) vive aqui.
 */
final class ProfileService
{
    use AuditTrait;

    public function __construct(
        private readonly UserRepository $users = new UserRepository(),
    ) {
    }

    /**
     * Atualiza nome e email do próprio utilizador.
     *
     * @return string[] erros (vazio = sucesso)
     */
    public function updateProfile(int $userId, array $input): array
    {
        $firstName = trim((string) ($input['first_name'] ?? ''));
        $lastName = trim((string) ($input['last_name'] ?? ''));
        $email = trim((string) ($input['email'] ?? ''));

        $errors = [];

        if ($firstName === '') {
            $errors[] = 'O nome é obrigatório.';
        }
        if ($lastName === '') {
            $errors[] = 'O apelido é obrigatório.';
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Indique um email válido.';
        } else {
            $existing = $this->users->findByEmail($email);
            if ($existing !== null && (int) $existing['id'] !== $userId) {
                $errors[] = 'Já existe outro utilizador com esse email.';
            }
        }

        if ($errors !== []) {
            return $errors;
        }

        $this->users->updateOwnProfile($userId, $firstName, $lastName, $email);

        // Mantém o nome mostrado no cabeçalho/sidebar em sincronia.
        Session::put('user_name', $firstName . ' ' . $lastName);

        $this->logAudit('UPDATE', 'tb_user', $userId, null, ['self_profile' => true]);

        return [];
    }

    /**
     * Altera a password do próprio utilizador (exige a password atual).
     *
     * @return string[] erros (vazio = sucesso)
     */
    public function changePassword(int $userId, array $input): array
    {
        $current = (string) ($input['current_password'] ?? '');
        $new = (string) ($input['new_password'] ?? '');
        $confirm = (string) ($input['confirm_password'] ?? '');

        $user = $this->users->findById($userId);
        if ($user === null) {
            return ['Utilizador não encontrado.'];
        }

        $errors = [];

        if (!password_verify($current, (string) $user['password'])) {
            $errors[] = 'A password atual está incorreta.';
        }

        if (strlen($new) < 8) {
            $errors[] = 'A nova password deve ter pelo menos 8 caracteres.';
        }

        if ($new !== $confirm) {
            $errors[] = 'A confirmação não coincide com a nova password.';
        }

        if ($current !== '' && $new !== '' && $current === $new) {
            $errors[] = 'A nova password tem de ser diferente da atual.';
        }

        if ($errors !== []) {
            return $errors;
        }

        $this->users->updatePassword($userId, password_hash($new, PASSWORD_BCRYPT));
        $this->logAudit('UPDATE', 'tb_user', $userId, null, ['password_changed' => true]);

        return [];
    }
}
