<?php

namespace App\Command;

use App\Repository\RepositoryConfigRepository;
use App\Service\GitHubSyncService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:github:sync', description: 'Trigger a manual sync for a configured repository')]
class RepositorySyncCommand extends Command
{
    public function __construct(private readonly RepositoryConfigRepository $repositoryConfigRepository, private readonly GitHubSyncService $githubSyncService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('configId', InputArgument::REQUIRED, 'RepositoryConfig id to sync');
        $this->addArgument('triggeredBy', InputArgument::OPTIONAL, 'Optional actor for observability');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $configId = (int) $input->getArgument('configId');
        $config = $this->repositoryConfigRepository->find($configId);

        $io = new SymfonyStyle($input, $output);

        if (null === $config) {
            $io->error("RepositoryConfig not found.");
            return Command::FAILURE;
        }

        $log = $this->githubSyncService->syncRepository($config, $input->getArgument('triggeredBy'));

        $io->success(sprintf('Sync finished with status %s', $log->getStatus()));

        return Command::SUCCESS;
    }
}
