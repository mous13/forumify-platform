<?php

declare(strict_types=1);

namespace Forumify\Forum\Entity;

use Doctrine\ORM\Mapping as ORM;
use Forumify\Core\Entity\IdentifiableEntityTrait;
use Forumify\Core\Entity\User;

#[ORM\Entity]
class CommentReaction
{
    use IdentifiableEntityTrait;

    public function __construct(
        #[ORM\ManyToOne(Comment::class, inversedBy: 'reactions')]
        #[ORM\JoinColumn(onDelete: 'CASCADE')]
        private readonly Comment $comment,
        #[ORM\ManyToOne(User::class)]
        private readonly User $user,
        #[ORM\ManyToOne(Reaction::class)]
        private readonly Reaction $reaction
    ) {
    }

    public function getComment(): Comment
    {
        return $this->comment;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getReaction(): Reaction
    {
        return $this->reaction;
    }
}
