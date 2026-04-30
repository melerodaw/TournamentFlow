<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
class User
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    private string $username;

    #[ORM\Column(length: 180, unique: true)]
    private string $email;

    #[ORM\Column(length: 255)]
    private string $passwordHash;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $avatarPath = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\OneToMany(mappedBy: 'organizer', targetEntity: Tournament::class)]
    private Collection $tournamentsOrganized;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Participant::class)]
    private Collection $participants;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->tournamentsOrganized = new ArrayCollection();
        $this->participants = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function setPasswordHash(string $passwordHash): self
    {
        $this->passwordHash = $passwordHash;

        return $this;
    }

    public function getAvatarPath(): ?string
    {
        return $this->avatarPath;
    }

    public function setAvatarPath(?string $avatarPath): self
    {
        $this->avatarPath = $avatarPath;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * @return Collection<int, Tournament>
     */
    public function getTournamentsOrganized(): Collection
    {
        return $this->tournamentsOrganized;
    }

    public function addTournamentOrganized(Tournament $tournament): self
    {
        if (!$this->tournamentsOrganized->contains($tournament)) {
            $this->tournamentsOrganized->add($tournament);
            $tournament->setOrganizer($this);
        }

        return $this;
    }

    public function removeTournamentOrganized(Tournament $tournament): self
    {
        if ($this->tournamentsOrganized->removeElement($tournament)) {
            if ($tournament->getOrganizer() === $this) {
                $tournament->setOrganizer(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Participant>
     */
    public function getParticipants(): Collection
    {
        return $this->participants;
    }

    public function addParticipant(Participant $participant): self
    {
        if (!$this->participants->contains($participant)) {
            $this->participants->add($participant);
            $participant->setUser($this);
        }

        return $this;
    }

    public function removeParticipant(Participant $participant): self
    {
        if ($this->participants->removeElement($participant)) {
            if ($participant->getUser() === $this) {
                $participant->setUser(null);
            }
        }

        return $this;
    }
}
