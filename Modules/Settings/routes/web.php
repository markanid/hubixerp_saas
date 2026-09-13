<?php


use Illuminate\Support\Facades\Route;
use Modules\Settings\app\Http\Controllers\{UserController,ProfileController,CompanyController,AuthController,LocalPrintJobController,PrintAgentController};

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::group(['middleware'=>'guest'],function(){
    Route::get('/', [AuthController::class, 'getLogin'])->name('auth.login');
    Route::post('/authenticate', [AuthController::class, 'authenticate'])->name('auth.authenticate');
    Route::get('/registration', [AuthController::class, 'registration'])->name('auth.registration');
    Route::post('/register-process', [AuthController::class, 'registerProcess'])->name('auth.registerProcess');
});

Route::group(['middleware'=>'auth'],function(){
    Route::get('/logout', [AuthController::class, 'logout'])->name('auth.logout');

    Route::get('/company', [CompanyController::class, 'index'])->name("company.index");
    Route::get('/company/edit', [CompanyController::class, 'edit'])->name("company.edit");
    Route::post('/company/update', [CompanyController::class, 'update'])->name("company.update");
    Route::get('/company/settings', [CompanyController::class, 'settings'])->name("company.settings");
    Route::post('/company/settings/update', [CompanyController::class, 'updateSettings'])->name("company.settings.update");
    Route::get('/company/delete', [CompanyController::class, 'destroy'])->name("company.delete");
    Route::post('/local-print-jobs', [LocalPrintJobController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('local-print-jobs.store');
    Route::get('/local-print-jobs/{localPrintJob}', [LocalPrintJobController::class, 'show'])->name('local-print-jobs.show');
    Route::middleware('can:sale.settings')->group(function () {
        Route::post('/print-agents', [PrintAgentController::class, 'store'])->name('print-agents.store');
        Route::post('/print-agents/{printAgent}/pairing-code', [PrintAgentController::class, 'refreshPairing'])->name('print-agents.pairing-code');
        Route::put('/print-agents/{printAgent}', [PrintAgentController::class, 'update'])->name('print-agents.update');
        Route::post('/print-agents/{printAgent}/default', [PrintAgentController::class, 'makeDefault'])->name('print-agents.default');
        Route::post('/print-agents/{printAgent}/bind-browser', [PrintAgentController::class, 'bindBrowser'])
            ->middleware('throttle:10,1')
            ->name('print-agents.bind-browser');
        Route::delete('/print-agent-browser-binding', [PrintAgentController::class, 'unbindBrowser'])->name('print-agents.unbind-browser');
        Route::post('/print-agents/{printAgent}/test', [PrintAgentController::class, 'test'])->name('print-agents.test');
    });

    Route::get('/users',[UserController::class,'index'])->name('users.index'); 
    Route::get('/users/create', [UserController::class, 'create'])->name("users.create");
    Route::post('/users/store',[UserController::class,'store'])->name('users.store');
    Route::get('/users/{id}', [UserController::class, 'show'])->name('users.show');
    Route::get('/userprofile/{id}', [UserController::class, 'showProfile'])->name('users.showprofile');
    Route::get('/users/edit/{id}', [UserController::class, 'edit'])->name('users.edit');
    Route::post('/users/update/{id}', [UserController::class, 'update'])->name('users.update');
    Route::get('/users/delete/{id}', [UserController::class, 'destroy'])->name('users.delete');
    Route::get('/user/changepassword', [UserController::class, 'showChangePasswordForm'])->name('users.changePasswordForm');
    Route::post('/user/changepassword', [UserController::class, 'changePassword'])->name('users.changepassword');
    
    Route::get('/dashboard', [ProfileController::class, 'dashboard'])->name('profile.dashboard');
    Route::get('/profile/dbbackup', [ProfileController::class, 'dbbackup'])->name('profile.dbbackup');
    Route::get('/profile/restore', [ProfileController::class, 'restore'])->name('profile.restore');
    Route::post('/profile/dbrestore', [ProfileController::class, 'restoreBackup'])->name('profile.restoreDB');
});
