<?php

namespace App\Http\Controllers;

use App\Models\Playlist;
use Illuminate\Http\Request;

class PlaylistController extends Controller
{
    public function index()
    {
        return Playlist::with('songs')->get();
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
        ]);

        $playlist = Playlist::create($request->all());
        return response()->json($playlist, 201);
    }
}
