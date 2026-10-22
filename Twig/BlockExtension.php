<?php

namespace Kibatic\CmsBundle\Twig;

use Kibatic\CmsBundle\Controller\BlockController;
use Kibatic\CmsBundle\Entity\Block;
use Kibatic\CmsBundle\Repository\BlockRepository;
use Kibatic\CmsBundle\Snapshot\SnapshotPreview;
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
        private TranslatorInterface $translator,
        private SnapshotPreview $snapshotPreview,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('cms_block', $this->cmsBlock(...), ['is_safe' => ['html']]),
            new TwigFunction('cms_snapshot_preview', $this->snapshotPreview->current(...)),
        ];
    }

    public function cmsBlock(string $slug, bool $strict = false): ?string
    {
        $locale = $this->translator->getLocale();
        $fallbackLocales = $this->translator->getFallbackLocales();

        $locales = [$locale, $fallbackLocales];
        $snapshot = $this->snapshotPreview->current();

        foreach ($locales as $language) {
            $block = $this->findBlock($slug, $language);

            if ($block !== null) {
                break;
            }
        }

        $languages = null !== $snapshot ? $snapshot->getLanguages() : $this->blockRepository->getExistingLanguages();

        if (empty($languages) && $block === null) {
            $block = $this->findBlock($slug);
        }

        if ($block === null) {
            if ($strict) {
                throw new \Exception('Block "' . $slug . '" does not exist');
            }

            if ($this->security->isGranted(BlockController::$role)) {
                $url = $this->router->generate('cms_block_new', ['slug' => $slug, 'typeName' => 'html']);
                return '<div class="alert alert-danger">' . $this->translator->trans('missing_block', [
                    'slug' => htmlspecialchars($slug),
                    'url' => htmlspecialchars($url),
                ], 'KibaticCmsBundle') . '</div>';
            }

            return null;
        }

        $content = $block->getContent();

        $mainRequest = $this->requestStack->getMainRequest();
        $debug = false;

        if ($mainRequest !== null) {
            $debug = $mainRequest->query->get('cms-debug');

            if (!$debug && $mainRequest->hasPreviousSession()) {
                $debug = $mainRequest->getSession()->get('cms-debug');
            }
        }

        // Blocks of a previewed snapshot have no id: nothing to edit
        if ($debug && null !== $block->getId() && $this->security->isGranted(BlockController::$role)) {
            return
                '<div class="alert alert-info" title="Block : ' . $block->getSlug() . '">' .
                    '<a class="btn btn-primary btn-sm float-end" href="' . $this->router->generate('cms_block_edit', ['id' => $block->getId()]) . '">✏️</a>' .
                    $content .
                '</div>';
        }

        return $content;
    }

    /**
     * Block of the previewed snapshot (SnapshotPreview), or else of the database
     *
     * @param string|list<string>|false|null $language false: any language
     */
    private function findBlock(string $slug, string|array|false|null $language = false): ?Block
    {
        $snapshot = $this->snapshotPreview->current();

        if (null === $snapshot) {
            return $this->blockRepository->findOneBy(false === $language ? ['slug' => $slug] : ['slug' => $slug, 'language' => $language]);
        }

        foreach ($snapshot->getBlocks() as $block) {
            if ($block->getSlug() === $slug && (false === $language || \in_array($block->getLanguage(), (array) $language, true) || $block->getLanguage() === $language)) {
                return $block;
            }
        }

        return null;
    }
}
