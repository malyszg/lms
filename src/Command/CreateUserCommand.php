<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\UserServiceInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:create-user',
    description: 'Create a new user',
)]
class CreateUserCommand extends Command
{
    public function __construct(
        private readonly UserServiceInterface $userService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'User email (used for login)')
            ->addArgument('username', InputArgument::REQUIRED, 'Username (display name)')
            ->addArgument('password', InputArgument::REQUIRED, 'Password')
            ->addOption('role', 'r', InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'User role(s)', ['ROLE_USER'])
            ->addOption('first-name', null, InputOption::VALUE_OPTIONAL, 'First name')
            ->addOption('last-name', null, InputOption::VALUE_OPTIONAL, 'Last name')
            ->setHelp(<<<'HELP'
The <info>app:create-user</info> command creates a new user:

    <info>php bin/console app:create-user admin@example.com admin password123</info>

You can optionally specify roles:

    <info>php bin/console app:create-user admin@firma.pl "Jan Kowalski" password123 --role=ROLE_ADMIN</info>

Available roles:
- ROLE_USER (default)
- ROLE_CALL_CENTER (full access to leads)
- ROLE_BOK (read-only access)
- ROLE_ADMIN (full system access)

You can also specify first and last name:

    <info>php bin/console app:create-user admin@firma.pl admin password123 --role=ROLE_ADMIN --first-name=Jan --last-name=Kowalski</info>

HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = $input->getArgument('email');
        $username = $input->getArgument('username');
        $password = $input->getArgument('password');
        $roles = $input->getOption('role');
        $firstName = $input->getOption('first-name');
        $lastName = $input->getOption('last-name');

        try {
            $user = $this->userService->createUser(
                email: $email,
                username: $username,
                plainPassword: $password,
                roles: $roles,
                firstName: $firstName,
                lastName: $lastName
            );

            $io->success(sprintf(
                'User "%s" (ID: %d) created successfully with email: %s',
                $user->getUsername(),
                $user->getId(),
                $user->getEmail()
            ));

            $io->table(
                ['Property', 'Value'],
                [
                    ['ID', $user->getId()],
                    ['Email (login)', $user->getEmail()],
                    ['Username (display)', $user->getUsername()],
                    ['Roles', implode(', ', $user->getRoles())],
                    ['First Name', $user->getFirstName() ?? 'N/A'],
                    ['Last Name', $user->getLastName() ?? 'N/A'],
                    ['Active', $user->isActive() ? 'Yes' : 'No'],
                ]
            );
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
    }
}

