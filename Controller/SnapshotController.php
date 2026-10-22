<?php

namespace Kibatic\CmsBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Kibatic\CmsBundle\Entity\Snapshot;
use Kibatic\CmsBundle\Repository\SnapshotRepository;
use Kibatic\CmsBundle\Snapshot\SnapshotPreview;
use Kibatic\CmsBundle\Snapshot\Snapshots;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Snapshots of every block: take one, compare it to the current blocks, preview the site with it, restore it.
 */
class SnapshotController extends AbstractController
{
    public const CSRF_TOKEN_ID = 'kibatic_cms_snapshot';

    public function __construct(
        private readonly SnapshotRepository $snapshotRepository,
        private readonly Snapshots $snapshots,
        private readonly SnapshotPreview $preview,
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator,
        #[Autowire(param: 'kibatic_cms.preview_path')]
        private readonly string $previewPath,
    ) {
    }

    public function index(): Response
    {
        $this->denyAccessUnlessGranted(BlockController::$role);

        return $this->render('@KibaticCms/snapshot/index.html.twig', [
            'snapshots' => $this->snapshotRepository->findAllByDate(),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->denyAccessUnlessGranted(BlockController::$role);

        if ($this->isCsrfTokenValid(self::CSRF_TOKEN_ID, $request->getPayload()->getString('_token'))) {
            $name = trim($request->getPayload()->getString('name'));
            $this->snapshots->take('' !== $name ? mb_substr($name, 0, 255) : null);
            $this->addFlash('kibatic_cms', 'flash.snapshot_taken');
        }

        return $this->redirectToRoute('cms_snapshot_index');
    }

    public function show(Snapshot $snapshot): Response
    {
        $this->denyAccessUnlessGranted(BlockController::$role);

        return $this->render('@KibaticCms/snapshot/show.html.twig', [
            'snapshot' => $snapshot,
            'changes' => $this->snapshots->compare($snapshot),
        ]);
    }

    public function restore(Request $request, Snapshot $snapshot): Response
    {
        $this->denyAccessUnlessGranted(BlockController::$role);

        if ($this->isCsrfTokenValid(self::CSRF_TOKEN_ID, $request->getPayload()->getString('_token'))) {
            // The current blocks are kept in a snapshot first, to be able to undo the restore
            $this->snapshots->take($this->translator->trans('snapshot.before_restore', [
                'name' => $snapshot->getName() ?? $snapshot->getCreatedAt()->format($this->translator->trans('format.datetime', [], 'KibaticCmsBundle')),
            ], 'KibaticCmsBundle'));
            $this->snapshots->restore($snapshot);
            $this->preview->stop();
            $this->addFlash('kibatic_cms', 'flash.snapshot_restored');
        }

        return $this->redirectToRoute('cms_snapshot_index');
    }

    public function delete(Request $request, Snapshot $snapshot): Response
    {
        $this->denyAccessUnlessGranted(BlockController::$role);

        if ($this->isCsrfTokenValid(self::CSRF_TOKEN_ID, $request->getPayload()->getString('_token'))) {
            if ($this->preview->current() === $snapshot) {
                $this->preview->stop();
            }
            $this->entityManager->remove($snapshot);
            $this->entityManager->flush();
            $this->addFlash('kibatic_cms', 'flash.snapshot_deleted');
        }

        return $this->redirectToRoute('cms_snapshot_index');
    }

    public function preview(Request $request, Snapshot $snapshot): Response
    {
        $this->denyAccessUnlessGranted(BlockController::$role);

        if (!$this->isCsrfTokenValid(self::CSRF_TOKEN_ID, $request->getPayload()->getString('_token'))) {
            return $this->redirectToRoute('cms_snapshot_index');
        }

        $this->preview->start($snapshot);

        return $this->redirect($this->previewPath);
    }

    public function exitPreview(Request $request): Response
    {
        $this->denyAccessUnlessGranted(BlockController::$role);

        if ($this->isCsrfTokenValid(self::CSRF_TOKEN_ID, $request->getPayload()->getString('_token'))) {
            $this->preview->stop();
        }

        $referer = $request->headers->get('referer');

        // Same host only: behind a proxy, the scheme seen by PHP may not be the one of the browser
        return $referer && parse_url($referer, \PHP_URL_HOST) === $request->getHost()
            ? $this->redirect($referer)
            : $this->redirectToRoute('cms_snapshot_index');
    }
}
