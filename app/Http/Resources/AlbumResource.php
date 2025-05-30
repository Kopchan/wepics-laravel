<?php

namespace App\Http\Resources;

use App\Models\ReactionImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class AlbumResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        return [
//          'id'          => $this->id,
            'name'        => $this->name,
            'hash'        => $this->hash,
            'seed'        => $this->when($this?->seed,                   fn() => $this->seed),
            'path'        => $this->when($user?->is_admin,              fn() => $this->path),
            'alias'       => $this->when($this->alias,                  fn() => $this->alias),
            'orderLevel'  => $this->when($this->order_level,            fn() => $this->order_level),
            'guestAllow'  => $this->when(($this->guest_allow !== null), fn() => $this->guest_allow),
            'createdAt'   => $this->when($this->created_at,             fn() => $this->created_at),
            'indexedAt'   => $this->when($this->last_indexation,        fn() => $this->last_indexation),
            'ratingId'    => $this->when($this->age_rating_id,          fn() => $this->age_rating_id),
            'mediasCount' => $this->when($this->medias_count,           fn() => $this->medias_count),
            'imagesCount' => $this->when($this->images_count,           fn() => $this->images_count),
            'videosCount' => $this->when($this->videos_count,           fn() => $this->videos_count),
            'audiosCount' => $this->when($this->audios_count,           fn() => $this->audios_count),
            'albumsCount' => $this->when($this->albums_count,           fn() => $this->albums_count),
            'size'        => $this->when($this->size,                   fn() => $this->size),
            'duration'    => $this->when($this->duration,               fn() => (int)$this->duration / 1000),
            'contentSort' => $this->when($this->content_sort_field,     fn() => $this->content_sort_field),
            'sign'        => $this->when($this->sign,                   fn() => $this->sign),
//          'images'      => $this->whenLoaded('images',                fn() => ImageResource::collection($this->images)),
            'images'      => $this->when(
                $this->imagesLoaded || $this->relationLoaded('images'),
                fn() => ImageResource::collection($this->imagesLoaded ?? $this->images)
            ),
            'ancestors'   => $this->whenLoaded('ancestors',   fn() => AlbumResource::collection($this->ancestors)),
            'children'    => $this->whenLoaded('childAlbums', fn() => AlbumResource::collection($this->childAlbums)),
        ];
    }
}
