<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BookController;

Route::get('/', function () {
    return view('welcome');
});

// Rute untuk meminjam buku
Route::post('/api/books/borrow', [BookController::class, 'borrowBook']);

// Rute untuk mengembalikan buku
Route::post('/api/books/return', [BookController::class, 'returnBook']);

// Rute untuk mengecek semua buku
Route::get('/api/books', [BookController::class, 'checkBooks']);

// Rute untuk mengecek semua anggota
Route::get('/api/members', [BookController::class, 'checkMembers']);

Route::get('/api/debug', [BookController::class,'Debug']);