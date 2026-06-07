<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTierRequest;
use App\Models\Tier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * US-ADMIN-5 — CRUD account tiers. Invariants: exactly one default tier;
 * the default tier and tiers with users assigned cannot be deleted.
 */
class TierController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/tiers/index', [
            'tiers' => Tier::withCount('users')->orderBy('id')->get()
                ->map(fn (Tier $t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'slug' => $t->slug,
                    'maxDomains' => $t->max_domains,
                    'maxScanPages' => $t->max_scan_pages,
                    'monthlyPageviewCap' => $t->monthly_pageview_cap,
                    'scheduledScansAllowed' => $t->scheduled_scans_allowed,
                    'isDefault' => $t->is_default,
                    'users' => $t->users_count,
                ]),
        ]);
    }

    public function store(StoreTierRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $tier = Tier::create($request->validated());
            $this->keepSingleDefault($tier);
        });

        return redirect()->route('admin.tiers.index')->with('status', 'Tier created.');
    }

    public function update(StoreTierRequest $request, Tier $tier): RedirectResponse
    {
        DB::transaction(function () use ($request, $tier) {
            $tier->update($request->validated());
            $this->keepSingleDefault($tier);
        });

        return redirect()->route('admin.tiers.index')->with('status', 'Tier updated.');
    }

    public function destroy(Tier $tier): RedirectResponse
    {
        if ($tier->is_default) {
            return back()->withErrors(['tier' => 'Cannot delete the default tier. Mark another tier default first.']);
        }

        if ($tier->users()->exists()) {
            return back()->withErrors(['tier' => 'Reassign this tier\'s users before deleting it.']);
        }

        $tier->delete();

        return redirect()->route('admin.tiers.index')->with('status', 'Tier deleted.');
    }

    /**
     * Enforce exactly one default tier. If $tier was just marked default,
     * clear the flag on every other tier; if nothing is default, this is a
     * no-op (the seeder guarantees an initial default).
     */
    private function keepSingleDefault(Tier $tier): void
    {
        if ($tier->is_default) {
            Tier::whereKeyNot($tier->getKey())->where('is_default', true)->update(['is_default' => false]);
        }
    }
}
