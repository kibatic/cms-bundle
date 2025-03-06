<?php

namespace Kibatic\CmsBundle\Twig;

use Kibatic\CmsBundle\Controller\BlockController;
use Kibatic\CmsBundle\Entity\Block;
use Kibatic\CmsBundle\Repository\BlockRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Router;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class BlockExtension extends AbstractExtension
{
    public function __construct(
        private BlockRepository $blockRepository,
        private Security $security,
        private RouterInterface $router,
        private RequestStack $requestStack,
        private TranslatorInterface $translator
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('cms_block', $this->cmsBlock(...), ['is_safe' => ['html']]),
            new TwigFunction('cms_block_i18n', $this->cmsBlockI18n(...), ['is_safe' => ['html']]),
        ];
    }

    public function cmsBlock(string $slug, bool $strict = false)
    {
        /**
         * @var Block $block
         */
        $block = $this->blockRepository->findOneBy(['slug' => $slug]);

        if ($block === null) {
            if ($strict) {
                throw new \Exception('Block "' . $slug . '" does not exist');
            }

            if ($this->security->isGranted(BlockController::$role)) {
                $url = $this->router->generate('cms_block_new', ['slug' => $slug, 'typeName' => 'html']);
                return '<div class="alert alert-danger">CMS Block <b>"' . $slug . '"</b> does not exist, <a href="' . $url . '">create it.</a></div>';
            }

            return null;
        }

        $content = $block->getContent();

        $debug = $this->requestStack->getMainRequest()->get('cms-debug')
            || $this->requestStack->getSession()->get('cms-debug');

        if ($debug && $this->security->isGranted(BlockController::$role)) {
            return
                '<div class="alert alert-info" title="Block : ' . $block->getSlug() . '">' .
                    '<a class="btn btn-primary btn-sm float-end" href="' . $this->router->generate('cms_block_edit', ['id' => $block->getId()]) . '">✏️</a>' .
                    $content .
                '</div>';
        }

        return $content;
    }

    public function cmsBlockI18n(string $slug, bool $strict = false)
    {
        $locale = $this->translator->getLocale();

        $realSlug = "{$slug}_{$locale}";

        $block = $this->blockRepository->findOneBy(['slug' => $realSlug]);

        if (!$block) {
            $realSlug = "{$slug}_en";
        }

        return $this->cmsBlock($realSlug, $strict);
    }
}
