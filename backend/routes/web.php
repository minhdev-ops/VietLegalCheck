<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// SPA sử dụng route `/dang-nhap`; route này tồn tại để URL generator không lỗi
// khi guard Sanctum tạo redirect cho request API không có Accept JSON (FR-AUTH-01).
Route::get('/dang-nhap', fn () => abort(404))->name('login');
