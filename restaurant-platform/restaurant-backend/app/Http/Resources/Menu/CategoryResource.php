<?php

declare(strict_types=1);

namespace App\Http\Resources\Menu;

use App\Http\Resources\Concerns\FormatsTranslatable;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Category
 */
final class CategoryResource extends JsonResource
{
    use FormatsTranslatable;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->translatable($this->name, $this->name_ar),
            'description' => $this->translatable($this->description, $this->description_ar),
            'image_url' => $this->image_path !== null ? asset($this->image_path) : null,
        ];
    }
}
