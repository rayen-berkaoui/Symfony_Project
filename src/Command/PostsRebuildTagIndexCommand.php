<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Post;
use App\Service\PostTagIndexBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:posts:rebuild-tag-index',
    description: 'Recalcule la colonne hashtags_index pour toutes les publications (filtre par hashtag).',
)]
final class PostsRebuildTagIndexCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PostTagIndexBuilder $postTagIndexBuilder,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $repo = $this->entityManager->getRepository(Post::class);
        $posts = $repo->findAll();
        $n = 0;
        foreach ($posts as $post) {
            if (!$post instanceof Post) {
                continue;
            }
            $idx = $this->postTagIndexBuilder->build((string) $post->getContent());
            $post->setHashtagsIndex($idx);
            ++$n;
        }
        $this->entityManager->flush();
        $io->success(sprintf('%d publication(s) mise(s) à jour.', $n));

        return Command::SUCCESS;
    }
}
