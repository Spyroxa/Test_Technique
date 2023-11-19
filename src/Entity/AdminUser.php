<?php

namespace App\Entity;

use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use App\Security\AdminUserInterface;

class AdminUser implements UserInterface, PasswordAuthenticatedUserInterface
{
    private $id;
    private $login;
    private $mot_de_passe;

    public function __construct($id, $login, $mot_de_passe)
    {
        $this->id = $id;
        $this->login = $login;
        $this->mot_de_passe = $mot_de_passe;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): string
    {
        return $this->login;
    }

    public function getPassword(): string
    {
        return $this->mot_de_passe;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        

        return array_unique($roles);
    }

    public function getSalt(): ?string
    {
        return null;
    }

    public function eraseCredentials(): void
    {
    }
    public function getUserIdentifier(): string
    {
        return $this->getLogin();
    }
    public function getPasswordHash(): string
    {
        return $this->getPassword();
    }
}
