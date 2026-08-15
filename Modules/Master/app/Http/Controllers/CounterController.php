<?php

namespace Modules\Master\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Pos\app\Models\PosCounter;
use Modules\Settings\app\Models\User;

class CounterController extends Controller
{
    public function index()
    {
        $counters = PosCounter::with('user')->latest('id')->get();

        return view('master::counters.index', [
            'page_title' => 'POS Counters',
            'counters' => $counters,
        ]);
    }

    public function createOrEdit($id = null)
    {
        return view('master::counters.create', [
            'page_title' => $id ? 'Edit POS Counter' : 'Create POS Counter',
            'counter' => $id ? PosCounter::findOrFail($id) : new PosCounter(['is_active' => true]),
            'users' => User::orderBy('user_name')->get(),
        ]);
    }

    public function storeOrUpdate(Request $request)
    {
        $validated = $request->validate([
            'id' => ['nullable', 'integer', 'exists:pos_counters,id'],
            'name' => ['required', 'string', 'max:100', 'unique:pos_counters,name,' . $request->id],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $counter = PosCounter::updateOrCreate(
            ['id' => $validated['id'] ?? null],
            [
                'name' => $validated['name'],
                'user_id' => $validated['user_id'] ?? null,
                'is_active' => (bool) ($validated['is_active'] ?? false),
            ]
        );

        return redirect()
            ->route('counters.show', $counter->id)
            ->with('success', 'POS counter saved successfully.');
    }

    public function show($id)
    {
        return view('master::counters.view', [
            'page_title' => 'View POS Counter',
            'counter' => PosCounter::with('user')->findOrFail($id),
        ]);
    }

    public function destroy($id)
    {
        $counter = PosCounter::findOrFail($id);

        if ($counter->sessions()->where('status', 'open')->exists()) {
            return redirect()->route('counters.index')->with('error', 'Close this counter before deleting it.');
        }

        $counter->delete();

        return redirect()->route('counters.index')->with('success', 'POS counter deleted successfully.');
    }
}
