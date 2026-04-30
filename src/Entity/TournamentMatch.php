<?php

namespace App\Entity;

use App\Repository\TournamentMatchRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TournamentMatchRepository::class)]
#[ORM\Table(name: 'tournament_match', indexes: [
    new ORM\Index(name: 'idx_match_status', columns: ['status']),
    new ORM\Index(name: 'idx_match_round', columns: ['round_number']),
])]
class TournamentMatch
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'matches')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Tournament $tournament = null;

    #[ORM\Column(name: 'round_number')]
    private int $roundNumber;

    #[ORM\Column(length: 30)]
    private string $status = 'scheduled';

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $scheduledAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $playedAt = null;

    #[ORM\ManyToOne]
    private ?Participant $winner = null;

    #[ORM\OneToMany(mappedBy: 'match', targetEntity: MatchParticipant::class)]
    private Collection $matchParticipants;

    public function __construct()
    {
        $this->matchParticipants = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTournament(): ?Tournament
    {
        return $this->tournament;
    }

    public function setTournament(?Tournament $tournament): self
    {
        $this->tournament = $tournament;

        return $this;
    }

    public function getRoundNumber(): int
    {
        return $this->roundNumber;
    }

    public function setRoundNumber(int $roundNumber): self
    {
        $this->roundNumber = $roundNumber;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getScheduledAt(): ?\DateTimeImmutable
    {
        return $this->scheduledAt;
    }

    public function setScheduledAt(?\DateTimeImmutable $scheduledAt): self
    {
        $this->scheduledAt = $scheduledAt;

        return $this;
    }

    public function getPlayedAt(): ?\DateTimeImmutable
    {
        return $this->playedAt;
    }

    public function setPlayedAt(?\DateTimeImmutable $playedAt): self
    {
        $this->playedAt = $playedAt;

        return $this;
    }

    public function getWinner(): ?Participant
    {
        return $this->winner;
    }

    public function setWinner(?Participant $winner): self
    {
        $this->winner = $winner;

        return $this;
    }

    /**
     * @return Collection<int, MatchParticipant>
     */
    public function getMatchParticipants(): Collection
    {
        return $this->matchParticipants;
    }

    public function addMatchParticipant(MatchParticipant $matchParticipant): self
    {
        if (!$this->matchParticipants->contains($matchParticipant)) {
            $this->matchParticipants->add($matchParticipant);
            $matchParticipant->setMatch($this);
        }

        return $this;
    }

    public function removeMatchParticipant(MatchParticipant $matchParticipant): self
    {
        if ($this->matchParticipants->removeElement($matchParticipant)) {
            if ($matchParticipant->getMatch() === $this) {
                $matchParticipant->setMatch(null);
            }
        }

        return $this;
    }
}
