<?php
namespace App\Security;

interface AdminUserInterface
{
    public function getUsername(): string;

    public function getPassword(): string;
}