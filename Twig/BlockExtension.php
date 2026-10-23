<?php

namespace Kibatic\CmsBundle\Twig;

use Kibatic\CmsBundle\Controller\BlockController;
use Kibatic\CmsBundle\Entity\Block;
use Kibatic\CmsBundle\Repository\BlockRepository;
use Kibatic\CmsBundle\Upload\ImageStorage;
use Kibatic\CmsBundle\Version\VersionPreview;
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
        private VersionPreview $versionPreview,
        private ImageStorage $imageStorage,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('cms_block', $this->cmsBlock(...), ['is_safe' => ['html']]),
            new TwigFunction('cms_image', $this->cmsImage(...)),
            new TwigFunction('cms_version_preview', $this->versionPreview->current(...)),
            new TwigFunction('cms_published_version', $this->versionPreview->published(...)),
            new TwigFunction('cms_draft_changes', $this->versionPreview->draftChanges(...)),
            new TwigFunction('cms_uploads_path', $this->imageStorage->getPublicPath(...)),
            new TwigFunction('cms_upload_max_size', $this->imageStorage->getMaxSize(...)),
        ];
    }

    public function cmsBlock(string $slug, bool $strict = false): ?string
    {
        $block = $this->resolveBlock($slug);

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

        return $this->render($block);
    }

    /**
     * URL of the image of an image block, to use it in an attribute (src, background…); null when the block does not
     * exist, e.g. {{ cms_image('home-photo') ?? asset('images/default.webp') }}
     */
    public function cmsImage(string $slug): ?string
    {
        $block = $this->resolveBlock($slug);

        return null !== $block && 'image' === $block->getType() && '' !== (string) $block->getContent() ? $block->getContent() : null;
    }

    /**
     * Block of the page's language, or of a fallback language, or shared by every language
     */
    private function resolveBlock(string $slug): ?Block
    {
        $locale = $this->translator->getLocale();
        $fallbackLocales = $this->translator->getFallbackLocales();

        // Then the block shared by every language (no language)
        $locales = [$locale, $fallbackLocales, null];
        $version = $this->versionPreview->displayed();

        foreach ($locales as $language) {
            $block = $this->findBlock($slug, $language);

            if ($block !== null) {
                break;
            }
        }

        $languages = null !== $version ? $version->getLanguages() : $this->blockRepository->getExistingLanguages();

        if (empty($languages) && $block === null) {
            $block = $this->findBlock($slug);
        }

        return $block;
    }

    private function render(Block $block): ?string
    {
        $content = $block->getContent();

        // A text block is plain text: "&" or "<" are displayed as typed
        if ('text' === $block->getType()) {
            $content = nl2br(htmlspecialchars((string) $content));
        }

        $mainRequest = $this->requestStack->getMainRequest();
        $debug = false;

        if ($mainRequest !== null) {
            $debug = $mainRequest->query->get('cms-debug');

            if (!$debug && $mainRequest->hasPreviousSession()) {
                $debug = $mainRequest->getSession()->get('cms-debug');
            }
        }

        // Blocks of a version have no id: nothing to edit
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
     * Block of the displayed version (previewed or published, VersionPreview::displayed()), or else of the database
     *
     * @param string|list<string>|false|null $language false: any language
     */
    private function findBlock(string $slug, string|array|false|null $language = false): ?Block
    {
        $version = $this->versionPreview->displayed();

        if (null === $version) {
            return $this->blockRepository->findOneBy(false === $language ? ['slug' => $slug] : ['slug' => $slug, 'language' => $language]);
        }

        foreach ($version->getBlocks() as $block) {
            if ($block->getSlug() === $slug && (false === $language || \in_array($block->getLanguage(), (array) $language, true) || $block->getLanguage() === $language)) {
                return $block;
            }
        }

        return null;
    }
}
