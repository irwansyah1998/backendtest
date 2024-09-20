<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BookController;

// Rute untuk meminjam buku
Route::post('/books/borrow', [BookController::class, 'borrowBook']);

// Rute untuk mengembalikan buku
Route::post('/books/return', [BookController::class, 'returnBook']);

// Rute untuk mengecek semua buku
Route::get('/books', [BookController::class, 'checkBooks']);

// Rute untuk mengecek semua anggota
Route::get('/members', [BookController::class, 'checkMembers']);