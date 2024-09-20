<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\Book;
use App\Models\Borrowing;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller; // IMPORT CONTROLLER

class BookController extends Controller
{
    public function borrowBook(Request $request) {
        // Cari member berdasarkan ID
        $member = Member::find($request->member_id);
        if (!$member) {
            return response()->json(['message' => 'Member not found'], 404);
        }

        // Cek apakah member sedang dikenakan penalti atau sudah meminjam 2 buku
        if ($member->borrowings()->count() >= 2 || $member->is_penalized) {
            return response()->json(['message' => 'You cannot borrow more than 2 books or you are penalized.']);
        }

        // Cari buku berdasarkan ID
        $book = Book::find($request->book_id);
        if (!$book || $book->stock < 1) {
            return response()->json(['message' => 'Book is not available.']);
        }

        // Buat catatan peminjaman
        Borrowing::create([
            'member_id' => $member->id,
            'book_id' => $book->id,
            'borrowed_at' => now(),
        ]);

        // Kurangi stok buku
        $book->decrement('stock');

        return response()->json(['message' => 'Book borrowed successfully.']);
    }
}
