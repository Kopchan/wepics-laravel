<?php

namespace App\Console\Commands;

use App\Enums\ImageExtension;
use App\Exceptions\ApiDebugException;
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

class StoreIndex extends Command
{
    protected $signature = 'app:index {--s|start-from=} {--d|auto-destroy}';

    protected $description = 'Command description';

    public function formatNumber($number, $pad = 6, $fg = 'white') {
        $padString = str_pad($number, $pad, '0', STR_PAD_LEFT);
        return preg_replace('/^(0*)(\d+)$/', "<fg=gray>$1</><fg=$fg>$2</>", $padString);
    }

    public function counter($position, $count, $pad = 6, $fg = 'yellow', $fgCount = 'white') {
        return
            $this->formatNumber($position, $pad, $fg)
            .'/'.
            $this->formatNumber($count, $pad, $fgCount);
    }

    public function handle(): void
    {
        //$this->output->getFormatter()->setStyle('error'  , new OutputFormatterStyle('red' ));
        $this->output->getFormatter()->setStyle('comment', new OutputFormatterStyle('gray'));

        $albums = Album
            ::query()
            ->orderByRaw('LENGTH(path) - LENGTH(REPLACE(path, "/", ""))') // Сортировка по количеству слешей
            ->orderByRaw('LENGTH(path)')                                  // Сортировка по длине пути
            ->orderBy('path')                                          // Сортировка по алфавиту
            ->get();
        $allowedExtensions = array_column(ImageExtension::cases(), 'value');

        //if (!$this->confirm('Do you wish index albums? ['. $albums->count() .' in DB already]', true)) return;

        $this->line('Fixing tree...');
        Album::fixTree();
        $this::newLine();

        $startFrom = $this->option('start-from');
        if (!$startFrom)
            $currentAlbumKey = 0;
        else {
            if (is_numeric($startFrom)) {
                $currentAlbumKey = $albums->search(fn ($a) => $a['id'] == $startFrom);
            } else {
                $currentAlbumKey = $albums->search(fn ($a) => $a['hash'] == $startFrom);
            }
        }
        if ($currentAlbumKey === false) {
            $this->warn("Album not found with $startFrom!");
            return;
        }

        while ($albums->count() > $currentAlbumKey) {
            $currentAlbum = $albums[$currentAlbumKey];
            $path = Storage::path("images$currentAlbum->path");

            $currentAlbumKey++;
            $this->line('<fg=gray;options=bold>['.$this->counter($currentAlbumKey, $albums->count())
                .'] #' . $this->formatNumber($currentAlbum->id)
                ." $currentAlbum->name "
                ." <bg=bright-cyan;fg=black;href=". url('../album/'. $currentAlbum->hash) ."> wepics $currentAlbum->hash </> "
                ." <bg=bright-magenta;fg=black;href=file:///$path> explorer $currentAlbum->path </></> "
            );

            try {
                $folders = File::directories($path);
            }
            catch (DirectoryNotFoundException $e)
            {
                $this->error(' DELETED ');
                if (!Album::find($currentAlbum->id)) continue;

                if ($this->option('auto-destroy') ||
                    $this->confirm("Do you wish remove not founded albums from DB? ["
                    .$currentAlbum->children->count()." subalbums & ". $currentAlbum->images->count() ." images known]")
                ) Album::destroy($currentAlbum->id);

                continue;
            }

            //$childrenInDB = $album->childAlbums->toArray();
            $albumChildren = $albums->where('parent_album_id', $currentAlbum->id);
            // Проход по папкам альбома (дочерние альбомы)
            $this->line('Checking folders in album ['
                . count($folders) .' in FS / '
                . $albumChildren->count() .' in DB]'
            );
            $newAlbums = [];
            foreach ($folders as $folder) {
                $childPath = $currentAlbum->path . basename($folder) .'/';
                $basename = basename($childPath);

                // Проверка наличия в БД вложенного альбома, создание если нет
                $key = $albumChildren->search(fn ($a) => $a['path'] === $childPath);
                if ($key !== false) {
                    $albumChild = $albumChildren[$key];
                    $this->line(
                        "  <fg=gray;href=". url("../album/$albumChild->hash") .">$albumChild->hash</> "
                        ."<fg=gray;href=file:///". Storage::path("images$childPath") .">$basename/</>"
                    );
                    $albumChildren->forget($key);
                }
                else {
                    $hash = Str::random(25);
                    $childAlbum = Album::create([
                        'name' => $basename,
                        'path' => $childPath,
                        'hash' => $hash,
                    ]);
                    $childAlbum->parent_album_id = $currentAlbum->id;
                    $childAlbum->save();
                    $newAlbums[] = $childAlbum;
                    $this->info('<fg=green>+ '
                        ."<fg=green;href=". url("../album/$hash") .">$hash</> "
                        ."<fg=green;href=file:///". Storage::path("images$childPath") .">$basename/</></>"
                    );
                }
            }
            foreach ($albumChildren as $key => $notFoundedAlbum) {
                $this->line('<fg=red>- '
                    ."<fg=red;href=". url("../album/$notFoundedAlbum->hash") .">$notFoundedAlbum->hash</> "
                    ."<fg=red;href=file:///". Storage::path('images'). $notFoundedAlbum->path .'>'.basename($notFoundedAlbum->path).'/</></>'
                );
                //dd("try delete?", $key, $albums[$key]);
                //$clone = clone $albums;
                $albums->forget($key);
                //dd($albums, $clone);
                //dd("after delete", $key, $albums[$key+1]);
            }
            $albums->splice($currentAlbumKey, 0, $newAlbums);

            $notFoundedCount = $albumChildren->count();
            if ($this->option('auto-destroy') || (
                $notFoundedCount &&
                $this->confirm("Do you wish remove not founded albums from DB? [$notFoundedCount]")
            )) {
                Album::destroy($albumChildren->pluck('id')->toArray());
            }



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
                $counter = '['.$this->counter($i, $filesCount).']';
                $this->output->write("  $counter <href=file:///$file>$name</>");

                try {
                    // Проверка наличия в БД картинки по названию, если есть — пропуск
                    // FIXME: А если юзер переименует две картинки наоборот?
                    $key = array_search($name, $imagesNames);
                    if ($key !== false) {
                        $existImage = $images[$key];
                        $isDuplica = array_key_exists('origName', $existImage);
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
                            .'<fg=gray;href='.$path.$existImage['origName'].">$existImage[origName]" : '')
                            .'</>'
                        );
                        unset($notFoundedImages[$key]);
                        continue;
                    }
                    //dd('end_here', array_search($name, $imagesNames), $name, $imagesNames, $images, $imagesInDB);

                    // Отсекание не-картинок по расширению файла
                    $extension = pathinfo($file, PATHINFO_EXTENSION);
                    if (!in_array($extension, $allowedExtensions)) {
                        $this->line("<fg=blue>\r× "
                            . $counter
                            ." <fg=blue;href=file:///$file>$name</></>"
                        );
                        continue;
                    }

                    // Получение хеша картинки (прожорливое к скорости чтения)
                    $hash = hash_file('xxh3', $file);

                    // Проверка наличия в БД картинки по хешу, если есть — проверяем в ФС
                    $key = array_search($hash, $imagesHashes);
                    if ($key !== false) {
                        // Полное имя картинки-оригинала (из БД)
                        $imageFullName = $path . $images[$key]['name'];

                        // Проверка наличие картинки-оригинала в ФС ...
                        $filesKey = array_search($imageFullName, $files);
                        if ($filesKey === false) {
                            // Если нету — переименовываем в БД
                            Image
                                ::where('id', $images[$key]['id'])
                                ->update(['name' => $name]);
                            $this->line("<fg=yellow>\r→ "
                                . $counter
                                ." <fg=yellow;href="
                                . url("api/albums/$currentAlbum->hash/images/$hash/orig")
                                .">$hash</>"
                                ."<fg=yellow;href=file:///$file>". $images[$key]['name'] ."<fg=white> renamed to </>$name</>"
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

                            $this->line("<fg=yellow>\r↩ "
                                . $counter
                                ." <fg=yellow;href="
                                . url("api/albums/$currentAlbum->hash/images/$hash/orig")
                                .">$hash</>"
                                ."<fg=yellow;href=file:///$file>$name<fg=white> linked to </>". $images[$key]['name'] .'</>'
                                .'</>'
                            );
                        }
                        // И пропуск создания картинки в БД
                        unset($notFoundedImages[$key]);
                        continue;
                    }

                    // Получение размеров картинки, если нет размеров (не получили) — пропуск
                    $sizes = getimagesize($file);
                    if (!$sizes) continue;

                    // Создание в БД записи
                    $imageModel = Image::create([
                        'name' => $name,
                        'hash' => $hash,
                        'date' => Carbon::createFromTimestamp(File::lastModified($file)),
                        'size' => File::size($file),
                        'width'  => $sizes[0],
                        'height' => $sizes[1],
                        'album_id' => $currentAlbum->id,
                    ]);
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
                    $this->error("\r/ "
                        . $counter
                        ." <bg=red;fg=white;href=file:///$file>$name</> "
                        .$ex->getMessage()
                    );
                    continue;
                }
            }
            $notFoundedDuplicas = [];
            $notFoundedOrigs = [];
            foreach ($notFoundedImages as $key => $notFoundedImage) {
                $isDuplica = array_key_exists('origName', $notFoundedImage);
                if ($isDuplica)
                    $notFoundedDuplicas[] = $notFoundedImage;
                else
                    $notFoundedOrigs[] = $notFoundedImage;

                try {
                    $this->line("<fg=gray>\r"
                        .($isDuplica ? '↩ ' : '  ')
                        .'['.$this->counter(0, $filesCount).'] '
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

        $this->line('Fixing tree...');
        Album::fixTree();
    }
}
