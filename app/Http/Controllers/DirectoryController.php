<?php

namespace App\Http\Controllers;

use App\Models\DirectoryUser;
use App\Models\SyncSelection;
use App\Services\Sync\RuleMatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class DirectoryController extends Controller
{
    public function index(Request $request, RuleMatcher $ruleMatcher): Response
    {
        $user = $request->user();
        $ruleMatches = $ruleMatcher->matchedDirectoryUsers($user);

        $manualEnabledIds = SyncSelection::query()
            ->where('user_id', $user->id)
            ->where('source', 'manual')
            ->where('enabled', true)
            ->pluck('directory_user_id');

        $syncedIds = $manualEnabledIds->merge($ruleMatches->keys())->unique()->values();

        $query = DirectoryUser::query()->where('account_enabled', true)->orderBy('display_name');

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('display_name', 'like', "%{$search}%")
                    ->orWhere('mail', 'like', "%{$search}%")
                    ->orWhere('job_title', 'like', "%{$search}%")
                    ->orWhere('department', 'like', "%{$search}%");
            });
        }

        if ($department = $request->string('department')->toString()) {
            $query->where('department', $department);
        }

        if ($jobTitle = $request->string('job_title')->toString()) {
            $query->where('job_title', $jobTitle);
        }

        $syncStatus = $request->string('sync_status')->toString();

        if ($syncStatus === 'synced') {
            $query->whereIn('id', $syncedIds);
        } elseif ($syncStatus === 'not_synced') {
            $query->whereNotIn('id', $syncedIds);
        }

        $directoryUsers = $query->paginate(100)->withQueryString();

        $selections = $user->syncSelections()->get()->keyBy('directory_user_id');

        $directoryUsers->getCollection()->transform(function (DirectoryUser $directoryUser) use ($selections, $ruleMatches, $user) {
            $selection = $selections->get($directoryUser->id);
            $matchedByRule = $ruleMatches->has($directoryUser->id);
            $source = $selection->source ?? ($matchedByRule ? 'rule' : null);

            if ($source === 'rule' && $user->sync_all_enabled) {
                $source = 'all';
            }

            $avatarPath = "avatars/{$directoryUser->id}.jpg";
            $avatarUrl = Storage::disk('public')->exists($avatarPath)
                ? Storage::disk('public')->url($avatarPath)
                : null;

            return [
                'id' => $directoryUser->id,
                'display_name' => $directoryUser->display_name,
                'mail' => $directoryUser->mail,
                'job_title' => $directoryUser->job_title,
                'department' => $directoryUser->department,
                'mobile_phone' => $directoryUser->mobile_phone,
                'business_phone' => $directoryUser->business_phone,
                'avatar_url' => $avatarUrl,
                'sync_enabled' => $selection->enabled ?? $matchedByRule,
                'sync_source' => $source,
            ];
        });

        return Inertia::render('Directory/Index', [
            'directoryUsers' => $directoryUsers,
            'filters' => $request->only(['search', 'department', 'job_title', 'sync_status']),
            'departments' => DirectoryUser::distinctDepartments(),
            'jobTitles' => DirectoryUser::distinctJobTitles(),
            'syncAllEnabled' => (bool) $user->sync_all_enabled,
        ]);
    }
}
