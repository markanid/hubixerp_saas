<?php

namespace Modules\Settings\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\UserHandler\UserHandler;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Modules\Settings\app\Models\Company;
use Modules\Settings\app\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use DataTables;


class UserController extends Controller
{
    public function index(){   
        $users = (new User())->getAllUsers();
        if ($users!=null && !$users->isEmpty()) {
            return view('settings::users.index', compact('users'))->with([
                'page_title' => "Users View",
                'title' => "Users Details"
            ]);
        } else {
            return redirect()->route('users.create');
        }
    }

    public function create(){
        $data = [
            'page_title'    => 'Add Users',
            'title'         =>  'User Details'
        ];
        return view('settings::users.create', $data);
    }

    public function store(Request $request){
        try {
            $validatedData  = $request->validate([
                'user_name' => 'required',
                'email'     => 'required|email|unique:users,email,' . ($request->id ?? 'null'),
                'password'  => 'required|confirmed',
                'user_role' => 'required',
                'user_logo' => 'image|mimes:jpeg,png,jpg,gif|max:5000',
            ]);

            $user = User::createUser($validatedData, $request);
       
            if($user){
                session()->flash('success', 'User has been successfully created.');
                return redirect()->route('users.index');
            } else {
                return redirect()->back()->with('error', 'User creation failed.');
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
        // Catch the validation exception and log the errors
            Log::error('Validation errors:', $e->errors());
            // Optionally, you can also log more info about the request
            Log::error('Request data:', $request->all());
            return redirect()->back()->withErrors($e->errors());
        }
    }

    public function edit(string $id){
        $user = User::findOrFail($id);
        $title = 'User Details';
        $page_title = 'Edit User';
        return view('settings::users.edit', compact('user', 'title','page_title'));
    }

    public function update(Request $request, string $id){
        try {
            $validatedData = $request->validate([
                'user_name' => 'required',
                'email'     => 'required|email|unique:users,email,' . ($request->id ?? 'null'),
                'user_role' => 'required',
                'user_logo' => 'image|mimes:jpeg,png,jpg,gif|max:5000',
            ]);

            $user = User::findOrFail($id);
            $result = $user->updateUser($validatedData, $request);
            if ($result) {
                return redirect()->route('users.show', ['id' => $user->id])
                    ->with('success', 'User has been successfully updated');
            } else {
                return redirect()->route('users.show', ['id' => $user->id])
                    ->with('info', 'No changes were made.');
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
        // Catch the validation exception and log the errors
            Log::error('Validation errors:', $e->errors());
            // Optionally, you can also log more info about the request
            Log::error('Request data:', $request->all());
        
            // Redirect back with validation errors
            return redirect()->back()->withErrors($e->errors());
        }
    }

    public function show($id) {
        $user = User::findOrFail($id);
        $title = 'View User';
        return view('settings::users.view', compact('user', 'title'));
    }
    public function showProfile($id) {
        $user = User::findOrFail($id);
        $title = 'View User';
        return view('settings::users.viewprofile', compact('user', 'title'));
    }
    public function showChangePasswordForm() {
        $id =    Auth::user()->id;
        $company_logo = Company::orderBy('created_at', 'DESC')->pluck('logo')->first();
        $company_logo = trim($company_logo, '"'); 
        $user = User::findOrFail($id);
        return view('settings::users.changepassword', compact('user', 'company_logo'));
    }

    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password'          => 'required',
            'new_password'              => 'required|min:8|confirmed',  // You can adjust the password requirements
            'new_password_confirmation' => 'required|min:8',
        ]);

        if ($validator->fails()) {
            return redirect()->route('users.changePasswordForm')
                ->withErrors($validator)
                ->withInput();
        }

        $user = Auth::user();

        // Check if the current password matches the user's current password
        if (!Hash::check($request->current_password, $user->password)) {
            return redirect()->route('users.changePasswordForm')
                ->withErrors(['current_password' => 'The current password is incorrect.']);
        }

        // Update the user's password
        $user->password = Hash::make($request->new_password);
        $user->save();

        return redirect()->route('company.index')->with('success', 'Your password has been changed successfully!');
    }

    public function destroy(string $id)
    {
        try {
            // Call the model method to handle deletion
            $user = User::findOrFail($id);
            $user->deleteUserWithLogo();

            return redirect()->route('users.index')->with('success', 'Record deleted successfully');
        } catch (\Exception $e) {
            // Log the error if there is one
            Log::error('Error deleting user:', ['error' => $e->getMessage(), 'id' => $id]);

            // Redirect back with an error message if deletion fails
            return redirect()->back()->with('error', 'There was an issue deleting the user.');
        }
    }
     
}
