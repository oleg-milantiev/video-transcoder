<?php

namespace App\Infrastructure\Persistence\Doctrine\User;

use App\Infrastructure\Persistence\Doctrine\Video\VideoEntity;
use App\Presentation\Validator\Constraints\AtLeastOneAdmin;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\UuidV4 as SymfonyUuid;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
class UserEntity implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    public ?SymfonyUuid $id = null {
        get {
            return $this->id;
        }
    }

    #[ORM\Column(length: 180)]
    public ?string $email = null {
        get {
            return $this->email;
        }
    }

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    #[AtLeastOneAdmin]
    public array $roles = [];

    /**
     * @var string|null The hashed password
     */
    #[ORM\Column(nullable: true)]
    public ?string $password = null;

    /**
     * Transient plain password used only for forms — not persisted.
     *
     * @var string|null
     */
    public ?string $plainPassword = null;

    #[ORM\ManyToOne(targetEntity: TariffEntity::class)]
    #[ORM\JoinColumn(name: "tariff_id", referencedColumnName: "id", nullable: true)]
    public ?TariffEntity $tariff = null;

    /**
     * @var Collection<int, VideoEntity>
     */
    #[ORM\OneToMany(targetEntity: VideoEntity::class, mappedBy: 'user', orphanRemoval: true)]
    public Collection $videos;

    public function __construct()
    {
        $this->videos = new ArrayCollection();
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     *
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @see UserInterface
     */
    #[\Deprecated('This method is deprecated since Symfony 7.3. Logic is kept here for now.', 'symfony/security-http')]
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        $this->plainPassword = null;
    }

    public function __toString(): string
    {
        return $this->email;
    }
}
