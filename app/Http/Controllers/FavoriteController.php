<?php

namespace App\Http\Controllers;

use App\Models\Court;
use App\Models\Favorite;

class FavoriteController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | UC08 - LIST
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        $favorites = Favorite::with('court')
            ->where('user_id', auth()->id())
            ->latest()
            ->paginate(12);

        return view('favorites.index', compact('favorites'));
    }

    /*
    |--------------------------------------------------------------------------
    | UC08 - ADD
    |--------------------------------------------------------------------------
    */

    public function store(Court $court)
    {
        Favorite::firstOrCreate([
            'user_id' => auth()->id(),
            'court_id' => $court->id,
        ]);

        return back()->with(
            'success',
            'Đã thêm sân vào danh sách yêu thích.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | UC08 - REMOVE
    |--------------------------------------------------------------------------
    */

    public function destroy(Court $court)
    {
        Favorite::where('user_id', auth()->id())
            ->where('court_id', $court->id)
            ->delete();

        return back()->with(
            'success',
            'Đã xóa sân khỏi danh sách yêu thích.'
        );
    }
}