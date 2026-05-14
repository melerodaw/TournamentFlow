<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:create-admin', description: 'Create admin user from ADMIN_EMAIL and ADMIN_PASSWORD env variables')]
final class CreateAdminCommand extends Command
{
    public function __construct(private EntityManagerInterface $em, private UserPasswordHasherInterface $hasher)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = getenv('ADMIN_EMAIL') ?: ($_SERVER['ADMIN_EMAIL'] ?? null);
        $password = getenv('ADMIN_PASSWORD') ?: ($_SERVER['ADMIN_PASSWORD'] ?? null);
        $username = getenv('ADMIN_USERNAME') ?: ($_SERVER['ADMIN_USERNAME'] ?? null);

        if (!$email || !$password) {
            $output->writeln('<error>ADMIN_EMAIL and ADMIN_PASSWORD must be set in environment.</error>');
            return Command::FAILURE;
        }

        $repo = $this->em->getRepository(User::class);
        $existing = $repo->findOneBy(['email' => $email]);
        if ($existing) {
            $output->writeln('<info>Admin user already exists.</info>');
            return Command::SUCCESS;
        }

        $user = new User();
        $user->setEmail($email);
        $user->setUsername($username ?: explode('@', $email)[0]);
        $hashed = $this->hasher->hashPassword($user, $password);
        $user->setPassword($hashed);
        $user->setRole('admin');

        $this->em->persist($user);
        $this->em->flush();

        $output->writeln('<info>Admin user created: '.$email.'</info>');

        return Command::SUCCESS;
    }
}
