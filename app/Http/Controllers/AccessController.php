<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Http\Requests\AccessRightRequest;
use App\Models\AccessRight;
use App\Models\Album;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class AccessController extends Controller
{
    public function showAll($hash)
    {
        $album = Album::getByHashOrAlias($hash);

        $rights = $album->accessRights()->with('user')->get();
        //if (count($rights) < 1)
        //    return response(['message' => 'Nobody has rights to this album']);

        $allowed    = [];
        $disallowed = [];
        foreach ($rights as $right) {
            if ($right->allowed) $allowed[] = [
                'user_id'  => $right->user_id,
                'nickname' => $right->user->nickname
            ];
            else $disallowed[] = [
                'user_id'  => $right->user_id,
                'nickname' => $right->user->nickname
            ];
        }
        $response = [];
                         $response['isGuestAllowed'] = $album->guest_allow;
        if ($allowed)    $response['listAllowed'   ] = $allowed;
        if ($disallowed) $response['listDisallowed'] = $disallowed;
        return response($response);
    }

    public function create(AccessRightRequest $request, $hash)
    {
        $album = Album::getByHash($hash);

        if ($request->user_id === null)
            $album->update(['guest_allow' => $request->allow]);

        else
            AccessRight::updateOrCreate([
                'album_id' => $album->id,
                'user_id' => $request->user_id,
            ], [
                'allowed'  => $request->allow,
            ]);

        Cache::flush(); // FIXME: костыль

        return response(null, 204);
    }

    public function delete(AccessRightRequest $request, $hash, $user_id = null)
    {
        $album = Album::getByHash($hash);

        $user = $user_id
            ? User::find($user_id)
            : $request->user();

        if ($user->id === $album->user_id)
            throw new ApiException(409, 'You are owner');

        $right = AccessRight
            ::where('album_id', $album->id)
            ->where('user_id', $user->id)
            ->first();

        if (!$right)
            throw new ApiException(404, 'Access right not found');

        Cache::flush(); // FIXME: костыль
        $right->delete();

        return response(null, '204');
    }
}
