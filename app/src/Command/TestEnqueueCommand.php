<?php

namespace App\Command;

use App\Repository\DocumentNodeRepository;
use App\Repository\IngestionQueueItemRepository;
use App\Repository\UserRepository;
use App\Service\IngestionQueueManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use App\Entity\IngestionQueueItem;

#[AsCommand(
    name: 'app:test:enqueue',
    description: 'Add a short description for your command',
)]
class TestEnqueueCommand extends Command
{
    public function __construct(
        protected DocumentNodeRepository $documentNodeRepository,
        protected UserRepository $userRepository,
        protected IngestionQueueManager $ingestionQueueManager
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
     
        $node = $this->documentNodeRepository->find(100);
        $queueItem = $this->ingestionQueueManager->enqueue($node, $this->userRepository->find(1));

        if($queueItem->getStatus() === IngestionQueueItem::STATUS_QUEUED) {
            $io->success('Seems like its working.');
            return Command::SUCCESS;
        } 

        $io->error('Seems like its broken');
        return Command::FAILURE;
    }
}
