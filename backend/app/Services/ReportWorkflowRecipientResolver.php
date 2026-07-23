<?php

namespace App\Services;

use App\Models\User;
use App\Support\ManagementRole;
use Illuminate\Support\Collection;

/**
 * Canonical recipient sets for report-workflow in-app alerts and simulations.
 *
 * No active/enabled column exists on users — all canonical role matches are used.
 * Super Admin is intentionally excluded from daily operational alert recipients.
 */
class ReportWorkflowRecipientResolver
{
    /**
     * Daily Organizer recipients for new CMart report requests.
     *
     * @return Collection<int, User>
     */
    public function activeOrganizers(): Collection
    {
        return User::query()
            ->where('role', ManagementRole::ORGANIZER)
            ->orderBy('id')
            ->get();
    }

    /**
     * CMart Management workspace recipients (single-venue fallback: all cmart_management).
     *
     * @return Collection<int, User>
     */
    public function activeCmartManagement(): Collection
    {
        return User::query()
            ->where('role', ManagementRole::CMART_MANAGEMENT)
            ->orderBy('id')
            ->get();
    }
}
