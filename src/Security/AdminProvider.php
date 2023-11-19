<?php

namespace App\Security;

use App\Service\DatabaseManager;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;

class AdminProvider implements UserProviderInterface
{
    private $databaseManager;

    public function __construct(DatabaseManager $databaseManager)
    {
        $this->databaseManager = $databaseManager;
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        try {
            $adminData = $this->databaseManager->executeQuery('SELECT * FROM admin a WHERE a.login = :username', ['username' => $identifier]);
        } catch (\Exception $e) {
            // Gérer l'erreur de base de données, par exemple, en journalisant l'erreur ou en lançant une exception personnalisée.
            throw new DatabaseException('Erreur lors de l\'exécution de la requête SQL.', 0, $e);
        }
        if (empty($adminData)) {
            throw new UsernameNotFoundException();
        }
        $adminData = reset($adminData); // Obtenez le premier élément du tableau
        return new \App\Entity\AdminUser(
            $adminData['id'],
            $adminData['login'],
            $adminData['mot_de_passe']
        );
    }

    public function loadUserByUsername(string $username): UserInterface
    {
        $adminData = $this->databaseManager->executeQuery('SELECT * FROM admin WHERE login = :username', ['username' => $username]);

        if (!$adminData || !isset($adminData[0]['id'])) {
            throw new UsernameNotFoundException('User not found.');
        }
        $adminData = $adminData[0];
        return new \App\Entity\AdminUser(
            $adminData['id'],
            $adminData['username'],
            $adminData['password']
        );
    }

    public function refreshUser(UserInterface $admin): UserInterface
    {
        if (!$admin instanceof \App\Entity\AdminUser) {
            throw new UnsupportedUserException();
        }

        return $this->loadUserByIdentifier($admin->getId());
    }

    public function supportsClass(string $class): bool
    {
        return $class === \App\Entity\AdminUser::class;
    }
}
