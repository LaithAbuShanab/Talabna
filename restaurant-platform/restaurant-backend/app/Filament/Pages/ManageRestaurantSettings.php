<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Support\NavigationGroup;
use App\Models\RestaurantSetting;
use App\Services\AdminActivityLogger;
use App\Support\Money;
use BackedEnum;
use Closure;
use DateTimeZone;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * A single-record "settings" page bound to RestaurantSetting::current()
 * rather than a Resource — there's only ever one row, so list/create/
 * delete would be meaningless. This is where "اسم وشعار مبدئي للمطعم
 * قابلان للتعديل" (the initial restaurant name/logo are editable) is
 * satisfied: both feed App\Providers\Filament\AdminPanelProvider's
 * ->brandName()/->brandLogo(), so a save here updates the panel's own
 * branding immediately. Guarded by App\Policies\RestaurantSettingPolicy,
 * not a plugin — this project doesn't use spatie/laravel-settings or any
 * other settings package (see docs/ADMIN_PANEL.md).
 *
 * `push_notification_key` is the one genuinely sensitive field here (see
 * docs/ADMIN_RESTAURANT_SETTINGS.md for the full story): it's never
 * prefilled on mount (so a decrypted secret never round-trips back into the
 * browser), it's only visible to users who pass
 * RestaurantSettingPolicy::viewSensitive(), and a blank submission leaves
 * the stored value untouched — the same "blank = keep current" convention
 * already used for User passwords (see UserForm).
 */
class ManageRestaurantSettings extends Page
{
    protected string $view = 'filament.pages.manage-restaurant-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Settings;

    protected static ?string $title = 'Restaurant Settings';

