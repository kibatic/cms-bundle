<?php

namespace Kibatic\CmsBundle\Ai\Tool;

use Kibatic\CmsBundle\Ai\BlockDescriber;
use Kibatic\CmsBundle\Repository\BlockRepository;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool(
    name: 'cms_list_blocks',
    description: 'Lists every content block of the CMS: slug, language (null: shared by every language), type, last update and the beginning of its text.',
)]
final class ListBlocksTool
{
    public function __construct(
        private readonly BlockRepository $blocks,
    ) {
    }

    public function __invoke(): string
    {
        return json_encode(
            array_map(fn ($block) => BlockDescriber::describe($block, false), $this->blocks->findAll()),
            \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES
        );
    }
}
