<?php

declare(strict_types=1);

namespace App\Filament\Resources\Coupons\Schemas;

use App\Enums\CouponType;
use App\Models\Coupon;
use App\Models\RestaurantSetting;
use App\Support\Money;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

/**
 * `value`'s meaning depends on `type` (App\Enums\CouponType) — this one
 * field switches label/suffix/prefix/step/max and how it converts to/from
 * the stored integer, all keyed off the live `type` Select. See
 * App\Models\Coupon's own docblock and App\Services\CartPricingService for
 * how `value`/`max_discount_amount` are actually applied.
 */
class CouponForm
{
    public static function configure(Schema $schema): Schema
    {
        $currencyCode = RestaurantSetting::current()->currency_code;

        return $schema
            ->components([
                Section::make('Coupon')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('code')
                                    ->required()
                                    ->maxLength(50)
                                    ->unique(ignoreRecord: true)
                                    ->dehydrateStateUsing(fn (?string $state): ?string => $state !== null ? Str::upper($state) : null)
                                    ->helperText('Stored in upper case regardless of how it\'s typed.'),
                                Select::make('type')
                                    ->options(CouponType::class)
                                    ->required()
                                    ->live()
                                    ->default(CouponType::Percentage),
                            ]),
                        TextInput::make('value')
                            ->label(fn (Get $get): string => $get('type') === CouponType::Percentage ? 'Percentage value' : 'Amount')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->maxValue(fn (Get $get): ?int => $get('type') === CouponType::Percentage ? 100 : null)
                            ->suffix(fn (Get $get): ?string => $get('type') === CouponType::Percentage ? '%' : null)
                            ->prefix(fn (Get $get) => $get('type') === CouponType::FixedAmount ? $currencyCode : null)
                            ->step(fn (Get $get) => $get('type') === CouponType::FixedAmount ? (1 / (10 ** Money::decimalsFor($currencyCode))) : 1)
                            ->formatStateUsing(function (Get $get, $state) use ($currencyCode): int|float|null {
                                if ($state === null) {
                                    return null;
                                }

                                return $get('type') === CouponType::FixedAmount
                                    ? Money::toMajorUnits((int) $state, $currencyCode)
                                    : (int) $state;
                            })
                            ->dehydrateStateUsing(function (Get $get, $state) use ($currencyCode): int {
                                return $get('type') === CouponType::FixedAmount
                                    ? Money::toMinorUnits((float) $state, $currencyCode)
                                    : (int) $state;
                            }),
                        TextInput::make('max_discount_amount')
                            ->label('Maximum discount amount')
                            ->numeric()
                            ->minValue(0)
                            ->prefix($currencyCode)
                            ->step(1 / (10 ** Money::decimalsFor($currencyCode)))
                            ->formatStateUsing(
                                fn (?int $state): ?float => $state !== null ? Money::toMajorUnits($state, $currencyCode) : null
                            )
                            ->dehydrateStateUsing(
                                fn ($state): ?int => $state !== null && $state !== '' ? Money::toMinorUnits((float) $state, $currencyCode) : null
                            )
                            ->helperText('Caps how much a percentage discount can ever be worth. Leave blank for no cap.')
                            ->visible(fn (Get $get): bool => $get('type') === CouponType::Percentage),
                        TextInput::make('min_order_amount')
                            ->label('Minimum order amount')
                            ->numeric()
                            ->minValue(0)
                            ->prefix($currencyCode)
                            ->step(1 / (10 ** Money::decimalsFor($currencyCode)))
                            ->formatStateUsing(
                                fn (?int $state): ?float => $state !== null ? Money::toMajorUnits($state, $currencyCode) : null
                            )
                            ->dehydrateStateUsing(
                                fn ($state): ?int => $state !== null && $state !== '' ? Money::toMinorUnits((float) $state, $currencyCode) : null
                            )
                            ->helperText('Leave blank for no minimum.'),
                        Grid::make(2)
                            ->schema([
                                DateTimePicker::make('starts_at')
                                    ->label('Start date')
                                    ->native(false),
                                DateTimePicker::make('expires_at')
                                    ->label('End date')
                                    ->native(false)
                                    ->rule(fn (Get $get): ?string => filled($get('starts_at')) ? 'after:'.$get('starts_at') : null),
                            ]),
                        Grid::make(2)
                            ->schema([
                                TextInput::make('usage_limit')
                                    ->label('Total usage limit')
                                    ->numeric()
                                    ->minValue(1)
                                    ->helperText('Leave blank for unlimited total uses.'),
                                TextInput::make('per_user_limit')
                                    ->label('Per-user limit')
                                    ->numeric()
                                    ->minValue(1)
                                    ->helperText('Leave blank for unlimited uses per customer.'),
                            ]),
                        Toggle::make('is_active')
                            ->default(true),
                    ]),
                Section::make('Restrict to specific categories/products')
                    ->description('Optional. Leave both empty to apply the coupon to the whole cart. If either is set, the coupon only discounts the matching items\' subtotal.')
                    ->schema([
                        Select::make('categories')
                            ->relationship('categories', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload(),
                        Select::make('products')
                            ->relationship('products', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload(),
                    ])
                    ->collapsible(),
                Section::make('Usage statistics')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Placeholder::make('usages_count')
                                    ->label('Times used')
                                    ->content(fn (Coupon $record): string => (string) $record->usages()->count()),
                                Placeholder::make('total_discount_given')
                                    ->label('Total discount given')
                                    ->content(function (Coupon $record) use ($currencyCode): string {
                                        $total = (int) $record->usages()->sum('discount_amount');

                                        return Money::format($total, $currencyCode)['formatted'];
                                    }),
                            ]),
                    ])
                    ->visible(fn (?Coupon $record): bool => $record !== null),
            ]);
    }
}
