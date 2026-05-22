<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:create-admin', description: 'Create or reset the admin user for the panel')]
final class CreateAdminCommand extends Command
{
    private const DEFAULT_USERNAME = 'admin';
    private const DEFAULT_EMAIL = 'admin@tournamentflow.com';
    private const DEFAULT_PASSWORD = 'Admin1234!';

    public function __construct(private EntityManagerInterface $em, private UserPasswordHasherInterface $hasher)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $user = $this->findAdminUser();
        $password = self::DEFAULT_PASSWORD;

        if ($user === null) {
            $user = new User();
            $user->setUsername(self::DEFAULT_USERNAME);
            $user->setEmail(self::DEFAULT_EMAIL);
            $user->setRole('admin');

            $this->em->persist($user);
            $action = 'created';
        } else {
            $user->setRole('admin');
            $action = 'updated';
        }

        $user->setPassword($this->hasher->hashPassword($user, $password));
        $this->em->flush();

        $output->writeln(sprintf('<info>Admin user %s.</info>', $action));
        $output->writeln(sprintf('<info>Username: %s</info>', $user->getUsername()));
        $output->writeln(sprintf('<info>Email: %s</info>', $user->getEmail()));
        $output->writeln(sprintf('<info>Password: %s</info>', $password));

        return Command::SUCCESS;
    }

    private function findAdminUser(): ?User
    {
        $users = $this->em->getRepository(User::class)->findAll();

        foreach ($users as $user) {
            if (!$user instanceof User) {
                continue;
            }

            if ($user->getRole() === 'admin' || in_array('ROLE_ADMIN', $user->getRoles(), true)) {
                return $user;
            }
        }

        return null;
    }
}
