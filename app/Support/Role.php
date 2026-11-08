<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Section 5 user roles. Checks are enforced in the service layer, not only in views.
 */
final class Role
{
    public const STORE_KEEPER = 'store_keeper';
    public const DEPARTMENT_USER = 'department_user';
    public const ENGINEER = 'biomedical_engineer';
    public const SUPERVISOR = 'supervisor';
    public const ADMINISTRATOR = 'administrator';
    public const AUDITOR = 'auditor';

    public const ALL = [
        self::STORE_KEEPER, self::DEPARTMENT_USER, self::ENGINEER,
        self::SUPERVISOR, self::ADMINISTRATOR, self::AUDITOR,
    ];

    public static function of(User $user): string
    {
        return $user->role ?? self::DEPARTMENT_USER;
    }

    public static function is(User $user, string ...$roles): bool
    {
        return in_array(self::of($user), $roles, true);
    }

    /** @throws AuthorizationException */
    public static function require(User $user, string $action, string ...$roles): void
    {
        if (!self::is($user, ...$roles)) {
            throw new AuthorizationException(sprintf(
                "Role '%s' is not authorized to %s (requires: %s).",
                self::of($user), $action, implode(', ', $roles)
            ));
        }
    }
}
