<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreArtistRequest;
use App\Http\Requests\Admin\UpdateArtistRequest;
use App\Models\Artist;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ArtistController extends Controller
{
    public function index(): Response
    {
        $artists = Artist::query()
            ->with('owner:id,name')
            ->select(['id', 'name', 'slug', 'owner_id', 'created_at'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return Inertia::render('Admin/Artists/Index', [
            'artists' => $artists,
        ]);
    }

    public function create(): Response
    {
        $users = User::select(['id', 'name', 'email'])->orderBy('name')->get();

        return Inertia::render('Admin/Artists/Create', [
            'users' => $users,
        ]);
    }

    public function store(StoreArtistRequest $request): RedirectResponse
    {
        Artist::create($request->validated());

        return redirect()->route('admin.artists.index')
            ->with('success', 'Artist created successfully.');
    }

    public function show(Artist $artist): Response
    {
        $artist->load([
            'owner:id,name,email',
            'teamMembers:id,name,email',
        ]);

        return Inertia::render('Admin/Artists/Show', [
            'artist' => $artist,
        ]);
    }

    public function edit(Artist $artist): Response
    {
        $users = User::select(['id', 'name', 'email'])->orderBy('name')->get();

        return Inertia::render('Admin/Artists/Edit', [
            'artist' => $artist->only(['id', 'name', 'slug', 'owner_id']),
            'users' => $users,
        ]);
    }

    public function update(UpdateArtistRequest $request, Artist $artist): RedirectResponse
    {
        $artist->update($request->validated());

        return redirect()->route('admin.artists.index')
            ->with('success', 'Artist updated successfully.');
    }

    public function destroy(Artist $artist): RedirectResponse
    {
        $artist->delete();

        return redirect()->route('admin.artists.index')
            ->with('success', 'Artist deleted successfully.');
    }
}
