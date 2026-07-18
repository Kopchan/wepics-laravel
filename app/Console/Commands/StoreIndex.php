<?php

namespace App\Console\Commands;

use App\Models\Album;
use App\Models\Image;
use App\Models\ImageDuplica;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;
use ProtoneMedia\LaravelFFMpeg\FFMpeg\FFProbe;

class StoreIndex extends Command
{
    protected $signature = 'app:index {--s|start-from=} {--d|auto-destroy} {--l|layers} {--r|recursive}';

    protected $description = 'Index root album for new albums/images and remove if not found';

    public static function formatNumber($number, $pad = 6, $fg = 'white') {
        $padString = str_pad($number, $pad, '0', STR_PAD_LEFT);
        return preg_replace('/^(0*)(\d+)$/', "<fg=gray>$1</><fg=$fg>$2</>", $padString);
    }

    public static function counter($position, $count, $pad = 6, $fg = 'yellow', $fgCount = 'white') {
        return
            static::formatNumber($position, $pad, $fg)
            .'/'.
            static::formatNumber($count, $pad, $fgCount);
    }

    public function handle(): void
    {
        //$this->output->getFormatter()->setStyle('error'  , new OutputFormatterStyle('red' ));
        $this->output->getFormatter()->setStyle('comment', new OutputFormatterStyle('gray'));

        // Устранение конфликтов иерархии
        $this->line('Fixing tree...');
        Album::fixTree();
        $this::newLine();

        // Сортировки
        $sortOptions = [
            'layers' => 'By layers (shallow to deep)',
            'recursive' => 'Recursively (alphabetically, including nested)'
        ];

        // Проверка параметров сортировки
        $sortMode = $this->hasOption('layers') ? 'layers' :
            ($this->hasOption('recursive') ? 'recursive' :
                $this->choice('How would you like to iterate through the albums?', array_values($sortOptions), 0)
            );

        // Получение всех альбомов
        $this->line('Get all albums...');
        $query = Album::query()
            ->whereNull('owner_user_id');

        if ($sortMode === 'layers') {
            $query->orderByRaw('LENGTH(path) - LENGTH(REPLACE(path, "/", ""))') // По глубине
            ->orderByRaw('LENGTH(path)')                                       // По длине
            ->orderBy('path');                                                // По алфавиту
        } else {
            // Рекурсивно — просто по алфавиту
            $query->orderBy('path');
        }

        $albums = $query->get();
        $this::newLine();

        // Разрешённые расширения
        $allowedImageExtensions = config('setups.allowed_image_extensions');
        $allowedVideoExtensions = config('setups.allowed_video_extensions');
        $allowedAudioExtensions = config('setups.allowed_audio_extensions');

        //if (!$this->confirm('Do you wish index albums? ['. $albums->count() .' in DB already]', true)) return;

        // Драйвер FFProbe для получения информации из видео/аудио файлов
        $probe = FFProbe::create();

        // Если была указана опция "начать с", то пробуем искать такое альбом и ставим как начальный ключ, если найден
        $startFrom = $this->option('start-from');
        if (!$startFrom)
            $currentAlbumKey = 0;
        else {
            if (is_numeric($startFrom))
                $currentAlbumKey = $albums->search(fn ($a) => $a['id'] == $startFrom);
            else
                $currentAlbumKey = $albums->search(fn ($a) => $a['hash'] === $startFrom || $a['alias'] === $startFrom);
        }
        if ($currentAlbumKey === false) {
            $this->warn("Album not found with \"$startFrom\"");
            return;
        }

        // =========================== Папки -> Альбомы ===========================

        // Проход по альбомам
        while ($albums->count() > $currentAlbumKey) {
            $currentAlbum = $albums[$currentAlbumKey];
            $path = Storage::path("images$currentAlbum->path");

            $currentAlbumKey++;
            $this->line('<fg=gray;options=bold>['.static::counter($currentAlbumKey, $albums->count())
                .']  #' . static::formatNumber($currentAlbum->id)
                ."  <fg=yellow;options=bold>$currentAlbum->name</> "
                ." <bg=black;fg=white;href=". url('../album/'. $currentAlbum->hash) ."> 🌐 ".($currentAlbum->alias ?? $currentAlbum->hash)." </> "
                ." <bg=gray;fg=black;href=file:///$path> 📁 $currentAlbum->path </></> "
            );

            // Попытка получить все дочерние директории в альбоме
            try {
                $folders = File::directories($path);
            }
            catch (DirectoryNotFoundException $e)
            {
                // Альбом не найден, спрашиваем "удалить ли", если не было передано опции авто-удаления
                $this->error(' DELETED ');
                if (!Album::find($currentAlbum->id)) continue;

                if ($this->option('auto-destroy') ||
                    $this->confirm("Do you wish remove not founded albums from DB? ["
                    .$currentAlbum->children->count()." subalbums & ". $currentAlbum->images->count() ." images known]")
                ) Album::destroy($currentAlbum->id);

                continue;
            }

            // Дочерние альбомы из БД
            $albumChildren = $albums->where('parent_album_id', $currentAlbum->id);
            //$keysToForget = [];
            // Отображаем сколько в файловой системе и в базе данных альбомов
            $this->line('Checking folders in album ['
                . count($folders) .' in FS / '
                . $albumChildren->count() .' in DB]'
            );
            $newAlbums = [];

            // Проход по папкам альбома (дочерние альбомы)
            foreach ($folders as $folder) {
                $childPath = $currentAlbum->path . basename($folder) .'/';
                $basename = basename($childPath);

                // Проверка наличия в БД вложенного альбома, создание если нет
                $key = $albumChildren->search(fn ($a) => $a['path'] === $childPath);
                if ($key !== false) {
                    $albumChild = $albumChildren[$key];
                    // Вывод известной [ ] записи в консоль
                    $this->line(
                        "  <fg=gray;href=". url("../album/$albumChild->hash") .">$albumChild->hash</> "
                        ."<fg=gray;href=file:///". Storage::path("images$childPath") .">$basename/</> "
                    );
                    //$keysToForget[] = $key;
                    $albumChildren->forget($key);
                }
                else {
                    // Создание, прикрепление как дочернего (appendToNode) и сохранение в БД
                    $hash = Str::random(25);
                    $childAlbum = Album::create([
                        'name' => $basename,
                        'path' => $childPath,
                        'hash' => $hash,
                    ]);
                    $childAlbum->appendToNode($currentAlbum);
                    //$childAlbum->parent_album_id = $currentAlbum->id;
                    $childAlbum->save();
                    $newAlbums[] = $childAlbum;
                    // Вывод добавленной [+] записи в консоль
                    $this->info('<fg=green>+ '
                        ."<fg=green;href=". url("../album/$hash") .">$hash</> "
                        ."<fg=green;href=file:///". Storage::path("images$childPath") .">$basename/</></> "
                    );
                }
            }
            // Отображение всех не найденных альбомов
            foreach ($albumChildren as $key => $notFoundedAlbum) {
                // Вывод не найденной [-] записи в консоль
                $this->line('<fg=red>- '
                    ."<fg=red;href=". url("../album/$notFoundedAlbum->hash") .">$notFoundedAlbum->hash</> "
                    ."<fg=red;href=file:///". Storage::path('images'). $notFoundedAlbum->path .'>'.basename($notFoundedAlbum->path).'/</></> '
                );
                //dd("try delete?", $key, $albums[$key]);
                //$clone = clone $albums;
                $albums->forget($key);
                //dd($albums, $clone);
                //dd("after delete", $key, $albums[$key+1]);
            }
            $albums->splice($currentAlbumKey, 0, $newAlbums);

            // Спрашиваем "удалить ли не найденные альбомы", если не было передано опции авто-удаления
            $notFoundedCount = $albumChildren->count();
            if ($this->option('auto-destroy') || (
                $notFoundedCount &&
                $this->confirm("Do you wish remove not founded albums from DB? [$notFoundedCount]")
            )) {
                Album::destroy($albumChildren->pluck('id')->toArray());
            }

            // =========================== Файлы -> Медиа ===========================

            // Получение файлов альбома
            $start = now();
            $glob = glob("$path*", GLOB_MARK);
            $timeGlob = $start->diffInMilliseconds();

            $files = array_filter($glob, fn ($path) => !in_array($path[-1], ['/', '\\']));

            // Получение имеющихся картинок в БД
            $imagesInDB = $currentAlbum->images()->with('duplicas')->get();

            //dd($currentAlbum->id, $currentAlbum->images, $imagesInDB, $currentAlbum->images()->with('duplicas')->toSql());
            //dd($currentAlbum->id, $currentAlbum->images, $imagesInDB);
            // Объединение картинок и их дубликатов в единый массив
            $images = $imagesInDB->flatMap(function ($image) {
                $origImage = $image->toArray();
                return array_merge(
                    [$origImage],
                    $image->duplicas->map(fn ($duplica) =>
                        array_merge($origImage, [
                            'name' => $duplica->name,
                            'origId' => $image->Id,
                            'origName' => $image->name,
                        ])
                    )->toArray()
                );
            })->toArray();

            $filesCount = count($files);

            $this->line('Checking images in album ['
                . $filesCount .' files in FS / '
                . count($images) ." in DB] [glob $timeGlob ms]"
            );

            $notFoundedImages = $images;
            //dd($notFoundedImages, $images);

            // Массивы для поиска
            $imagesNames  = array_column($images, 'name');
            $imagesHashes = array_column($images, 'hash');

            // Проход по файлам
            foreach ($files as $i => $file) {
                $name = basename($file);
                $counter = '['.static::counter($i+1, $filesCount).']';
                $this->output->write("  $counter <href=file:///$file>$name</>");

                try {
                    // Проверка наличия в БД картинки по названию, если есть — пропуск
                    // FIXME: А если юзер переименует две картинки наоборот?
                    $key = array_search($name, $imagesNames);
                    if ($key !== false) {
                        $existImage = $images[$key];
                        $isDuplica = array_key_exists('origName', $existImage);
                        // FIXME: если есть дубликаты и один из них удалили (а в базе есть), то в консоли выводится не связные картинки
                        // Вывод известной [ /↩] записи в консоль
                        $this->line("<fg=gray>\r"
                            .($isDuplica ? '↩ ' : '  ')
                            . $counter
                            .' '
                            .($isDuplica ? '' : '<fg=gray;href='
                                . url("api/albums/$currentAlbum->hash/images/$existImage[hash]/orig")
                                .">$existImage[hash]</> "
                            )
                            ."<fg=gray;href=file:///$file>$name</>"
                            .($isDuplica ? '<fg=white> duplica of </>'
                                .'<fg=gray;href='. $path . $existImage['origName'] .">$existImage[origName]</>" : ''
                            )
                            .'</>'
                        );
                        unset($notFoundedImages[$key]);
                        continue;
                    }
                    //dd('end_here', array_search($name, $imagesNames), $name, $imagesNames, $images, $imagesInDB);

                    // Отсекание не-медиа по расширению файла, определение типа
                    $extension = pathinfo($file, PATHINFO_EXTENSION);
                    $extension = strtolower($extension);
                    if (in_array($extension, $allowedImageExtensions))
                        $type = 'image';
                    else
                    if (in_array($extension, $allowedVideoExtensions))
                        $type = 'video';
                    else
                    if (in_array($extension, $allowedAudioExtensions))
                        $type = 'audio';
                    else {
                        // Вывод пропущенный [×] файл в консоль
                        $this->line("<fg=blue>\r× "
                            . $counter
                            ." <fg=blue;href=file:///$file>$name</>"
                            ." \".$extension\" not support/allowed"
                            .'</>'
                        );
                        continue;
                    }

                    // Отсекаем не совпадающие c определителем MIME по заголовкам файла
                    // TODO: Надо сохранять если формат всё же поддерживается, хоть и под другим расширением (mp4v в .mp4, webp в .jpg)
                    $guessExtension = File::guessExtension($file);
                    if ($extension !== $guessExtension) {
                        $this->line("<fg=red>\r× "
                            . $counter
                            ." <fg=blue;href=file:///$file>$name</>"
                            ." \".$guessExtension\" is actual format"
                            .'</>'
                        );
                        continue;
                    }

                    // Получение хеша картинки (прожорливое к скорости чтения)
                    $hash = base64url_encode(hash_file('xxh3', $file, true));

                    // Проверка наличия в БД картинки по хешу, если есть — проверяем в ФС
                    $key = array_search($hash, $imagesHashes);
                    if ($key !== false) {
                        // Полное имя картинки-оригинала (из БД)
                        $imageFullName = $path . $images[$key]['name'];

                        // Проверка наличие картинки-оригинала в ФС ...
                        $filesKey = array_search($imageFullName, $files);
                        if ($filesKey === false) {
                            // Если нет — переименовываем в БД
                            Image
                                ::where('id', $images[$key]['id'])
                                ->update(['name' => $name]);

                            // Вывод переименованную [→] запись в консоль
                            $this->line("<fg=yellow>\r→ "
                                . $counter
                                .' <fg=yellow;href='. url("api/albums/$currentAlbum->hash/images/$hash/orig") .">$hash</>"
                                ." <fg=yellow;href=file:///$file>". $images[$key]['name'] ."<fg=white> renamed to </>$name</>"
                                .'</>'
                            );
                            $images[$key]['name'] = $name;
                            $imagesNames[$key] = $name;
                            unset($notFoundedImages[$key]);

                        } else {
                            // Если есть — создаём дубликат в БД (чтобы в следующий раз не создавать хеш)
                            ImageDuplica::create([
                                'image_id' => $images[$key]['id'],
                                'name' => $name,
                            ]);

                            // Записываем в массивы чтобы не наткнутся на повторы снова
                            $images[$key]['duplicas'][] = ['name' => $name];
                            $images[] = array_merge($images[$key], [
                                'name' => $name,
                                'origId' => $images[$key]['id'],
                                'origName' => $images[$key]['name']
                            ]);

                            // Вывод добавленный дубликат [↩] запись в консоль
                            $this->line("<fg=yellow>\r↩ "
                                . $counter
                                .' <fg=yellow;href='. url("api/albums/$currentAlbum->hash/images/$hash/orig") .">$hash</>"
                                ." <fg=yellow;href=file:///$file>$name<fg=white> linked to </>". $images[$key]['name'] .'</>'
                                .'</>'
                            );
                        }
                        // И пропуск создания картинки в БД
                        unset($notFoundedImages[$key]);
                        continue;
                    }

                    // Получение размеров картинки, если нет размеров (не получили) — пропуск
                    if ($type === 'image') {
                        $sizes = getimagesize($file); // FIXME: на перевёрнутых JPG даёт те же размеры
                        if (!$sizes) {
                            // Вывод пропущенный [×] файл в консоль
                            $this->line("<fg=red>\r× "
                                . $counter
                                ." <fg=blue;href=file:///$file>$name</>"
                                ." cannot get image sizes"
                                .'</>'
                            );
                            continue;
                        }
                    }

                    // Получение информации об основном потоке (картинки тоже). Для аудио выбираем аудиопоток
                    if ($type !== 'audio')
                        $probeInfo = $probe->streams($file)->videos()->first();
                    else
                        $probeInfo = $probe->streams($file)->audios()->first();

                    // Информации о потоке. Идёт в основную БД запись
                    $streamContentFields = [];

                    // Если в картинке есть длительность, то значит это анимированная картинка
                    if ($type === 'image' && ($probeInfo?->get('duration_ts') ?? 0) > 1)
                        $type = 'imageAnimated';

                    // Сбор информации о потоке. Картинка пропускается
                    if ($type !== 'image') {
                        // Отбрасываем если длительность не можем получить
                        if (!($probeInfo?->get('duration_ts'))) {
                            // Вывод пропущенной [×] файл в консоль
                            $this->line("<fg=red>\r× "
                                . $counter
                                ." <fg=blue;href=file:///$file>$name</>"
                                ." cannot get duration_ts from $type"
                                .'</>'
                            );
                            continue;
                        }

                        // TODO: Всё же надо хранить отдельно аудио и видео кодек для видеоконтейнеров
                        // TODO: Ещё лучше хранить отдельно записи об каждом потоке?
                        // Основной кодек
                        $streamContentFields['codec_name'] = $probeInfo->get('codec_name');

                        // FFMpeg возвращает в длительность секунды. Переводим в миллисекунды
                        $number = $probeInfo->get('duration');
                        if (!str_contains($number, '.')) $number .= '.000';
                        [$intPart, $decimalPart] = explode('.', $number, 2);
                        $decimalPart = substr($decimalPart . '000', 0, 3);
                        $streamContentFields['duration_ms'] = (int)($intPart . $decimalPart);

                        if ($type !== 'audio') {
                            // Получение размеров видео/анимации
                            $sizes = [
                                $probeInfo->get('width'),
                                $probeInfo->get('height')
                            ];

                            // Частота кадров как [0] - числитель, [1] - знаменатель
                            $framerate = array_map('intval',
                                explode('/', $probeInfo->get('avg_frame_rate'))
                            );
                            $streamContentFields['avg_frame_rate_num'] = $framerate[0];
                            $streamContentFields['avg_frame_rate_den'] = $framerate[1];

                            // Общее число кадров
                            $streamContentFields['frame_count'] = (int)$probeInfo->get('nb_frames');
                        }
                        else {
                            // Получение размеров превью аудио
                            $sizes = [500, 500]; // TODO: Тупая заглушка, надо читать из обложки аудио
                        }
                    }

                    //dd($extension, $type, $steamContentFields);

                    // Создание в БД записи
                    $imageModel = Image::create([
                        'album_id' => $currentAlbum->id,
                        'name' => $name,
                        'type' => $type,
                        'hash' => $hash,
                        'date' => Carbon::createFromTimestamp(File::lastModified($file)),
                        'size' => File::size($file),
                        'width'  => $sizes[0],
                        'height' => $sizes[1],
                        ...$streamContentFields,
                    ]);
                    // Вывод добавленной [+] запись в консоль
                    $this->line("<fg=green>\r+ "
                        . $counter
                        ." <fg=green;href="
                        . url("api/albums/$currentAlbum->hash/images/$hash/orig")
                        .">$hash</> "
                        ."<fg=green;href=file:///$file>$name</></>"
                    );

                    // Обновление массивов картинок альбома
                    $images[] = $imageModel->toArray();
                    $imagesNames[] = $name;
                    $imagesHashes[] = $hash;
                }
                catch (\Exception $ex) {
                    // Вывод не удавшийся [/] файл в консоль
                    $this->error("\r/ "
                        . $counter
                        ." <bg=red;fg=white;href=file:///$file>$name</> "
                        .$ex->getMessage()
                    );
                    continue;
                }
            }
            // Отображение не найденных медиа
            $notFoundedDuplicas = [];
            $notFoundedOrigs = [];
            foreach ($notFoundedImages as $key => $notFoundedImage) {
                $isDuplica = array_key_exists('origName', $notFoundedImage);
                if ($isDuplica)
                    $notFoundedDuplicas[] = $notFoundedImage;
                else
                    $notFoundedOrigs[] = $notFoundedImage;

                try {
                    // Вывод не найденной [-] записи в консоль
                    $this->line("<fg=red>\r- "
                        .'['.static::counter(0, $filesCount).'] '
                        .($isDuplica ? '' : '<fg=gray;href='
                            . url("api/albums/$notFoundedImage[hash]/images/$notFoundedImage[hash]/orig")
                            .">$notFoundedImage[hash]</> "
                        )
                        ."<fg=gray;href=file:///$path.$notFoundedImage[name]>$notFoundedImage[name]</>"
                        .($isDuplica ? '<fg=white> duplica of </>'
                            .'<fg=gray;href='.$path.$notFoundedImage['origName'].">$notFoundedImage[origName]</>" : '')
                        .'</>'
                    );
                }
                catch (\Exception $e) {
                    $this->error($e);
                }
            }
            // Спрашиваем "удалить ли не найденные медиа", если не было передано опции авто-удаления
            $notFoundedCount = count($notFoundedImages);
            if ($this->option('auto-destroy') || (
                $notFoundedCount &&
                $this->confirm("Do you wish remove not founded images and duplicas from DB? [$notFoundedCount]")
            )) {
                Image::destroy(array_column($notFoundedOrigs, 'id'));

                foreach ($notFoundedDuplicas as $duplica) {
                    ImageDuplica
                        ::where('image_id', $duplica['origId'])
                        ->where('name', $duplica['name'])
                        ->delete();
                }
            }

            $currentAlbum->last_indexation = now();
            $currentAlbum->save();
        }
        $this->line('Albums are out.');
        $this::newLine();

        $this->line('Fixing tree...');
        Album::fixTree();
        $this::newLine();
    }
}
