<?php

namespace App\Infrastructure\Security\Voter;

use App\Domain\Video\Entity\Video;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class VideoAccessVoter extends Voter
{
    public const string CAN_VIEW_DETAILS = 'CAN_VIEW_DETAILS';
    public const string CAN_START_TRANSCODE = 'CAN_START_TRANSCODE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!$subject instanceof Video) {
            return false;
        }

        return in_array($attribute, [self::CAN_VIEW_DETAILS, self::CAN_START_TRANSCODE], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        if (!$subject instanceof Video) {
            return false;
        }

        $user = $token->getUser();
        if (!is_object($user)) {
            return false;
        }

        if ($this->isAdmin($user)) {
            return true;
        }

        if ($user->id === null) {
            return false;
        }

        return $subject->userId() === $user->id;
    }

    private function isAdmin(object $user): bool
    {
        $roles = $user->getRoles();

        return is_array($roles) && in_array('ROLE_ADMIN', $roles, true);
    }
}

