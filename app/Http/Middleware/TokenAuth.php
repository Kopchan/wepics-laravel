<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiDebugException;
use App\Exceptions\ApiException;
use App\Models\Album;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;

class TokenAuth
{
    public function handle(Request $request, Closure $next, $allowedLevel = 'user')
    {
        // Получаем значение токена из запроса
        $tokenValue = $request->bearerToken();

        if (!$tokenValue) {
            if (!($allowedLevel === 'guest'))
                // Если токена нет, но заданный уровень доступа НЕ "гость" —— выводить 401 ошибку
                throw new ApiException(401, 'Token not provided');
        }
        else {
            // Получение пользователя
            $user = User::getByToken($tokenValue);

            if (!$user)
                // Если пользователь не настоящий, но заданный уровень доступа не "гость" —— выводить 401 ошибку
                throw new ApiException(401, 'Token corrupted');

            if (!$user->is_admin) {
                if ($allowedLevel === 'admin')
                    // Если пользователь не админ, но заданный уровень доступа "администратор" —— выводить 403 ошибку
                    throw new ApiException(403, 'Admin rights required');


                if ($allowedLevel === 'owner') {
                    $albumHash = $request->route('album_hash');
                    $album = Album::getByHash($albumHash);
                    //dd($album, $user, $allowedLevel);

                    if (!$user || $album->owner_user_id !== $user->id)
                        // Если пользователь не владелец, но заданный уровень доступа "владелец" —— выводить 403 ошибку
                        throw new ApiException(403, 'You not a owner of this album');
                }
            }

            // Запись пользователя в запрос для последующих обработок в контроллерах
            $request->setUserResolver(function () use ($user) {
                return $user;
            });
        }
        return $next($request);
    }
}
