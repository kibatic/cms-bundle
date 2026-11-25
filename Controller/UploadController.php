<?php

namespace Kibatic\CmsBundle\Controller;

use Kibatic\CmsBundle\Upload\ImageStorage;
use Kibatic\CmsBundle\Upload\InvalidImageException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Image uploaded from the admin (image blocks, assistant), in JavaScript: answers its URL (ImageStorage)
 */
class UploadController extends AbstractController
{
    public const CSRF_TOKEN_ID = 'kibatic_cms_upload';

    public function __construct(
        private readonly ImageStorage $storage,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function image(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(BlockController::$role);

        if (!$this->isCsrfTokenValid(self::CSRF_TOKEN_ID, $request->headers->get('X-CSRF-Token'))) {
            return $this->error('chat.error.csrf', Response::HTTP_FORBIDDEN);
        }

        $file = $request->files->get('file');

        // Not valid: larger than upload_max_filesize of PHP, or upload interrupted
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            return $this->error(UPLOAD_ERR_INI_SIZE === $file?->getError() || UPLOAD_ERR_FORM_SIZE === $file?->getError() ? 'editor.upload.too_big' : 'editor.upload.failed', Response::HTTP_BAD_REQUEST);
        }

        try {
            return $this->json(['url' => $this->storage->store($file), 'name' => $file->getClientOriginalName(), 'size' => $file->getSize()]);
        } catch (InvalidImageException $e) {
            return $this->error('editor.upload.'.$e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    private function error(string $key, int $status): JsonResponse
    {
        return $this->json(['error' => $this->translator->trans($key, ['size' => round($this->storage->getMaxSize() / 1024 / 1024, 1)], 'KibaticCmsBundle')], $status);
    }
}
