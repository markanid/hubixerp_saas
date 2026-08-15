<?php

namespace Modules\Master\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Master\app\Models\Group;

class GroupController extends Controller
{
    
    public function index($id = null)
    {
        $groups = Group::latest('id')->get();
        if ($groups!=null && !$groups->isEmpty()) {
            $data['groups'] = $groups;
            $data['page_title'] = "Groups List";
            return view('master::groups.index',$data);
        } else {
            return redirect()->route('groups.create');
        } 
    }

    public function createOrEdit($id = null)
    {
        $group = $id ? Group::findOrFail($id) : new Group();
        $data['page_title'] = $id ? "Edit Group" : "Create Group";
        $data['group'] = $group;
        return view('master::groups.create', $data);
    }

    public function storeOrUpdate(Request $request)
    {
        $validated = $request->validate([
            'groups'         => 'required|string|max:255',
        ]);

        $group = Group::updateOrCreate(
            ['id' => $request->id ?? null], 
            $validated
        );
    
        if ($group) {
            return redirect()->route('groups.index')->with('success', 'Group details updated successfully.');
        } else {
            return redirect()->back()->with('error', 'Failed to update brand details.');
        }
    }
    
    public function destroy($id)
    {
        $group = Group::findOrFail($id);
        $group->delete();
        return redirect()->route('groups.index')->with('success', 'Record deleted successfully');
    }
}
