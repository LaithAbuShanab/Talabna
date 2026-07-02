<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\RelationManagers;

use App\Models\Product;
use App\Models\ProductImage;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Manages a product's "صورة رئيسية" (primary image) and "صور إضافية"
 * (additional images) together as one list, rather than two separate UI
 * concepts bound to the same underlying table: every row is a
 * ProductImage, and exactly one is ever marked `is_primary` — enforced by
 * normalizePrimary() after every create/update, not left to convention.
 */
class ImagesRelationManager extends RelationManager
{
    protected static string $relationship = 'images';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('path')
                    ->label('Image')
                    ->image()
                    ->required()
                    ->disk('public')
                    ->directory('products')
                    ->visibility('public'),
                Toggle::make('is_primary')
                    ->label('Primary image')
                    ->helperText('Marking this primary automatically unmarks any other image on this product.'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('path')
            ->columns([
                ImageColumn::make('path')
                    ->label('Preview')
                    ->disk('public'),
                IconColumn::make('is_primary')
                    ->boolean(),
                TextColumn::make('sort_order')
                    ->numeric()
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->headerActions([
                CreateAction::make()
                    ->after(fn (ProductImage $record) => self::normalizePrimary($record->product, $record)),
            ])
            ->recordActions([
                EditAction::make()
                    ->after(fn (ProductImage $record) => self::normalizePrimary($record->product, $record)),
                Action::make('setPrimary')
                    ->label('Set as primary')
                    ->icon(Heroicon::OutlinedStar)
                    ->visible(fn (ProductImage $record): bool => ! $record->is_primary)
                    ->action(function (ProductImage $record): void {
                        $record->update(['is_primary' => true]);
                        self::normalizePrimary($record->product, $record);
                    }),
                DeleteAction::make()
                    ->after(fn (ProductImage $record) => self::normalizePrimary($record->product)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Keeps exactly one image marked `is_primary` per product. `$justSaved`
     * — the record a create/edit/setPrimary action just touched — takes
     * priority when it's one of the (now multiple) primary candidates: this
     * unambiguously reflects the admin's most recent action, rather than
     * guessing from `updated_at`, which two images saved within the same
     * second (routine — not just in fast test runs) would tie on. Only
     * falls back to "first primary candidate, else first image" when no
     * `$justSaved` is given (e.g. after a delete, where nothing was just
     * marked primary).
     */
    public static function normalizePrimary(Product $product, ?ProductImage $justSaved = null): void
    {
        $images = $product->images()->orderBy('sort_order')->get();

        if ($images->isEmpty()) {
            return;
        }

        $primaryCandidates = $images->where('is_primary', true);

        if ($primaryCandidates->count() === 1) {
            return;
        }

        $keep = ($justSaved?->is_primary && $primaryCandidates->contains(fn (ProductImage $image): bool => $image->is($justSaved)))
            ? $justSaved
            : ($primaryCandidates->first() ?? $images->first());

        $images->each(function (ProductImage $image) use ($keep): void {
            $isPrimary = $image->is($keep);

            if ($image->is_primary !== $isPrimary) {
                $image->update(['is_primary' => $isPrimary]);
            }
        });
    }
}
