<?php

namespace App\Models;

use App\Exceptions\ApiException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Tags\HasTags;

class Image extends Model
{
    use HasFactory;
    use HasTags;

    // Заполняемые поля
    protected $fillable = [
        'name', 'hash', 'date', 'size',
        'width', 'height', 'album_id',
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
            $item->natural_sort_key = $item->normalizeName($item->name);
        });
    }

    // Генерация имени для натуральной сортировки
    public static function normalizeName(string $name): string
    {
        // Нормализация чисел
        $normalizedName = preg_replace_callback('/(?<=[^\p{L}\d])\d+/u', function ($matches) {
            return str_pad($matches[0], 10, '0', STR_PAD_LEFT);
        }, $name);

        // Определяем расширение файла
        $extension = '';
        if (preg_match('/\.([a-zA-Z0-9]+)(\.[a-zA-Z0-9]+)?$/', $normalizedName, $matches)) {
            $extension = $matches[0]; // Получаем расширение (включая точку)
        }

        // Если имя файла состоит только из расширения
        if (strlen($normalizedName) === strlen($extension)) {
            // Обрезаем расширение до 255 символов
            return substr($extension, 0, 255);
        }

        // Вычисляем максимальную длину основной части имени
        $maxBaseLength = 255 - strlen($extension);

        // Обрезаем основную часть имени, если она превышает допустимую длину
        $baseName = substr($normalizedName, 0, $maxBaseLength);

        // Собираем итоговое имя
        return $baseName . $extension;
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
