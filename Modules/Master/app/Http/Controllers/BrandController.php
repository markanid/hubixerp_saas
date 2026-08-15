<?php

namespace Modules\Master\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Modules\Master\app\Models\Brand;

class BrandController extends Controller
{
    public function index($id = null)
    {
        $brand = Brand::latest('id')->get();
        if ($brand!=null && !$brand->isEmpty()) {
            $data['brand'] = $brand;
            $data['page_title'] = "Brands List";
            return view('master::brands.index',$data);
        } else {
            return redirect()->route('brands.create');
        }       
    }

    public function createOrEdit($id = null)
    {
        $brand = $id ? Brand::findOrFail($id) : new Brand();
        $data['page_title'] = $id ? "Edit Brand" : "Create Brand";
        $data['brand'] = $brand;
        return view('master::brands.create', $data);
    }

    public function storeOrUpdate(Request $request)
    {
        $validated = $request->validate([
            'brand'         => 'required|string|max:255',
            'brand_image'   => 'nullable|image|mimes:jpg,png,jpeg|max:300000', 
        ]);

        $isNew = empty($request->id);
        $brand = Brand::find($request->id);
        
        if ($request->hasFile('brand_image')) {
            // Delete the old image if it exists
            if ($brand && $brand->brand_image) {
                Storage::disk('public')->delete('brand_logos/' . $brand->brand_image);
            }
            $file = $request->file('brand_image');
            $filename = time() . '_' . $file->getClientOriginalName(); 
            $file->storeAs('brand_logos', $filename, 'public'); 
            $validated['brand_image'] = $filename; 
        }    
        $brand = Brand::updateOrCreate(
            ['id' => $request->id ?? null], 
            $validated
        );
    
        if ($brand) {
            return $isNew
                ? redirect()->route('brands.index')->with('success', 'Brand created successfully.')
                : redirect()->route('brands.show', $brand->id)->with('success', 'Brand details updated successfully.');
        } else {
            return redirect()->back()->with('error', 'Failed to update brand details.');
        }
    }

    public function show($id)
    {
        $data['page_title'] = "View Brand";
        $data['brand'] = Brand::findOrFail($id);
        return view('master::brands.view',$data);
    }

    public function destroy($id)
    {
        $brand = Brand::findOrFail($id);
        if (!empty($brand->brand_image) && Storage::disk('public')->exists('brand_logos/' . $brand->brand_image)) {
            Storage::disk('public')->delete('brand_logos/' . $brand->brand_image);
        }
        Storage::delete('public/brand_logos/' . $brand->brand_image);
        $brand->delete();
        return redirect()->route('brands.index')->with('success', 'Record deleted successfully');
    }
}
