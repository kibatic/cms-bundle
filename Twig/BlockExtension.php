<?php

namespace Kibatic\CmsBundle\Twig;

use Kibatic\CmsBundle\Controller\BlockController;
use Kibatic\CmsBundle\Entity\Block;
use Kibatic\CmsBundle\Meta\MetaContent;
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
use Symfony\Contracts\Service\ResetInterface;

class BlockExtension extends AbstractExtension implements ResetInterface
{
    /** Metablocks displayed during the request: the first one adds their styles, each one numbers its tabs */
    private int $metablocks = 0;
    /** @var array<string, true> slugs of the blocks whose styles were added to the page */
    private array $styled = [];

    /** Styles of the layouts of the metablocks, neutral (colors of the site), overridable with the --cms-meta-* variables */
    private const META_STYLES = <<<'CSS'
        .cms-meta { display: grid; gap: var(--cms-meta-gap, 24px); }
        .cms-meta-columns { grid-template-columns: repeat(var(--cms-meta-columns, 2), minmax(0, 1fr)); }
        .cms-meta-grid { grid-template-columns: repeat(auto-fill, minmax(var(--cms-meta-min-width, 240px), 1fr)); }
        @media (max-width: 768px) { .cms-meta-columns { grid-template-columns: minmax(0, 1fr); } }
        .cms-meta-item > :first-child, .cms-meta-panel > :first-child { margin-top: 0; }
        .cms-meta-item > :last-child, .cms-meta-panel > :last-child { margin-bottom: 0; }
        .cms-meta img { max-width: 100%; height: auto; }
        .cms-meta-tabs { display: block; }
        .cms-meta-tabs > input { position: absolute; opacity: 0; pointer-events: none; }
        .cms-meta-tabs > label { display: inline-block; padding: 8px 14px; border-bottom: 2px solid transparent; cursor: pointer; opacity: .65; }
        .cms-meta-tabs > input:checked + label { border-bottom-color: currentColor; opacity: 1; }
        .cms-meta-tabs > input:focus-visible + label { outline: 2px solid currentColor; outline-offset: 2px; }
        .cms-meta-panel { display: none; padding-top: 16px; }
        CSS;

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
            new TwigFunction('cms_meta_choices', $this->metaChoices(...)),
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
        $content = match ($block->getType()) {
            // A text block is plain text: "&" or "<" are displayed as typed
            'text' => nl2br(htmlspecialchars((string) $block->getContent())),
            'meta' => $this->renderMeta(MetaContent::fromJson($block->getContent())),
            default => $block->getContent(),
        };

        // Styles of the block, scoped to it with CSS nesting; not for an image block, whose URL goes in an attribute
        if (null !== $block->getStyles() && 'image' !== $block->getType()) {
            $attribute = htmlspecialchars($block->getSlug());
            $styles = isset($this->styled[$block->getSlug()]) ? '' : sprintf(
                '<style>[data-cms-block="%s"] { %s }</style>',
                addcslashes($block->getSlug(), '"\\'),
                // No "<": the CSS cannot close its <style>
                str_replace('<', '', $block->getStyles()),
            );
            $this->styled[$block->getSlug()] = true;
            $content = $styles.'<div data-cms-block="'.$attribute.'">'.$content.'</div>';
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
     * Blocks of a metablock with their layout; each one in the page's language, the missing ones (or metablocks) left
     * out; its sub-metablocks with their own layout
     */
    private function renderMeta(MetaContent $meta): string
    {
        $id = 'cms-meta-'.++$this->metablocks;
        // Read before the sub-metablocks are rendered: they count too
        $html = 1 === $this->metablocks ? '<style>'.self::META_STYLES.$this->tabStyles().'</style>' : '';
        $items = [];

        foreach ($meta->items as $item) {
            // Sub-metablock, saved inside this one
            if (isset($item['meta'])) {
                $items[] = ['title' => $item['title'] ?? '…', 'html' => $this->renderMeta($item['meta'])];

                continue;
            }

            $block = $this->resolveBlock($item['slug']);

            if (null !== $block && 'meta' !== $block->getType()) {
                $items[] = [
                    'title' => $item['title'] ?? $item['slug'],
                    // Shown as an image, as cms_block() gives the URL alone
                    'html' => 'image' === $block->getType()
                        ? '<img src="'.htmlspecialchars((string) $block->getContent()).'" alt="">'
                        : (string) $this->render($block),
                ];
            }
        }

        if ('tabs' === $meta->layout) {
            $html .= '<div class="cms-meta cms-meta-tabs">';
            foreach ($items as $i => $item) {
                $html .= sprintf('<input type="radio" name="%1$s" id="%1$s-%2$d"%3$s><label for="%1$s-%2$d">%4$s</label>', $id, $i, 0 === $i ? ' checked' : '', htmlspecialchars($item['title']));
            }
            foreach ($items as $item) {
                $html .= '<div class="cms-meta-panel">'.$item['html'].'</div>';
            }

            return $html.'</div>';
        }

        $html .= sprintf('<div class="cms-meta cms-meta-%s"%s>', $meta->layout, 'columns' === $meta->layout ? ' style="--cms-meta-columns: '.$meta->columns.'"' : '');
        foreach ($items as $item) {
            $html .= '<div class="cms-meta-item">'.$item['html'].'</div>';
        }

        return $html.'</div>';
    }

    /**
     * The panel of the checked tab is shown: one rule per position (tabs: radio buttons, no JavaScript)
     */
    private function tabStyles(): string
    {
        return implode('', array_map(
            fn (int $n) => ".cms-meta-tabs > input:nth-of-type($n):checked ~ .cms-meta-panel:nth-of-type($n) { display: block; }",
            range(1, 20),
        ));
    }

    /**
     * Blocks a metablock can show, for its editor: one per slug, metablocks excluded
     *
     * @return array<string, string> type by slug
     */
    public function metaChoices(): array
    {
        $choices = [];
        foreach ($this->blockRepository->findAll() as $block) {
            if ('meta' !== $block->getType()) {
                $choices[$block->getSlug()] ??= $block->getType();
            }
        }

        return $choices;
    }

    public function reset(): void
    {
        $this->metablocks = 0;
        $this->styled = [];
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
