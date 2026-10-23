<?php

namespace Kibatic\CmsBundle\Command;

use Kibatic\CmsBundle\Repository\BlockRepository;
use Kibatic\CmsBundle\Repository\VersionRepository;
use Kibatic\CmsBundle\Upload\ImageStorage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Lists, or deletes with --delete, the uploaded images (ImageStorage) used neither by a block nor by a version
 */
#[AsCommand(name: 'kibatic:cms:clean-uploads', description: 'Lists the uploaded images no block or version uses; --delete deletes them')]
final class CleanUploadsCommand
{
    public function __construct(
        private readonly ImageStorage $storage,
        private readonly BlockRepository $blocks,
        private readonly VersionRepository $versions,
    ) {
    }

    public function __invoke(SymfonyStyle $io, #[Option('Delete the unused images')] bool $delete = false): int
    {
        // The images are named after their content: their name in a block's content (URL, <img src>) is a use
        $contents = implode("\n", [
            ...array_map(fn ($block) => (string) $block->getContent(), $this->blocks->findAll()),
            ...array_map(fn ($version) => implode("\n", array_map(fn ($block) => (string) $block->getContent(), $version->getBlocks())), $this->versions->findAll()),
        ]);

        $unused = array_values(array_filter($this->storage->files(), fn (string $name) => !str_contains($contents, $name)));

        if ([] === $unused) {
            $io->success('No unused image.');

            return Command::SUCCESS;
        }

        $io->listing($unused);

        if (!$delete) {
            $io->note(sprintf('%d unused image(s). Run again with --delete to delete them.', \count($unused)));

            return Command::SUCCESS;
        }

        array_walk($unused, $this->storage->delete(...));
        $io->success(sprintf('%d image(s) deleted.', \count($unused)));

        return Command::SUCCESS;
    }
}
