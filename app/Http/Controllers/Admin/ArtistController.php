<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreArtistRequest;
use App\Http\Requests\Admin\UpdateArtistRequest;
use App\Models\Artist;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
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
        $validated = $request->validated();

        if ($request->hasFile('hero_image')) {
            $validated['hero_image'] = $request->file('hero_image')->store('artists', 'public');
        } else {
            unset($validated['hero_image']);
        }

        Artist::create($validated);

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
            'artist' => array_merge(
                $artist->only(['id', 'name', 'slug', 'owner_id', 'bio']),
                ['hero_image' => $artist->hero_image ? '/storage/'.$artist->hero_image : null],
            ),
            'users' => $users,
        ]);
    }

    public function update(UpdateArtistRequest $request, Artist $artist): RedirectResponse
    {
        $validated = $request->validated();

        if ($request->hasFile('hero_image')) {
            if ($artist->hero_image && Storage::disk('public')->exists($artist->hero_image)) {
                Storage::disk('public')->delete($artist->hero_image);
            }
            $validated['hero_image'] = $request->file('hero_image')->store('artists', 'public');
        } else {
            unset($validated['hero_image']);
        }

        $artist->update($validated);

        return redirect()->route('admin.artists.index')
            ->with('success', 'Artist updated successfully.');
    }

    public function destroy(Artist $artist): RedirectResponse
    {
        if ($artist->hero_image && Storage::disk('public')->exists($artist->hero_image)) {
            Storage::disk('public')->delete($artist->hero_image);
        }

        $artist->delete();

        return redirect()->route('admin.artists.index')
            ->with('success', 'Artist deleted successfully.');
    }
}
