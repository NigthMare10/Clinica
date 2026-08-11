<?php

use App\Http\Controllers\Admin\CatalogController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FiscalAuthorizationController;
use App\Http\Controllers\Admin\GeneratedMedicalDocumentController;
use App\Http\Controllers\Admin\GlobalSearchController;
use App\Http\Controllers\Admin\InstitutionalAssetController;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\MedicalDocumentController;
use App\Http\Controllers\Admin\PatientController;
use App\Http\Controllers\Admin\PdfTemplateController;
use App\Http\Controllers\Admin\PrivateDoctorAssetController;
use App\Http\Controllers\Admin\PrivateDocumentDownloadController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\SitePageController;
use App\Http\Controllers\Admin\SpecialtyController;
use App\Http\Controllers\Admin\VerificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicInvoiceVerificationController;
use App\Http\Controllers\PublicSiteController;
use App\Http\Controllers\PublicVerificationController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicSiteController::class, 'home'])->name('public.home');
Route::get('/especialidades', [PublicSiteController::class, 'specialties'])->name('public.specialties.index');
Route::get('/especialidades/{specialty:slug}', [PublicSiteController::class, 'specialty'])->name('public.specialties.show');
Route::get('/clinica', fn (PublicSiteController $controller) => $controller->page('clinica', 'Public/Clinic'))->name('public.clinic');
Route::get('/clinicas', [PublicSiteController::class, 'clinics'])->name('public.clinics.index');
Route::get('/contacto', fn (PublicSiteController $controller) => $controller->page('contacto', 'Public/Contact'))->name('public.contact');

Route::middleware('noindex')->prefix('verificar')->name('public.verify.')->group(function () {
    Route::get('/', [PublicVerificationController::class, 'lookup'])->name('lookup');
    Route::get('/{token}', [PublicVerificationController::class, 'token'])->middleware('throttle:30,1')->name('token');
    Route::post('/codigo', [PublicVerificationController::class, 'code'])->middleware('throttle:10,1')->name('code');
    Route::post('/archivo', [PublicVerificationController::class, 'file'])->middleware('throttle:3,1')->name('file');
});

Route::get('/verificar/factura/{token}', PublicInvoiceVerificationController::class)
    ->middleware(['noindex', 'throttle:30,1'])->name('public.invoice.verify');

Route::middleware(['auth', 'verified', 'role:SUPER_ADMIN,ADMINISTRATOR,DOCTOR,DOCUMENT_OPERATOR,AUDITOR', 'noindex'])
    ->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::get('/search', GlobalSearchController::class)->name('search');
        Route::get('/documents', [MedicalDocumentController::class, 'index'])->name('documents.index');
        Route::get('/documents/create', [MedicalDocumentController::class, 'create'])->name('documents.create');
        Route::get('/documents/generate/{kind}', [GeneratedMedicalDocumentController::class, 'create'])->name('documents.generate');
        Route::post('/documents/generate/{kind}/analyze', [GeneratedMedicalDocumentController::class, 'analyze'])->name('documents.generate.analyze');
        Route::post('/documents/generate/{kind}', [GeneratedMedicalDocumentController::class, 'store'])->name('documents.generate.store');
        Route::post('/documents', [MedicalDocumentController::class, 'store'])->name('documents.store');
        Route::get('/documents/{document}/review', [MedicalDocumentController::class, 'review'])->name('documents.review');
        Route::put('/documents/{document}/review', [MedicalDocumentController::class, 'confirm'])->name('documents.confirm');
        Route::post('/documents/{document}/issue', [MedicalDocumentController::class, 'issue'])->name('documents.issue');
        Route::post('/documents/{document}/revoke', [MedicalDocumentController::class, 'revoke'])->name('documents.revoke');
        Route::post('/documents/{document}/corrections', [MedicalDocumentController::class, 'correct'])->name('documents.corrections.store');
        Route::get('/documents/{document}/download/{version}', PrivateDocumentDownloadController::class)->name('documents.download');
        Route::get('/verifications', [VerificationController::class, 'index'])->name('verifications.index');
        Route::get('/fiscal-authorizations', [FiscalAuthorizationController::class, 'index'])->name('fiscal-authorizations.index');
        Route::post('/fiscal-authorizations', [FiscalAuthorizationController::class, 'store'])->name('fiscal-authorizations.store');
        Route::put('/fiscal-authorizations/billing-profile', [FiscalAuthorizationController::class, 'upsertBillingProfile'])->name('fiscal-authorizations.billing-profile.upsert');
        Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('/invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
        Route::post('/invoices', [InvoiceController::class, 'store'])->name('invoices.store');
        Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
        Route::get('/invoices/{invoice}/download', [InvoiceController::class, 'download'])->name('invoices.download');
        Route::post('/invoices/{invoice}/issue', [InvoiceController::class, 'issue'])->name('invoices.issue');
        Route::post('/invoices/{invoice}/void', [InvoiceController::class, 'void'])->name('invoices.void');
        Route::get('/provider/private-assets/{doctor}/{asset}', PrivateDoctorAssetController::class)->name('doctors.assets.show');
        Route::middleware('role:SUPER_ADMIN,ADMINISTRATOR')->prefix('settings/signature')->name('settings.signature.')->group(function () {
            Route::get('/', [InstitutionalAssetController::class, 'index'])->name('index');
            Route::post('/', [InstitutionalAssetController::class, 'store'])->name('store');
            Route::post('/extract', [InstitutionalAssetController::class, 'extract'])->name('extract');
            Route::post('/import-combined', [InstitutionalAssetController::class, 'importCombined'])->name('import-combined');
            Route::get('/{asset}/preview', [InstitutionalAssetController::class, 'preview'])->name('preview');
            Route::post('/{asset}/activate', [InstitutionalAssetController::class, 'activate'])->name('activate');
            Route::delete('/{asset}', [InstitutionalAssetController::class, 'destroy'])->name('destroy');
        });
        Route::resource('specialties', SpecialtyController::class)->except(['show', 'destroy']);
        Route::patch('/specialties/{specialty}/activation', [SpecialtyController::class, 'activate'])->name('specialties.activation');
        Route::resource('patients', PatientController::class)->except(['destroy']);
        Route::resource('templates', PdfTemplateController::class)->except(['show', 'destroy'])->parameters(['templates' => 'template']);
        Route::patch('/templates/{template}/activation', [PdfTemplateController::class, 'activate'])->name('templates.activation');
        Route::get('/audit', [CatalogController::class, 'audit'])->name('audit.index');
        Route::resource('settings', SettingController::class)->except(['show', 'destroy']);
        Route::resource('content', SitePageController::class)->except(['show', 'destroy'])->parameters(['content' => 'page']);
        Route::patch('/content/{page}/activation', [SitePageController::class, 'activate'])->name('content.activation');
    });

Route::redirect('/dashboard', '/admin')->middleware(['auth', 'verified', 'role:SUPER_ADMIN,ADMINISTRATOR,DOCTOR,DOCUMENT_OPERATOR,AUDITOR', 'noindex'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
