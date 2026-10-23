<?php

namespace Kibatic\CmsBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Kibatic\CmsBundle\BlockTypeChain;
use Kibatic\CmsBundle\Entity\Block;
use Kibatic\CmsBundle\Repository\BlockRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class BlockController extends AbstractController
{
    public static string $role = 'ROLE_CMS';

    public function __construct(
        private BlockRepository $blockRepository,
        #[Autowire(param: 'kibatic_cms.ai.enabled')]
        private bool $aiEnabled,
    ) {
    }

    public function index(Request $request, BlockRepository $repository, BlockTypeChain $blockTypeChain): Response
    {
        $this->denyAccessUnlessGranted(self::$role);

        $languages = $repository->getExistingLanguages();

        $blocks = $repository->findAll();
        $blockTypeNames = $blockTypeChain->getBlockTypeNames();

        $blocksBySlug = [];

        foreach ($blocks as $block) {
            $blocksBySlug[$block->getSlug()][] = $block;
        }

        return $this->render('@KibaticCms/block/index.html.twig', [
            'i18nDisplay' => !empty($languages),
            'languages' => $languages,
            'blocksBySlug' => $blocksBySlug,
            'blocks' => $blocks,
            'blockTypeNames' => $blockTypeNames,
            'aiEnabled' => $this->aiEnabled,
        ]);
    }

    public function new(
        Request $request,
        string $typeName,
        BlockTypeChain $blockTypeChain,
        EntityManagerInterface $entityManager,
    ): Response {
        $this->denyAccessUnlessGranted(self::$role);

        $blockType  = $blockTypeChain->getBlockType($typeName);
        $block = new Block();

        $slug = $request->query->get('slug');

        if ($slug !== null) {
            $block->setSlug($slug);
        }

        // Prefilled by the "add translation" links; never for a shared block (Block::SHARED_TYPES)
        $language = $request->query->get('language');

        if ($language !== null && !\in_array($blockType::getBlockTypeName(), Block::SHARED_TYPES, true)) {
            $block->setLanguage($language);
        }

        $form = $this->createForm($blockType::class, $block, [
            'existing_languages' => $this->blockRepository->getExistingLanguages()
        ]);

        $block->setType($blockType::getBlockTypeName());

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($block);
            $entityManager->flush();
            $this->addFlash('kibatic_cms', 'flash.created');

            return $this->redirectToRoute('cms_block_edit', ['id' => $block->getId()]);
        }

        return $this->render('@KibaticCms/block/new.html.twig', [
            'block' => $block,
            'typeName' => $typeName,
            'form' => $form->createView(),
        ]);
    }

    public function edit(
        Request $request,
        Block $block,
        BlockTypeChain $blockTypeChain,
        EntityManagerInterface $entityManager,
    ): Response {
        $this->denyAccessUnlessGranted(self::$role);

        $blockType = $blockTypeChain->getBlockType($block->getType());
        $deleteForm = $this->createDeleteForm($block);

        $editForm = $this->createForm($blockType::class, $block);

        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $entityManager->flush();
            $this->addFlash('kibatic_cms', 'flash.saved');

            return $this->redirectToRoute('cms_block_edit', ['id' => $block->getId()]);
        }

        return $this->render('@KibaticCms/block/edit.html.twig', [
            'block' => $block,
            'translations' => $this->blockRepository->findBySlug($block->getSlug()),
            'aiEnabled' => $this->aiEnabled,
            'languages' => $this->blockRepository->getExistingLanguages(),
            'edit_form' => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
        ]);
    }

    public function editBySlug(
        Request $request,
        string $slug,
        BlockTypeChain $blockTypeChain,
        EntityManagerInterface $entityManager,
        FormFactoryInterface $formFactory,
    ): Response {
        $this->denyAccessUnlessGranted(self::$role);

        $blocks = $this->blockRepository->findBySlug($slug);
        $editForms = [];
        $deleteForms = [];

        foreach ($blocks as $block) {
            $blockType = $blockTypeChain->getBlockType($block->getType());
            $deleteForm = $this->createDeleteForm($block);
            $deleteForms[] = $deleteForm->createView();

            $editForm = $formFactory->createNamed('block_edit_' . $block->getId(), $blockType::class, $block);
            $editForm->handleRequest($request);
            $editForms[] = $editForm->createView();

            if ($editForm->isSubmitted() && $editForm->isValid()) {
                $entityManager->flush();
                $this->addFlash('kibatic_cms', 'flash.saved');

                return $this->redirectToRoute('cms_block_edit_by_slug', ['slug' => $block->getSlug()]);
            }
        }

        return $this->render('@KibaticCms/block/edit_by_slug.html.twig', [
            'blocks' => $blocks,
            'edit_forms' => $editForms,
            'delete_forms' => $deleteForms,
        ]);
    }

    public function delete(
        Request $request,
        Block $block,
        EntityManagerInterface $entityManager
    ): Response {
        $this->denyAccessUnlessGranted(self::$role);

        $form = $this->createDeleteForm($block);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->remove($block);
            $entityManager->flush();
            $this->addFlash('kibatic_cms', 'flash.deleted');
        }

        return $this->redirectToRoute('cms_block_index');
    }

    /**
     * @param Block $block
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createDeleteForm(Block $block)
    {
        return $this->container->get('form.factory')->createNamedBuilder('block_delete_' . $block->getId())
            ->setAction($this->generateUrl('cms_block_delete', ['id' => $block->getId()]))
            ->setMethod('POST')
            ->getForm()
        ;
    }

    public function debug(Request $request): Response
    {
        $session = $request->getSession();
        $session->set('cms-debug', !$session->get('cms-debug', false));

        $referer = $request->headers->get('referer');

        return $referer && parse_url($referer, \PHP_URL_HOST) === $request->getHost()
            ? $this->redirect($referer)
            : $this->redirectToRoute('cms_block_index');
    }
}
