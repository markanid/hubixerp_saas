<?php

namespace Modules\Settings\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Modules\Settings\app\Models\Company;
use Modules\Settings\app\Models\User;

class AuthController extends Controller
{
    public function getLogin(){
        $company_logo = Company::orderBy('id', 'DESC')->pluck('company_logo')->first();
        $company_logo = trim($company_logo, '"'); 

        // Generate last 5 financial years from the current April-March year.
        $currentFinancialYearStart = now()->month >= 4 ? now()->year : now()->year - 1;
        $currentFinancialYear = $currentFinancialYearStart . '-' . ($currentFinancialYearStart + 1);
        $financialYears = [];
        for ($i = 0; $i < 5; $i++) {
            $startYear = $currentFinancialYearStart - $i;
            $endYear = $startYear + 1;
            $financialYears[] = "$startYear-$endYear";
        }
        return view('settings::auth.login', compact('company_logo', 'financialYears', 'currentFinancialYear'));
    }

    public function authenticate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "email"             => 'required|email',
            "password"          => 'required',
            "financial_year"    => 'required',
        ]);

        if ($validator->passes()) {
            // Change 'email' to 'user_email' and 'password' to 'user_password'
            if (Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
                $user = Auth::user();
                session(['id' => $user->id]);
                session(['financial_year' => $request->financial_year]); // Store year in session
                return redirect()->route('profile.dashboard');
            } else {
                return redirect()->route('auth.login')->with('error', 'Email or Password is incorrect');
            }
        } else {
            return redirect()->route('auth.login')
                ->withInput()
                ->withErrors($validator);
        }
    }

    public function registration(){
        return view('settings::auth.register');
    }

    public function registerProcess(Request $request){
        $validator = Validator::make($request->all(), [
            "name"=> 'required',
            "email"=> 'required|email|unique:users',
            "password"=> 'required|min:8|confirmed'
            ]);
            if ($validator->passes()) {
                $user = new User();
                $user->user_name    = $request->name;
                $user->email        = $request->email;
                $user->password     = Hash::make($request->password);
                $user->user_role    = 'Super Admin';
                $user->save();
                return redirect()->route('auth.login')->with('success', 'You have registered successfully...');
        } else {
            return redirect()->route('auth.registration')
            ->withInput()
            ->withErrors($validator); 
        }
    }

    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password'          => 'required',
            'new_password'              => 'required|min:8|confirmed',  // You can adjust the password requirements
            'new_password_confirmation' => 'required|min:8',
        ]);

        if ($validator->fails()) {
            dd($validator->errors());
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

    public function logout()
    {
        Auth::logout();
        return redirect()->route('auth.login');
    }

    
}
