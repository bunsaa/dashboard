<?php

namespace App\Http\Middleware;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class EnsureTeamMembership
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, ?string $minimumRole = null): Response
    {
        [$user, $team] = [$request->user(), $this->team($request)];

        // Fast-path: if current_team_id already matches we know the user belongs to this team,
        // skipping an extra DB query on every authenticated request.
        $belongsToTeam = $user && $team && (
            $user->current_team_id === $team->id || $user->belongsToTeam($team)
        );

        abort_if(! $user || ! $team || ! $belongsToTeam, 403);

        $this->ensureTeamMemberHasRequiredRole($user, $team, $minimumRole);

        if ($request->route('current_team') && ! $user->isCurrentTeam($team)) {
            $user->switchTeam($team);
        }

        return $next($request);
    }

    /**
     * Ensure the given user has at least the given role, if applicable.
     */
    protected function ensureTeamMemberHasRequiredRole(User $user, Team $team, ?string $minimumRole): void
    {
        if ($minimumRole === null) {
            return;
        }

        $role = $user->teamRole($team);

        $requiredRole = TeamRole::tryFrom($minimumRole);

        abort_if(
            $requiredRole === null ||
            $role === null ||
            ! $role->isAtLeast($requiredRole),
            403,
        );
    }

    /**
     * Get the team associated with the request.
     */
    protected function team(Request $request): ?Team
    {
        $team = $request->route('current_team') ?? $request->route('team');

        if (\is_string($team)) {
            // Cache only the scalar ID (not the model object) to avoid __PHP_Incomplete_Class on deserialization.
            // Team::find() by primary key is fast and reliably hydrates the model.
            $teamId = Cache::remember("team.slug-id.{$team}", 300, fn () => Team::where('slug', $team)->value('id'));
            $team = $teamId ? Team::find($teamId) : null;
        }

        return $team;
    }
}
