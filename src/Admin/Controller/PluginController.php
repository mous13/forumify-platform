<?php

declare(strict_types=1);

namespace Forumify\Admin\Controller;

use Forumify\Core\Repository\PluginRepository;
use Forumify\Plugin\Service\PluginService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/plugins', 'plugin')]
class PluginController extends AbstractController
{
    public function __construct(
        private readonly PluginRepository $pluginRepository,
        private readonly PluginService $pluginService,
    ) {
    }

    #[Route('', '_list')]
    public function list(): Response
    {
        $activePlugins = $this->pluginRepository->findBy(['active' => true], ['package' => 'ASC']);
        $inactivePlugins = $this->pluginRepository->findInactivePluginsExcludingPackage('forumify/forumify-platform');

        $latestVersions = $this->pluginService->getLatestVersions();
        $platformVersions = $latestVersions['forumify/forumify-platform'] ?? null;

        return $this->render('@Forumify/admin/plugin/pluginManager.html.twig', [
            'activePlugins' => $activePlugins,
            'inactivePlugins' => $inactivePlugins,
            'pluginService' => $this->pluginService,
            'platformVersions' => $platformVersions,
        ]);
    }

    #[Route('/refresh', '_refresh')]
    public function refresh(): Response
    {
        $this->pluginService->refresh();
        return $this->redirectToRoute('forumify_admin_plugin_list');
    }
}
