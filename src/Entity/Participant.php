<?php

namespace App\Entity;

use App\Repository\ParticipantRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ParticipantRepository::class)]
#[ORM\Table(name: 'participant', uniqueConstraints: [
    new ORM\UniqueConstraint(name: 'uniq_participant_tournament_user', columns: ['tournament_id', 'user_id']),
])]
class Participant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'participants')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'participants')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Tournament $tournament = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $registeredAt;

    #[ORM\Column(nullable: true)]
    private ?int $seed = null;

    #[ORM\Column(length: 30)]
    private string $status = 'active';

    #[ORM\OneToMany(mappedBy: 'participant', targetEntity: MatchParticipant::class)]
    private Collection $matchParticipants;

    public function __construct()
    {
        $this->registeredAt = new \DateTimeImmutable();
        $this->matchParticipants = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
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

    public function getRegisteredAt(): \DateTimeImmutable
    {
        return $this->registeredAt;
    }

    public function setRegisteredAt(\DateTimeImmutable $registeredAt): self
    {
        $this->registeredAt = $registeredAt;

        return $this;
    }

    public function getSeed(): ?int
    {
        return $this->seed;
    }

    public function setSeed(?int $seed): self
    {
        $this->seed = $seed;

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
            $matchParticipant->setParticipant($this);
        }

        return $this;
    }

    public function removeMatchParticipant(MatchParticipant $matchParticipant): self
    {
        if ($this->matchParticipants->removeElement($matchParticipant)) {
            if ($matchParticipant->getParticipant() === $this) {
                $matchParticipant->setParticipant(null);
            }
        }

        return $this;
    }
}
