<?php

namespace App\Infrastructure\Security\Voter;

use App\Domain\Video\Entity\Video;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;

final class VideoAccessVoter extends Voter
{
    public const string CAN_VIEW_DETAILS = 'CAN_VIEW_DETAILS';
    public const string CAN_START_TRANSCODE = 'CAN_START_TRANSCODE';
    public const string CAN_DOWNLOAD_TRANSCODE = 'CAN_DOWNLOAD_TRANSCODE';
    public const string CAN_CANCEL_TRANSCODE = 'CAN_CANCEL_TRANSCODE';
    public const string CAN_EDIT = 'CAN_EDIT';
    public const string CAN_DELETE = 'CAN_DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!$subject instanceof Video) {
            return false;
        }

        return in_array($attribute, [self::CAN_VIEW_DETAILS, self::CAN_START_TRANSCODE, self::CAN_DOWNLOAD_TRANSCODE, self::CAN_CANCEL_TRANSCODE, self::CAN_DELETE, self::CAN_EDIT], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
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

        return $subject->userId()->equals($user->id);
    }

    private function isAdmin(object $user): bool
    {
        $roles = $user->getRoles();

        return is_array($roles) && in_array('ROLE_ADMIN', $roles, true);
    }
}

