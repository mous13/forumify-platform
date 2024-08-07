<?php

declare(strict_types=1);

namespace Forumify\Cms\Controller\Admin;

use Forumify\Cms\Entity\Resource;
use Forumify\Cms\Form\ResourceType;
use Forumify\Cms\Repository\ResourceRepository;
use Forumify\Core\Service\MediaService;
use League\Flysystem\FilesystemOperator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('resources', 'resource_')]
#[IsGranted('forumify.admin.cms.resources.view')]
class ResourceController extends AbstractController
{
    public function __construct(
        private readonly ResourceRepository $resourceRepository,
        private readonly MediaService $mediaService,
        private readonly FilesystemOperator $resourceStorage,
    ) {
    }

    #[Route('', 'list')]
    public function list(): Response
    {
        return $this->render('@Forumify/admin/cms/resource/list.html.twig');
    }

    #[Route('/create', 'create')]
    #[IsGranted('forumify.admin.cms.resources.manage')]
    public function create(Request $request): Response
    {
        return $this->handleForm($request, null);
    }

    #[Route('/{slug}', 'edit')]
    #[IsGranted('forumify.admin.cms.resources.manage')]
    public function edit(Request $request, Resource $resource): Response
    {
        return $this->handleForm($request, $resource);
    }

    #[Route('/{slug}/delete', 'delete')]
    #[IsGranted('forumify.admin.cms.resources.manage')]
    public function delete(Resource $resource, Request $request): Response
    {
        if (!$request->get('confirmed')) {
            return $this->render('@Forumify/admin/cms/resource/delete.html.twig', [
                'resource' => $resource,
            ]);
        }

        $this->resourceRepository->remove($resource);

        $this->addFlash('success', 'flashes.resource_removed');
        return $this->redirectToRoute('forumify_admin_cms_resource_list');
    }

    private function handleForm(Request $request, ?Resource $resource): Response
    {
        $form = $this->createForm(ResourceType::class, $resource);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var Resource $resource */
            $resource = $form->getData();

            $file = $form->get('file')->getData();
            if ($file instanceof UploadedFile) {
                $path = $this->mediaService->saveToFilesystem($this->resourceStorage, $file);
                $resource->setPath($path);
            }

            $this->resourceRepository->save($resource);

            $this->addFlash('success', 'flashes.resource_saved');
            return $this->redirectToRoute('forumify_admin_cms_resource_list');
        }

        return $this->render('@Forumify/admin/cms/resource/resource.html.twig', [
            'form' => $form->createView(),
            'resource' => $resource,
        ]);
    }
}
