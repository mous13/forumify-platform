<?php

declare(strict_types=1);

namespace Forumify\Core\Twig\Extension;

use Forumify\Admin\MenuBuilder\AdminMenuManager;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class MenuExtension extends AbstractExtension
{
    public function __construct(
        private readonly AdminMenuManager $adminMenuManager
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('forum_menu', [MenuRuntime::class, 'buildForumMenu'], ['is_safe' => ['html']]),
            new TwigFunction('admin_menu', $this->adminMenuManager->getMenu(...)),
        ];
    }
}
