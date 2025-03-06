<?php

namespace Kibatic\CmsBundle\Controller;

use App\Repository\MediaRepository;
use Doctrine\ORM\EntityManagerInterface;
use Kibatic\CmsBundle\BlockTypeChain;
use Kibatic\CmsBundle\Entity\Block;
use Kibatic\CmsBundle\Repository\BlockRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class BlockController extends AbstractController
{
    public static string $role = 'ROLE_CMS';

    public function index(Request $request, BlockRepository $repository, BlockTypeChain $blockTypeChain)
    {
        $this->denyAccessUnlessGranted(self::$role);

        $blocks = $repository->findAll();
        $blockTypeNames = $blockTypeChain->getBlockTypeNames();

        dump($request->getSession()->get('cms-debug'));

        return $this->render('@KibaticCms/block/index.html.twig', [
            'blocks' => $blocks,
            'blockTypeNames' => $blockTypeNames
        ]);
    }

    public function new(
        Request $request,
        string $typeName,
        BlockTypeChain $blockTypeChain,
        EntityManagerInterface $entityManager
    ) {
        $this->denyAccessUnlessGranted(self::$role);

        $blockType  = $blockTypeChain->getBlockType($typeName);
        $block = new Block();

        $slug = $request->get('slug');

        if ($slug !== null) {
            $block->setSlug($slug);
        }

        $form = $this->createForm($blockType::class, $block);

        $block->setType($blockType::getBlockTypeName());

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($block);
            $entityManager->flush();

            return $this->redirectToRoute('cms_block_index');
        }

        return $this->render('@KibaticCms/block/new.html.twig', [
            'block' => $block,
            'form' => $form->createView(),
        ]);
    }

    public function edit(
        Request $request,
        Block $block,
        BlockTypeChain $blockTypeChain,
        EntityManagerInterface $entityManager
    ) {
        $this->denyAccessUnlessGranted(self::$role);

        $blockType = $blockTypeChain->getBlockType($block->getType());
        $deleteForm = $this->createDeleteForm($block);

        $editForm = $this->createForm($blockType::class, $block);

        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('cms_block_edit', ['id' => $block->getId()]);
        }

        return $this->render('@KibaticCms/block/edit.html.twig', [
            'block' => $block,
            'edit_form' => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
        ]);
    }

    public function delete(
        Request $request,
        Block $block,
        EntityManagerInterface $entityManager
    ) {
        $this->denyAccessUnlessGranted(self::$role);

        $form = $this->createDeleteForm($block);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->remove($block);
            $entityManager->flush();
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
            ->setMethod('DELETE')
            ->getForm()
        ;
    }

    public function debug(Request $request)
    {
        $request->getSession()->set('cms-debug', !$request->getSession()->get('cms-debug', false));

        return $this->redirect($request->headers->get('referer'));
    }
}
