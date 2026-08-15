<?php

namespace Modules\Master\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Master\app\Models\Category;
use Modules\Master\app\Models\Subcategory;

class SubcategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index($id = null)
    {
        $subcategory = Subcategory::latest('id')->get();
        if ($subcategory!=null && !$subcategory->isEmpty()) {
            $data['subcategory']   = $subcategory;
            $data['page_title'] = "Sub-Category List";
            return view('master::subcategory.index',$data);
        } else {
            return redirect()->route('subcategory.create');
        } 
    }

    public function createOrEdit($id = null)
    {
        $categories = Category::all();
        $subcategory = $id ? Subcategory::findOrFail($id) : new Subcategory();
        $data['page_title'] = $id ? "Edit Sub-Category" : "Create Sub-Category";
        $data['subcategory']   = $subcategory;
        $data['categories']    = $categories ;
        return view('master::subcategory.create', $data);
    }
    
    public function storeOrUpdate(Request $request)
    {
        $validated = $request->validate([
            'subcategory'  => 'required|string|max:50',
            'categoryid'   => 'required|exists:category,id'
        ]);

        $subcategory = Subcategory::updateOrCreate(
            ['id' => $request->id ?? null], 
            $validated
        );

        if ($subcategory) {
            return redirect()->route('subcategory.index')->with('success', 'Sub-Category details updated successfully.');
        } else {
            return redirect()->back()->with('error', 'Failed to update brand details.');
        }
    }

    public function destroy($id)
    {
        $subcategory = Subcategory::findOrFail($id);
        $subcategory->delete();
        return redirect()->route('subcategory.index')->with('success', 'Record deleted successfully');
    }
}
