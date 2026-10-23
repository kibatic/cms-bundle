<?php

namespace Kibatic\CmsBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Kibatic\CmsBundle\BlockTypeChain;
use Kibatic\CmsBundle\Entity\Version;
use Kibatic\CmsBundle\Repository\VersionRepository;
use Kibatic\CmsBundle\Version\VersionPreview;
use Kibatic\CmsBundle\Version\Versions;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Versions of every block: take one, compare it to the current blocks, preview the site with it, restore it.
 */
class VersionController extends AbstractController
{
    public const CSRF_TOKEN_ID = 'kibatic_cms_version';

    public function __construct(
        private readonly VersionRepository $versionRepository,
        private readonly Versions $versions,
        private readonly VersionPreview $preview,
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator,
        #[Autowire(param: 'kibatic_cms.preview_path')]
        private readonly string $previewPath,
    ) {
    }

    public function index(): Response
    {
        $this->denyAccessUnlessGranted(BlockController::$role);

        return $this->render('@KibaticCms/version/index.html.twig', [
            'versions' => $this->versionRepository->findAllByDate(),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->denyAccessUnlessGranted(BlockController::$role);

        if ($this->isCsrfTokenValid(self::CSRF_TOKEN_ID, $request->getPayload()->getString('_token'))) {
            $name = trim($request->getPayload()->getString('name'));
            $version = $this->versions->take('' !== $name ? mb_substr($name, 0, 255) : null);

            // "Take and publish" button
            if ($request->getPayload()->getBoolean('publish')) {
                $this->versions->publish($version);
                $this->addFlash('kibatic_cms', 'flash.version_taken_published');
            } else {
                $this->addFlash('kibatic_cms', 'flash.version_taken');
            }
        }

        return $this->redirectToRoute('cms_version_index');
    }

    public function show(Version $version): Response
    {
        $this->denyAccessUnlessGranted(BlockController::$role);

        return $this->render('@KibaticCms/version/show.html.twig', [
            'version' => $version,
            'changes' => $this->versions->compare($version),
        ]);
    }

    public function rename(Request $request, Version $version): Response
    {
        $this->denyAccessUnlessGranted(BlockController::$role);

        if ($this->isCsrfTokenValid(self::CSRF_TOKEN_ID, $request->getPayload()->getString('_token'))) {
            $name = trim($request->getPayload()->getString('name'));
            $version->setName('' !== $name ? mb_substr($name, 0, 255) : null);
            $this->entityManager->flush();
            $this->addFlash('kibatic_cms', 'flash.version_renamed');
        }

        return $this->redirectToRoute('cms_version_show', ['id' => $version->getId()]);
    }

    /**
     * Edits the content of a block of the version (?slug=…&language=…, no language: shared by every language), with
     * the block editor without its slug and language fields
     */
    public function editBlock(Request $request, Version $version, BlockTypeChain $blockTypes): Response
    {
        $this->denyAccessUnlessGranted(BlockController::$role);

        $slug = $request->query->getString('slug');
        $language = '' !== $request->query->getString('language') ? $request->query->getString('language') : null;
        $block = $version->getBlock($slug, $language) ?? throw $this->createNotFoundException();

        $form = $this->createForm($blockTypes->getBlockType($block->getType())::class, $block)
            ->remove('slug')
            ->remove('language');
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $version->setBlockContent($slug, $language, $block->getContent());
            $this->entityManager->flush();
            $this->addFlash('kibatic_cms', 'flash.version_block_saved');

            return $this->redirectToRoute('cms_version_show', ['id' => $version->getId()]);
        }

        return $this->render('@KibaticCms/version/edit_block.html.twig', [
            'version' => $version,
            'block' => $block,
            'form' => $form->createView(),
        ]);
    }

    public function restore(Request $request, Version $version): Response
    {
        $this->denyAccessUnlessGranted(BlockController::$role);

        if ($this->isCsrfTokenValid(self::CSRF_TOKEN_ID, $request->getPayload()->getString('_token'))) {
            // The current blocks are kept in a version first, to be able to undo the restore
            $this->versions->take($this->translator->trans('version.before_restore', [
                'name' => $version->getName() ?? $version->getCreatedAt()->format($this->translator->trans('format.datetime', [], 'KibaticCmsBundle')),
            ], 'KibaticCmsBundle'));
            $this->versions->restore($version);
            $this->preview->stop();
            $this->addFlash('kibatic_cms', 'flash.version_restored');
        }

        return $this->redirectToRoute('cms_version_index');
    }

    public function publish(Request $request, Version $version): Response
    {
        $this->denyAccessUnlessGranted(BlockController::$role);

        if ($this->isCsrfTokenValid(self::CSRF_TOKEN_ID, $request->getPayload()->getString('_token'))) {
            $this->versions->publish($version);
            $this->addFlash('kibatic_cms', 'flash.version_published');
        }

        return $this->redirectToRoute('cms_version_index');
    }

    /**
     * Publishes the draft (the current blocks) in a new version
     */
    public function publishDraft(Request $request): Response
    {
        $this->denyAccessUnlessGranted(BlockController::$role);

        if ($this->isCsrfTokenValid(self::CSRF_TOKEN_ID, $request->getPayload()->getString('_token'))) {
            $version = $this->versions->take($this->translator->trans('version.draft_name', [
                'date' => (new \DateTimeImmutable())->format($this->translator->trans('format.datetime', [], 'KibaticCmsBundle')),
            ], 'KibaticCmsBundle'));
            $this->versions->publish($version);
            $this->preview->stop();
            $this->addFlash('kibatic_cms', 'flash.draft_published');
        }

        return $this->redirectBack($request);
    }

    /**
     * The admin sees the published version instead of the draft (exitPreview() goes back to the draft)
     */
    public function showPublished(Request $request): Response
    {
        $this->denyAccessUnlessGranted(BlockController::$role);

        if ($this->isCsrfTokenValid(self::CSRF_TOKEN_ID, $request->getPayload()->getString('_token'))) {
            $this->preview->showPublished();
        }

        return $this->redirectBack($request);
    }

    public function unpublish(Request $request): Response
    {
        $this->denyAccessUnlessGranted(BlockController::$role);

        if ($this->isCsrfTokenValid(self::CSRF_TOKEN_ID, $request->getPayload()->getString('_token'))) {
            $this->versions->unpublish();
            $this->addFlash('kibatic_cms', 'flash.version_unpublished');
        }

        return $this->redirectToRoute('cms_version_index');
    }

    public function delete(Request $request, Version $version): Response
    {
        $this->denyAccessUnlessGranted(BlockController::$role);

        // The published version is unpublished first (its button is hidden): deleting it would change the public site
        if (!$version->isPublished() && $this->isCsrfTokenValid(self::CSRF_TOKEN_ID, $request->getPayload()->getString('_token'))) {
            if ($this->preview->current() === $version) {
                $this->preview->stop();
            }
            $this->entityManager->remove($version);
            $this->entityManager->flush();
            $this->addFlash('kibatic_cms', 'flash.version_deleted');
        }

        return $this->redirectToRoute('cms_version_index');
    }

    public function preview(Request $request, Version $version): Response
    {
        $this->denyAccessUnlessGranted(BlockController::$role);

        if (!$this->isCsrfTokenValid(self::CSRF_TOKEN_ID, $request->getPayload()->getString('_token'))) {
            return $this->redirectToRoute('cms_version_index');
        }

        $this->preview->start($version);

        return $this->redirect($this->previewPath);
    }

    public function exitPreview(Request $request): Response
    {
        $this->denyAccessUnlessGranted(BlockController::$role);

        if ($this->isCsrfTokenValid(self::CSRF_TOKEN_ID, $request->getPayload()->getString('_token'))) {
            $this->preview->stop();
        }

        // "Preview" button of the draft in the admin
        if ($request->getPayload()->getBoolean('site')) {
            return $this->redirect($this->previewPath);
        }

        return $this->redirectBack($request);
    }

    /**
     * Back to the page of the site the banner's button was on
     */
    private function redirectBack(Request $request): Response
    {
        $referer = $request->headers->get('referer');

        // Same host only: behind a proxy, the scheme seen by PHP may not be the one of the browser
        return $referer && parse_url($referer, \PHP_URL_HOST) === $request->getHost()
            ? $this->redirect($referer)
            : $this->redirectToRoute('cms_version_index');
    }
}
