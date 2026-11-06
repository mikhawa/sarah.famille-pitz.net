<?php

namespace App\Command;

use App\Repository\MediaRepository;
use App\Service\MediaStorage;
use App\Service\MediaUploader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Recrée les miniatures manquantes (ou toutes avec --all) à partir des originaux stockés.
 * Aucune modification en base : seuls les fichiers de var/storage/media/thumbs/ sont écrits.
 */
#[AsCommand(
    name: 'app:media:regenerate-thumbs',
    description: 'Recrée les miniatures manquantes des photos et des vidéos',
)]
class RegenerateThumbnailsCommand extends Command
{
    public function __construct(
        private readonly MediaRepository $mediaRepository,
        private readonly MediaStorage $storage,
        private readonly MediaUploader $uploader,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('all', null, InputOption::VALUE_NONE, 'Recrée aussi les miniatures déjà présentes');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $all = (bool) $input->getOption('all');
        $done = 0;
        $failed = 0;

        foreach ($this->mediaRepository->findBy([], ['id' => 'ASC']) as $media) {
            if (!$media->getType()->hasThumbnail()) {
                continue;
            }
            if (!$all && $this->hasAllThumbnails($media->getStoragePath())) {
                continue;
            }

            try {
                $this->uploader->regenerateThumbnails($media);
                ++$done;
                $io->writeln(\sprintf('  <info>OK</info>     média n°%d (%s)', $media->getId(), $media->getStoragePath()));
            } catch (\Throwable $e) {
                ++$failed;
                $io->writeln(\sprintf('  <error>ÉCHEC</error>  média n°%d : %s', $media->getId(), $e->getMessage()));
            }
        }

        if ($failed > 0) {
            $io->error(\sprintf('%d média(s) traité(s), %d échec(s).', $done, $failed));

            return Command::FAILURE;
        }

        $io->success(0 === $done ? 'Aucune miniature manquante.' : \sprintf('%d média(s) traité(s).', $done));

        return Command::SUCCESS;
    }

    private function hasAllThumbnails(string $storagePath): bool
    {
        foreach (array_keys(MediaStorage::THUMB_SIZES) as $size) {
            if (!is_file($this->storage->getThumbnailPathFor($storagePath, $size))) {
                return false;
            }
        }

        return true;
    }
}
