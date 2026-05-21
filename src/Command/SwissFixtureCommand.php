<?php

namespace App\Command;

use App\Entity\Participant;
use App\Entity\Tournament;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:fixture:swiss', description: 'Create a Swiss tournament fixture with users and participants')]
final class SwissFixtureCommand extends Command
{
    public function __construct(private EntityManagerInterface $em, private UserPasswordHasherInterface $hasher)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('tournament', null, InputOption::VALUE_REQUIRED, 'Tournament ID', 6)
            ->addOption('participants', null, InputOption::VALUE_REQUIRED, 'Number of participants to create', 8);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $tournamentId = (int) $input->getOption('tournament');
        $participantCount = max(4, (int) $input->getOption('participants'));

        $tournament = $this->em->getRepository(Tournament::class)->find($tournamentId);
        if (!$tournament) {
            $output->writeln('<error>Tournament not found: '.$tournamentId.'</error>');

            return Command::FAILURE;
        }

        $tournament->setFormat('swiss');
        $tournament->setSwissRounds(3);
        $tournament->setStatus('open');

        $userRepository = $this->em->getRepository(User::class);
        $participantRepository = $this->em->getRepository(Participant::class);

        $createdUsers = 0;
        $createdParticipants = 0;

        for ($index = 1; $index <= $participantCount; $index++) {
            $username = sprintf('swiss_player_%02d', $index);
            $email = sprintf('swiss_player_%02d@example.test', $index);

            $user = $userRepository->findOneBy(['email' => $email]);
            if (!$user) {
                $user = new User();
                $user->setUsername($username);
                $user->setEmail($email);
                $user->setPassword($this->hasher->hashPassword($user, 'Password123!'));
                $user->setRole('user');
                $this->em->persist($user);
                ++$createdUsers;
            }

            $existingParticipant = $participantRepository->findOneBy([
                'user' => $user,
                'tournament' => $tournament,
            ]);

            if ($existingParticipant) {
                continue;
            }

            $participant = new Participant();
            $participant->setUser($user);
            $participant->setTournament($tournament);
            $participant->setStatus('active');
            $participant->setSeed($index);
            $participant->setRegisteredAt(new \DateTimeImmutable(sprintf('-%d minutes', $participantCount - $index)));
            $this->em->persist($participant);
            ++$createdParticipants;
        }

        $this->em->flush();

        $output->writeln(sprintf('<info>Swiss fixture ready for tournament %d.</info>', $tournamentId));
        $output->writeln(sprintf('<info>Swiss rounds: %d</info>', $tournament->getSwissRounds() ?? 0));
        $output->writeln(sprintf('<info>Users created: %d</info>', $createdUsers));
        $output->writeln(sprintf('<info>Participants created: %d</info>', $createdParticipants));
        $output->writeln(sprintf('<info>Total participants targeted: %d</info>', $participantCount));

        return Command::SUCCESS;
    }
}