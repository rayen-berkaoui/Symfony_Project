<?php

namespace App\Service;

use App\Entity\Post;
use App\Entity\PostImage;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class PostMediaUploadService
{
    public function __construct(
        private readonly string $uploadDir,
        private readonly string $projectDir,
    ) {
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0775, true);
        }
    }

    public function addImagesToPost(Post $post, iterable $imageFiles): void
    {
        $maxPos = -1;
        foreach ($post->getImages() as $existing) {
            $maxPos = max($maxPos, $existing->getPosition());
        }

        $i = 0;
        foreach ($imageFiles as $file) {
            if (!$file instanceof UploadedFile) {
                continue;
            }
            $image = new PostImage();
            $image->setPath($this->store($file, 'img'));
            $image->setPosition($maxPos + $i + 1);
            $post->addImage($image);
            ++$i;
        }
    }

    public function removePostImagesByIds(Post $post, array $ids): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return;
        }

        $toRemove = [];
        foreach ($post->getImages() as $image) {
            $id = $image->getId();
            if (null !== $id && in_array($id, $ids, true)) {
                $toRemove[] = $image;
            }
        }

        foreach ($toRemove as $image) {
            $this->removeStoredFile($image->getPath());
            $post->removeImage($image);
        }
    }

    public function applyVideoToPost(Post $post, ?UploadedFile $videoFile): void
    {
        if ($videoFile instanceof UploadedFile) {
            $this->removeStoredFile($post->getVideoPath());
            $post->setVideoPath($this->store($videoFile, 'vid'));
        }
    }

    public function clearVideo(Post $post): void
    {
        $this->removeStoredFile($post->getVideoPath());
        $post->setVideoPath(null);
    }

    public function purgePostMediaFromDisk(Post $post): void
    {
        foreach ($post->getImages() as $image) {
            $this->removeStoredFile($image->getPath());
        }
        $this->removeStoredFile($post->getVideoPath());
    }

    private function store(UploadedFile $file, string $prefix): string
    {
        $ext = strtolower((string) ($file->guessExtension() ?: $file->getClientOriginalExtension()));
        $ext = preg_replace('/[^a-z0-9]/', '', $ext) ?: 'bin';
        $name = sprintf('%s_%s.%s', $prefix, bin2hex(random_bytes(8)), $ext);

        $file->move($this->uploadDir, $name);

        return 'uploads/posts/'.$name;
    }

    private function removeStoredFile(?string $path): void
    {
        if (null === $path || '' === $path || str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return;
        }

        $normalized = ltrim($path, '/');
        if (!str_starts_with($normalized, 'uploads/posts/')) {
            return;
        }

        $full = $this->projectDir.'/public/'.$normalized;
        if (is_file($full)) {
            @unlink($full);
        }
    }
}
