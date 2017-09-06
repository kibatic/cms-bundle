<?php

namespace Kibatic\CmsBundle\Controller;

use Kibatic\CmsBundle\BlockTypeChain;
use Kibatic\CmsBundle\Entity\Block;
use Kibatic\CmsBundle\Repository\BlockRepository;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class BlockController extends Controller
{
    public function indexAction()
    {
        $em = $this->getDoctrine()->getManager();

        $blocks = $em->getRepository(Block::class)->findAll();

        $blockTypeNames = $this->get(BlockTypeChain::class)->getBlockTypeNames();

        return $this->render('@KibaticCms/block/index.html.twig', [
            'blocks' => $blocks,
            'blockTypeNames' => $blockTypeNames
        ]);
    }

    public function newAction(Request $request, string $typeName)
    {
        $blockType  = $this->get(BlockTypeChain::class)->getBlockType($typeName);

        $block = new Block();

        $slug = $request->get('slug');

        if ($slug !== null) {
            $block->setSlug($slug);
        }

        $form = $this->createForm(get_class($blockType), $block);

        $block->setType($blockType::getBlockTypeName());

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($block);
            $em->flush();

            return $this->redirectToRoute('cms_block_index');
        }

        return $this->render('@KibaticCms/block/new.html.twig', [
            'block' => $block,
            'form' => $form->createView(),
        ]);
    }

    public function showAction(string $slug, string $template = null)
    {
        /**
         * @var Block $block
         */
        $block = $this->get(BlockRepository::class)->findOneBy(['slug' => $slug]);

        if ($block === null) {
            throw $this->createNotFoundException();
        }

        if ($template === null) {
            $template = 'KibaticCmsBundle:block:' . $block->getType() . '_block.html.twig';
        }

        return $this->render($template, [
            'block' => $block
        ]);
    }

    public function editAction(Request $request, Block $block)
    {
        $blockType  = $this->get(BlockTypeChain::class)->getBlockType($block->getType());

        $deleteForm = $this->createDeleteForm($block);

        $editForm = $this->createForm(get_class($blockType), $block);

        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('cms_block_edit', ['id' => $block->getId()]);
        }

        return $this->render('@KibaticCms/block/edit.html.twig', [
            'block' => $block,
            'edit_form' => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
        ]);
    }

    public function deleteAction(Request $request, Block $block)
    {
        $form = $this->createDeleteForm($block);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($block);
            $em->flush();
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
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('cms_block_delete', ['id' => $block->getId()]))
            ->setMethod('DELETE')
            ->getForm()
        ;
    }
}
