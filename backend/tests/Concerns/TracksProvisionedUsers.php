<?php

namespace Tests\Concerns;

use App\Models\User;

/**
 * Tracks users created by requireUser() helpers so they can be removed after tests.
 */
trait TracksProvisionedUsers
{
    use CleansUpTestFixtures;

    /** @var list<int> */
    private array $provisionedUserIds = [];

    protected function provisionUser(string $email, string $role, string $name): User
    {
        $user = User::where('email', $email)->first();
        if ($user) {
            if ($user->role !== $role) {
                $user->role = $role;
                $user->save();
            }

            return $user;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => bcrypt('password123'),
            'phone_number' => '0199999999',
            'role' => $role,
            'vendor_status' => 'none',
        ]);

        $this->provisionedUserIds[] = $user->id;

        return $user;
    }

    protected function cleanupProvisionedUsers(): void
    {
        try {
            if ($this->provisionedUserIds !== []) {
                $this->deleteUsersAndDependencies($this->provisionedUserIds);
            }
        } finally {
            $this->provisionedUserIds = [];
        }
    }
}
