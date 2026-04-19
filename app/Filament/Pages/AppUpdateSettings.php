<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Notifications\Notification;


class AppUpdateSettings extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationGroup = 'CÀI ĐẶT HỆ THỐNG';
    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-circle';
    protected static ?string $navigationLabel = 'Cập nhật ứng dụng';
    protected static string $view = 'filament.pages.app-update-settings';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    public array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'app_version'       => Setting::where('key', 'app_version')->value('value'),
            'force_update'      => filter_var(Setting::where('key', 'force_update')->value('value'), FILTER_VALIDATE_BOOLEAN),
            'store_url_android' => Setting::where('key', 'store_url_android')->value('value'),
            'store_url_ios'     => Setting::where('key', 'store_url_ios')->value('value'),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('app_version')
                    ->label('Phiên bản mới nhất')
                    ->required(),

                Forms\Components\Toggle::make('force_update')
                    ->label('Bắt buộc cập nhật'),

                Forms\Components\TextInput::make('store_url_android')
                    ->label('Google Play URL')
                    ->url(),

                Forms\Components\TextInput::make('store_url_ios')
                    ->label('App Store URL')
                    ->url(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        Setting::updateOrCreate(['key' => 'app_version'], ['value' => $data['app_version']]);
        Setting::updateOrCreate(['key' => 'force_update'], ['value' => $data['force_update'] ? 'true' : 'false']);
        Setting::updateOrCreate(['key' => 'store_url_android'], ['value' => $data['store_url_android']]);
        Setting::updateOrCreate(['key' => 'store_url_ios'], ['value' => $data['store_url_ios']]);

        Notification::make()
            ->title('Đã lưu cấu hình cập nhật ứng dụng')
            ->success()
            ->send();
    }

}
