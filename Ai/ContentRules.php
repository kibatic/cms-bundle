<?php

namespace Kibatic\CmsBundle\Ai;

use Kibatic\CmsBundle\Repository\BlockRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * How to write the blocks, for any AI that edits them with the CMS's tools: the CMS's assistant (ChatController) and the
 * MCP clients of the application (Ai\Tool\GuideTool)
 */
final class ContentRules
{
    public function __construct(
        private readonly BlockRepository $blocks,
        #[Autowire(param: 'kibatic_cms.ai.style_guide')]
        private readonly ?string $styleGuide,
    ) {
    }

    /**
     * @return string a Markdown list, then the site's style guide if any
     */
    public function text(): string
    {
        $languages = $this->blocks->getExistingLanguages();
        $languageList = [] === $languages ? 'none (no block has a language yet)' : implode(', ', $languages);
        $styleGuide = null !== $this->styleGuide ? "\n\nStyle guide of the website, for the styles of the blocks:\n".trim($this->styleGuide) : '';

        return <<<RULES
            - Languages of the existing blocks: {$languageList}. A block without language is shared by every language; a
              translation is a block with the same slug in another language.
            - Before modifying a block, read it with cms_get_block. Saving replaces the whole content of the block.
            - HTML blocks are displayed inside the website's pages: write a clean HTML fragment (h2, h3, p, ul, ol, li, a,
              strong, em, img), without <html>, <body>, <style>, <script> or inline styles.
            - New slugs are short, lowercase, in English and with dashes (e.g. "home-intro", "footer-legal").
            - The pages show the blocks their templates ask for: a new block appears nowhere until a developer adds it to a
              template. To change something on a page, modify its existing blocks (images included: the URL of an image
              block); create a block only when asked for a new one, and if no block matches, say so.
            - You cannot delete a block: the admin does it in the block list.
            - A metablock (type "meta") shows existing blocks together with a layout (stack, columns, grid, tabs), and may
              contain sub-metablocks made inside it; save it with cms_save_metablock, never with cms_save_block. To put
              blocks side by side or in tabs, make a metablock of them. A metablock is shared by every language, with the
              titles of its tabs in each language. The blocks you create only to show them in a metablock are saved with
              meta_only true (cms_save_block): the block list shows them under their metablock instead of among the others.
            - A navigation (type "navigation") is a menu: a tree of entries, each one with its label in each language of the
              site, leading to the page of a block (/page/its-slug) or heading its sub-entries. It is shared by every
              language; save it with cms_save_navigation. To add a page to a menu, read the menu with cms_get_block and
              save it back with the new entry, every other entry kept. The menus the site shows are those its templates
              ask for (e.g. "header-nav"): modify them rather than creating a new one.
            - Never write an image in base64 (data:): an image is a URL.
            - When the request is ambiguous (which block, which language), ask before saving.
            - Each block has its own CSS ("styles"), scoped to it by the CMS: plain declarations apply to the block itself,
              nested rules to its content (e.g. "padding: 40px; border-radius: 28px; background: var(--x); h2 { margin-top: 0; }
              li { padding: 8px; }"); @media rules can be nested too. Classes in the HTML are fine when the block's styles use them.
            - A block must look the same on every page that shows it (a page, a metablock, another page): its styles carry
              its whole look, its background, padding, rounded corners and text colors included, and never rely on the page
              around it. A block with a dark background sets its own text colors; one without background inherits them.
            - Before styling a new block, read with cms_get_block one or two blocks of the site that have styles
              (cms_list_blocks says which ones), and give it the same kind of look: same spacing, corners, colors and cards.
              Use the site's CSS variables rather than fixed colors.
            - When you only change the text of a block, do not send its styles (they are kept); send them only to change
              its look, complete, as they replace the previous ones.
            - Versions: a version is a copy of every block. When a version is published, the visitors see it, and the changes
              made since (the draft) are not online until a new publication; without published version, every change is
              online right away. Read them with cms_list_versions.{$styleGuide}
            RULES;
    }
}
