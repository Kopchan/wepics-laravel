<?php

namespace App\Models;

use App\Exceptions\ApiException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Tags\HasTags;
use Staudenmeir\EloquentEagerLimit\HasEagerLimit;

class Image extends Model
{
    use HasFactory, HasTags; //, HasEagerLimit;

    // Заполняемые поля
    protected $fillable = [
        'name',
        'type',
        'hash',
        'date',
        'size',
        'width',
        'height',
        'album_id',
        'age_rating_id',
        'codec_name',
        'frame_count',
        'duration_ms',
        'avg_frame_rate_num',
        'avg_frame_rate_den',
    ];

    // Получение картинки по хешу
    static public function getByHash($albumHash, $imageHash): Image
    {
        $album = Album::getByHash($albumHash);
        $image = Image
            ::where('album_id', $album->id)
            ->where('hash', $imageHash)
            ->first();
        if(!$image)
            throw new ApiException(404, "Image not found");

        $image->album = $album;

        return $image;
    }
    // Получение картинки по хешу или алиасу
    static public function getByHashOrAlias($albumHashOrAlias, $imageHash): Image
    {
        $album = Album::getByHashOrAlias($albumHashOrAlias);
        $image = Image
            ::where('album_id', $album->id)
            ->where('hash', $imageHash)
            ->first();
        if(!$image)
            throw new ApiException(404, "Image not found");

        $image->album = $album;

        return $image;
    }

    // Получение имя класса, управляющий тегами на этой модели
    public static function getTagClassName(): string {
        return Tag::class;
    }

    // Обработка событий модели
    protected static function booted()
    {
        static::saving(function ($item) {
            // Автоматически обновляем natural_sort_name при сохранении
            $item->natural_sort_key = self::normalizeName($item->name);
        });
    }

    const MAX_SORT_KEY_LENGTH = 255;

    // Генерация имени для натуральной сортировки
    public static function normalizeName(string $originalName): string
    {
        // Нормализация чисел
        $normalizedName = preg_replace_callback('/\d+/', function ($matches) {
            return str_pad($matches[0], 12, '0', STR_PAD_LEFT);
        }, $originalName);

        $fullNameLength = strlen($normalizedName);

        // Если общая длина превышает максимальную, обрезаем и имя, и расширение
        if ($fullNameLength > static::MAX_SORT_KEY_LENGTH) {
            $parts = pathinfo($normalizedName);
            $name = $parts['filename'];
            $ext = isset($parts['extension']) ? '.' . $parts['extension'] : '';

            // Вычисляем, сколько символов можно оставить для имени файла
            $maxNameLength = static::MAX_SORT_KEY_LENGTH - strlen($ext);

            // Если расширение слишком длинное, обрезаем его
            if ($maxNameLength < 0) {
                $ext = substr($ext, 0, static::MAX_SORT_KEY_LENGTH);
                $name = ''; // Имя файла будет пустым, если расширение занимает весь лимит
            } else {
                // Обрезаем имя файла, если оно превышает допустимую длину
                $name = substr($name, 0, $maxNameLength);
            }
            $truncatedName = $name . $ext;

            //dd($truncatedName, $name, $ext, $normalizedName, $originalName, $maxNameLength);
        }
        else {
            $truncatedName = $normalizedName;
        }

        // Собираем итоговое имя
        return iconv('UTF-8', 'UTF-8//IGNORE', $truncatedName);
    }

    // Связи
    public function album() {
        return $this->belongsTo(Album::class);
    }
    public function duplicas() {
        return $this->hasMany(ImageDuplica::class);
    }
    public function reactions() {
        return $this->belongsToMany(Reaction::class, ReactionImage::class)
            ->withPivot('user_id')
            ->using(ReactionImage::class);
    }
    public function tags() {
        return $this->belongsToMany(Tag::class, 'tag_image');
        //TODO: Понять что это
//        return $this
//            ->morphToMany(self::getTagClassName(), 'tag_id', 'tag_image', null, 'tag_id')
//            ->orderBy('order_column');
    }
}
