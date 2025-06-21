<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\InvitationCreateRequest;
use App\Http\Resources\AlbumResource;
use App\Http\Resources\ImageResource;
use App\Http\Resources\InvitationResource;
use App\Models\AccessRight;
use App\Models\Album;
use App\Models\Image;
use App\Models\Invitation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

use Illuminate\Support\Str;

class InvitationController extends Controller
{
    public function store(InvitationCreateRequest $request, $album_hash): JsonResponse
    {
        $album = Album::getByHash($album_hash);

        $expiresAt = $request->input('expiresAt');
        $timeLimit = $request->input('timeLimit');

        $expiresDate = null;

        if ($expiresAt)
            $expiresDate = $expiresAt;

        else if ($timeLimit)
            $expiresDate = now()->addMinutes((int)$timeLimit);

        $invitation = Invitation::create([
            'expires_at' => $expiresDate ?? null,
            'album_id' => $album->id,
            'join_limit' => $request->joinLimit ?? null,
            'link' => Str::random(8)
        ]);

        return response()->json(['invitation' => InvitationResource::make($invitation)], 201);
    }

    public function album(Invitation $invitation): JsonResponse
    {
        $invitation->failOnExpires();
        $invitation->load(['album', 'album.user']);
        $images = Image
            ::where('album_id', $invitation->album_id)
            ->orderBy('date', 'DESC')
            ->limit(30)
            ->get();

        $album = Album
            ::where('id', $invitation->album_id)
            ->withCount([
                'images',
            ])
            ->with([
                'images' => fn ($query) => $query->orderBy('date', 'DESC')->limit(4),
                'user',
            ])
            ->first();

        $res = [
            'invitation' => $invitation,
            'album' => AlbumResource::make($album),
            'images' => ImageResource::collection($images),
        ];


        if (Auth::id() != null && $invitation?->album?->user?->id == Auth::id())
            $res['invite'] = InvitationResource::make($invitation);

        return response()->json($res);
    }

    public function join(Invitation $invitation): JsonResponse
    {
        $invitation->failOnExpires();
        $user = Auth::user();

        if ($user->id === $invitation->album->user_id)
            throw new ApiException('You are owner', 409);

        if (AccessRight
            ::where('user_id', $user->id)
            ->where('album_id', $invitation->album_id)
            ->first()
        ) throw new ApiException('Already accessible', 409);

        AccessRight::create([
            'user_id' => $user->id,
            'album_id' => $invitation->album_id
        ]);

        if ($invitation->join_limit !== null) {
            $invitation->join_limit -= 1;

            if ($invitation->join_limit > 0)
                $invitation->save();
            else
                $invitation->delete();
        }
        return response()->json(null, 204);
    }

    public function destroy(Invitation $invitation): JsonResponse
    {
        $user = Auth::user();
        if ($user->id !== $invitation->album->user_id)
            throw new ApiException(403, 'You are not owner of invitation');

        $invitation->delete();
        return response()->json(null, 204);
    }
}
