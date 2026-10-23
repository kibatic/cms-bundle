<?php

namespace Kibatic\CmsBundle\Ai\Tool;

use Kibatic\CmsBundle\Ai\VersionProposals;
use Kibatic\CmsBundle\Repository\VersionRepository;
use Kibatic\CmsBundle\Version\VersionPreview;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool(
    name: 'cms_propose_version_action',
    description: <<<'TXT'
        Proposes to the admin an action on the versions: it is NOT done by this tool. A card is shown under your answer,
        where the admin confirms or cancels it (and can change the name of a new version). Actions: "save" (save the
        current blocks in a new version, not published), "publish_draft" (save the current blocks in a new version and
        publish it: the visitors will see them), "publish" (publish an existing version), "restore" (put the blocks of a
        version back in the draft; the current blocks are saved in a version first), "unpublish" (the visitors see the
        current blocks again, live).
        TXT,
)]
final class ProposeVersionActionTool
{
    private const ACTIONS = ['save', 'publish_draft', 'publish', 'restore', 'unpublish'];

    public function __construct(
        private readonly VersionRepository $versions,
        private readonly VersionPreview $preview,
        private readonly VersionProposals $proposals,
    ) {
    }

    /**
     * @param string $action  "save", "publish_draft", "publish", "restore" or "unpublish"
     * @param string $name    For "save" and "publish_draft": the name you suggest for the new version, very short (2 to 5 words, in the admin's language), saying what changed (e.g. "Hamster theme", "New kitten recipe")
     * @param int    $version For "publish" and "restore": the id of the version (cms_list_versions)
     */
    public function __invoke(string $action, string $name = '', int $version = 0): string
    {
        if (!\in_array($action, self::ACTIONS, true)) {
            return \sprintf('Not proposed: the action must be one of %s.', implode(', ', self::ACTIONS));
        }

        $published = $this->preview->published();
        $target = null;

        if (\in_array($action, ['publish', 'restore'], true)) {
            $target = $this->versions->find($version);

            if (null === $target) {
                return \sprintf('Not proposed: there is no version %d. Use cms_list_versions.', $version);
            }

            if ('publish' === $action && $target->isPublished()) {
                return 'Not proposed: this version is already published.';
            }
        }

        if ('publish_draft' === $action && null !== $published && 0 === $this->preview->draftChanges()) {
            return 'Not proposed: nothing changed since the published version, there is no draft to publish.';
        }

        if ('unpublish' === $action && null === $published) {
            return 'Not proposed: no version is published.';
        }

        $name = trim($name);
        if (\in_array($action, ['save', 'publish_draft'], true) && '' === $name) {
            return 'Not proposed: suggest a short name for the new version.';
        }

        $this->proposals->add(
            $action,
            \in_array($action, ['save', 'publish_draft'], true) ? mb_substr($name, 0, 255) : null,
            null !== $target ? ['id' => $target->getId(), 'name' => $target->getName()] : null,
            $this->preview->draftChanges(),
        );

        return 'Proposed: a confirmation card is shown to the admin under your answer; nothing is done until the admin confirms it. Tell the admin what the card does'
            .(\in_array($action, ['save', 'publish_draft'], true) ? ', the name you suggest, and that it can be changed in the card' : '')
            .'. Do not say it is done.';
    }
}