    protected static ?string $navigationLabel = 'Restaurant Settings';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Auth::user()?->can('view', RestaurantSetting::class) ?? false;
    }

    public function mount(): void
    {
        // push_notification_key is deliberately excluded: never re-display a
        // decrypted secret in the browser (see save()'s "blank keeps the
        // current value" handling below).
        $data = Arr::except(RestaurantSetting::current()->toArray(), ['push_notification_key']);

        $this->form->fill($data);
    }

    public function form(Schema $schema): Schema
    {
        $currencyCode = RestaurantSetting::current()->currency_code;
        $canViewSensitive = Auth::user()?->can('viewSensitive', RestaurantSetting::class) ?? false;

        return $schema
            ->components([
                Tabs::make('Settings')
                    ->tabs([
                        Tab::make('Basic info')
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('restaurant_name')
                                            ->label('Restaurant name (English)')
                                            ->required()
                                            ->maxLength(255),
                                        TextInput::make('restaurant_name_ar')
                                            ->label('Restaurant name (Arabic)')
                                            ->maxLength(255),
                                    ]),
                                Grid::make(2)
                                    ->schema([
                                        FileUpload::make('logo_path')
                                            ->label('Logo')
                                            ->image()
                                            ->disk('public')
                                            ->directory('restaurant')
                                            ->visibility('public')
                                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                                            ->maxSize(2048),
                                        FileUpload::make('cover_image_path')
                                            ->label('Cover image')
                                            ->image()
                                            ->disk('public')
                                            ->directory('restaurant')
                                            ->visibility('public')
                                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                                            ->maxSize(2048),
                                    ]),
                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('phone')
                                            ->tel(),
                                        TextInput::make('email')
                                            ->email(),
                                    ]),
                                Textarea::make('address')
                                    ->rows(2),
                            ]),
                        Tab::make('Regional & tax')
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        Select::make('currency_code')
                                            ->label('Currency')
                                            ->options([
                                                'JOD' => 'JOD — Jordanian Dinar',
                                                'USD' => 'USD — US Dollar',
                                                'EUR' => 'EUR — Euro',
                                                'GBP' => 'GBP — British Pound',
                                                'SAR' => 'SAR — Saudi Riyal',
                                                'AED' => 'AED — UAE Dirham',
                                                'KWD' => 'KWD — Kuwaiti Dinar',
                                                'BHD' => 'BHD — Bahraini Dinar',
                                                'OMR' => 'OMR — Omani Rial',
                                                'QAR' => 'QAR — Qatari Riyal',
                                                'EGP' => 'EGP — Egyptian Pound',
                                            ])
                                            ->required()
                                            ->native(false),
                                        Select::make('timezone')
                                            ->label('Timezone')
                                            ->options(array_combine(
                                                DateTimeZone::listIdentifiers(),
                                                DateTimeZone::listIdentifiers(),
                                            ))
                                            ->required()
                                            ->searchable()
                                            ->native(false),
                                    ]),
                                Toggle::make('is_tax_enabled')
                                    ->label('Tax enabled')
                                    ->live()
                                    ->helperText('Whether tax is calculated on orders at all.'),
                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('tax_rate_bps')
                                            ->label('Tax rate')
                                            ->numeric()
                                            ->minValue(0)
                                            ->maxValue(100)
                                            ->step(0.01)
                                            ->suffix('%')
                                            ->required()
                                            ->formatStateUsing(fn (?int $state): float => ($state ?? 0) / 100)
                                            ->dehydrateStateUsing(fn ($state): int => (int) round(((float) $state) * 100))
                                            ->visible(fn (Get $get): bool => (bool) $get('is_tax_enabled')),
                                        Toggle::make('is_tax_inclusive')
                                            ->label('Prices include tax')
                                            ->helperText('On: the tax is extracted from the item price. Off: tax is added on top at checkout.')
                                            ->visible(fn (Get $get): bool => (bool) $get('is_tax_enabled')),
                                    ]),
                            ]),
                        Tab::make('Ordering')
                            ->schema([
                                TextInput::make('min_order_amount')
                                    ->label('Minimum order amount')
                                    ->numeric()
                                    ->minValue(0)
                                    ->required()
                                    ->prefix($currencyCode)
                                    ->step(1 / (10 ** Money::decimalsFor($currencyCode)))
                                    ->formatStateUsing(
                                        fn (?int $state): float => Money::toMajorUnits($state ?? 0, $currencyCode)
                                    )
                                    ->dehydrateStateUsing(
                                        fn ($state): int => Money::toMinorUnits((float) $state, $currencyCode)
                                    ),
                                TextInput::make('default_preparation_minutes')
                                    ->label('Default preparation time (minutes)')
                                    ->numeric()
                                    ->minValue(1)
                                    ->required(),
                                Grid::make(2)
                                    ->schema([
                                        Toggle::make('allows_delivery')
                                            ->label('Accept delivery orders'),
                                        Toggle::make('allows_pickup')
                                            ->label('Accept pickup orders'),
                                    ]),
                                Toggle::make('is_accepting_orders')
                                    ->label('Restaurant open (accepting orders)')
                                    ->live()
                                    ->helperText('Turning this off immediately stops new orders being accepted, regardless of business hours.'),
                                Textarea::make('closure_message')
                                    ->label('Closure message')
                                    ->rows(2)
                                    ->helperText('Shown to customers while the restaurant is manually closed.')
                                    ->visible(fn (Get $get): bool => ! $get('is_accepting_orders')),
                            ]),
                        Tab::make('Policies')
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        Textarea::make('cancellation_policy_text')
                                            ->label('Cancellation policy (English)')
                                            ->rows(4),
                                        Textarea::make('cancellation_policy_text_ar')
                                            ->label('Cancellation policy (Arabic)')
                                            ->rows(4),
                                    ]),
                                Grid::make(2)
                                    ->schema([
                                        Textarea::make('terms_text')
                                            ->label('Terms & conditions (English)')
                                            ->rows(4),
                                        Textarea::make('terms_text_ar')
                                            ->label('Terms & conditions (Arabic)')
                                            ->rows(4),
                                    ]),
                                Grid::make(2)
                                    ->schema([
                                        Textarea::make('privacy_text')
                                            ->label('Privacy policy (English)')
                                            ->rows(4),
                                        Textarea::make('privacy_text_ar')
                                            ->label('Privacy policy (Arabic)')
                                            ->rows(4),
                                    ]),
                            ]),
                        Tab::make('Social media')
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('facebook_url')
                                            ->label('Facebook URL')
                                            ->url(),
                                        TextInput::make('instagram_url')
                                            ->label('Instagram URL')
                                            ->url(),
                                        TextInput::make('twitter_url')
                                            ->label('X / Twitter URL')
                                            ->url(),
                                        TextInput::make('whatsapp_number')
                                            ->label('WhatsApp number')
                                            ->tel(),
                                    ]),
                            ]),
                        Tab::make('Notifications')
                            ->schema([
                                Toggle::make('notify_new_orders_by_email')
                                    ->label('Email admins on new orders')
                                    ->live(),
                                Textarea::make('order_notification_emails')
                                    ->label('Notification email addresses')
                                    ->rows(2)
                                    ->helperText('Comma-separated list of email addresses to notify.')
                                    ->required(fn (Get $get): bool => (bool) $get('notify_new_orders_by_email'))
                                    ->visible(fn (Get $get): bool => (bool) $get('notify_new_orders_by_email'))
                                    ->rule(static fn (): Closure => static function (string $attribute, $value, Closure $fail): void {
                                        foreach (explode(',', (string) $value) as $email) {
                                            $email = trim($email);

                                            if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                                                $fail('Each notification email must be a valid, comma-separated email address.');
                                            }
                                        }
                                    }),
                                Toggle::make('push_notifications_enabled')
                                    ->label('Push notifications enabled'),
                                TextInput::make('push_notification_key')
                                    ->label('Push notification server key')
                                    ->password()
                                    ->revealable()
                                    ->visible($canViewSensitive)
                                    ->dehydrated(fn (?string $state): bool => filled($state))
                                    ->helperText('Leave blank to keep the current key unchanged. Stored encrypted; never shown once saved.'),
                            ]),
                    ])
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save')
                ->submit('save'),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $settings = RestaurantSetting::current();

        // push_notification_key is deliberately not mass-assignable (see
        // App\Models\RestaurantSetting's #[Fillable] list) — it can only be
        // set here, via forceFill(), and only when the admin actually typed
        // a new value (see the field's dehydrated() callback above).
        $pushKeyProvided = array_key_exists('push_notification_key', $data);
        $newPushKey = Arr::pull($data, 'push_notification_key');

        $changes = array_diff_assoc($data, $settings->only(array_keys($data)));

        $settings->update($data);

        if ($pushKeyProvided) {
            $settings->forceFill(['push_notification_key' => $newPushKey])->save();
            $changes['push_notification_key'] = true;
        }

        if ($changes !== []) {
            app(AdminActivityLogger::class)->log(
                actor: Auth::user(),
                action: 'settings.updated',
                subject: $settings,
                description: 'Updated restaurant settings.',
                metadata: array_keys($changes),
            );
        }

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();
    }
}
