<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ImageLinkResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $userId = $request->user()?->id;
        return [
//          'id'         => $this->id,
            'name'       => $this->name,
            'hash'       => $this->hash,
            'album'      => $this->when($this->album, fn () => [
                'hash'   => $this->when($this->album?->hash, $this->album?->hash),
                'name'   => $this->when($this->album?->name, $this->album?->name),
                'sign'   => $this->when($this->album?->sign != null, $this->album?->sign),
            ]),
            'date'       => $this->date,
            'size'       => $this->size,
            'width'      => $this->width,
            'height'     => $this->height,
            'tags'       => $this->whenLoaded('tags', fn() =>
                $this->when($this->tags->isNotEmpty(), fn () => TagResource::collection($this->tags))
            ),
            'reactions' => $this->whenLoaded('reactions', fn() => $this->when($this->reactions->isNotEmpty(),
                fn () => $this->reactions->groupBy('value')->map(function ($group) use ($userId) {
                    $reactionParams = [];
                    $reactionParams['count']  = $group->count();

                    $youSet = $group->contains(fn ($reaction) =>
                        isset($userId) && $reaction->pivot?->user_id === $userId
                    );
                    if ($youSet)
                        $reactionParams['isYouSet'] = true;

                    return $reactionParams;
                })
            )),
        ];
    }
}
