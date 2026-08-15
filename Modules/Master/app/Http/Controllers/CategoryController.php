<?php

namespace Modules\Master\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Master\app\Models\Brand;
use Modules\Master\app\Models\Category;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index($id = null)
    {
        $category = Category::latest('id')->get();
        if ($category!=null && !$category->isEmpty()) {
            $data['category']   = $category;
            $data['page_title'] = "Category List";
            return view('master::category.index',$data);
        } else {
            return redirect()->route('category.create');
        }  
    }

    /**
     * Show the form for creating a new resource.
     */
    public function createOrEdit($id = null)
    {
        $category = $id ? Category::findOrFail($id) : new Category();
        $data['page_title'] = $id ? "Edit Category" : "Create Category";
        $data['category']   = $category;
        return view('master::category.create', $data);
    }

    public function storeOrUpdate(Request $request)
    {
        $validated = $request->validate([
            'category'  => 'required|string|max:50'
        ]);

        $category = Category::updateOrCreate(
            ['id' => $request->id ?? null], 
            $validated
        );

        if ($category) {
            return redirect()->route('category.index')->with('success', 'Category details updated successfully.');
        } else {
            return redirect()->back()->with('error', 'Failed to update brand details.');
        }
    }


    public function show($id)
    {
        $category = Category::findOrFail($id);
        $data['page_title'] = "View Category";
        $data['category']   = $category;
        return view('master::category.view',$data);
    }

    public function destroy($id)
    {
        $category = Category::findOrFail($id);
        $category->delete();
        return redirect()->route('category.index')->with('success', 'Record deleted successfully');
    }
}
