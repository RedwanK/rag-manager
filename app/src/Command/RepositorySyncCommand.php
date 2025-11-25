<?php

namespace App\Command;

use App\Repository\RepositoryConfigRepository;
use App\Service\GitHubSyncService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:github:sync', description: 'Trigger a manual sync for a configured repository')]
class RepositorySyncCommand extends Command
{
    public function __construct(private readonly RepositoryConfigRepository $configs, private readonly GitHubSyncService $sync)
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
        $config = $this->configs->find($configId);

        if (null === $config) {
            $output->writeln('<error>RepositoryConfig not found.</error>');
            return Command::FAILURE;
        }

        $log = $this->sync->syncRepository($config, $input->getArgument('triggeredBy'));

        $output->writeln(sprintf('Sync finished with status %s', $log->getStatus()));

        return Command::SUCCESS;
    }
}
