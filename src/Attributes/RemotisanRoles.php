<?php

namespace PayMe\Remotisan\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class RemotisanRoles
{
    private array $roles;

    public function __construct(array $roles)
    {
        $this->roles = $roles;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function hasRole(string $role): bool
    {
        return in_array('*', $this->roles) || in_array($role, $this->roles);
    }
}