<?php

declare(strict_types=1);

namespace Forumify\Core\Twig\Extension;

use Forumify\Core\Entity\Role;
use Forumify\Core\Entity\User;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class RoleExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('role_color', [$this, 'roleColor']),
        ];
    }

    public function roleColor(?User $user): ?string
    {
        $role = $user
            ?->getRoleEntities()
            ?->filter(fn(Role $r) => $r->isShowOnUsername() && $r->getColor() !== '#000000')
            ?->first();

        return $role ? $role->getColor() : null;
    }
}
