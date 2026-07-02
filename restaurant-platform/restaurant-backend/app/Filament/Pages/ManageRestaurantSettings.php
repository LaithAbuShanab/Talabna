<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Support\NavigationGroup;
use App\Models\RestaurantSetting;
use App\Services\AdminActivityLogger;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
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
        $this->form->fill(RestaurantSetting::current()->only([
            'restaurant_name',
            'logo_path',
            'phone',
            'email',
            'address',
            'is_accepting_orders',
        ]));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('restaurant_name')
                    ->label('Restaurant name')
                    ->required()
                    ->maxLength(255),
                FileUpload::make('logo_path')
                    ->label('Logo')
                    ->image()
                    ->directory('restaurant')
                    ->visibility('public'),
                TextInput::make('phone')
                    ->tel(),
                TextInput::make('email')
                    ->email(),
                Textarea::make('address')
                    ->rows(2),
                Toggle::make('is_accepting_orders')
                    ->label('Accepting orders')
                    ->helperText('Turning this off immediately stops new orders being accepted, regardless of business hours.'),
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
        $changes = array_diff_assoc($data, $settings->only(array_keys($data)));

        $settings->update($data);

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
