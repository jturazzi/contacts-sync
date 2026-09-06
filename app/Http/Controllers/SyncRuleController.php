<?php

namespace App\Http\Controllers;

use App\Jobs\SyncUserContactsJob;
use App\Models\DirectoryUser;
use App\Models\SyncRule;
use App\Services\Sync\RuleMatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SyncRuleController extends Controller
{
    public function index(Request $request, RuleMatcher $ruleMatcher): Response
    {
        $rules = $request->user()->syncRules()->latest()->get()->map(function (SyncRule $rule) use ($ruleMatcher) {
            $matches = $ruleMatcher->matchingDirectoryUsers($rule);

            return [
                'id' => $rule->id,
                'name' => $rule->name,
                'department' => $rule->department,
                'job_title' => $rule->job_title,
                'match_type' => $rule->match_type,
                'enabled' => $rule->enabled,
                'matches_count' => $matches->count(),
                'matched_names' => $matches->pluck('display_name')->values(),
            ];
        });

        return Inertia::render('Rules/Index', [
            'rules' => $rules,
            'departments' => DirectoryUser::distinctDepartments(),
            'jobTitles' => DirectoryUser::distinctJobTitles(),
            'syncAllEnabled' => (bool) $request->user()->sync_all_enabled,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        $request->user()->syncRules()->create($validated);

        SyncUserContactsJob::dispatch($request->user()->id);

        return back()->with('success', __('messages.rule_created'));
    }

    public function update(Request $request, SyncRule $syncRule): RedirectResponse
    {
        $this->authorizeOwnership($request, $syncRule);

        $syncRule->update($this->validated($request));

        SyncUserContactsJob::dispatch($request->user()->id);

        return back()->with('success', __('messages.rule_updated'));
    }

    public function destroy(Request $request, SyncRule $syncRule): RedirectResponse
    {
        $this->authorizeOwnership($request, $syncRule);

        $syncRule->delete();

        SyncUserContactsJob::dispatch($request->user()->id);

        return back()->with('success', __('messages.rule_deleted'));
    }

    public function preview(Request $request, RuleMatcher $ruleMatcher): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'department' => ['nullable', 'string'],
            'job_title' => ['nullable', 'string'],
            'match_type' => ['required', 'in:exact,contains'],
        ]);

        $rule = new SyncRule($validated);
        $matches = $ruleMatcher->matchingDirectoryUsers($rule);

        return response()->json([
            'count' => $matches->count(),
            'names' => $matches->pluck('display_name')->values(),
        ]);
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'match_type' => ['required', 'in:exact,contains'],
            'enabled' => ['boolean'],
        ]);
    }

    protected function authorizeOwnership(Request $request, SyncRule $syncRule): void
    {
        abort_unless($syncRule->user_id === $request->user()->id, 403);
    }
}
