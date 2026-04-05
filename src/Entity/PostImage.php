<?php

namespace App\Entity;

use App\Repository\PostImageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PostImageRepository::class)]
#[ORM\Table(name: 'post_images')]
class PostImage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Post::class, inversedBy: 'images')]
    #[ORM\JoinColumn(name: 'post_id', nullable: false, onDelete: 'CASCADE')]
    private ?Post $post = null;

    #[ORM\Column(type: Types::TEXT)]
    private string $path = '';

    #[ORM\Column(name: 'sort_position', options: ['default' => 0])]
    private int $position = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPost(): ?Post
    {
        return $this->post;
    }

    public function setPost(?Post $post): static
    {
        $this->post = $post;

        return $this;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): static
    {
        $this->path = $path;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }
}
