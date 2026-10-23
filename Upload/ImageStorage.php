<?php

namespace Kibatic\CmsBundle\Upload;

use League\Flysystem\FilesystemOperator;
use League\Flysystem\StorageAttributes;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\File;

/**
 * Images of the blocks, uploaded in the admin (image blocks, assistant), in a Flysystem storage: local by default
 * (public/uploads/cms), or the application's one (kibatic_cms.uploads.storage: S3…). Named after their content (the
 * same image uploaded twice is one file); the blocks keep their public URL (publicUrl() of the storage). Never deleted
 * on their own, as an old version may still show them: see the kibatic:cms:clean-uploads command.
 */
final class ImageStorage
{
    /** Formats accepted, by MIME type read in the file (not SVG: it can contain scripts) */
    private const EXTENSIONS = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];
    private const NAME = '/^[0-9a-f]{40}\.(png|jpg|gif|webp)$/';

    public function __construct(
        #[Autowire(service: 'kibatic_cms.uploads.storage')]
        private readonly FilesystemOperator $storage,
        #[Autowire(param: 'kibatic_cms.uploads.max_size')]
        private readonly int $maxSize,
    ) {
    }

    /**
     * @return string public URL of the image
     *
     * @throws InvalidImageException
     */
    public function store(File $file): string
    {
        $extension = self::EXTENSIONS[(new \finfo(\FILEINFO_MIME_TYPE))->file($file->getPathname())] ?? null;

        if (null === $extension) {
            throw new InvalidImageException('not_image');
        }

        if ($file->getSize() > $this->maxSize) {
            throw new InvalidImageException('too_big');
        }

        $name = sha1_file($file->getPathname()).'.'.$extension;

        if (!$this->storage->fileExists($name)) {
            $stream = fopen($file->getPathname(), 'r');
            $this->storage->writeStream($name, $stream);
            \is_resource($stream) && fclose($stream);
        }

        return $this->publicUrl($name);
    }

    /**
     * Content and MIME type of an image of the storage, from its public URL; null when the URL is not one of the storage
     *
     * @return array{content: string, type: string}|null
     */
    public function read(string $url): ?array
    {
        $name = basename((string) parse_url($url, \PHP_URL_PATH));

        if (!preg_match(self::NAME, $name, $match) || $this->publicUrl($name) !== $url || !$this->storage->fileExists($name)) {
            return null;
        }

        return ['content' => $this->storage->read($name), 'type' => array_search($match[1], self::EXTENSIONS, true)];
    }

    /**
     * Beginning of the URLs of the storage's images (e.g. "/uploads/cms/")
     */
    public function getPublicPath(): string
    {
        return $this->publicUrl('');
    }

    public function getMaxSize(): int
    {
        return $this->maxSize;
    }

    /**
     * @return list<string> names of the images of the storage
     */
    public function files(): array
    {
        return $this->storage->listContents('')
            ->filter(fn (StorageAttributes $item) => $item->isFile() && preg_match(self::NAME, basename($item->path())))
            ->map(fn (StorageAttributes $item) => basename($item->path()))
            ->toArray();
    }

    public function delete(string $name): void
    {
        $this->storage->delete($name);
    }

    private function publicUrl(string $name): string
    {
        // publicUrl() is in Flysystem's FilesystemOperator from its version 4 only (all its filesystems have it in 3)
        return $this->storage->publicUrl($name);
    }
}
