<?php

namespace App\Http\Controllers;

use OpenApi\Annotations as OA;
use App\Models\Member;
use App\Models\Book;
use App\Models\Borrowing;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller; // IMPORT CONTROLLER

class BookController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/books/borrow",
     *     summary="Borrow a book",
     *     tags={"Books"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="member_id", type="string", example="M001"),
     *             @OA\Property(property="book_id", type="string", example="JK-45"),
     *         )
     *     ),
     *     @OA\Response(response="200", description="Book borrowed successfully"),
     *     @OA\Response(response="404", description="Member not found"),
     *     @OA\Response(response="400", description="Book is not available or you cannot borrow more than 2 books"),
     * )
     */
    public function borrowBook(Request $request)
    {
        // Validasi input
        $request->validate([
            'member_id' => 'required|string',
            'book_id' => 'required|string',
        ]);

        // Cari member berdasarkan ID
        $member = Member::where('code', $request->member_id)->first();
        if (!$member) {
            return response()->json(['message' => 'Member not found'], 404);
        }

        // Cek apakah member sedang dikenakan penalti atau sudah meminjam 2 buku
        if ($member->borrowings()->count() >= 2 || $member->is_penalized) {
            return response()->json(['message' => 'You cannot borrow more than 2 books or you are penalized.'], 400);
        }

        // Cari buku berdasarkan ID
        $book = Book::where('code', $request->book_id)->first();
        if (!$book || $book->stock < 1) {
            return response()->json(['message' => 'Book is not available.'], 400);
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

    /**
     * @OA\Post(
     *     path="/api/books/return",
     *     summary="Return a borrowed book",
     *     tags={"Books"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="member_id", type="string", example="M001"),
     *             @OA\Property(property="book_id", type="string", example="JK-45"),
     *         )
     *     ),
     *     @OA\Response(response="200", description="Book returned successfully"),
     *     @OA\Response(response="404", description="Member or book not found"),
     *     @OA\Response(response="400", description="This book was not borrowed by the member"),
     * )
     */
    public function returnBook(Request $request)
    {
        // Cari member berdasarkan ID
        $member = Member::find($request->member_id);
        if (!$member) {
            return response()->json(['message' => 'Member not found'], 404);
        }

        // Cari buku berdasarkan ID
        $book = Book::find($request->book_id);
        if (!$book) {
            return response()->json(['message' => 'Book not found'], 404);
        }

        // Periksa apakah member telah meminjam buku ini
        $borrowing = Borrowing::where('member_id', $member->id)
            ->where('book_id', $book->id)
            ->first();

        if (!$borrowing) {
            return response()->json(['message' => 'This book was not borrowed by the member'], 400);
        }

        // Hitung lama peminjaman
        $daysBorrowed = now()->diffInDays($borrowing->borrowed_at);
        if ($daysBorrowed > 7) {
            // Penalti jika dikembalikan setelah lebih dari 7 hari
            $member->is_penalized = true;
            $member->save();
        }

        // Hapus catatan peminjaman dan tambahkan kembali stok buku
        $borrowing->delete();
        $book->increment('stock');

        return response()->json(['message' => 'Book returned successfully.']);
    }

    /**
     * @OA\Get(
     *     path="/api/books",
     *     summary="Check all books",
     *     tags={"Books"},
     *     @OA\Response(response="200", description="List of books"),
     * )
     */
    public function checkBooks()
    {
        // Ambil semua buku dan tampilkan stok
        $books = Book::all();
        return response()->json($books);
    }

    /**
     * @OA\Get(
     *     path="/api/members",
     *     summary="Check all members",
     *     tags={"Books"},
     *     @OA\Response(response="200", description="List of members with borrowed books count"),
     * )
     */
    public function checkMembers()
    {
        // Ambil semua anggota dan jumlah buku yang sedang dipinjam
        $members = Member::withCount('borrowings')->get();
        return response()->json($members);
    }
}
