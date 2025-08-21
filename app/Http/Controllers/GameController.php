<?php


namespace App\Http\Controllers;

use App\Models\Game;
use Inertia\Inertia;

class GameController extends Controller
{
    public function index()
    {
        // Аксессор imageUrl() сам вернёт корректный URL
        $games = Game::query()
            ->orderBy('name')
            ->get(['id','name','slug','image_url']);

        return Inertia::render('Games/Index', ['games' => $games]);
    }

    public function show(Game $game)
    {
        $game->load(['categories:id,game_id,name,slug,type,image']);

        return Inertia::render('Games/Show', ['game' => $game]);
    }
}