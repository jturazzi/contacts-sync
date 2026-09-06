<?php

namespace App\Http\Controllers;

use App\Jobs\SyncUserContactsJob;
use App\Models\DirectoryUser;
use App\Models\SyncSelection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SyncSelectionController extends Controller
{
    public function toggle(Request $request, DirectoryUser $directoryUser): RedirectResponse
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        SyncSelection::query()->updateOrCreate(
            ['user_id' => $request->user()->id, 'directory_user_id' => $directoryUser->id],
            ['source' => 'manual', 'enabled' => $validated['enabled']],
        );

        SyncUserContactsJob::dispatch($request->user()->id);

        return back();
    }

    public function updateSyncAll(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        $request->user()->update(['sync_all_enabled' => $validated['enabled']]);

        SyncUserContactsJob::dispatch($request->user()->id);

        return back()->with('success', $validated['enabled']
            ? __('messages.sync_all_enabled')
            : __('messages.sync_all_disabled'));
    }
}
