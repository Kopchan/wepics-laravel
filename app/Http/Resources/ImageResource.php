<?php

namespace App\Http\Resources;

use App\Models\ReactionImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class ImageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $userId = $request->user()?->id;
        return [
//          'id'        => $this->id,
            'name'      => $this->name,
            'hash'      => $this->hash,
            'date'      => $this->date,
            'size'      => $this->size,
            'width'     => $this->width,
            'height'    => $this->height,
            'tags'      => $this->whenLoaded('tags', fn() =>
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
