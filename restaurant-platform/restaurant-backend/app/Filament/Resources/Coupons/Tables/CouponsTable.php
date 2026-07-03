<?php

declare(strict_types=1);

namespace App\Filament\Resources\Coupons\Tables;

use App\Enums\CouponType;
use App\Models\Coupon;
use App\Models\RestaurantSetting;
use App\Support\Money;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * No ForceDeleteAction/ForceDeleteBulkAction anywhere: a coupon is only
 * ever soft-deleted (see App\Policies\CouponPolicy) — past
 * `coupon_usages`/`orders` rows keep referencing it.
 */
class CouponsTable
{
    public static function configure(Table $table): Table
    {
        $currencyCode = RestaurantSetting::current()->currency_code;

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('usages'))
            ->columns([
                TextColumn::make('code')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->badge(),
                TextColumn::make('value')
                    ->formatStateUsing(function (Coupon $record) use ($currencyCode): string {
                        return $record->type === CouponType::Percentage
                            ? "{$record->value}%"
                            : Money::format($record->value, $currencyCode)['formatted'];
                    }),
                TextColumn::make('min_order_amount')
                    ->label('Min. order')
                    ->formatStateUsing(
                        fn (?int $state): string => $state !== null ? Money::format($state, $currencyCode)['formatted'] : '—'
                    )
                    ->toggleable(),
                TextColumn::make('usages_count')
                    ->label('Used')
                    ->sortable(),
                TextColumn::make('starts_at')
                    ->label('Starts')
                    ->dateTime()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->boolean(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->options(CouponType::class),
                TernaryFilter::make('is_active'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
