<?php

namespace App\Entity;

use App\Repository\MatchParticipantRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MatchParticipantRepository::class)]
#[ORM\Table(name: 'match_participant', uniqueConstraints: [
    new ORM\UniqueConstraint(name: 'uniq_match_slot', columns: ['match_id', 'slot']),
    new ORM\UniqueConstraint(name: 'uniq_match_participant', columns: ['match_id', 'participant_id']),
], indexes: [
    new ORM\Index(name: 'idx_match_participant_match', columns: ['match_id']),
    new ORM\Index(name: 'idx_match_participant_participant', columns: ['participant_id']),
])]
class MatchParticipant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'matchParticipants')]
    #[ORM\JoinColumn(nullable: false)]
    private ?TournamentMatch $match = null;

    #[ORM\ManyToOne(inversedBy: 'matchParticipants')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Participant $participant = null;

    #[ORM\Column]
    private int $slot;

    #[ORM\Column(nullable: true)]
    private ?int $score = null;

    #[ORM\Column]
    private bool $isWinner = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMatch(): ?TournamentMatch
    {
        return $this->match;
    }

    public function setMatch(?TournamentMatch $match): self
    {
        $this->match = $match;

        return $this;
    }

    public function getParticipant(): ?Participant
    {
        return $this->participant;
    }

    public function setParticipant(?Participant $participant): self
    {
        $this->participant = $participant;

        return $this;
    }

    public function getSlot(): int
    {
        return $this->slot;
    }

    public function setSlot(int $slot): self
    {
        $this->slot = $slot;

        return $this;
    }

    public function getScore(): ?int
    {
        return $this->score;
    }

    public function setScore(?int $score): self
    {
        $this->score = $score;

        return $this;
    }

    public function isWinner(): bool
    {
        return $this->isWinner;
    }

    public function setIsWinner(bool $isWinner): self
    {
        $this->isWinner = $isWinner;

        return $this;
    }
}
