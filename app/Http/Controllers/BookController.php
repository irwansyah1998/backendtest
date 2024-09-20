<?php

namespace App\Http\Controllers;

use OpenApi\Annotations as OA;
use App\Models\Member;
use App\Models\Book;
use App\Models\Borrowing;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

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

        // Cek apakah member sedang dikenakan penalti
        if ($member->is_penalized && now()->lessThan($member->penalty_until)) {
            return response()->json(['message' => 'You are under penalty and cannot borrow books for 3 days.'], 400);
        }

        // Cek jumlah buku yang sedang dipinjam oleh member
        $borrowedCount = Borrowing::where('member_id', $member->id)
            ->whereNull('returned_at') // Pastikan hanya yang belum dikembalikan
            ->count();

        if ($borrowedCount >= 2) {
            return response()->json(['message' => 'You cannot borrow more than 2 books.'], 400);
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

        // Cari buku berdasarkan ID
        $book = Book::where('code', $request->book_id)->first();
        if (!$book) {
            return response()->json(['message' => 'Book not found'], 404);
        }

        // Periksa apakah member telah meminjam buku ini
        $borrowing = Borrowing::where('member_id', $member->id)
            ->where('book_id', $book->id)
            ->whereNull('returned_at') // Pastikan hanya yang belum dikembalikan
            ->first();

        if (!$borrowing) {
            return response()->json(['message' => 'This book was not borrowed by the member'], 400);
        }

        // Hitung lama peminjaman
        $daysBorrowed = now()->diffInDays($borrowing->borrowed_at);
        if ($daysBorrowed > 7) {
            $member->is_penalized = true;
            $member->penalty_until = now()->addDays(3);
            $member->save();
        }

        // Tandai buku sebagai dikembalikan
        $borrowing->returned_at = now();
        $borrowing->save();

        // Tambahkan kembali stok buku
        $book->increment('stock');

        return response()->json(['message' => 'Book returned successfully.']);
    }

    /**
     * @OA\Get(
     *     path="/api/books",
     *     summary="Check all books",
     *     tags={"Books"},
     *     @OA\Response(response="200", description="List of books with available quantities"),
     *     @OA\Response(response="500", description="Server error")
     * )
     */
    public function checkBooks()
    {
        // Ambil semua buku dan tampilkan stok yang tidak sedang dipinjam
        $books = Book::withCount(['borrowings' => function ($query) {
            $query->whereNull('returned_at'); // Hanya hitung yang belum dikembalikan
        }])->get()->map(function ($book) {
            return [
                'id' => $book->id,
                'code' => $book->code,
                'title' => $book->title,
                'available_stock' => $book->stock - $book->borrowings_count, // Mengurangi dengan jumlah yang sedang dipinjam
            ];
        });

        return response()->json($books);
    }

    /**
     * @OA\Get(
     *     path="/api/members",
     *     summary="Check all members",
     *     tags={"Members"},
     *     @OA\Response(response="200", description="List of members with borrowed books count"),
     * )
     */
    public function checkMembers()
    {
        // Ambil semua anggota dan jumlah buku yang sedang dipinjam
        $members = Member::withCount('borrowings')->get();
        return response()->json($members);
    }

    public function Debug()
    {
        return response()->json(['message' => 'Debug endpoint hit successfully']);
    }
}
