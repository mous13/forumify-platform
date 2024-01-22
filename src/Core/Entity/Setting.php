<?php

declare(strict_types=1);

namespace Forumify\Core\Entity;

use Doctrine\ORM\Mapping as ORM;
use Forumify\Core\Repository\SettingRepository;

#[ORM\Entity(repositoryClass: SettingRepository::class)]
class Setting
{
    #[ORM\Id]
    #[ORM\Column('`key`')]
    private readonly string $key;

    #[ORM\Column(type: 'json')]
    private mixed $value = null;

    public function __construct(string $key)
    {
        $this->key = $key;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getValue(): int|string|float|array|null
    {
        return $this->value;
    }

    public function setValue(int|string|float|array|null $value): void
    {
        $this->value = $value;
    }
}
