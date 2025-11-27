<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Console command to create users with hashed passwords and roles.
 */
#[AsCommand(
    name: 'app:user:create',
    description: 'Create a user with hashed password and roles.',
)]
class CreateUserCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::OPTIONAL, 'Email used as user identifier')
            ->addOption('password', null, InputOption::VALUE_OPTIONAL, 'Plain password (will prompt if missing)')
            ->addOption(
                'role',
                null,
                InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
                'Roles to assign (repeatable option)',
                ['ROLE_VIEWER']
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $helper = $this->getHelper('question');

        $email = $input->getArgument('email');
        if (!$email) {
            $emailQuestion = new Question('Email (used as user identifier)');
            $email = (string) $helper->ask($input, $output, $emailQuestion);
        }

        $email = trim((string) $email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $io->error('Please provide a valid email address.');
            return Command::FAILURE;
        }

        if ($this->userRepository->findOneBy(['email' => $email])) {
            $io->error(sprintf('A user with email "%s" already exists.', $email));
            return Command::FAILURE;
        }

        $plainPassword = $input->getOption('password');
        if (!$plainPassword) {
            $passwordQuestion = new Question('Plain password');
            $passwordQuestion->setHidden(true);
            $passwordQuestion->setHiddenFallback(false);
            $plainPassword = (string) $helper->ask($input, $output, $passwordQuestion);
        }

        if (strlen((string) $plainPassword) < 8) {
            $io->error('Password must be at least 8 characters long.');
            return Command::FAILURE;
        }

        $roles = $input->getOption('role') ?? [];
        $roles[] = 'ROLE_USER';
        $roles = array_values(array_unique(array_map(static fn ($role) => strtoupper(trim((string) $role)), $roles)));

        $user = new User();
        $user->setEmail($email);
        $user->setRoles($roles);
        $hashedPassword = $this->passwordHasher->hashPassword($user, (string) $plainPassword);
        $user->setPassword($hashedPassword);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success(sprintf('User "%s" created with roles: %s', $email, implode(', ', $roles)));

        return Command::SUCCESS;
    }
}
