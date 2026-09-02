<?php

use App\Http\Controllers\Auth\KeycloakController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Keycloak SSO routes
|--------------------------------------------------------------------------
|
| Di-load dari bootstrap/app.php dengan middleware group 'web' (butuh session
| + CSRF state untuk alur OAuth). Sengaja di luar middleware 'auth' supaya
| tamu bisa memulai login. Login lokal (email/NIP + password) tetap lewat
| routes/web.php + LoginController, tidak tersentuh.
|
*/

Route::get('/auth/keycloak/redirect', [KeycloakController::class, 'redirect'])->name('sso.redirect');
Route::get('/auth/keycloak/callback', [KeycloakController::class, 'callback'])->name('sso.callback');
