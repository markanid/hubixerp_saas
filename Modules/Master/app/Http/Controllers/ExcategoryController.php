<?php

namespace Modules\Master\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Master\app\Models\Excategory;

class ExcategoryController extends Controller
{
    public function index($id = null)
    {
        $excategory = Excategory::latest('id')->get();
        if ($excategory!=null && !$excategory->isEmpty()) {
            $data['excategory'] = $excategory;
            $data['page_title'] = "Ex-Category List";
            return view('master::excategory.index',$data);
        } else {
            return redirect()->route('excategory.create');
        }       
    }

    public function createOrEdit($id = null)
    {
        $excategory = $id ? Excategory::findOrFail($id) : new Excategory();
        $data['page_title'] = $id ? "Edit Ex-Category" : "Create Ex-Category";
        $data['excategory'] = $excategory;
        return view('master::excategory.create', $data);
    }

    public function storeOrUpdate(Request $request)
    {
        $validated = $request->validate([
            'category'         => 'required|string|max:255',
        ]);
      
        $excategory = Excategory::updateOrCreate(
            ['id' => $request->id ?? null], 
            $validated
        );
    
        if ($excategory) {
            return redirect()->route('excategory.index')->with('success', 'Expense Category details updated successfully.');
        } else {
            return redirect()->back()->with('error', 'Failed to update expense category details.');
        }
    }

    public function destroy($id)
    {
        $excategory = Excategory::findOrFail($id);
        $excategory->delete();
        return redirect()->route('excategory.index')->with('success', 'Record deleted successfully');
    }
}
