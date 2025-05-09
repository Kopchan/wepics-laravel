<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\AgeRating;
use App\Models\Reaction;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    protected static function insertIfNotExists($model, array $uniqueKeys, array $rows)
    {
        foreach ($rows as $row) {
            $query = $model::query();

            // Добавляем условия для проверки уникальности
            foreach ($uniqueKeys as $key) {
                if (!array_key_exists($key, $row)) {
                    throw new \InvalidArgumentException("Key `{$key}` not found in row");
                }
                $query->where($key, $row[$key]);
            }

            // Если запись не найдена — вставляем
            if (!$query->exists()) {
                $model::create($row);
            }
        }
    }
    public function run(): void
    {
        self::insertIfNotExists(User::class, ['login'], [
            [
                'nickname' => 'Administrator',
                'login'    => 'admin',
                'password' => 'admin123',
                'is_admin' => true,
            ],
        ]);

        self::insertIfNotExists(AgeRating::class, ['code', 'name'], [
            [
                'code' => 'G',
                'name' => 'General',
                'description' => 'Anybody can view',
                'color' => '#00a74f',
                'level' => 10,
                'preset' => 'show',
            ],
            [
                'code' => 'PG12',
                'name' => 'Parental Guidance 12',
                'description' => 'Mild violence or hints of romance',
                'color' => '#00afef',
                'level' => 20,
                'preset' => 'show',
            ],
            [
                'code' => 'R15',
                'name' => 'Restricted 15+',
                'description' => 'More explicit scenes, blood, moderate violence',
                'color' => '#ed008d',
                'level' => 30,
                'preset' => 'blur',
            ],
            [
                'code' => 'R18',
                'name' => 'Restricted 18+',
                'description' => 'Explicit sex, cruelty',
                'color' => '#ee151f',
                'level' => 40,
                'preset' => 'blur',
            ],
            [
                'code' => 'R18G',
                'name' => 'Restricted 18+ Graphic',
                'description' => 'Extremely shocking or explicitly violent content',
                'color' => '#5915eb',
                'level' => 50,
                'preset' => 'hide',
            ],
        ]);

        self::insertIfNotExists(Reaction::class, ['value'], [
            ['value' => '👍'],
            ['value' => '👎'],
            ['value' => '⚡'],
            ['value' => '✨'],
            ['value' => '❤️'],
            ['value' => '📌'],
            ['value' => '🎉'],
            ['value' => '💀'],
            ['value' => '🍗'],
            ['value' => '👀'],
            ['value' => '🌚'],
            ['value' => '🫣'],
            ['value' => '🤨'],
            ['value' => '🤤'],
        ]);
    }
}
